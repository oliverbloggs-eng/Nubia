<?php
// Tenant statement: one client in one unit over a date range (all time by default).
// Opening balance, each invoice with its lines, each payment, running and closing balance.
// Dates follow the account ledger: an invoice is dated on its due date.
require __DIR__ . '/inc/bootstrap.php';
require_login();

$account = (string)get('account');
[$unitId, $clientId] = array_map('intval', explode('-', $account . '-0'));
$validDate = fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) ? $d : '';
$from = $validDate(get('from'));
$to = $validDate(get('to'));
if ($from && $to && $from > $to) [$from, $to] = [$to, $from];

$accounts = accounts();
$info = ($unitId && $clientId) ? one('SELECT u.unit_no, p.name property, c.name client, c.phone, c.email FROM units u JOIN properties p ON p.id=u.property_id, clients c WHERE u.id=? AND c.id=?', [$unitId, $clientId]) : null;

$opening = 0.0; $lines = [];
if ($info) {
    if ($from) {
        $opening = round((float)val('SELECT COALESCE(SUM(total),0) FROM invoices WHERE unit_id=? AND client_id=? AND due_date < ?', [$unitId, $clientId, $from])
                 - (float)val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE unit_id=? AND client_id=? AND paid_on < ?', [$unitId, $clientId, $from]), 2);
    }
    $range = ''; $params = [$unitId, $clientId];
    if ($from) { $range .= ' AND %s >= ?'; $params[] = $from; }
    if ($to) { $range .= ' AND %s <= ?'; $params[] = $to; }

    foreach (all('SELECT * FROM invoices WHERE unit_id=? AND client_id=?' . str_replace('%s', 'due_date', $range), $params) as $i) {
        $parts = [];
        if ((float)$i['amount']) $parts[] = ['Rent / instalment', (float)$i['amount']];
        if ((float)$i['service_charge']) $parts[] = ['Service charge', (float)$i['service_charge']];
        foreach (invoice_readings((int)$i['id']) as $r) $parts[] = [reading_line($r), (float)$r['amount']];
        if ((float)$i['other_charges']) $parts[] = [$i['other_label'] ?: 'Other charges', (float)$i['other_charges']];
        $lines[] = ['date' => $i['due_date'], 'sort' => 0, 'id' => (int)$i['id'], 'desc' => 'Invoice ' . $i['invoice_no'] . ' · ' . period_label($i['period']), 'parts' => $parts, 'debit' => (float)$i['total'], 'credit' => 0.0];
    }
    foreach (all('SELECT * FROM payments WHERE unit_id=? AND client_id=?' . str_replace('%s', 'paid_on', $range), $params) as $p) {
        $lines[] = ['date' => $p['paid_on'], 'sort' => 1, 'id' => (int)$p['id'], 'desc' => 'Payment received · ' . $p['method'] . ($p['reference'] ? ' ' . $p['reference'] : '') . ' · RCT-' . str_pad((string)$p['id'], 6, '0', STR_PAD_LEFT), 'parts' => [], 'debit' => 0.0, 'credit' => (float)$p['amount']];
    }
    usort($lines, fn($a, $b) => [$a['date'], $a['sort'], $a['id']] <=> [$b['date'], $b['sort'], $b['id']]);
}

$s = settings();
page_start('Tenant statement', 'tenant_statement');
?>
<form class="filters card no-print" method="get">
  <div style="min-width:280px"><label>Client and unit</label><select name="account" required><option value="">— Choose —</option>
    <?php foreach ($accounts as $a): $k = $a['unit_id'] . '-' . $a['client_id']; ?><option value="<?= e($k) ?>" <?= $k === "$unitId-$clientId" ? 'selected' : '' ?>><?= e($a['client']) ?> · <?= e($a['property']) ?> <?= e($a['unit_no']) ?><?= $a['is_current'] ? '' : ' (moved out)' ?></option><?php endforeach; ?>
  </select></div>
  <div><label>From <span class="opt">(optional)</span></label><input type="date" name="from" value="<?= e($from) ?>"></div>
  <div><label>To <span class="opt">(optional)</span></label><input type="date" name="to" value="<?= e($to) ?>"></div>
  <div class="actions"><button class="btn">Show</button>
    <?php if ($info): ?><button type="button" class="btn ghost" onclick="window.print()"><?= icon('print') ?>Print / PDF</button><?php endif; ?></div>
</form>

<?php if (!$info): ?>
  <div class="card empty">Choose a client and unit, then select Show. Leave the dates blank for the whole account history.</div>
<?php else: ?>
<div class="doc">
  <div class="doc-head">
    <div class="co"><strong><?= e($s['company_name']) ?></strong><div><?= e($s['address']) ?></div><div>Tel <?= e($s['phone']) ?> · <?= e($s['email']) ?></div></div>
    <img src="assets/mark.svg" alt="">
  </div>
  <div class="doc-title"><h2>Statement of account</h2><p><?= $from ? fdate($from) : 'Start of account' ?> to <?= fdate($to ?: date('Y-m-d')) ?></p></div>
  <dl class="kv" style="margin-bottom:20px"><dt>Client</dt><dd><strong><?= e($info['client']) ?></strong></dd><dt>Phone</dt><dd><?= e($info['phone']) ?: '—' ?></dd><dt>Property</dt><dd><?= e($info['property']) ?>, unit <?= e($info['unit_no']) ?></dd></dl>
  <div class="table-wrap"><table class="stmt">
    <tr><th>Date</th><th>Details</th><th class="num">Charged</th><th class="num">Paid</th><th class="num">Balance</th></tr>
    <tr><td></td><td><strong>Opening balance</strong></td><td></td><td></td><td class="num"><strong><?= money($opening, false) ?></strong></td></tr>
    <?php $run = $opening; $td = 0; $tc = 0; foreach ($lines as $l): $run = round($run + $l['debit'] - $l['credit'], 2); $td += $l['debit']; $tc += $l['credit']; ?>
      <tr><td class="nowrap"><?= fdate($l['date']) ?></td>
        <td><?= e($l['desc']) ?>
          <?php if ($l['parts']): ?><div class="stmt-parts"><?php foreach ($l['parts'] as [$what, $amt]): ?><div><span><?= e($what) ?></span><span><?= money($amt, false) ?></span></div><?php endforeach; ?></div><?php endif; ?></td>
        <td class="num"><?= $l['debit'] ? money($l['debit'], false) : '' ?></td><td class="num"><?= $l['credit'] ? money($l['credit'], false) : '' ?></td>
        <td class="num"><?= money($run, false) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$lines): ?><tr><td></td><td class="muted">No invoices or payments in this period.</td><td></td><td></td><td></td></tr><?php endif; ?>
    <tfoot><tr><td colspan="2">Closing balance</td><td class="num"><?= money($td, false) ?></td><td class="num"><?= money($tc, false) ?></td><td class="num"><?= money($run) ?></td></tr></tfoot>
  </table></div>
  <p style="margin-top:18px"><strong><?= $run > 0 ? 'Balance due: ' . money($run) : ($run < 0 ? 'In credit by ' . money(-$run) : 'Fully paid up.') ?></strong></p>
  <?php if (!empty($s['payment_instructions']) && $run > 0): ?><p class="muted"><?= e($s['payment_instructions']) ?></p><?php endif; ?>
  <p class="muted small" style="margin-top:24px">Printed <?= fdate(date('Y-m-d')) ?> · <?= e($s['company_name']) ?></p>
</div>
<?php endif;
page_end();
