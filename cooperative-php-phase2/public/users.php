<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_role(['system_admin']);

$pdo = db();
$q = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$status = isset($_GET['status']) && is_string($_GET['status']) ? $_GET['status'] : '';
$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(u.full_name LIKE ? OR u.email LIKE ? OR c.name LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if (in_array($status, ['active', 'disabled'], true)) {
    $where[] = 'u.status = ?';
    $params[] = $status;
}

$sql = 'SELECT u.id, u.full_name, u.email, u.role, u.status, u.last_login_at, c.name AS cooperative_name '
    . 'FROM users u LEFT JOIN cooperatives c ON c.id = u.cooperative_id'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
    . ' ORDER BY u.created_at DESC LIMIT 500';
$statement = $pdo->prepare($sql);
$statement->execute($params);
$users = $statement->fetchAll();

$pageTitle = 'User management';
require __DIR__ . '/includes/header.php';
?>
<div class="welcome-row">
    <div><h2>Users and staff</h2><p class="muted">Create accounts, assign roles, and manage workspace access.</p></div>
    <a class="btn primary" href="user-form.php">＋ Add user</a>
</div>
<section class="panel">
    <form class="filters" method="get">
        <div class="search-field"><span>⌕</span><input type="search" name="q" maxlength="100" placeholder="Search name, email or cooperative" value="<?= e($q) ?>"></div>
        <select name="status" aria-label="Filter by account status">
            <option value="">All account statuses</option>
            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="disabled" <?= $status === 'disabled' ? 'selected' : '' ?>>Disabled</option>
        </select>
        <button class="btn secondary" type="submit">Filter</button>
        <?php if ($q !== '' || $status !== ''): ?><a class="btn ghost" href="users.php">Clear</a><?php endif; ?>
    </form>
    <div class="table-meta"><span><?= count($users) ?> account(s) shown</span><span>Maximum 500 accounts per view</span></div>
    <?php if (!$users): ?>
        <div class="empty-state"><div class="empty-icon">♙</div><h3>No matching accounts</h3><p>Try another search or create a user account.</p><a class="btn primary" href="user-form.php">Add user</a></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Cooperative</th><th>Last sign-in</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($users as $managedUser): ?>
                        <tr>
                            <td><strong><?= e($managedUser['full_name']) ?></strong></td>
                            <td><?= e($managedUser['email']) ?></td>
                            <td><?= e(ucwords(str_replace('_', ' ', $managedUser['role']))) ?></td>
                            <td><?= e($managedUser['cooperative_name'] ?? 'All cooperatives') ?></td>
                            <td><?= e($managedUser['last_login_at'] ?? 'Never') ?></td>
                            <td><span class="badge <?= $managedUser['status'] === 'active' ? 'active' : 'inactive' ?>"><?= e(ucfirst($managedUser['status'])) ?></span></td>
                            <td><a class="icon-btn" title="Edit user" aria-label="Edit <?= e($managedUser['full_name']) ?>" href="user-form.php?id=<?= (int)$managedUser['id'] ?>">✎</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
