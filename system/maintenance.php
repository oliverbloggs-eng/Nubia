<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();

$statuses = ['Open', 'In progress', 'Done'];
$priorities = ['Urgent', 'Normal', 'Low'];

if (is_post()) {
    $action = post('action');
    $id = (int)post('id');
    if ($action === 'save') {
        $status = in_array(post('status'), $statuses, true) ? post('status') : 'Open';
        $completed = $status === 'Done' ? (post('completed_on') ?: date('Y-m-d')) : null;
        $data = [(int)post('property_id'), nullable(post('unit_id')), post('title'), post('details'),
                 in_array(post('priority'), $priorities, true) ? post('priority') : 'Normal', $status, num(post('cost')),
                 post('charge_owner') ? 1 : 0, post('reported_on') ?: date('Y-m-d'), $completed];
        if (!$data[0] || $data[2] === '') {
            flash('Choose a property and describe the job.', 'error');
        } elseif ($id) {
            q('UPDATE maintenance SET property_id=?, unit_id=?, title=?, details=?, priority=?, status=?, cost=?, charge_owner=?, reported_on=?, completed_on=? WHERE id=?', [...$data, $id]);
            flash('Job updated.');
        } else {
            q('INSERT INTO maintenance (property_id, unit_id, title, details, priority, status, cost, charge_owner, reported_on, completed_on) VALUES (?,?,?,?,?,?,?,?,?,?)', $data);
            flash('Job logged.');
        }
        log_action("Saved maintenance job: {$data[2]}");
    } elseif ($action === 'status' && in_array(post('status'), $statuses, true)) {
        q('UPDATE maintenance SET status=?, completed_on=? WHERE id=?', [post('status'), post('status') === 'Done' ? date('Y-m-d') : null, $id]);
        log_action("Maintenance #$id set to " . post('status'));
        flash('Job updated.' . (post('status') === 'Done' ? ' Add the cost with Edit so it shows on the owner statement.' : ''));
    } elseif ($action === 'delete' && is_admin()) {
        q('DELETE FROM maintenance WHERE id=?', [$id]);
        log_action("Deleted maintenance #$id");
        flash('Job deleted.');
    }
    redirect('maintenance.php' . (get('show') ? '?show=' . urlencode(get('show')) : ''));
}

$properties = all('SELECT id, name FROM properties ORDER BY name');
$units = all('SELECT u.id, u.unit_no, u.property_id, p.name property FROM units u JOIN properties p ON p.id=u.property_id ORDER BY p.name, LENGTH(u.unit_no), u.unit_no');
$edit = get('edit') ? one('SELECT * FROM maintenance WHERE id=?', [(int)get('edit')]) : null;
$show = get('show') === 'done' ? 'done' : (get('show') === 'all' ? 'all' : 'open');
$where = $show === 'open' ? 'WHERE m.status <> "Done"' : ($show === 'done' ? 'WHERE m.status = "Done"' : '');
$rows = all("SELECT m.*, p.name property, u.unit_no FROM maintenance m JOIN properties p ON p.id=m.property_id LEFT JOIN units u ON u.id=m.unit_id $where ORDER BY m.status='Done', FIELD(m.priority,'Urgent','Normal','Low'), m.reported_on DESC LIMIT 300");

page_start('Maintenance', 'maintenance');

if (!$properties) { echo '<div class="card empty">Add a property first.</div>'; page_end(); exit; }
section_head('Repairs and maintenance', 'Completed jobs with a cost are deducted on the owner statement for the month they were completed.');
?>
<details class="panel" id="new" <?= $edit ? 'open' : '' ?>>
  <summary><?= icon('plus') ?><?= $edit ? 'Edit job' : 'Log a repair' ?></summary>
  <div class="body">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="form-grid">
        <div><label>Property</label><select name="property_id" required><?php foreach ($properties as $p): ?><option value="<?= $p['id'] ?>" <?= ($edit['property_id'] ?? '') == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
        <div><label>Unit <span class="opt">(leave blank for common areas)</span></label><select name="unit_id"><option value="">— Whole building / common area —</option><?php foreach ($units as $u): ?><option value="<?= $u['id'] ?>" <?= ($edit['unit_id'] ?? '') == $u['id'] ? 'selected' : '' ?>><?= e($u['property']) ?> · <?= e($u['unit_no']) ?></option><?php endforeach; ?></select></div>
        <div class="full"><label>What needs doing</label><input name="title" required value="<?= e($edit['title'] ?? '') ?>" placeholder="e.g. Leaking kitchen tap"></div>
        <div class="full"><label>Details <span class="opt">(optional)</span></label><textarea name="details"><?= e($edit['details'] ?? '') ?></textarea></div>
        <div><label>Priority</label><select name="priority"><?= options($priorities, $edit['priority'] ?? 'Normal') ?></select></div>
        <div><label>Status</label><select name="status"><?= options($statuses, $edit['status'] ?? 'Open') ?></select></div>
        <div><label>Reported on</label><input type="date" name="reported_on" value="<?= e($edit['reported_on'] ?? date('Y-m-d')) ?>"></div>
        <div><label>Completed on <span class="opt">(when done)</span></label><input type="date" name="completed_on" value="<?= e($edit['completed_on'] ?? '') ?>"></div>
        <div><label>Cost (KES)</label><input type="number" step="0.01" min="0" name="cost" value="<?= e($edit['cost'] ?? '0') ?>"></div>
        <div style="align-self:end"><label class="check"><input type="checkbox" name="charge_owner" value="1" <?= ($edit['charge_owner'] ?? 1) ? 'checked' : '' ?>> Deduct the cost from the owner's payout</label></div>
      </div>
      <div class="form-actions"><?php if ($edit): ?><a class="btn ghost" href="maintenance.php">Cancel</a><?php endif; ?><button class="btn gold"><?= $edit ? 'Save changes' : 'Log job' ?></button></div>
    </form>
  </div>
</details>

<div class="actions" style="margin-bottom:14px">
  <a class="btn sm <?= $show === 'open' ? '' : 'ghost' ?>" href="?show=open">Open</a>
  <a class="btn sm <?= $show === 'done' ? '' : 'ghost' ?>" href="?show=done">Completed</a>
  <a class="btn sm <?= $show === 'all' ? '' : 'ghost' ?>" href="?show=all">All</a>
</div>

<div class="card flush">
  <?php if (!$rows): ?><div class="empty">No jobs here.</div><?php else: ?>
  <div class="table-wrap"><table>
    <tr><th>Job</th><th>Where</th><th>Priority</th><th>Status</th><th class="num">Cost</th><th></th></tr>
    <?php foreach ($rows as $m): ?>
      <tr>
        <td><strong><?= e($m['title']) ?></strong><div class="small muted">Reported <?= fdate($m['reported_on']) ?><?= $m['completed_on'] ? ' · done ' . fdate($m['completed_on']) : '' ?></div></td>
        <td><?= e($m['property']) ?><?= $m['unit_no'] ? ' · ' . e($m['unit_no']) : ' · common area' ?></td>
        <td><?= badge($m['priority']) ?></td>
        <td><?= badge($m['status']) ?></td>
        <td class="num"><?= (float)$m['cost'] ? money($m['cost']) : '—' ?><?= (float)$m['cost'] && !$m['charge_owner'] ? '<div class="small muted">not charged</div>' : '' ?></td>
        <td><div class="row-actions">
          <?php if ($m['status'] !== 'Done'): ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= $m['id'] ?>"><input type="hidden" name="status" value="<?= $m['status'] === 'Open' ? 'In progress' : 'Done' ?>"><button class="btn sm ghost"><?= $m['status'] === 'Open' ? 'Start' : 'Mark done' ?></button></form><?php endif; ?>
          <a class="btn sm ghost" href="maintenance.php?edit=<?= $m['id'] ?>#new">Edit</a>
          <?php if (is_admin()): ?><form method="post" class="inline" data-confirm="Delete this job?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $m['id'] ?>"><button class="btn sm danger">Delete</button></form><?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; ?>
  </table></div><?php endif; ?>
</div>
<?php page_end();
