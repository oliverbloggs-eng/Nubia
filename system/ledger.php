<?php
// Running account for one client in one unit: every charge and payment in date order.
require __DIR__ . '/inc/bootstrap.php';
require_login();

$unitId = (int)get('unit');
$clientId = (int)get('client');
$info = one('SELECT u.unit_no, u.monthly_amount, u.service_charge, p.name property, c.name client, c.phone FROM units u JOIN properties p ON p.id=u.property_id, clients c WHERE u.id=? AND c.id=?', [$unitId, $clientId]);

page_start('Account ledger', 'units');
if (!$info) { echo '<div class="card empty">Account not found.</div>'; page_end(); exit; }

$lines = array_merge(
    array_map(fn($i) => ['date' => $i['due_date'], 'sort' => 0, 'desc' => 'Invoice ' . $i['invoice_no'] . ' · ' . period_label($i['period']) . ((float)$i['other_charges'] ? ' (incl. ' . ($i['other_label'] ?: 'other') . ')' : ''), 'debit' => (float)$i['total'], 'credit' => 0.0, 'link' => 'invoice.php?id=' . $i['id']],
        all('SELECT * FROM invoices WHERE unit_id=? AND client_id=?', [$unitId, $clientId])),
    array_map(fn($p) => ['date' => $p['paid_on'], 'sort' => 1, 'desc' => 'Payment · ' . $p['method'] . ($p['reference'] ? ' ' . $p['reference'] : ''), 'debit' => 0.0, 'credit' => (float)$p['amount'], 'link' => 'invoice.php?receipt=' . $p['id']],
        all('SELECT * FROM payments WHERE unit_id=? AND client_id=?', [$unitId, $clientId]))
);
usort($lines, fn($a, $b) => [$a['date'], $a['sort']] <=> [$b['date'], $b['sort']]);

section_head($info['client'] . ' · ' . $info['property'] . ' ' . $info['unit_no'], 'Monthly: ' . money($info['monthly_amount'] + $info['service_charge']),
    '<button class="btn ghost no-print" onclick="window.print()">' . icon('print') . 'Print</button><a class="btn gold no-print" href="payments.php?account=' . $unitId . '-' . $clientId . '#new">Record payment</a>');
?>
<div class="card flush">
  <?php if (!$lines): ?><div class="empty">No charges or payments yet.</div><?php else: ?>
  <div class="table-wrap"><table>
    <tr><th>Date</th><th>Details</th><th class="num">Charged</th><th class="num">Paid</th><th class="num">Balance</th></tr>
    <?php $run = 0; $td = 0; $tc = 0; foreach ($lines as $l): $run += $l['debit'] - $l['credit']; $td += $l['debit']; $tc += $l['credit']; ?>
      <tr><td class="nowrap"><?= fdate($l['date']) ?></td><td><a href="<?= e($l['link']) ?>" target="_blank"><?= e($l['desc']) ?></a></td>
        <td class="num"><?= $l['debit'] ? money($l['debit'], false) : '' ?></td><td class="num"><?= $l['credit'] ? money($l['credit'], false) : '' ?></td>
        <td class="num <?= $run > 0 ? 'pos' : ($run < 0 ? 'neg' : '') ?>"><?= money($run, false) ?></td></tr>
    <?php endforeach; ?>
    <tfoot><tr><td colspan="2">Totals</td><td class="num"><?= money($td, false) ?></td><td class="num"><?= money($tc, false) ?></td><td class="num"><?= money($run) ?></td></tr></tfoot>
  </table></div><?php endif; ?>
</div>
<?php page_end();
