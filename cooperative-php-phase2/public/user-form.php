<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_role(['system_admin']);

$pdo = db();
$user = current_user();
$rawId = $_GET['id'] ?? '';
if (!is_string($rawId) || ($rawId !== '' && (!ctype_digit($rawId) || (int)$rawId < 1))) {
    http_response_code(400);
    exit('Invalid user record.');
}
$id = $rawId === '' ? 0 : (int)$rawId;
$managedUser = null;
$errors = [];

if ($id > 0) {
    $statement = $pdo->prepare('SELECT id, cooperative_id, full_name, email, role, status FROM users WHERE id = ?');
    $statement->execute([$id]);
    $managedUser = $statement->fetch();
    if (!$managedUser) {
        http_response_code(404);
        exit('User not found.');
    }
}

$cooperatives = $pdo->query('SELECT id, name, status FROM cooperatives ORDER BY name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $postedId = filter_var($_POST['id'] ?? '0', FILTER_VALIDATE_INT);
    if ($postedId === false || (int)$postedId !== $id) {
        http_response_code(400);
        exit('Invalid user record.');
    }

    $name = trim((string)($_POST['full_name'] ?? ''));
    $email = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $role = (string)($_POST['role'] ?? '');
    $status = (string)($_POST['status'] ?? 'active');
    $rawCooperativeId = $_POST['cooperative_id'] ?? '';
    $password = (string)($_POST['password'] ?? '');
    $cooperativeId = null;

    if ($name === '' || strlen($name) > 160) {
        $errors[] = 'Enter a name no longer than 160 characters.';
    }
    if (!$email || strlen($email) > 190) {
        $errors[] = 'Enter a valid email address no longer than 190 characters.';
    }
    if (!in_array($role, ['system_admin', 'cooperative_manager', 'staff', 'auditor'], true)) {
        $errors[] = 'Select a valid role.';
    }
    if (!in_array($status, ['active', 'disabled'], true)) {
        $errors[] = 'Select a valid account status.';
    }
    if ($id === 0 && $status !== 'active') {
        $errors[] = 'New accounts must start with active status.';
    }

    if ($role === 'system_admin') {
        if ($rawCooperativeId !== '' && $rawCooperativeId !== null) {
            $errors[] = 'System administrators cannot be assigned to one cooperative.';
        }
    } elseif (!is_string($rawCooperativeId) || !ctype_digit($rawCooperativeId) || (int)$rawCooperativeId < 1) {
        $errors[] = 'Select a cooperative for this role.';
    } else {
        $cooperativeId = (int)$rawCooperativeId;
        $validCooperative = false;
        foreach ($cooperatives as $cooperative) {
            if ((int)$cooperative['id'] === $cooperativeId) {
                $validCooperative = true;
                break;
            }
        }
        if (!$validCooperative) {
            $errors[] = 'Select an existing cooperative.';
        }
    }

    if ($id === 0 && strlen($password) < 10) {
        $errors[] = 'New accounts require a password of at least 10 characters.';
    } elseif ($password !== '' && strlen($password) < 10) {
        $errors[] = 'A replacement password must be at least 10 characters.';
    }
    if ($id === (int)$user['id'] && ($role !== $user['role'] || $status !== 'active' || (int)($cooperativeId ?? 0) !== (int)($user['cooperative_id'] ?? 0))) {
        $errors[] = 'You cannot change your own role, cooperative assignment, or account status here.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            if ($id > 0 && $status === 'disabled' && $managedUser['role'] === 'system_admin' && $managedUser['status'] === 'active') {
                $activeAdmins = $pdo->query(
                    "SELECT id FROM users WHERE role = 'system_admin' AND status = 'active' FOR UPDATE"
                )->fetchAll();
                if (count($activeAdmins) <= 1) {
                    throw new DomainException('The last active system administrator cannot be disabled.');
                }
            }

            if ($id > 0) {
                $sql = 'UPDATE users SET cooperative_id = ?, full_name = ?, email = ?, role = ?, status = ?';
                $params = [$cooperativeId, $name, $email, $role, $status];
                if ($password !== '') {
                    $sql .= ', password_hash = ?';
                    $params[] = password_hash($password, PASSWORD_DEFAULT);
                }
                $sql .= ' WHERE id = ?';
                $params[] = $id;
                $pdo->prepare($sql)->execute($params);
                $action = $password !== '' ? 'update_and_password_reset' : 'update';
            } else {
                $pdo->prepare(
                    'INSERT INTO users (cooperative_id, full_name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?, ?)'
                )->execute([$cooperativeId, $name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $status]);
                $id = (int)$pdo->lastInsertId();
                $action = 'create';
            }

            $pdo->prepare(
                'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([
                $user['id'],
                $action,
                'user',
                $id,
                json_encode(['email' => $email, 'role' => $role, 'status' => $status]),
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
            $pdo->commit();
            flash('success', $action === 'create' ? 'User account created.' : 'User account updated.');
            redirect('users.php');
        } catch (DomainException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $exception->getMessage();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $exception->getCode() === '23000'
                ? 'Could not save the account. That email address may already be in use.'
                : 'Could not save the account because of a database error.';
        }
    }

    $managedUser = [
        'id' => $id,
        'cooperative_id' => $cooperativeId,
        'full_name' => $name,
        'email' => is_string($email) ? $email : (string)($_POST['email'] ?? ''),
        'role' => $role,
        'status' => $status,
    ];
}

$pageTitle = $id > 0 ? 'Edit user' : 'Add user';
require __DIR__ . '/includes/header.php';
?>
<div class="welcome-row">
    <div><h2><?= $id > 0 ? 'Update user account' : 'Create a user account' ?></h2><p class="muted">Assign the appropriate role and cooperative access.</p></div>
    <a class="btn secondary" href="users.php">← Back to users</a>
</div>
<?php if ($errors): ?>
    <div class="alert error" role="alert"><strong>Please fix the following:</strong><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<section class="panel form-panel">
    <form method="post" class="member-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <div class="form-grid">
            <label>Full name *<input name="full_name" required maxlength="160" value="<?= e($managedUser['full_name'] ?? '') ?>"></label>
            <label>Email address *<input type="email" name="email" required maxlength="190" value="<?= e($managedUser['email'] ?? '') ?>"></label>
            <label>Role *
                <select name="role" required>
                    <?php foreach (['staff' => 'Staff', 'cooperative_manager' => 'Cooperative manager', 'auditor' => 'Auditor', 'system_admin' => 'System administrator'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= ($managedUser['role'] ?? 'staff') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Cooperative
                <select name="cooperative_id">
                    <option value="">Not assigned (system administrators only)</option>
                    <?php foreach ($cooperatives as $cooperative): ?>
                        <option value="<?= (int)$cooperative['id'] ?>" <?= (int)($managedUser['cooperative_id'] ?? 0) === (int)$cooperative['id'] ? 'selected' : '' ?>>
                            <?= e($cooperative['name'] . ($cooperative['status'] === 'active' ? '' : ' (inactive)')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if ($id > 0): ?>
                <label>Account status
                    <select name="status">
                        <option value="active" <?= ($managedUser['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="disabled" <?= ($managedUser['status'] ?? '') === 'disabled' ? 'selected' : '' ?>>Disabled</option>
                    </select>
                </label>
            <?php else: ?>
                <input type="hidden" name="status" value="active">
            <?php endif; ?>
            <label><?= $id > 0 ? 'Reset password' : 'Temporary password *' ?>
                <input type="password" name="password" <?= $id === 0 ? 'required' : '' ?> minlength="10" autocomplete="new-password" placeholder="<?= $id > 0 ? 'Leave blank to keep current password' : 'At least 10 characters' ?>">
            </label>
        </div>
        <p class="muted"><?= $id > 0 ? 'Password resets take effect the next time the user signs in.' : 'Provide the temporary password to the user securely; they can update it in My profile.' ?></p>
        <div class="form-actions"><a class="btn ghost" href="users.php">Cancel</a><button class="btn primary" type="submit"><?= $id > 0 ? 'Save changes' : 'Create account' ?> →</button></div>
    </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
