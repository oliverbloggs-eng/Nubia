<?php
require __DIR__ . '/inc/bootstrap.php';

if (user()) redirect('index.php');

// First run: no accounts yet, send to setup
if ((int)val('SELECT COUNT(*) FROM users') === 0) redirect('setup.php');

$error = '';
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if (is_post()) {
    check_csrf();
    $recent = (int)val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > (NOW() - INTERVAL 15 MINUTE)', [$ip]);
    if ($recent >= 5) {
        $error = 'Too many failed attempts. Wait 15 minutes and try again.';
    } else {
        $u = one('SELECT * FROM users WHERE email = ? AND active = 1', [mb_strtolower(post('email'))]);
        if ($u && password_verify((string)post('password'), $u['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => (int)$u['id'], 'name' => $u['name'], 'email' => $u['email'], 'role' => $u['role']];
            q('UPDATE users SET last_login = NOW() WHERE id = ?', [$u['id']]);
            q('DELETE FROM login_attempts WHERE ip = ?', [$ip]);
            log_action('Signed in');
            redirect('index.php');
        }
        q('INSERT INTO login_attempts (ip) VALUES (?)', [$ip]);
        q('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
        $error = 'Email or password is incorrect.';
    }
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Sign in · Nubia KC Estates</title>
<link rel="icon" href="assets/mark.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/app.css?v=1">
</head>
<body>
<div class="login-page">
  <form class="login-card" method="post" autocomplete="on">
    <div class="logo">
      <img src="assets/mark.svg" alt="" width="56" height="56">
      <strong>Nubia KC Estates</strong>
      <small>Management System</small>
    </div>
    <?php foreach (take_flash() as [$t, $m]): ?><div class="alert alert-<?= e($t) ?>"><?= e($m) ?></div><?php endforeach; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" required autofocus value="<?= e(post('email')) ?>"></div>
    <div class="field"><label for="password">Password</label><input id="password" type="password" name="password" required></div>
    <button class="btn gold" type="submit">Sign in</button>
  </form>
</div>
</body>
</html>
