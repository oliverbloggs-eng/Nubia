<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();

$period = valid_period(post('period') ?: get('period'));
$dueDay = max(1, min(28, (int)(settings()['rent_due_day'] ?? 5)));
$dueDate = $period . '-' . str_pad((string)$dueDay, 2, '0', STR_PAD_LEFT);

/** Units that should be billed for a period: someone assigned, money due monthly. */
function billable_units(string $period): array
{
    return all('
        SELECT u.*, p.name property, c.name client, c.phone,
               (SELECT id FROM invoices i WHERE i.unit_id=u.id AND i.period=?) AS invoice_id
        FROM units u JOIN properties p ON p.id=u.property_id JOIN clients c ON c.id=u.client_id
        WHERE u.status IN ("Occupied","Sold") AND (u.monthly_amount + u.service_charge) > 0
          AND (u.start_date IS NULL OR u.start_date <= LAST_DAY(?))
        ORDER BY p.name, LENGTH(u.unit_no), u.unit_no', [$period, $period . '-01']);
}

if (is_post()) {
    $action = post('action');
    if ($action === 'run') {
        $made = 0;
        db()->beginTransaction();
        try {
            foreach (billable_units($period) as $u) {
                if ($u['invoice_id']) continue;
                $total = (float)$u['monthly_amount'] + (float)$u['service_charge'];
                q('INSERT IGNORE INTO invoices (invoice_no, unit_id, client_id, period, amount, service_charge, total, due_date, created_by) VALUES (?,?,?,?,?,?,?,?,?)',
                  [next_invoice_no($period), $u['id'], $u['client_id'], $period, $u['monthly_amount'], $u['service_charge'], $total, $dueDate, user()['id']]);
                $made++;
            }
            db()->commit();
        } catch (Throwable $t) {
            db()->rollBack();
            flash('Billing stopped with an error. Nothing was saved. Try again.', 'error');
            redirect('billing.php?period=' . $period);
        }
        log_action("Ran billing for $period: $made invoice(s)");
        flash($made ? "$made invoice(s) created for " . period_label($period) . '.' : 'Every unit was already billed for ' . period_label($period) . '. Nothing new to create.');
    } elseif ($action === 'adjust') {
        $inv = one('SELECT * FROM invoices WHERE id=?', [(int)post('id')]);
        if ($inv) {
            $other = num(post('other_charges'));
            q('UPDATE invoices SET other_charges=?, other_label=?, total=amount+service_charge+? WHERE id=?', [$other, nullable(post('other_label')), $other, $inv['id']]);
            log_action("Adjusted invoice {$inv['invoice_no']}: other charges $other");
            flash('Invoice ' . $inv['invoice_no'] . ' updated.');
        }
    } elseif ($action === 'delete' && is_admin()) {
        $inv = one('SELECT * FROM invoices WHERE id=?', [(int)post('id')]);
        if ($inv) {
            q('DELETE FROM invoices WHERE id=?', [$inv['id']]);
            log_action("Deleted invoice {$inv['invoice_no']}");
            flash('Invoice ' . $inv['invoice_no'] . ' deleted. Run billing again to recreate it with current amounts.');
        }
    }
    redirect('billing.php?period=' . $period);
}

$units = billable_units($period);
$pending = array_filter($units, fn($u) => !$u['invoice_id']);
$pendingTotal = array_sum(array_map(fn($u) => $u['monthly_amount'] + $u['service_charge'], $pending));
$invoices = all('SELECT i.*, u.unit_no, p.name property, c.name client, c.phone FROM invoices i JOIN units u ON u.id=i.unit_id JOIN properties p ON p.id=u.property_id JOIN clients c ON c.id=i.client_id WHERE i.period=? ORDER BY p.name, LENGTH(u.unit_no), u.unit_no', [$period]);
$adjust = get('adjust') ? one('SELECT i.*, u.unit_no, c.name client FROM invoices i JOIN units u ON u.id=i.unit_id JOIN clients c ON c.id=i.client_id WHERE i.id=?', [(int)get('adjust')]) : null;
$s = settings();

page_start('Monthly billing', 'billing');
?>
<form class="filters card" method="get">
  <div><label>Billing month</label><select name="period" onchange="this.form.submit()"><?= period_options($period) ?></select></div>
  <div class="muted small" style="padding-bottom:10px">Due date for this month: <strong><?= fdate($dueDate) ?></strong> (day <?= $dueDay ?>, set in Company settings)</div>
</form>

<div class="card">
  <h3>Billing run for <?= e(period_label($period)) ?></h3>
  <?php if (!$units): ?>
    <p class="muted">No units have a tenant or buyer assigned with a monthly amount. Assign clients on the <a href="units.php">Units</a> page.</p>
  <?php elseif (!$pending): ?>
    <p>All <?= count($units) ?> unit(s) are already billed for this month. Running it again will not create duplicates.</p>
  <?php else: ?>
    <p><strong><?= count($pending) ?></strong> unit(s) will be billed, totalling <strong><?= money($pendingTotal) ?></strong>. Already billed: <?= count($units) - count($pending) ?>.</p>
    <div class="table-wrap"><table>
      <tr><th>Unit</th><th>Client</th><th class="num">Monthly</th><th class="num">S. charge</th><th class="num">Total</th></tr>
      <?php foreach ($pending as $u): ?><tr><td><?= e($u['property']) ?> · <?= e($u['unit_no']) ?></td><td><?= e($u['client']) ?></td><td class="num"><?= money($u['monthly_amount']) ?></td><td class="num"><?= money($u['service_charge']) ?></td><td class="num"><?= money($u['monthly_amount'] + $u['service_charge']) ?></td></tr><?php endforeach; ?>
    </table></div>
    <form method="post" class="form-actions" data-confirm="Create <?= count($pending) ?> invoice(s) for <?= e(period_label($period)) ?>, totalling <?= e(money($pendingTotal)) ?>?">
      <?= csrf_field() ?><input type="hidden" name="action" value="run"><input type="hidden" name="period" value="<?= e($period) ?>">
      <button class="btn gold"><?= icon('invoice') ?>Create <?= count($pending) ?> invoice(s)</button>
    </form>
  <?php endif; ?>
</div>

<?php if ($adjust): ?>
<div class="card" id="adjust">
  <h3>Extra charges on <?= e($adjust['invoice_no']) ?> · <?= e($adjust['client']) ?>, unit <?= e($adjust['unit_no']) ?></h3>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="adjust"><input type="hidden" name="id" value="<?= $adjust['id'] ?>"><input type="hidden" name="period" value="<?= e($period) ?>">
    <div class="form-grid">
      <div><label>Amount (KES)</label><input type="number" step="0.01" name="other_charges" value="<?= e($adjust['other_charges']) ?>"></div>
      <div><label>What it is for</label><input name="other_label" value="<?= e($adjust['other_label']) ?>" placeholder="e.g. Water bill, late payment fee"></div>
    </div>
    <p class="help">Use a negative amount for a discount or credit.</p>
    <div class="form-actions"><a class="btn ghost" href="billing.php?period=<?= e($period) ?>">Cancel</a><button class="btn gold">Save</button></div>
  </form>
</div>
<?php endif; ?>

<div class="card flush">
  <div style="padding:20px 22px 6px"><h3>Invoices for <?= e(period_label($period)) ?></h3></div>
  <?php if (!$invoices): ?><div class="empty">No invoices for this month yet.</div><?php else: ?>
  <div class="table-wrap"><table>
    <tr><th>Invoice</th><th>Client</th><th class="num">Total</th><th class="num">Account balance</th><th></th></tr>
    <?php $sum = 0; foreach ($invoices as $i): $sum += $i['total']; $bal = balance((int)$i['unit_id'], (int)$i['client_id']);
      $msg = "Hello {$i['client']}, your " . period_label($period) . " invoice {$i['invoice_no']} for {$i['property']} unit {$i['unit_no']} is " . money($i['total']) . ', due ' . fdate($i['due_date']) . '.'
           . ($bal > $i['total'] ? ' Total balance on your account: ' . money($bal) . '.' : '') . ' ' . ($s['payment_instructions'] ?? '') . ' Thank you, ' . ($s['company_name'] ?? 'Nubia KC Estates') . '.'; ?>
      <tr>
        <td><strong><?= e($i['invoice_no']) ?></strong><div class="small muted"><?= e($i['property']) ?> · <?= e($i['unit_no']) ?> · due <?= fdate($i['due_date']) ?></div></td>
        <td><?= e($i['client']) ?><div class="small muted"><?= e($i['phone']) ?></div></td>
        <td class="num"><?= money($i['total']) ?><?= (float)$i['other_charges'] ? '<div class="small muted">incl. ' . e($i['other_label'] ?: 'other') . ' ' . money($i['other_charges']) . '</div>' : '' ?></td>
        <td class="num <?= $bal > 0 ? 'pos' : ($bal < 0 ? 'neg' : '') ?>"><?= money($bal) ?></td>
        <td><div class="row-actions">
          <a class="btn sm ghost" href="invoice.php?id=<?= $i['id'] ?>" target="_blank"><?= icon('print') ?>Invoice</a>
          <?php if ($i['phone']): ?><a class="btn sm ghost" href="<?= e(wa_link($i['phone'], $msg)) ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?>Send</a><?php endif; ?>
          <a class="btn sm ghost" href="billing.php?period=<?= e($period) ?>&adjust=<?= $i['id'] ?>#adjust">Extra charge</a>
          <?php if (is_admin()): ?><form method="post" class="inline" data-confirm="Delete invoice <?= e($i['invoice_no']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $i['id'] ?>"><input type="hidden" name="period" value="<?= e($period) ?>"><button class="btn sm danger">Delete</button></form><?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; ?>
    <tfoot><tr><td colspan="2">Total billed</td><td class="num"><?= money($sum) ?></td><td colspan="2"></td></tr></tfoot>
  </table></div><?php endif; ?>
</div>
<?php page_end();
