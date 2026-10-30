<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();

$statuses = ['Available', 'Occupied', 'Sold', 'Dormant'];
$back = 'units.php' . (get('property') ? '?property=' . (int)get('property') : '');

if (is_post()) {
    $action = post('action');
    $id = (int)post('id');
    $back = post('back') ?: 'units.php';
    if (!preg_match('/^units\.php(\?[\w=&%-]*)?$/', $back)) $back = 'units.php';

    if ($action === 'save') {
        $cat = post('category') === 'Sale' ? 'Sale' : 'Rent';
        $data = [(int)post('property_id'), post('unit_no'), $cat, post('bedrooms'), num(post('monthly_amount')), num(post('service_charge')), num(post('sale_price')), num(post('deposit'))];
        if (!$data[0] || $data[1] === '') {
            flash('Choose a property and enter a unit number.', 'error');
        } else {
            try {
                if ($id) {
                    q('UPDATE units SET property_id=?, unit_no=?, category=?, bedrooms=?, monthly_amount=?, service_charge=?, sale_price=?, deposit=? WHERE id=?', [...$data, $id]);
                    flash('Unit updated.');
                } else {
                    q('INSERT INTO units (property_id, unit_no, category, bedrooms, monthly_amount, service_charge, sale_price, deposit) VALUES (?,?,?,?,?,?,?,?)', $data);
                    flash('Unit added.');
                }
                log_action(($id ? 'Updated' : 'Added') . " unit {$data[1]}");
            } catch (PDOException $e) {
                flash('That property already has a unit numbered “' . $data[1] . '”.', 'error');
            }
        }
    } elseif ($action === 'assign') {
        $unit = one('SELECT * FROM units WHERE id=?', [$id]);
        $clientId = (int)post('client_id');
        if (!$clientId && post('new_name') !== '') {
            q('INSERT INTO clients (name, type, phone, email, id_no, kra_pin) VALUES (?,?,?,?,?,?)',
              [post('new_name'), $unit['category'] === 'Sale' ? 'Buyer' : 'Tenant', post('new_phone'), nullable(post('new_email')), nullable(post('new_id_no')), nullable(post('new_kra'))]);
            $clientId = (int)db()->lastInsertId();
        }
        if ($unit && $clientId) {
            $status = $unit['category'] === 'Sale' ? 'Sold' : 'Occupied';
            q('UPDATE units SET client_id=?, status=?, start_date=? WHERE id=?', [$clientId, $status, nullable(post('start_date')) ?? date('Y-m-d'), $id]);
            q('UPDATE clients SET status="Active" WHERE id=?', [$clientId]);
            log_action("Assigned client #$clientId to unit {$unit['unit_no']}");
            flash('Client assigned. They will be included in the next monthly billing.');
        } else {
            flash('Choose an existing client or enter a new client\'s name.', 'error');
        }
    } elseif ($action === 'vacate') {
        $unit = one('SELECT * FROM units WHERE id=?', [$id]);
        if ($unit && $unit['client_id']) {
            $bal = balance((int)$unit['id'], (int)$unit['client_id']);
            q('UPDATE units SET client_id=NULL, status="Available", start_date=NULL WHERE id=?', [$id]);
            log_action("Moved out client #{$unit['client_id']} from unit {$unit['unit_no']}");
            flash('Tenant moved out. The unit is now available.' . ($bal > 0 ? ' They still owe ' . money($bal) . ', which stays on the Arrears page.' : ''));
        }
    } elseif ($action === 'status' && in_array(post('status'), $statuses, true)) {
        q('UPDATE units SET status=? WHERE id=?', [post('status'), $id]);
        log_action("Set unit #$id to " . post('status'));
        flash('Status updated.');
    } elseif ($action === 'delete' && is_admin()) {
        $history = (int)val('SELECT (SELECT COUNT(*) FROM invoices WHERE unit_id=?) + (SELECT COUNT(*) FROM payments WHERE unit_id=?)', [$id, $id]);
        if ($history) {
            flash('This unit has billing history, so it cannot be deleted. Mark it Dormant instead.', 'error');
        } else {
            q('DELETE FROM units WHERE id=?', [$id]);
            log_action("Deleted unit #$id");
            flash('Unit deleted.');
        }
    }
    redirect($back);
}

$propertyFilter = (int)get('property');
$statusFilter = in_array(get('status'), $statuses, true) ? get('status') : '';
$properties = all('SELECT id, name FROM properties ORDER BY name');
$edit = get('edit') ? one('SELECT * FROM units WHERE id=?', [(int)get('edit')]) : null;
$assign = get('assign') ? one('SELECT u.*, p.name property FROM units u JOIN properties p ON p.id=u.property_id WHERE u.id=?', [(int)get('assign')]) : null;

$where = []; $params = [];
if ($propertyFilter) { $where[] = 'u.property_id=?'; $params[] = $propertyFilter; }
if ($statusFilter) { $where[] = 'u.status=?'; $params[] = $statusFilter; }
$rows = all('SELECT u.*, p.name property, c.name client, c.phone FROM units u JOIN properties p ON p.id=u.property_id LEFT JOIN clients c ON c.id=u.client_id'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY p.name, LENGTH(u.unit_no), u.unit_no', $params);

page_start('Units', 'units');

if (!$properties): ?>
  <div class="card empty">Add a property first, then its units. <a href="properties.php#new">Register a property</a></div>
<?php page_end(); exit; endif;

section_head('Unit inventory', count($rows) . ' unit(s)' . ($statusFilter ? ' · ' . e($statusFilter) : ''));
?>
<?php if ($assign): ?>
<div class="card" id="assign">
  <h3>Assign a <?= $assign['category'] === 'Sale' ? 'buyer' : 'tenant' ?> to <?= e($assign['property']) ?> · <?= e($assign['unit_no']) ?></h3>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="assign"><input type="hidden" name="id" value="<?= $assign['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
    <div class="form-grid">
      <div class="full"><label>Existing client</label>
        <select name="client_id"><option value="">— Register a new client below —</option>
          <?php foreach (all('SELECT id, name, phone, type FROM clients WHERE type IN ("Tenant","Buyer") ORDER BY name') as $c): ?>
            <option value="<?= $c['id'] ?>"><?= e($c['name']) ?> · <?= e($c['phone']) ?> (<?= e($c['type']) ?>)</option>
          <?php endforeach; ?>
        </select></div>
      <div><label>New client name</label><input name="new_name"></div>
      <div><label>Phone</label><input name="new_phone" placeholder="07XX XXX XXX"></div>
      <div><label>Email <span class="opt">(optional)</span></label><input type="email" name="new_email"></div>
      <div><label>ID number <span class="opt">(optional)</span></label><input name="new_id_no"></div>
      <div><label>KRA PIN <span class="opt">(optional)</span></label><input name="new_kra"></div>
      <div><label><?= $assign['category'] === 'Sale' ? 'Sale date' : 'Lease start' ?></label><input type="date" name="start_date" value="<?= date('Y-m-d') ?>"></div>
    </div>
    <div class="form-actions"><a class="btn ghost" href="<?= e($back) ?>">Cancel</a><button class="btn gold">Assign</button></div>
  </form>
</div>
<?php endif; ?>

<details class="panel" id="new" <?= $edit ? 'open' : '' ?>>
  <summary><?= icon('plus') ?><?= $edit ? 'Edit unit ' . e($edit['unit_no']) : 'Add a unit' ?></summary>
  <div class="body">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
      <div class="form-grid">
        <div><label>Property</label><select name="property_id" required><?php foreach ($properties as $p): ?><option value="<?= $p['id'] ?>" <?= ($edit['property_id'] ?? $propertyFilter) == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
        <div><label>Unit number</label><input name="unit_no" required value="<?= e($edit['unit_no'] ?? '') ?>" placeholder="e.g. A1"></div>
        <div><label>Category</label><select name="category" id="categorySelect"><?= options(['Rent', 'Sale'], $edit['category'] ?? 'Rent') ?></select></div>
        <div><label>Bedrooms <span class="opt">(optional)</span></label><input name="bedrooms" value="<?= e($edit['bedrooms'] ?? '') ?>" placeholder="e.g. 2 bedroom, Bedsitter, Shop"></div>
        <div><label id="monthlyLabel">Monthly rent (KES) </label><input name="monthly_amount" type="number" step="0.01" min="0" value="<?= e($edit['monthly_amount'] ?? '') ?>" required></div>
        <div><label>Service charge (KES / month)</label><input name="service_charge" type="number" step="0.01" min="0" value="<?= e($edit['service_charge'] ?? '0') ?>"></div>
        <div data-sale-only><label>Sale price (KES)</label><input name="sale_price" type="number" step="0.01" min="0" value="<?= e($edit['sale_price'] ?? '0') ?>"></div>
        <div><label>Deposit held (KES)</label><input name="deposit" type="number" step="0.01" min="0" value="<?= e($edit['deposit'] ?? '0') ?>"></div>
      </div>
      <p class="help">For a unit sold on a payment plan, choose “Sale” and enter the monthly instalment. It is billed each month like rent.</p>
      <div class="form-actions"><?php if ($edit): ?><a class="btn ghost" href="<?= e($back) ?>">Cancel</a><?php endif; ?><button class="btn gold"><?= $edit ? 'Save changes' : 'Add unit' ?></button></div>
    </form>
  </div>
</details>

<form class="filters card" method="get">
  <div><label>Property</label><select name="property"><option value="">All properties</option><?php foreach ($properties as $p): ?><option value="<?= $p['id'] ?>" <?= $propertyFilter == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
  <div><label>Status</label><select name="status"><option value="">Any status</option><?= options($statuses, $statusFilter) ?></select></div>
  <div><button class="btn ghost">Filter</button></div>
</form>

<div class="card flush">
  <?php if (!$rows): ?><div class="empty">No units match.</div><?php else: ?>
  <div class="table-wrap"><table>
    <tr><th>Unit</th><th>Category</th><th class="num">Monthly</th><th class="num">S. charge</th><th>Status</th><th>Client</th><th class="num">Balance</th><th></th></tr>
    <?php foreach ($rows as $u): $bal = $u['client_id'] ? balance((int)$u['id'], (int)$u['client_id']) : 0; ?>
      <tr>
        <td><strong><?= e($u['unit_no']) ?></strong><div class="small muted"><?= e($u['property']) ?><?= $u['bedrooms'] ? ' · ' . e($u['bedrooms']) : '' ?></div></td>
        <td><?= badge($u['category']) ?></td>
        <td class="num"><?= money($u['monthly_amount']) ?></td>
        <td class="num"><?= money($u['service_charge']) ?></td>
        <td><?= badge($u['status']) ?></td>
        <td><?= $u['client'] ? '<a href="clients.php?view=' . $u['client_id'] . '">' . e($u['client']) . '</a><div class="small muted">Since ' . fdate($u['start_date']) . '</div>' : '<span class="muted">—</span>' ?></td>
        <td class="num <?= $bal > 0 ? 'pos' : ($bal < 0 ? 'neg' : '') ?>"><?= $u['client_id'] ? money($bal) : '' ?></td>
        <td><div class="row-actions">
          <?php if ($u['client_id']): ?>
            <a class="btn sm ghost" href="ledger.php?unit=<?= $u['id'] ?>&client=<?= $u['client_id'] ?>">Ledger</a>
            <form method="post" class="inline" data-confirm="Move <?= e($u['client']) ?> out of <?= e($u['unit_no']) ?>? Their history and any balance are kept."><?= csrf_field() ?><input type="hidden" name="action" value="vacate"><input type="hidden" name="id" value="<?= $u['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>"><button class="btn sm ghost">Move out</button></form>
          <?php else: ?>
            <a class="btn sm gold" href="<?= e($back) ?><?= str_contains($back, '?') ? '&' : '?' ?>assign=<?= $u['id'] ?>#assign">Assign</a>
            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= $u['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>"><input type="hidden" name="status" value="<?= $u['status'] === 'Dormant' ? 'Available' : 'Dormant' ?>"><button class="btn sm ghost"><?= $u['status'] === 'Dormant' ? 'Reactivate' : 'Mark dormant' ?></button></form>
          <?php endif; ?>
          <a class="btn sm ghost" href="<?= e($back) ?><?= str_contains($back, '?') ? '&' : '?' ?>edit=<?= $u['id'] ?>#new">Edit</a>
          <?php if (is_admin() && !$u['client_id']): ?><form method="post" class="inline" data-confirm="Delete unit <?= e($u['unit_no']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $u['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>"><button class="btn sm danger">Delete</button></form><?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; ?>
  </table></div><?php endif; ?>
</div>
<?php page_end();
