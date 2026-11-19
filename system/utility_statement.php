<?php
// Utility billing statement: one property, one month, one utility.
require __DIR__ . '/inc/bootstrap.php';
require_login();

$propertyId = (int)get('property');
$period = valid_period(get('period'));
$utilityIn = get('utility');
$utility = is_string($utilityIn) && isset(UTILITIES[$utilityIn]) ? $utilityIn : 'water';
$label = UTILITIES[$utility];
$property = $propertyId ? one('SELECT * FROM properties WHERE id=?', [$propertyId]) : null;

$rows = $property ? all('
    SELECT m.*, u.unit_no, c.name client, c.phone, i.invoice_no
    FROM meter_readings m JOIN units u ON u.id=m.unit_id JOIN clients c ON c.id=m.client_id LEFT JOIN invoices i ON i.id=m.invoice_id
    WHERE u.property_id=? AND m.utility=? AND m.period=?
    ORDER BY LENGTH(u.unit_no), u.unit_no', [$propertyId, $utility, $period . '-01']) : [];
$totUnits = array_sum(array_column($rows, 'units_used'));
$totAmount = array_sum(array_column($rows, 'amount'));

if ($property && get('export') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $utility . '-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($property['name'])) . '-' . $period . '.csv"');
    $f = fopen('php://output', 'w');
    fputcsv($f, ['Property', 'Month', 'Utility', 'Unit', 'Tenant', 'Phone', 'Previous', 'Current', 'Meter replaced', 'Units used', 'Price per unit', 'Amount', 'Invoice']);
    foreach ($rows as $r) fputcsv($f, [$property['name'], period_label($period), $label, $r['unit_no'], $r['client'], $r['phone'], $r['previous_reading'], $r['current_reading'], $r['meter_replaced'] ? 'Yes' : 'No', $r['units_used'], $r['price_per_unit'], $r['amount'], $r['invoice_no']]);
    fputcsv($f, ['TOTAL', '', '', '', '', '', '', '', '', $totUnits, '', $totAmount, '']);
    exit;
}

$s = settings();
$properties = all('SELECT id, name FROM properties ORDER BY name');
page_start('Utility statement', 'utility_statement');
?>
<form class="filters card no-print" method="get">
  <div><label>Property</label><select name="property" required><option value="">— Choose —</option><?php foreach ($properties as $p): ?><option value="<?= $p['id'] ?>" <?= $propertyId == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
  <div><label>Month</label><select name="period"><?= period_options($period) ?></select></div>
  <div><label>Utility</label><select name="utility"><?= options(UTILITIES, $utility, true) ?></select></div>
  <div class="actions"><button class="btn">Show</button>
    <?php if ($property): ?>
    <a class="btn ghost" href="?<?= e(http_build_query(['property' => $propertyId, 'period' => $period, 'utility' => $utility, 'export' => 'csv'])) ?>"><?= icon('download') ?>CSV</a>
    <button type="button" class="btn ghost" onclick="window.print()"><?= icon('print') ?>Print / PDF</button>
    <a class="btn ghost" href="meter_readings.php?<?= e(http_build_query(['property' => $propertyId, 'period' => $period, 'utility' => $utility])) ?>">Edit readings</a>
    <?php endif; ?></div>
</form>

<?php if (!$property): ?>
  <div class="card empty">Choose a property, month and utility, then select Show.</div>
<?php else: ?>
<div class="doc">
  <div class="doc-head">
    <div class="co"><strong><?= e($s['company_name']) ?></strong><div><?= e($s['address']) ?></div><div>Tel <?= e($s['phone']) ?> · <?= e($s['email']) ?></div></div>
    <img src="assets/mark.svg" alt="">
  </div>
  <div class="doc-title"><h2><?= e($label) ?> billing statement</h2><p><?= e($property['name']) ?> · <?= e(period_label($period)) ?></p></div>
  <?php if (!$rows): ?><div class="empty">No <?= e(strtolower($label)) ?> readings saved for this month.</div><?php else: ?>
  <div class="table-wrap"><table>
    <tr><th>Unit</th><th>Tenant</th><th class="num">Previous</th><th class="num">Current</th><th class="num">Units</th><th class="num">Price</th><th class="num">Amount</th></tr>
    <?php foreach ($rows as $r): ?>
      <tr><td><strong><?= e($r['unit_no']) ?></strong></td><td><?= e($r['client']) ?></td>
        <td class="num"><?= e(qty($r['previous_reading'])) ?><?= $r['meter_replaced'] ? '<div class="small muted">new meter</div>' : '' ?></td>
        <td class="num"><?= e(qty($r['current_reading'])) ?></td><td class="num"><?= e(qty($r['units_used'])) ?></td>
        <td class="num"><?= money($r['price_per_unit'], false) ?></td><td class="num"><?= money($r['amount'], false) ?></td></tr>
    <?php endforeach; ?>
    <tfoot><tr><td colspan="4">Total · <?= count($rows) ?> unit(s)</td><td class="num"><?= e(qty($totUnits)) ?></td><td></td><td class="num"><?= money($totAmount) ?></td></tr></tfoot>
  </table></div>
  <?php endif; ?>
  <p class="muted small" style="margin-top:24px">Printed <?= fdate(date('Y-m-d')) ?> · <?= e($s['company_name']) ?></p>
</div>
<?php endif;
page_end();
