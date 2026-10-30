<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();

$types = ['Residential', 'Commercial', 'Mixed use', 'Land'];

if (is_post()) {
    $action = post('action');
    if ($action === 'save') {
        $id = (int)post('id');
        $data = [
            post('name'), post('location'), in_array(post('type'), $types, true) ? post('type') : 'Residential',
            nullable(post('owner_id')), num(post('fee_pct', settings()['default_fee_pct'] ?? 8)), post('notes'),
        ];
        if ($data[0] === '' || $data[1] === '') {
            flash('Name and location are required.', 'error');
        } elseif ($id) {
            q('UPDATE properties SET name=?, location=?, type=?, owner_id=?, fee_pct=?, notes=? WHERE id=?', [...$data, $id]);
            log_action("Updated property {$data[0]}");
            flash('Property updated.');
        } else {
            q('INSERT INTO properties (name, location, type, owner_id, fee_pct, notes) VALUES (?,?,?,?,?,?)', $data);
            log_action("Added property {$data[0]}");
            flash('Property added. Now add its units.');
            redirect('units.php?property=' . db()->lastInsertId() . '#new');
        }
    } elseif ($action === 'delete' && is_admin()) {
        $id = (int)post('id');
        if ((int)val('SELECT COUNT(*) FROM units WHERE property_id=?', [$id]) > 0) {
            flash('Remove this property\'s units first. Deleting a property with units would also delete their billing history.', 'error');
        } else {
            $name = val('SELECT name FROM properties WHERE id=?', [$id]);
            q('DELETE FROM properties WHERE id=?', [$id]);
            log_action("Deleted property $name");
            flash('Property deleted.');
        }
    }
    redirect('properties.php');
}

$edit = get('edit') ? one('SELECT * FROM properties WHERE id=?', [(int)get('edit')]) : null;
$owners = all('SELECT id, name FROM clients WHERE type="Owner" ORDER BY name');
$rows = all('
    SELECT p.*, c.name owner, c.phone owner_phone,
           COUNT(u.id) units, SUM(u.status="Occupied") occupied, SUM(u.status="Available") vacant,
           COALESCE(SUM(CASE WHEN u.status="Occupied" THEN u.monthly_amount ELSE 0 END),0) rent_roll
    FROM properties p LEFT JOIN clients c ON c.id=p.owner_id LEFT JOIN units u ON u.property_id=p.id
    GROUP BY p.id ORDER BY p.name');

page_start('Properties', 'properties');
section_head('Building portfolio', count($rows) . ' propert' . (count($rows) === 1 ? 'y' : 'ies') . ' under management');
?>
<details class="panel" id="new" <?= $edit || !$rows ? 'open' : '' ?>>
  <summary><?= icon('plus') ?><?= $edit ? 'Edit ' . e($edit['name']) : 'Register a property' ?></summary>
  <div class="body">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="form-grid">
        <div><label>Property name</label><input name="name" required value="<?= e($edit['name'] ?? '') ?>" placeholder="e.g. SK Suites"></div>
        <div><label>Location</label><input name="location" required value="<?= e($edit['location'] ?? '') ?>" placeholder="e.g. Thika"></div>
        <div><label>Type</label><select name="type"><?= options($types, $edit['type'] ?? 'Residential') ?></select></div>
        <div>
          <label>Owner (landlord)</label>
          <select name="owner_id"><option value="">— Not set —</option><?php foreach ($owners as $o): ?><option value="<?= $o['id'] ?>" <?= ($edit['owner_id'] ?? '') == $o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option><?php endforeach; ?></select>
          <div class="help">Owners are added on the <a href="clients.php?type=Owner#new">Clients</a> page with type “Owner”.</div>
        </div>
        <div><label>Management fee (% of rent collected)</label><input name="fee_pct" type="number" step="0.01" min="0" max="100" value="<?= e($edit['fee_pct'] ?? (settings()['default_fee_pct'] ?? 8)) ?>"></div>
        <div class="full"><label>Notes <span class="opt">(optional)</span></label><textarea name="notes"><?= e($edit['notes'] ?? '') ?></textarea></div>
      </div>
      <div class="form-actions"><?php if ($edit): ?><a class="btn ghost" href="properties.php">Cancel</a><?php endif; ?><button class="btn gold"><?= $edit ? 'Save changes' : 'Add property' ?></button></div>
    </form>
  </div>
</details>

<div class="card flush">
  <?php if (!$rows): ?><div class="empty">No properties yet. Register the first one above.</div><?php else: ?>
  <div class="table-wrap"><table>
    <tr><th>Property</th><th>Owner</th><th>Units</th><th class="num">Monthly rent roll</th><th class="num">Fee</th><th></th></tr>
    <?php foreach ($rows as $r): $occ = $r['units'] ? round($r['occupied'] / $r['units'] * 100) : 0; ?>
      <tr>
        <td><strong><?= e($r['name']) ?></strong><div class="small muted"><?= e($r['location']) ?> · <?= e($r['type']) ?></div></td>
        <td><?= $r['owner'] ? e($r['owner']) . '<div class="small muted">' . e($r['owner_phone']) . '</div>' : '<span class="muted">Not set</span>' ?></td>
        <td style="min-width:150px"><?= (int)$r['occupied'] ?> of <?= (int)$r['units'] ?> occupied<div class="bar"><span style="width:<?= $occ ?>%"></span></div></td>
        <td class="num"><?= money($r['rent_roll']) ?></td>
        <td class="num"><?= rtrim(rtrim($r['fee_pct'], '0'), '.') ?>%</td>
        <td><div class="row-actions">
          <a class="btn sm ghost" href="units.php?property=<?= $r['id'] ?>">Units</a>
          <a class="btn sm ghost" href="owner_statement.php?property=<?= $r['id'] ?>">Owner statement</a>
          <a class="btn sm ghost" href="properties.php?edit=<?= $r['id'] ?>#new">Edit</a>
          <?php if (is_admin()): ?><form method="post" class="inline" data-confirm="Delete <?= e($r['name']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn sm danger">Delete</button></form><?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; ?>
  </table></div><?php endif; ?>
</div>
<?php page_end();
