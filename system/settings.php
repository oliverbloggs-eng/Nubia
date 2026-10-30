<?php
require __DIR__ . '/inc/bootstrap.php';
require_admin();

if (is_post()) {
    q('UPDATE settings SET company_name=?, address=?, phone=?, email=?, website=?, default_fee_pct=?, rent_due_day=?, payment_instructions=? WHERE id=1', [
        post('company_name'), post('address'), post('phone'), post('email'), post('website'),
        num(post('default_fee_pct')), max(1, min(28, (int)post('rent_due_day'))), post('payment_instructions'),
    ]);
    log_action('Updated company settings');
    flash('Settings saved.');
    redirect('settings.php');
}

$s = settings();
$log = all('SELECT a.*, u.name FROM activity_log a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 40');
page_start('Company settings', 'settings');
?>
<div class="card">
  <h3>Details on invoices, receipts and statements</h3>
  <form method="post">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div><label>Company name</label><input name="company_name" required value="<?= e($s['company_name']) ?>"></div>
      <div><label>Website</label><input name="website" value="<?= e($s['website']) ?>"></div>
      <div class="full"><label>Address</label><input name="address" value="<?= e($s['address']) ?>"></div>
      <div><label>Phone</label><input name="phone" value="<?= e($s['phone']) ?>"></div>
      <div><label>Email</label><input type="email" name="email" value="<?= e($s['email']) ?>"></div>
      <div><label>Default management fee (%)</label><input type="number" step="0.01" min="0" max="100" name="default_fee_pct" value="<?= e($s['default_fee_pct']) ?>"><div class="help">Used for new properties. Each property can have its own rate.</div></div>
      <div><label>Rent due on day</label><input type="number" min="1" max="28" name="rent_due_day" value="<?= e($s['rent_due_day']) ?>"><div class="help">Day of the month printed as the due date on invoices.</div></div>
      <div class="full"><label>Payment instructions</label><input name="payment_instructions" value="<?= e($s['payment_instructions']) ?>" placeholder="e.g. Pay via M-Pesa Paybill 123456, account = your unit number."><div class="help">Shown on invoices and added to WhatsApp reminders.</div></div>
    </div>
    <div class="form-actions"><button class="btn gold">Save settings</button></div>
  </form>
</div>

<div class="card flush">
  <div style="padding:20px 22px 6px"><h3>Recent activity</h3></div>
  <div class="table-wrap"><table>
    <tr><th>When</th><th>Who</th><th>What</th></tr>
    <?php foreach ($log as $l): ?><tr><td class="nowrap"><?= date('d M Y, H:i', strtotime($l['created_at'])) ?></td><td><?= e($l['name'] ?? '—') ?></td><td><?= e($l['action']) ?></td></tr><?php endforeach; ?>
  </table></div>
</div>
<?php page_end();
