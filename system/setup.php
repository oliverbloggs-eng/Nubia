<?php
// Creates the first administrator account. Works only while there are no
// accounts at all, so it is safe to leave in place, but you can delete it
// after the first sign-in.
require __DIR__ . '/inc/bootstrap.php';

try {
    $count = (int)val('SELECT COUNT(*) FROM users');
} catch (PDOException $e) {
    exit('The tables are missing. Import install/database.sql in phpMyAdmin first.');
}
if ($count > 0) redirect('login.php');

$errors = [];
if (is_post()) {
    check_csrf();
    $name = post('name'); $email = mb_strtolower(post('email')); $pass = (string)post('password');
    if ($name === '') $errors[] = 'Enter your name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (strlen($pass) < 10) $errors[] = 'Use a password of at least 10 characters.';
    if ($pass !== post('password2')) $errors[] = 'The two passwords do not match.';
    if (!$errors) {
        q('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, "admin")', [$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
        flash('Administrator account created. Sign in to continue.');
        redirect('login.php');
    }
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>First-time setup · Nubia KC Estates</title><link rel="stylesheet" href="assets/app.css?v=1"></head>
<body><div class="login-page"><form class="login-card" method="post">
  <div class="logo"><img src="assets/mark.svg" alt="" width="56" height="56"><strong>First-time setup</strong><small>Create the administrator</small></div>
  <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
  <?= csrf_field() ?>
  <div class="field"><label>Full name</label><input name="name" required value="<?= e(post('name')) ?>"></div>
  <div class="field"><label>Email</label><input type="email" name="email" required value="<?= e(post('email')) ?>"></div>
  <div class="field"><label>Password <span class="opt">(10+ characters)</span></label><input type="password" name="password" required minlength="10"></div>
  <div class="field"><label>Repeat password</label><input type="password" name="password2" required minlength="10"></div>
  <button class="btn gold" type="submit">Create administrator</button>
</form></div></body></html>
