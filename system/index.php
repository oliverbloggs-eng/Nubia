<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();

$period = current_period();
$start = $period . '-01';
$end = date('Y-m-t', strtotime($start));

$billed    = (float)val('SELECT COALESCE(SUM(total),0) FROM invoices WHERE period = ?', [$period]);
$collected = (float)val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE paid_on BETWEEN ? AND ?', [$start, $end]);
$rate      = $billed > 0 ? min(100, round($collected / $billed * 100)) : 0;

$arrears = 0.0; $inArrears = 0;
foreach (accounts() as $a) {
    $bal = (float)$a['invoiced'] - (float)$a['paid'];
    if ($bal > 0.004) { $arrears += $bal; $inArrears++; }
}

$counts = [];
foreach (all('SELECT status, COUNT(*) n FROM units GROUP BY status') as $r) $counts[$r['status']] = (int)$r['n'];
$totalUnits = array_sum($counts);
$occupied = $counts['Occupied'] ?? 0;
$occupancy = ($totalUnits - ($counts['Sold'] ?? 0) - ($counts['Dormant'] ?? 0)) > 0
    ? round($occupied / ($totalUnits - ($counts['Sold'] ?? 0) - ($counts['Dormant'] ?? 0)) * 100) : 0;

$propertiesN = (int)val('SELECT COUNT(*) FROM properties');
$clientsN    = (int)val('SELECT COUNT(*) FROM clients WHERE status = "Active"');
$ownersN     = (int)val('SELECT COUNT(*) FROM clients WHERE type = "Owner" AND status = "Active"');
$openJobs    = (int)val('SELECT COUNT(*) FROM maintenance WHERE status <> "Done"');
$billedThisMonth = (int)val('SELECT COUNT(*) FROM invoices WHERE period = ?', [$period]);
$billable    = (int)val('SELECT COUNT(*) FROM units WHERE client_id IS NOT NULL AND (monthly_amount + service_charge) > 0 AND status IN ("Occupied","Sold")');

$recentPayments = all('SELECT y.*, c.name client, u.unit_no, p.name property FROM payments y JOIN clients c ON c.id=y.client_id JOIN units u ON u.id=y.unit_id JOIN properties p ON p.id=u.property_id ORDER BY y.paid_on DESC, y.id DESC LIMIT 6');
$urgentJobs = all('SELECT m.*, p.name property, u.unit_no FROM maintenance m JOIN properties p ON p.id=m.property_id LEFT JOIN units u ON u.id=m.unit_id WHERE m.status <> "Done" ORDER BY FIELD(m.priority,"Urgent","Normal","Low"), m.reported_on LIMIT 5');

// Until migration 002 has been run the table is missing; the dashboard must keep working.
try {
    $listingCounts = array_column(all('SELECT status, COUNT(*) n FROM listings GROUP BY status'), 'n', 'status');
} catch (PDOException $ex) {
    $listingCounts = null;
}

page_start('Dashboard', 'dashboard');
?>
<?php if ($billable > $billedThisMonth): ?>
  <div class="alert alert-info"><?= $billable - $billedThisMonth ?> unit(s) have not been billed for <?= e(period_label($period)) ?> yet. <a href="billing.php">Run monthly billing</a>.</div>
<?php endif; ?>

<?php section_head(period_label($period), 'Figures for this month. Arrears cover all months to date.'); ?>

<div class="grid grid-4">
  <div class="stat navy"><div class="label">Billed this month</div><div class="value"><?= money($billed) ?></div><div class="hint"><?= $billedThisMonth ?> invoice(s)</div></div>
  <div class="stat green"><div class="label">Collected this month</div><div class="value"><?= money($collected) ?></div><div class="hint">Payments dated this month</div></div>
  <div class="stat"><div class="label">Collection rate</div><div class="value"><?= $rate ?>%</div><div class="bar"><span style="width:<?= $rate ?>%"></span></div></div>
  <div class="stat red"><div class="label">Total arrears</div><div class="value"><?= money($arrears) ?></div><div class="hint"><a href="arrears.php"><?= $inArrears ?> account(s) owing</a></div></div>
</div>

<div class="tiles mt">
  <a class="tile" href="properties.php"><span class="ic"><?= icon('building') ?></span><span><strong><?= $propertiesN ?></strong><small>Properties</small></span></a>
  <a class="tile" href="units.php"><span class="ic"><?= icon('door') ?></span><span><strong><?= $totalUnits ?></strong><small>Units · <?= $occupancy ?>% occupied</small></span></a>
  <a class="tile" href="units.php?status=Available"><span class="ic"><?= icon('door') ?></span><span><strong><?= $counts['Available'] ?? 0 ?></strong><small>Vacant units</small></span></a>
  <a class="tile" href="units.php?status=Occupied"><span class="ic"><?= icon('users') ?></span><span><strong><?= $occupied ?></strong><small>Occupied units</small></span></a>
  <a class="tile" href="units.php?status=Sold"><span class="ic"><?= icon('owner') ?></span><span><strong><?= $counts['Sold'] ?? 0 ?></strong><small>Sold units</small></span></a>
  <a class="tile" href="clients.php"><span class="ic"><?= icon('users') ?></span><span><strong><?= $clientsN ?></strong><small>Active clients</small></span></a>
  <a class="tile" href="clients.php?type=Owner"><span class="ic"><?= icon('owner') ?></span><span><strong><?= $ownersN ?></strong><small>Property owners</small></span></a>
  <a class="tile" href="maintenance.php"><span class="ic"><?= icon('wrench') ?></span><span><strong><?= $openJobs ?></strong><small>Open repairs</small></span></a>
</div>

<?php if ($listingCounts !== null): ?>
<div class="card mt">
  <h3>Listings</h3>
  <dl class="kv">
    <dt>Published</dt><dd><?= (int)($listingCounts['published'] ?? 0) ?></dd>
    <?php if (is_admin()): ?><dt>Waiting for review</dt><dd><a href="listings.php?status=review"><?= (int)($listingCounts['review'] ?? 0) ?></a></dd><?php endif; ?>
    <dt>Drafts</dt><dd><?= (int)($listingCounts['draft'] ?? 0) ?></dd>
  </dl>
</div>
<?php endif; ?>

<div class="grid grid-2 mt">
  <div class="card flush">
    <div style="padding:20px 22px 6px;display:flex;justify-content:space-between;align-items:center"><h3 style="margin:0">Latest payments</h3><a class="btn sm gold" href="payments.php#new"><?= icon('plus') ?>Record payment</a></div>
    <?php if (!$recentPayments): ?><div class="empty">No payments recorded yet.</div><?php else: ?>
    <div class="table-wrap"><table>
      <tr><th>Date</th><th>Client</th><th>Unit</th><th class="num">Amount</th></tr>
      <?php foreach ($recentPayments as $p): ?>
        <tr><td class="nowrap"><?= fdate($p['paid_on']) ?></td><td><?= e($p['client']) ?><div class="small muted"><?= e($p['method']) ?> <?= e($p['reference']) ?></div></td><td><?= e($p['property']) ?> · <?= e($p['unit_no']) ?></td><td class="num"><?= money($p['amount']) ?></td></tr>
      <?php endforeach; ?>
    </table></div><?php endif; ?>
  </div>
  <div class="card flush">
    <div style="padding:20px 22px 6px;display:flex;justify-content:space-between;align-items:center"><h3 style="margin:0">Open repairs</h3><a class="btn sm ghost" href="maintenance.php">View all</a></div>
    <?php if (!$urgentJobs): ?><div class="empty">No open repairs.</div><?php else: ?>
    <div class="table-wrap"><table>
      <tr><th>Job</th><th>Where</th><th>Priority</th></tr>
      <?php foreach ($urgentJobs as $j): ?>
        <tr><td><?= e($j['title']) ?><div class="small muted">Reported <?= fdate($j['reported_on']) ?></div></td><td><?= e($j['property']) ?><?= $j['unit_no'] ? ' · ' . e($j['unit_no']) : '' ?></td><td><?= badge($j['priority']) ?></td></tr>
      <?php endforeach; ?>
    </table></div><?php endif; ?>
  </div>
</div>
<?php page_end();
