<?php
require_once __DIR__ . '/../app/bootstrap.php';
if (current_user()) redirect('index.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $password = (string)($_POST['password'] ?? '');
    if (!$email || $password === '') $error = 'Enter a valid email address and password.';
    else {
        try {
            $stmt = db()->prepare('SELECT id, cooperative_id, full_name, email, password_hash, role, status FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]); $user = $stmt->fetch();
            if ($user && $user['status'] === 'active' && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true); unset($user['password_hash']); $_SESSION['user'] = $user;
                db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
                db()->prepare('INSERT INTO audit_logs (user_id,action,entity_type,entity_id,ip_address) VALUES (?,?,?,?,?)')->execute([$user['id'],'login','user',$user['id'],$_SERVER['REMOTE_ADDR'] ?? null]);
                redirect('index.php');
            }
            $error = 'Email or password is incorrect.';
        } catch (PDOException $ex) { $error = 'Database connection failed. Check the setup instructions and database credentials.'; }
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in · <?= e(APP_NAME) ?></title><link rel="stylesheet" href="assets/css/app.css"></head><body class="login-body"><main class="login-wrap"><section class="login-art"><div class="brand light"><span class="brand-mark">C</span><span><?= e(APP_NAME) ?></span></div><div class="art-copy"><span class="pill">ONE PLATFORM · MANY COOPERATIVES</span><h1>Stronger cooperatives start with clear information.</h1><p>Manage members, support accountability, and keep cooperative operations organized in one place.</p></div><div class="art-bottom">Member records · Role-based access · Operational visibility</div></section><section class="login-panel"><div class="login-card"><div class="login-icon">↗</div><div class="eyebrow">WELCOME BACK</div><h2>Sign in to your workspace</h2><p class="muted">Use the account provided by your system administrator.</p><?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?><form method="post" class="form-stack"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label>Email address<input type="email" name="email" required autocomplete="username" placeholder="you@cooperative.rw" value="<?= e((string)($_POST['email'] ?? '')) ?>"></label><label>Password<input type="password" name="password" required autocomplete="current-password" placeholder="Enter your password"></label><button class="btn primary full" type="submit">Sign in <span>→</span></button></form><p class="login-note">For security, this application does not provide a default password.</p></div></section></main></body></html>
