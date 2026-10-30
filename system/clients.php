<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();

$types = ['Tenant', 'Owner', 'Buyer'];

if (is_post()) {
    $action = post('action');
    $id = (int)post('id');
    if ($action === 'save') {
        $data = [post('name'), in_array(post('type'), $types, true) ? post('type') : 'Tenant', post('phone'), nullable(post('email')),
                 nullable(post('id_no')), nullable(strtoupper(post('kra_pin'))), nullable(post('based_in')), nullable(post('bank_details')),
                 post('status') === 'Dormant' ? 'Dormant' : 'Active', post('notes')];
        if ($data[0] === '') {
            flash('Name is required.', 'error');
            redirect('clients.php');
        }
        if ($id) {
            q('UPDATE clients SET name=?, type=?, phone=?, email=?, id_no=?, kra_pin=?, based_in=?, bank_details=?, status=?, notes=? WHERE id=?', [...$data, $id]);
            flash('Client updated.');
        } else {
            q('INSERT INTO clients (name, type, phone, email, id_no, kra_pin, based_in, bank_details, status, notes) VALUES (?,?,?,?,?,?,?,?,?,?)', $data);
            $id = (int)db()->lastInsertId();
            flash('Client added.');
        }
        log_action("Saved client {$data[0]}");
        redirect('clients.php?view=' . $id);
    }
    if ($action === 'delete' && is_admin()) {
        $history = (int)val('SELECT (SELECT COUNT(*) FROM invoices WHERE client_id=?) + (SELECT COUNT(*) FROM payments WHERE client_id=?) + (SELECT COUNT(*) FROM units WHERE client_id=?) + (SELECT COUNT(*) FROM properties WHERE owner_id=?)', [$id, $id, $id, $id]);
        if ($history) {
            flash('This client has units, properties or billing history, so they cannot be deleted. Mark them Dormant instead.', 'error');
        } else {
            q('DELETE FROM clients WHERE id=?', [$id]);
            log_action("Deleted client #$id");
            flash('Client deleted.');
        }
    }
    redirect('clients.php');
}

page_start('Clients', 'clients');

// ---------- single client view ----------
if ($view = (int)get('view')) {
    $c = one('SELECT * FROM clients WHERE id=?', [$view]);
    if (!$c) { echo '<div class="card empty">Client not found.</div>'; page_end(); exit; }
    $units = all('SELECT u.*, p.name property FROM units u JOIN properties p ON p.id=u.property_id WHERE u.client_id=?', [$view]);
    $owned = all('SELECT p.*, (SELECT COUNT(*) FROM units WHERE property_id=p.id) units FROM properties p WHERE owner_id=?', [$view]);
    $accts = array_filter(accounts(), fn($a) => (int)$a['client_id'] === $view);
    $pays = all('SELECT y.*, u.unit_no, p.name property FROM payments y JOIN units u ON u.id=y.unit_id JOIN properties p ON p.id=u.property_id WHERE y.client_id=? ORDER BY paid_on DESC LIMIT 20', [$view]);

    section_head($c['name'], badge($c['type']) . ' ' . badge($c['status']),
        '<a class="btn ghost" href="clients.php">All clients</a><a class="btn gold" href="clients.php?edit=' . $c['id'] . '#new">Edit</a>');
    ?>
    <div class="grid grid-2">
      <div class="card"><h3>Details</h3>
        <dl class="kv">
          <dt>Phone</dt><dd><?= e($c['phone']) ?: '—' ?><?php if ($c['phone']): ?> · <a href="<?= e(wa_link($c['phone'], 'Hello ' . $c['name'] . ', ')) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?></dd>
          <dt>Email</dt><dd><?= e($c['email']) ?: '—' ?></dd>
          <dt>ID number</dt><dd><?= e($c['id_no']) ?: '—' ?></dd>
          <dt>KRA PIN</dt><dd><?= e($c['kra_pin']) ?: '—' ?></dd>
          <?php if ($c['type'] === 'Owner'): ?><dt>Based in</dt><dd><?= e($c['based_in']) ?: '—' ?></dd><dt>Payout details</dt><dd><?= e($c['bank_details']) ?: '—' ?></dd><?php endif; ?>
          <dt>Notes</dt><dd><?= nl2br(e($c['notes'])) ?: '—' ?></dd>
        </dl>
      </div>
      <div class="card"><h3><?= $c['type'] === 'Owner' ? 'Properties owned' : 'Units held' ?></h3>
        <?php if ($c['type'] === 'Owner'): ?>
          <?php if (!$owned): ?><p class="muted">No properties linked. Set the owner on the <a href="properties.php">Properties</a> page.</p><?php endif; ?>
          <?php foreach ($owned as $p): ?><p><strong><?= e($p['name']) ?></strong> · <?= e($p['location']) ?> · <?= (int)$p['units'] ?> units · <a href="owner_statement.php?property=<?= $p['id'] ?>">Owner statement</a></p><?php endforeach; ?>
        <?php else: ?>
          <?php if (!$units): ?><p class="muted">Not currently assigned to a unit.</p><?php endif; ?>
          <?php foreach ($units as $u): ?><p><strong><?= e($u['property']) ?> · <?= e($u['unit_no']) ?></strong> · <?= money($u['monthly_amount'] + $u['service_charge']) ?>/month · since <?= fdate($u['start_date']) ?></p><?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($accts): ?>
    <div class="card flush"><div style="padding:20px 22px 6px"><h3>Accounts</h3></div><div class="table-wrap"><table>
      <tr><th>Unit</th><th class="num">Invoiced</th><th class="num">Paid</th><th class="num">Balance</th><th></th></tr>
      <?php foreach ($accts as $a): $b = $a['invoiced'] - $a['paid']; ?>
        <tr><td><?= e($a['property']) ?> · <?= e($a['unit_no']) ?><?= $a['is_current'] ? '' : ' <span class="small muted">(moved out)</span>' ?></td><td class="num"><?= money($a['invoiced']) ?></td><td class="num"><?= money($a['paid']) ?></td><td class="num <?= $b > 0 ? 'pos' : ($b < 0 ? 'neg' : '') ?>"><?= money($b) ?></td>
        <td><div class="row-actions"><a class="btn sm ghost" href="ledger.php?unit=<?= $a['unit_id'] ?>&client=<?= $a['client_id'] ?>">Ledger</a><a class="btn sm gold" href="payments.php?account=<?= $a['unit_id'] ?>-<?= $a['client_id'] ?>#new">Record payment</a></div></td></tr>
      <?php endforeach; ?>
    </table></div></div>
    <?php endif; ?>
    <?php if ($pays): ?>
    <div class="card flush"><div style="padding:20px 22px 6px"><h3>Recent payments</h3></div><div class="table-wrap"><table>
      <tr><th>Date</th><th>Unit</th><th>Method</th><th>Reference</th><th class="num">Amount</th></tr>
      <?php foreach ($pays as $p): ?><tr><td><?= fdate($p['paid_on']) ?></td><td><?= e($p['property']) ?> · <?= e($p['unit_no']) ?></td><td><?= e($p['method']) ?></td><td><?= e($p['reference']) ?></td><td class="num"><?= money($p['amount']) ?></td></tr><?php endforeach; ?>
    </table></div></div>
    <?php endif;
    page_end(); exit;
}

// ---------- list ----------
$typeFilter = in_array(get('type'), $types, true) ? get('type') : '';
$statusFilter = get('status') === 'Dormant' ? 'Dormant' : 'Active';
$search = get('q');
$edit = get('edit') ? one('SELECT * FROM clients WHERE id=?', [(int)get('edit')]) : null;

$where = ['c.status = ?']; $params = [$statusFilter];
if ($typeFilter) { $where[] = 'c.type = ?'; $params[] = $typeFilter; }
if ($search !== '') { $where[] = '(c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ? OR c.id_no LIKE ?)'; array_push($params, "%$search%", "%$search%", "%$search%", "%$search%"); }
$rows = all('SELECT c.*, (SELECT COUNT(*) FROM units u WHERE u.client_id=c.id) units_held, (SELECT COUNT(*) FROM properties p WHERE p.owner_id=c.id) props_owned FROM clients c WHERE ' . implode(' AND ', $where) . ' ORDER BY c.name', $params);

section_head('Client register', 'Tenants, buyers and property owners');
?>
<details class="panel" id="new" <?= $edit ? 'open' : '' ?>>
  <summary><?= icon('plus') ?><?= $edit ? 'Edit ' . e($edit['name']) : 'Add a client' ?></summary>
  <div class="body">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="form-grid">
        <div><label>Full name</label><input name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
        <div><label>Type</label><select name="type"><?= options($types, $edit['type'] ?? ($typeFilter ?: 'Tenant')) ?></select></div>
        <div><label>Phone</label><input name="phone" value="<?= e($edit['phone'] ?? '') ?>" placeholder="07XX XXX XXX"></div>
        <div><label>Email <span class="opt">(optional)</span></label><input type="email" name="email" value="<?= e($edit['email'] ?? '') ?>"></div>
        <div><label>ID / passport number <span class="opt">(optional)</span></label><input name="id_no" value="<?= e($edit['id_no'] ?? '') ?>"></div>
        <div><label>KRA PIN <span class="opt">(optional)</span></label><input name="kra_pin" value="<?= e($edit['kra_pin'] ?? '') ?>"></div>
        <div><label>Based in <span class="opt">(owners, e.g. UK for diaspora)</span></label><input name="based_in" value="<?= e($edit['based_in'] ?? '') ?>"></div>
        <div><label>Payout details <span class="opt">(owners: bank or M-Pesa)</span></label><input name="bank_details" value="<?= e($edit['bank_details'] ?? '') ?>"></div>
        <div><label>Status</label><select name="status"><?= options(['Active', 'Dormant'], $edit['status'] ?? 'Active') ?></select></div>
        <div class="full"><label>Notes <span class="opt">(optional)</span></label><textarea name="notes"><?= e($edit['notes'] ?? '') ?></textarea></div>
      </div>
      <div class="form-actions"><?php if ($edit): ?><a class="btn ghost" href="clients.php">Cancel</a><?php endif; ?><button class="btn gold"><?= $edit ? 'Save changes' : 'Add client' ?></button></div>
    </form>
  </div>
</details>

<form class="filters card" method="get">
  <div style="flex:1"><label>Search</label><input name="q" value="<?= e($search) ?>" placeholder="Name, phone, email or ID"></div>
  <div><label>Type</label><select name="type"><option value="">All types</option><?= options($types, $typeFilter) ?></select></div>
  <div><label>Status</label><select name="status"><?= options(['Active', 'Dormant'], $statusFilter) ?></select></div>
  <div><button class="btn ghost">Filter</button></div>
</form>

<div class="card flush">
  <?php if (!$rows): ?><div class="empty">No clients match.</div><?php else: ?>
  <div class="table-wrap"><table>
    <tr><th>Name</th><th>Type</th><th>Contact</th><th>KRA PIN</th><th>Holding</th><th></th></tr>
    <?php foreach ($rows as $c): ?>
      <tr>
        <td><a href="clients.php?view=<?= $c['id'] ?>"><strong><?= e($c['name']) ?></strong></a><?= $c['id_no'] ? '<div class="small muted">ID ' . e($c['id_no']) . '</div>' : '' ?></td>
        <td><?= badge($c['type']) ?></td>
        <td><?= e($c['phone']) ?><div class="small muted"><?= e($c['email']) ?></div></td>
        <td><?= e($c['kra_pin']) ?: '<span class="muted">—</span>' ?></td>
        <td><?= $c['type'] === 'Owner' ? (int)$c['props_owned'] . ' propert' . ($c['props_owned'] == 1 ? 'y' : 'ies') : (int)$c['units_held'] . ' unit(s)' ?></td>
        <td><div class="row-actions">
          <a class="btn sm ghost" href="clients.php?view=<?= $c['id'] ?>">Open</a>
          <a class="btn sm ghost" href="clients.php?edit=<?= $c['id'] ?>#new">Edit</a>
          <?php if (is_admin()): ?><form method="post" class="inline" data-confirm="Delete <?= e($c['name']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="btn sm danger">Delete</button></form><?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; ?>
  </table></div><?php endif; ?>
</div>
<?php page_end();
