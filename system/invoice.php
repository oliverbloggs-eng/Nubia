<?php
// Printable invoice (?id=) or payment receipt (?receipt=). Use the browser's
// Print button and choose "Save as PDF" to email it.
require __DIR__ . '/inc/bootstrap.php';
require_login();

$s = settings();
$receipt = (int)get('receipt');

if ($receipt) {
    $d = one('SELECT y.*, u.unit_no, p.name property, c.name client, c.phone, c.email FROM payments y JOIN units u ON u.id=y.unit_id JOIN properties p ON p.id=u.property_id JOIN clients c ON c.id=y.client_id WHERE y.id=?', [$receipt]);
    $title = 'Receipt';
} else {
    $d = one('SELECT i.*, u.unit_no, p.name property, c.name client, c.phone, c.email FROM invoices i JOIN units u ON u.id=i.unit_id JOIN properties p ON p.id=u.property_id JOIN clients c ON c.id=i.client_id WHERE i.id=?', [(int)get('id')]);
    $title = 'Invoice';
}
if (!$d) { page_start($title); echo '<div class="card empty">Not found.</div>'; page_end(); exit; }

$bal = balance((int)$d['unit_id'], (int)$d['client_id']);
page_start($title, $receipt ? 'payments' : 'billing');
?>
<div class="actions no-print" style="margin-bottom:16px"><button class="btn gold" onclick="window.print()"><?= icon('print') ?>Print or save as PDF</button><a class="btn ghost" href="<?= $receipt ? 'payments.php' : 'billing.php?period=' . e($d['period']) ?>">Back</a></div>
<div class="doc" style="max-width:820px">
  <div class="doc-head">
    <div class="co"><strong><?= e($s['company_name']) ?></strong><div><?= e($s['address']) ?></div><div>Tel <?= e($s['phone']) ?> · <?= e($s['email']) ?> · <?= e($s['website']) ?></div></div>
    <img src="assets/mark.svg" alt="">
  </div>
  <div class="doc-title"><h2><?= $receipt ? 'Payment receipt' : 'Invoice' ?></h2><p><?= $receipt ? 'Receipt no. RCT-' . str_pad((string)$d['id'], 6, '0', STR_PAD_LEFT) : e($d['invoice_no']) ?></p></div>
  <div class="grid grid-2" style="margin-bottom:20px">
    <dl class="kv"><dt><?= $receipt ? 'Received from' : 'Billed to' ?></dt><dd><strong><?= e($d['client']) ?></strong></dd><dt>Phone</dt><dd><?= e($d['phone']) ?: '—' ?></dd><dt>Property</dt><dd><?= e($d['property']) ?>, unit <?= e($d['unit_no']) ?></dd></dl>
    <dl class="kv">
      <?php if ($receipt): ?><dt>Date paid</dt><dd><?= fdate($d['paid_on']) ?></dd><dt>Method</dt><dd><?= e($d['method']) ?></dd><dt>Reference</dt><dd><?= e($d['reference']) ?: '—' ?></dd>
      <?php else: ?><dt>Period</dt><dd><?= e(period_label($d['period'])) ?></dd><dt>Issued</dt><dd><?= fdate(substr($d['created_at'], 0, 10)) ?></dd><dt>Due date</dt><dd><strong><?= fdate($d['due_date']) ?></strong></dd><?php endif; ?>
    </dl>
  </div>
  <table>
    <tr><th>Description</th><th class="num">Amount</th></tr>
    <?php if ($receipt): ?>
      <tr><td>Payment received<?= $d['notes'] ? ' · ' . e($d['notes']) : '' ?></td><td class="num"><?= money($d['amount']) ?></td></tr>
      <tfoot><tr><td>Total received</td><td class="num"><?= money($d['amount']) ?></td></tr></tfoot>
    <?php else: ?>
      <tr><td><?= (float)$d['amount'] ? 'Rent / instalment for ' . e(period_label($d['period'])) : '' ?></td><td class="num"><?= money($d['amount']) ?></td></tr>
      <?php if ((float)$d['service_charge']): ?><tr><td>Service charge</td><td class="num"><?= money($d['service_charge']) ?></td></tr><?php endif; ?>
      <?php if ((float)$d['other_charges']): ?><tr><td><?= e($d['other_label'] ?: 'Other charges') ?></td><td class="num"><?= money($d['other_charges']) ?></td></tr><?php endif; ?>
      <tfoot><tr><td>Invoice total</td><td class="num"><?= money($d['total']) ?></td></tr></tfoot>
    <?php endif; ?>
  </table>
  <p style="margin-top:18px"><strong><?= $bal > 0 ? 'Balance due on your account: ' . money($bal) : ($bal < 0 ? 'Your account is in credit by ' . money(-$bal) : 'Your account is fully paid up.') ?></strong></p>
  <?php if (!$receipt && !empty($s['payment_instructions'])): ?><p class="muted"><?= e($s['payment_instructions']) ?></p><?php endif; ?>
  <p class="muted small" style="margin-top:30px">Thank you. <?= e($s['company_name']) ?></p>
</div>
<?php page_end();
