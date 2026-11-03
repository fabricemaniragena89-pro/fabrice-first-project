<?php
require_once __DIR__ . '/../app/bootstrap.php';
if (current_user()) {
    try { db()->prepare('INSERT INTO audit_logs (user_id,action,entity_type,entity_id,ip_address) VALUES (?,?,?,?,?)')->execute([current_user()['id'],'logout','user',current_user()['id'],$_SERVER['REMOTE_ADDR'] ?? null]); } catch (Throwable $ignored) {}
}
$_SESSION = [];
if (ini_get('session.use_cookies')) { $p = session_get_cookie_params(); setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']); }
session_destroy(); header('Location: login.php'); exit;
