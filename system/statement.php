<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();

$period = valid_period(get('period'));
$propertyFilter = (int)get('property');
$start = $period . '-01';
$end = date('Y-m-t', strtotime($start));

$rows = [];
foreach (accounts($propertyFilter ?: null) as $a) {
    $u = (int)$a['unit_id']; $c = (int)$a['client_id'];
    $bf = balance($u, $c, $period);
    $billed = (float)val('SELECT COALESCE(SUM(total),0) FROM invoices WHERE unit_id=? AND client_id=? AND period=?', [$u, $c, $period]);
    $paid = (float)val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE unit_id=? AND client_id=? AND paid_on BETWEEN ? AND ?', [$u, $c, $start, $end]);
    $cf = round($bf + $billed - $paid, 2);
    if (abs($bf) < 0.005 && $billed == 0 && $paid == 0) continue;
    $rows[] = array_merge($a, compact('bf', 'billed', 'paid', 'cf'));
}
$t = ['bf' => 0, 'billed' => 0, 'paid' => 0, 'cf' => 0];
foreach ($rows as $r) foreach ($t as $k => $_) $t[$k] += $r[$k];

if (get('export') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="statement-' . $period . '.csv"');
    $f = fopen('php://output', 'w');
    fputcsv($f, ['Property', 'Unit', 'Client', 'Phone', 'Brought forward', 'Billed', 'Paid', 'Balance']);
    foreach ($rows as $r) fputcsv($f, [$r['property'], $r['unit_no'], $r['client'], $r['phone'], $r['bf'], $r['billed'], $r['paid'], $r['cf']]);
    fputcsv($f, ['TOTAL', '', '', '', $t['bf'], $t['billed'], $t['paid'], $t['cf']]);
    exit;
}

$s = settings();
$properties = all('SELECT id, name FROM properties ORDER BY name');
$propName = $propertyFilter ? val('SELECT name FROM properties WHERE id=?', [$propertyFilter]) : 'All properties';

page_start('Master statement', 'statement');
?>
<form class="filters card no-print" method="get">
  <div><label>Property</label><select name="property"><option value="">All properties</option><?php foreach ($properties as $p): ?><option value="<?= $p['id'] ?>" <?= $propertyFilter == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
  <div><label>Month</label><select name="period"><?= period_options($period) ?></select></div>
  <div class="actions"><button class="btn">Show</button>
    <a class="btn ghost" href="?<?= e(http_build_query(['property' => $propertyFilter ?: null, 'period' => $period, 'export' => 'csv'])) ?>"><?= icon('download') ?>CSV</a>
    <button type="button" class="btn ghost" onclick="window.print()"><?= icon('print') ?>Print / PDF</button></div>
</form>

<div class="doc">
  <div class="doc-head">
    <div class="co"><strong><?= e($s['company_name']) ?></strong><div><?= e($s['address']) ?></div><div>Tel <?= e($s['phone']) ?> · <?= e($s['email']) ?></div></div>
    <img src="assets/mark.svg" alt="">
  </div>
  <div class="doc-title"><h2>Master monthly statement</h2><p><?= e($propName) ?> · <?= e(period_label($period)) ?></p></div>
  <?php if (!$rows): ?><div class="empty">No charges, payments or balances for this month.</div><?php else: ?>
  <div class="table-wrap"><table>
    <tr><th>Property / unit</th><th>Client</th><th class="num">Brought forward</th><th class="num">Billed</th><th class="num">Paid</th><th class="num">Balance</th></tr>
    <?php foreach ($rows as $r): ?>
      <tr><td><strong><?= e($r['property']) ?></strong> · <?= e($r['unit_no']) ?></td><td><?= e($r['client']) ?><div class="small muted"><?= e($r['phone']) ?></div></td>
        <td class="num"><?= money($r['bf'], false) ?></td><td class="num"><?= money($r['billed'], false) ?></td><td class="num"><?= money($r['paid'], false) ?></td>
        <td class="num <?= $r['cf'] > 0 ? 'pos' : ($r['cf'] < 0 ? 'neg' : '') ?>"><?= money($r['cf'], false) ?></td></tr>
    <?php endforeach; ?>
    <tfoot><tr><td colspan="2">Totals (KES)</td><td class="num"><?= money($t['bf'], false) ?></td><td class="num"><?= money($t['billed'], false) ?></td><td class="num"><?= money($t['paid'], false) ?></td><td class="num"><?= money($t['cf'], false) ?></td></tr></tfoot>
  </table></div>
  <p class="small muted" style="margin-top:14px">Balance = brought forward + billed − paid. A negative balance means the client is in credit. Generated <?= date('d M Y, H:i') ?>.</p>
  <?php endif; ?>
</div>
<?php page_end();
