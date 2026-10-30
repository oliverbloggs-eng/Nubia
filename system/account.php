<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();

if (is_post()) {
    $me = one('SELECT * FROM users WHERE id=?', [user()['id']]);
    $new = (string)post('new_password');
    if (!password_verify((string)post('current_password'), $me['password_hash'])) {
        flash('Your current password is incorrect.', 'error');
    } elseif (strlen($new) < 10) {
        flash('Use a new password of at least 10 characters.', 'error');
    } elseif ($new !== post('new_password2')) {
        flash('The two new passwords do not match.', 'error');
    } else {
        q('UPDATE users SET password_hash=? WHERE id=?', [password_hash($new, PASSWORD_DEFAULT), $me['id']]);
        session_regenerate_id(true);
        log_action('Changed own password');
        flash('Password changed.');
    }
    redirect('account.php');
}

page_start('My account');
?>
<div class="card" style="max-width:560px">
  <h3>Change your password</h3>
  <p class="muted small">Signed in as <?= e(user()['email']) ?></p>
  <form method="post">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="full"><label>Current password</label><input type="password" name="current_password" required autocomplete="current-password"></div>
      <div><label>New password</label><input type="password" name="new_password" required minlength="10" autocomplete="new-password"></div>
      <div><label>Repeat new password</label><input type="password" name="new_password2" required minlength="10" autocomplete="new-password"></div>
    </div>
    <div class="form-actions"><button class="btn gold">Change password</button></div>
  </form>
</div>
<?php page_end();
