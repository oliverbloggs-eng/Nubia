<?php
// Monthly statement for a landlord: what was collected on their property,
// less the management fee and repair costs, leaves the amount paid out to them.
require __DIR__ . '/inc/bootstrap.php';
require_login();

$period = valid_period(get('period'));
$start = $period . '-01';
$end = date('Y-m-t', strtotime($start));
$properties = all('SELECT p.*, c.name owner, c.phone owner_phone, c.email owner_email, c.bank_details, c.based_in FROM properties p LEFT JOIN clients c ON c.id=p.owner_id ORDER BY p.name');

page_start('Owner statements', 'owner');
if (!$properties) { echo '<div class="card empty">Add a property first.</div>'; page_end(); exit; }

$pid = (int)get('property') ?: (int)$properties[0]['id'];
$prop = null;
foreach ($properties as $p) if ((int)$p['id'] === $pid) $prop = $p;
if (!$prop) $prop = $properties[0];
$pid = (int)$prop['id'];

$units = all('SELECT u.*, c.name client FROM units u LEFT JOIN clients c ON c.id=u.client_id WHERE u.property_id=? ORDER BY LENGTH(u.unit_no), u.unit_no', [$pid]);
$rows = [];
foreach ($units as $u) {
    $billed = (float)val('SELECT COALESCE(SUM(total),0) FROM invoices WHERE unit_id=? AND period=?', [$u['id'], $period]);
    $collected = (float)val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE unit_id=? AND paid_on BETWEEN ? AND ?', [$u['id'], $start, $end]);
    $rows[] = $u + compact('billed', 'collected');
}
$totBilled = array_sum(array_column($rows, 'billed'));
$totCollected = array_sum(array_column($rows, 'collected'));
$feePct = (float)$prop['fee_pct'];
$fee = round($totCollected * $feePct / 100, 2);
$repairs = all('SELECT m.*, u.unit_no FROM maintenance m LEFT JOIN units u ON u.id=m.unit_id WHERE m.property_id=? AND m.status="Done" AND m.charge_owner=1 AND m.cost > 0 AND m.completed_on BETWEEN ? AND ? ORDER BY m.completed_on', [$pid, $start, $end]);
$repairTotal = array_sum(array_column($repairs, 'cost'));
$net = round($totCollected - $fee - $repairTotal, 2);
$s = settings();
$feeLabel = rtrim(rtrim(number_format($feePct, 2), '0'), '.');
?>
<form class="filters card no-print" method="get">
  <div><label>Property</label><select name="property"><?php foreach ($properties as $p): ?><option value="<?= $p['id'] ?>" <?= $pid === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
  <div><label>Month</label><select name="period"><?= period_options($period) ?></select></div>
  <div class="actions"><button class="btn">Show</button><button type="button" class="btn ghost" onclick="window.print()"><?= icon('print') ?>Print / PDF</button>
  <?php if ($prop['owner_phone']): ?><a class="btn ghost" target="_blank" rel="noopener" href="<?= e(wa_link($prop['owner_phone'], "Hello {$prop['owner']}, your " . period_label($period) . " statement for {$prop['name']}: collected " . money($totCollected) . ", management fee " . money($fee) . ($repairTotal ? ', repairs ' . money($repairTotal) : '') . ', net payable to you ' . money($net) . '. The full statement is attached. ' . $s['company_name'])) ?>"><?= icon('whatsapp') ?>Send summary</a><?php endif; ?></div>
</form>

<div class="doc">
  <div class="doc-head">
    <div class="co"><strong><?= e($s['company_name']) ?></strong><div><?= e($s['address']) ?></div><div>Tel <?= e($s['phone']) ?> · <?= e($s['email']) ?></div></div>
    <img src="assets/mark.svg" alt="">
  </div>
  <div class="doc-title"><h2>Owner statement</h2><p><?= e($prop['name']) ?>, <?= e($prop['location']) ?> · <?= e(period_label($period)) ?></p></div>
  <div class="grid grid-2" style="margin-bottom:18px">
    <dl class="kv"><dt>Owner</dt><dd><strong><?= e($prop['owner'] ?: 'Not set') ?></strong></dd><dt>Based in</dt><dd><?= e($prop['based_in']) ?: '—' ?></dd></dl>
    <dl class="kv"><dt>Payout to</dt><dd><?= e($prop['bank_details']) ?: '—' ?></dd><dt>Management fee</dt><dd><?= $feeLabel ?>% of rent collected</dd></dl>
  </div>

  <div class="table-wrap"><table>
    <tr><th>Unit</th><th>Occupant</th><th>Status</th><th class="num">Billed</th><th class="num">Collected</th></tr>
    <?php foreach ($rows as $r): ?>
      <tr><td><?= e($r['unit_no']) ?></td><td><?= e($r['client'] ?? '—') ?></td><td><?= e($r['status']) ?></td><td class="num"><?= money($r['billed'], false) ?></td><td class="num"><?= money($r['collected'], false) ?></td></tr>
    <?php endforeach; ?>
    <tfoot><tr><td colspan="3">Totals (KES)</td><td class="num"><?= money($totBilled, false) ?></td><td class="num"><?= money($totCollected, false) ?></td></tr></tfoot>
  </table></div>

  <?php if ($repairs): ?>
  <h3 style="margin:24px 0 10px;font-size:16px">Repairs and maintenance</h3>
  <div class="table-wrap"><table>
    <tr><th>Completed</th><th>Job</th><th class="num">Cost</th></tr>
    <?php foreach ($repairs as $m): ?><tr><td><?= fdate($m['completed_on']) ?></td><td><?= e($m['title']) ?><?= $m['unit_no'] ? ' · unit ' . e($m['unit_no']) : '' ?></td><td class="num"><?= money($m['cost'], false) ?></td></tr><?php endforeach; ?>
  </table></div>
  <?php endif; ?>

  <table style="margin-top:24px;max-width:460px;margin-left:auto">
    <tr><td>Rent collected</td><td class="num"><?= money($totCollected) ?></td></tr>
    <tr><td>Less management fee (<?= $feeLabel ?>%)</td><td class="num">− <?= money($fee) ?></td></tr>
    <tr><td>Less repairs and maintenance</td><td class="num">− <?= money($repairTotal) ?></td></tr>
    <tfoot><tr><td>Net payable to owner</td><td class="num"><?= money($net) ?></td></tr></tfoot>
  </table>
  <p class="small muted" style="margin-top:16px">Collected means money received during <?= e(period_label($period)) ?>, including any arrears paid. Generated <?= date('d M Y, H:i') ?>.</p>
</div>
<?php page_end();
