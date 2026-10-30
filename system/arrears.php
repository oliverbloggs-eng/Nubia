<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();

$propertyFilter = (int)get('property');
$s = settings();
$rows = [];
foreach (accounts($propertyFilter ?: null) as $a) {
    $bal = round($a['invoiced'] - $a['paid'], 2);
    if ($bal > 0.004) $rows[] = $a + ['balance' => $bal];
}
usort($rows, fn($x, $y) => $y['balance'] <=> $x['balance']);
$total = array_sum(array_column($rows, 'balance'));

if (get('export') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="arrears-' . date('Y-m-d') . '.csv"');
    $f = fopen('php://output', 'w');
    fputcsv($f, ['Property', 'Unit', 'Client', 'Phone', 'Invoiced', 'Paid', 'Balance', 'Last payment', 'Status']);
    foreach ($rows as $r) fputcsv($f, [$r['property'], $r['unit_no'], $r['client'], $r['phone'], $r['invoiced'], $r['paid'], $r['balance'], $r['last_paid'], $r['is_current'] ? 'Current' : 'Moved out']);
    exit;
}

page_start('Arrears', 'arrears');
section_head('Accounts owing', count($rows) . ' account(s) · ' . money($total) . ' outstanding',
    '<a class="btn ghost" href="?' . e(http_build_query(['property' => $propertyFilter ?: null, 'export' => 'csv'])) . '">' . icon('download') . 'Export CSV</a>');
?>
<form class="filters card" method="get">
  <div><label>Property</label><select name="property" onchange="this.form.submit()"><option value="">All properties</option><?php foreach (all('SELECT id, name FROM properties ORDER BY name') as $p): ?><option value="<?= $p['id'] ?>" <?= $propertyFilter == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
</form>

<div class="card flush">
  <?php if (!$rows): ?><div class="empty">Nobody owes anything. Every account is paid up.</div><?php else: ?>
  <div class="table-wrap"><table>
    <tr><th>Client</th><th>Unit</th><th>Last payment</th><th class="num">Balance</th><th></th></tr>
    <?php foreach ($rows as $r):
      $msg = "Hello {$r['client']}, this is a reminder from " . ($s['company_name'] ?? 'Nubia KC Estates') . " that your account for {$r['property']} unit {$r['unit_no']} has an outstanding balance of " . money($r['balance']) . '. ' . ($s['payment_instructions'] ?? '') . ' If you have already paid, please share the reference. Thank you.'; ?>
      <tr>
        <td><a href="clients.php?view=<?= $r['client_id'] ?>"><strong><?= e($r['client']) ?></strong></a><div class="small muted"><?= e($r['phone']) ?><?= $r['is_current'] ? '' : ' · moved out' ?></div></td>
        <td><?= e($r['property']) ?> · <?= e($r['unit_no']) ?></td>
        <td><?= $r['last_paid'] ? fdate($r['last_paid']) : '<span class="pos">Never paid</span>' ?></td>
        <td class="num pos"><?= money($r['balance']) ?></td>
        <td><div class="row-actions">
          <?php if ($r['phone']): ?><a class="btn sm ghost" href="<?= e(wa_link($r['phone'], $msg)) ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?>Remind</a><?php endif; ?>
          <a class="btn sm ghost" href="ledger.php?unit=<?= $r['unit_id'] ?>&client=<?= $r['client_id'] ?>">Ledger</a>
          <a class="btn sm gold" href="payments.php?account=<?= $r['unit_id'] ?>-<?= $r['client_id'] ?>#new">Record payment</a>
        </div></td>
      </tr>
    <?php endforeach; ?>
    <tfoot><tr><td colspan="3">Total arrears</td><td class="num"><?= money($total) ?></td><td></td></tr></tfoot>
  </table></div><?php endif; ?>
</div>
<p class="muted small">The Remind button opens WhatsApp with the message filled in, using your own WhatsApp. There is no SMS cost.</p>
<?php page_end();
