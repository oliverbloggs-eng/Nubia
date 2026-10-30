<?php
require __DIR__ . '/inc/bootstrap.php';
require_admin();

if (is_post()) {
    $action = post('action');
    $id = (int)post('id');
    if ($action === 'create') {
        $email = mb_strtolower(post('email'));
        $pass = (string)post('password');
        if (post('name') === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 10) {
            flash('Enter a name, a valid email and a password of at least 10 characters.', 'error');
        } elseif (one('SELECT 1 FROM users WHERE email=?', [$email])) {
            flash('An account with that email already exists.', 'error');
        } else {
            q('INSERT INTO users (name, email, password_hash, role) VALUES (?,?,?,?)', [post('name'), $email, password_hash($pass, PASSWORD_DEFAULT), post('role') === 'admin' ? 'admin' : 'staff']);
            log_action("Created staff account $email");
            flash('Account created. Share the password with them privately and ask them to change it under My account.');
        }
    } elseif ($id === (int)user()['id']) {
        flash('Use My account to change your own details.', 'error');
    } elseif ($action === 'toggle') {
        q('UPDATE users SET active = 1 - active WHERE id=?', [$id]);
        log_action("Toggled access for user #$id");
        flash('Access updated.');
    } elseif ($action === 'role') {
        q('UPDATE users SET role=? WHERE id=?', [post('role') === 'admin' ? 'admin' : 'staff', $id]);
        log_action("Changed role for user #$id");
        flash('Role updated.');
    } elseif ($action === 'reset') {
        $pass = (string)post('password');
        if (strlen($pass) < 10) flash('Use at least 10 characters.', 'error');
        else { q('UPDATE users SET password_hash=? WHERE id=?', [password_hash($pass, PASSWORD_DEFAULT), $id]); log_action("Reset password for user #$id"); flash('Password reset.'); }
    }
    redirect('users.php');
}

$rows = all('SELECT * FROM users ORDER BY active DESC, name');
page_start('Staff accounts', 'users');
section_head('Who can sign in', 'Administrators can also delete records, change settings and manage accounts. Staff can do everything else.');
?>
<details class="panel" id="new">
  <summary><?= icon('plus') ?>Add a staff account</summary>
  <div class="body">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="create">
      <div class="form-grid">
        <div><label>Name</label><input name="name" required></div>
        <div><label>Email</label><input type="email" name="email" required></div>
        <div><label>Temporary password <span class="opt">(10+ characters)</span></label><input name="password" required minlength="10" autocomplete="new-password"></div>
        <div><label>Role</label><select name="role"><option value="staff">Staff</option><option value="admin">Administrator</option></select></div>
      </div>
      <div class="form-actions"><button class="btn gold">Create account</button></div>
    </form>
  </div>
</details>

<div class="card flush"><div class="table-wrap"><table>
  <tr><th>Name</th><th>Role</th><th>Last sign-in</th><th>Access</th><th></th></tr>
  <?php foreach ($rows as $u): $me = (int)$u['id'] === (int)user()['id']; ?>
    <tr>
      <td><strong><?= e($u['name']) ?></strong><?= $me ? ' <span class="small muted">(you)</span>' : '' ?><div class="small muted"><?= e($u['email']) ?></div></td>
      <td><?= $u['role'] === 'admin' ? 'Administrator' : 'Staff' ?></td>
      <td><?= $u['last_login'] ? date('d M Y, H:i', strtotime($u['last_login'])) : 'Never' ?></td>
      <td><?= $u['active'] ? badge('Active') : badge('Dormant') ?></td>
      <td><?php if (!$me): ?><div class="row-actions">
        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="role"><input type="hidden" name="id" value="<?= $u['id'] ?>"><input type="hidden" name="role" value="<?= $u['role'] === 'admin' ? 'staff' : 'admin' ?>"><button class="btn sm ghost"><?= $u['role'] === 'admin' ? 'Make staff' : 'Make admin' ?></button></form>
        <form method="post" class="inline" onsubmit="var p=prompt('New password (10+ characters)');if(!p){event.preventDefault();return false;}this.password.value=p;"><?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="<?= $u['id'] ?>"><input type="hidden" name="password"><button class="btn sm ghost">Reset password</button></form>
        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button class="btn sm <?= $u['active'] ? 'danger' : 'ghost' ?>"><?= $u['active'] ? 'Remove access' : 'Restore access' ?></button></form>
      </div><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
</table></div></div>
<?php page_end();
