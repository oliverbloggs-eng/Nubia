<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();

$methods = ['M-Pesa', 'Bank', 'Cash', 'Cheque'];

if (is_post()) {
    $action = post('action');
    if ($action === 'save') {
        [$unitId, $clientId] = array_map('intval', explode('-', post('account') . '-0'));
        $amount = num(post('amount'));
        $ref = nullable(strtoupper(preg_replace('/\s+/', '', post('reference'))));
        $paidOn = post('paid_on') ?: date('Y-m-d');
        if (!$unitId || !$clientId || $amount <= 0) {
            flash('Choose the account and enter an amount above zero.', 'error');
        } elseif ($paidOn > date('Y-m-d')) {
            flash('The payment date cannot be in the future.', 'error');
        } elseif (!one('SELECT 1 FROM units WHERE id=?', [$unitId])) {
            flash('That unit no longer exists.', 'error');
        } else {
            try {
                q('INSERT INTO payments (unit_id, client_id, amount, paid_on, method, reference, notes, recorded_by) VALUES (?,?,?,?,?,?,?,?)',
                  [$unitId, $clientId, $amount, $paidOn, in_array(post('method'), $methods, true) ? post('method') : 'M-Pesa', $ref, nullable(post('notes')), user()['id']]);
                $pid = (int)db()->lastInsertId();
                log_action("Recorded payment of $amount for unit #$unitId ($ref)");
                $bal = balance($unitId, $clientId);
                flash('Payment of ' . money($amount) . ' recorded. ' . ($bal > 0 ? 'Remaining balance: ' . money($bal) . '.' : ($bal < 0 ? 'Account is now in credit by ' . money(-$bal) . '.' : 'Account is fully paid up.')));
                redirect('payments.php?done=' . $pid);
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') flash("Reference $ref has already been recorded. Check the payments list before entering it again.", 'error');
                else throw $e;
            }
        }
        redirect('payments.php#new');
    }
    if ($action === 'delete' && is_admin()) {
        $p = one('SELECT * FROM payments WHERE id=?', [(int)post('id')]);
        if ($p) {
            q('DELETE FROM payments WHERE id=?', [$p['id']]);
            log_action("Deleted payment #{$p['id']} of {$p['amount']} ({$p['reference']})");
            flash('Payment deleted.');
        }
    }
    redirect('payments.php');
}

// Accounts to pay into: everyone currently in a unit, plus former occupants who still owe.
$opts = [];
foreach (all('SELECT u.id unit_id, u.client_id, u.unit_no, p.name property, c.name client FROM units u JOIN properties p ON p.id=u.property_id JOIN clients c ON c.id=u.client_id ORDER BY p.name, u.unit_no') as $r) {
    $opts[$r['unit_id'] . '-' . $r['client_id']] = $r + ['balance' => balance((int)$r['unit_id'], (int)$r['client_id']), 'former' => false];
}
foreach (accounts() as $a) {
    $k = $a['unit_id'] . '-' . $a['client_id'];
    $b = $a['invoiced'] - $a['paid'];
    if (!isset($opts[$k]) && abs($b) > 0.004) $opts[$k] = $a + ['balance' => $b, 'former' => true];
}
$pre = get('account');

$month = get('month') ? valid_period(get('month')) : '';
$search = get('q');
$where = []; $params = [];
if ($month) { $where[] = 'y.paid_on BETWEEN ? AND ?'; $params[] = $month . '-01'; $params[] = date('Y-m-t', strtotime($month . '-01')); }
if ($search !== '') { $where[] = '(c.name LIKE ? OR y.reference LIKE ? OR u.unit_no LIKE ? OR p.name LIKE ?)'; array_push($params, "%$search%", "%$search%", "%$search%", "%$search%"); }
$rows = all('SELECT y.*, c.name client, u.unit_no, p.name property, us.name recorder FROM payments y JOIN clients c ON c.id=y.client_id JOIN units u ON u.id=y.unit_id JOIN properties p ON p.id=u.property_id LEFT JOIN users us ON us.id=y.recorded_by'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY y.paid_on DESC, y.id DESC LIMIT 300', $params);
$done = (int)get('done');

page_start('Payments', 'payments');
?>
<?php if ($done): ?><div class="alert alert-info">Payment saved. <a href="invoice.php?receipt=<?= $done ?>" target="_blank">Print the receipt</a>.</div><?php endif; ?>

<div class="card" id="new">
  <h3>Record a payment</h3>
  <?php if (!$opts): ?><p class="muted">No tenants or buyers are assigned to units yet. Assign one on the <a href="units.php">Units</a> page.</p><?php else: ?>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="save">
    <div class="form-grid">
      <div class="full"><label>Client and unit</label>
        <select name="account" id="accountSelect" required><option value="">— Choose —</option>
          <?php foreach ($opts as $k => $o): ?><option value="<?= e($k) ?>" data-balance="<?= e(round($o['balance'], 2)) ?>" <?= $pre === $k ? 'selected' : '' ?>><?= e($o['client']) ?> · <?= e($o['property']) ?> <?= e($o['unit_no']) ?><?= $o['former'] ? ' (moved out)' : '' ?></option><?php endforeach; ?>
        </select>
        <div class="help" id="balanceHint"></div></div>
      <div><label>Amount paid (KES)</label><input type="number" step="0.01" min="1" name="amount" id="amountInput" required></div>
      <div><label>Date paid</label><input type="date" name="paid_on" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required></div>
      <div><label>Method</label><select name="method"><?= options($methods, 'M-Pesa') ?></select></div>
      <div><label>Reference <span class="opt">(M-Pesa code or bank slip no.)</span></label><input name="reference" placeholder="e.g. SJK3H7XQ2P"></div>
      <div class="full"><label>Notes <span class="opt">(optional)</span></label><input name="notes"></div>
    </div>
    <p class="help">Payments go against the account's total balance, oldest charges first. Overpayments carry forward as credit.</p>
    <div class="form-actions"><button class="btn gold"><?= icon('cash') ?>Record payment</button></div>
  </form>
  <?php endif; ?>
</div>

<form class="filters card" method="get">
  <div style="flex:1"><label>Search</label><input name="q" value="<?= e($search) ?>" placeholder="Client, unit, property or reference"></div>
  <div><label>Month</label><select name="month"><option value="">All months</option><?= period_options($month ?: 'x', 24, 0) ?></select></div>
  <div><button class="btn ghost">Filter</button></div>
</form>

<div class="card flush">
  <?php if (!$rows): ?><div class="empty">No payments found.</div><?php else: ?>
  <div class="table-wrap"><table>
    <tr><th>Date</th><th>Client</th><th>Unit</th><th>Method</th><th>Reference</th><th class="num">Amount</th><th></th></tr>
    <?php $sum = 0; foreach ($rows as $p): $sum += $p['amount']; ?>
      <tr>
        <td class="nowrap"><?= fdate($p['paid_on']) ?></td>
        <td><?= e($p['client']) ?><div class="small muted">Recorded by <?= e($p['recorder'] ?? '—') ?></div></td>
        <td><?= e($p['property']) ?> · <?= e($p['unit_no']) ?></td>
        <td><?= e($p['method']) ?></td>
        <td><?= e($p['reference']) ?: '—' ?></td>
        <td class="num"><?= money($p['amount']) ?></td>
        <td><div class="row-actions">
          <a class="btn sm ghost" href="invoice.php?receipt=<?= $p['id'] ?>" target="_blank">Receipt</a>
          <?php if (is_admin()): ?><form method="post" class="inline" data-confirm="Delete this payment of <?= e(money($p['amount'])) ?>? Only do this to correct a mistake."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="btn sm danger">Delete</button></form><?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; ?>
    <tfoot><tr><td colspan="5">Total shown</td><td class="num"><?= money($sum) ?></td><td></td></tr></tfoot>
  </table></div><?php endif; ?>
</div>
<?php page_end();
