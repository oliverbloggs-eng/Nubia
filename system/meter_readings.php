<?php
// Meter readings: one screen per property, month and utility. Saving adds
// each reading as a line on that month's invoice for the unit.
require __DIR__ . '/inc/bootstrap.php';
require_login();

$propertyId = (int)(post('property') ?: get('property'));
$period = valid_period(post('period') ?: get('period'));
$utilityIn = post('utility') ?: get('utility');
$utility = is_string($utilityIn) && isset(UTILITIES[$utilityIn]) ? $utilityIn : 'water';
$periodDate = $period . '-01';
$label = UTILITIES[$utility];
$back = 'meter_readings.php?' . http_build_query(['property' => $propertyId, 'period' => $period, 'utility' => $utility]);

$property = $propertyId ? one('SELECT * FROM properties WHERE id=?', [$propertyId]) : null;

/** Units to read: occupied or sold with a client, plus any unit already read this month. */
function reading_rows(int $propertyId, string $utility, string $periodDate): array
{
    $rows = all('
        SELECT u.*, m.id reading_id, m.client_id reading_client_id, m.previous_reading, m.current_reading, m.units_used,
               m.price_per_unit, m.amount, m.meter_replaced, m.invoice_id, i.invoice_no,
               COALESCE(m.client_id, u.client_id) bill_client_id, c.name client
        FROM units u
        LEFT JOIN meter_readings m ON m.unit_id=u.id AND m.utility=? AND m.period=?
        LEFT JOIN invoices i ON i.id=m.invoice_id
        LEFT JOIN clients c ON c.id=COALESCE(m.client_id, u.client_id)
        WHERE u.property_id=? AND (m.id IS NOT NULL OR (u.status IN ("Occupied","Sold") AND u.client_id IS NOT NULL))
        ORDER BY LENGTH(u.unit_no), u.unit_no', [$utility, $periodDate, $propertyId]);
    foreach ($rows as &$r) {
        // Previous reading = the latest earlier month's current reading
        $r['last'] = val('SELECT current_reading FROM meter_readings WHERE unit_id=? AND utility=? AND period<? ORDER BY period DESC LIMIT 1',
            [$r['id'], $utility, $periodDate]);
    }
    return $rows;
}

$errors = [];
$posted = null;

if (is_post() && $property) {
    $action = post('action');

    if ($action === 'delete') {
        if (!is_admin()) { flash('Only an administrator can delete a reading.', 'error'); redirect($back); }
        $r = one('SELECT m.*, u.unit_no FROM meter_readings m JOIN units u ON u.id=m.unit_id WHERE m.id=? AND u.property_id=?', [(int)post('id'), $propertyId]);
        if ($r) {
            db()->beginTransaction();
            q('DELETE FROM meter_readings WHERE id=?', [$r['id']]);
            if ($r['invoice_id']) invoice_recalc((int)$r['invoice_id']);
            db()->commit();
            log_action("Deleted {$utility} reading for {$property['name']} unit {$r['unit_no']}, " . period_label($period));
            flash(UTILITIES[$r['utility']] . ' reading for unit ' . $r['unit_no'] . ' deleted and removed from its invoice.');
        }
        redirect($back);
    }

    if ($action === 'save') {
        $posted = $_POST['rows'] ?? [];
        if (!is_array($posted)) $posted = [];
        $priceIn = post('price');
        if ($priceIn === '' || !is_numeric(str_replace(',', '', $priceIn)) || num($priceIn) < 0) {
            $errors[] = 'Enter the price per unit for this month.';
        }
        $price = num($priceIn);
        $saved = 0; $touched = [];

        db()->beginTransaction();
        try {
            foreach (reading_rows($propertyId, $utility, $periodDate) as $u) {
                $in = $posted[$u['id']] ?? null;
                if (!is_array($in)) continue;
                $curIn = trim((string)($in['current'] ?? ''));
                if ($curIn === '') continue;                     // not read yet; existing readings are only removed by Delete
                $replaced = !empty($in['replaced']) ? 1 : 0;
                $prevIn = trim((string)($in['previous'] ?? ''));
                $name = 'Unit ' . $u['unit_no'];

                if (!is_numeric(str_replace(',', '', $curIn))) { $errors[] = "$name: the current reading is not a number."; continue; }
                $cur = num($curIn);
                if ($u['last'] !== null && !$replaced) {
                    $prev = (float)$u['last'];                    // always last month's reading unless the meter was replaced
                } elseif ($prevIn === '' || !is_numeric(str_replace(',', '', $prevIn))) {
                    $errors[] = "$name: enter the previous (opening) reading."; continue;
                } else {
                    $prev = num($prevIn);
                }
                if ($cur < 0 || $prev < 0) { $errors[] = "$name: readings cannot be negative."; continue; }
                if ($cur < $prev) {
                    $errors[] = "$name: the current reading (" . qty($cur) . ') is lower than the previous one (' . qty($prev) . '). Check it, or tick "Meter replaced" and enter the new meter\'s opening reading.';
                    continue;
                }
                if ($errors) continue;                            // keep checking rows, save nothing

                $usedUnits = round($cur - $prev, 2);
                $amount = round($usedUnits * $price, 2);
                $clientId = (int)$u['bill_client_id'];

                if ($u['reading_id'] && (float)$u['previous_reading'] == $prev && (float)$u['current_reading'] == $cur
                    && (float)$u['price_per_unit'] == $price && (int)$u['meter_replaced'] === $replaced
                    && ($u['invoice_id'] || $amount == 0)) {
                    continue;                                     // unchanged and already on its invoice
                }

                $invoiceId = null;
                if ($amount != 0) {
                    $inv = invoice_for_period($u, $clientId, $period);
                    if (!$inv) { $errors[] = "$name: the " . period_label($period) . ' invoice for this unit belongs to a different client. Check the unit on the Units page.'; continue; }
                    $invoiceId = (int)$inv['id'];
                }

                if ($u['reading_id']) {
                    q('UPDATE meter_readings SET previous_reading=?, current_reading=?, units_used=?, price_per_unit=?, amount=?, meter_replaced=?, invoice_id=?, updated_by=? WHERE id=?',
                      [$prev, $cur, $usedUnits, $price, $amount, $replaced, $invoiceId, user()['id'], $u['reading_id']]);
                    log_action("Edited {$utility} reading for {$property['name']} unit {$u['unit_no']}, " . period_label($period) . ': ' . qty($prev) . ' → ' . qty($cur) . ' at ' . money($price));
                    if ($u['invoice_id']) $touched[(int)$u['invoice_id']] = true;
                } else {
                    q('INSERT INTO meter_readings (unit_id, client_id, utility, period, previous_reading, current_reading, units_used, price_per_unit, amount, meter_replaced, invoice_id, created_by, updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                      [$u['id'], $clientId, $utility, $periodDate, $prev, $cur, $usedUnits, $price, $amount, $replaced, $invoiceId, user()['id'], user()['id']]);
                    log_action("Added {$utility} reading for {$property['name']} unit {$u['unit_no']}, " . period_label($period) . ': ' . qty($prev) . ' → ' . qty($cur) . ' at ' . money($price));
                }
                if ($invoiceId) $touched[$invoiceId] = true;
                $saved++;
            }
            if ($errors) {
                db()->rollBack();
            } else {
                foreach (array_keys($touched) as $id) invoice_recalc($id);
                db()->commit();
            }
        } catch (Throwable $t) {
            if (db()->inTransaction()) db()->rollBack();
            $errors[] = 'Saving stopped with an error. Nothing was saved. Try again.';
        }

        if (!$errors) {
            // A later month that started from the old figure now needs checking
            $later = all('SELECT DISTINCT u.unit_no FROM meter_readings m JOIN units u ON u.id=m.unit_id
                          JOIN meter_readings cur ON cur.unit_id=m.unit_id AND cur.utility=m.utility AND cur.period=?
                          WHERE u.property_id=? AND m.utility=? AND m.period = (SELECT MIN(n.period) FROM meter_readings n WHERE n.unit_id=m.unit_id AND n.utility=m.utility AND n.period > ?)
                            AND m.meter_replaced=0 AND m.previous_reading <> cur.current_reading', [$periodDate, $propertyId, $utility, $periodDate]);
            flash($saved ? "$saved reading(s) saved and added to the " . period_label($period) . ' invoices.' : 'Nothing changed.');
            if ($later) flash('The next month\'s readings for unit(s) ' . implode(', ', array_column($later, 'unit_no')) . ' start from a different figure. Open that month and save it again to update those bills.', 'error');
            redirect($back);
        }
    }
}

$properties = all('SELECT id, name FROM properties ORDER BY name');
page_start('Meter readings', 'meters');
?>
<form class="filters card no-print" method="get">
  <div><label>Property</label><select name="property" required><option value="">— Choose —</option><?php foreach ($properties as $p): ?><option value="<?= $p['id'] ?>" <?= $propertyId == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
  <div><label>Month</label><select name="period"><?= period_options($period) ?></select></div>
  <div><label>Utility</label><select name="utility"><?= options(UTILITIES, $utility, true) ?></select></div>
  <div class="actions"><button class="btn">Show</button></div>
</form>
<?php
if (!$property) {
    echo '<div class="card empty">Choose a property, month and utility, then select Show.</div>';
    page_end(); exit;
}

$rows = reading_rows($propertyId, $utility, $periodDate);
$usual = utility_price($property, $utility);
$savedPrice = null;
foreach ($rows as $r) if ($r['reading_id']) { $savedPrice = (float)$r['price_per_unit']; break; }
$price = $posted !== null ? post('price') : ($savedPrice ?? $usual);
$usualFrom = ($property[$utility . '_price'] ?? null) !== null ? 'this property' : 'Company settings';

section_head($property['name'] . ' · ' . $label, e(period_label($period)) . ' · ' . count($rows) . ' unit(s)',
    '<a class="btn ghost" href="utility_statement.php?' . e(http_build_query(['property' => $propertyId, 'period' => $period, 'utility' => $utility])) . '">' . icon('report') . 'Utility statement</a>');

foreach ($errors as $err) echo '<div class="alert alert-error">' . e($err) . '</div>';
if ($errors) echo '<div class="alert alert-error">Nothing was saved. Fix the rows above and save again.</div>';

if (!$rows) {
    echo '<div class="card empty">No occupied or sold units with a client in this property. Assign clients on the <a href="units.php?property=' . $propertyId . '">Units</a> page.</div>';
    page_end(); exit;
}
?>
<form method="post" id="readingsForm">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save"><input type="hidden" name="property" value="<?= $propertyId ?>">
  <input type="hidden" name="period" value="<?= e($period) ?>"><input type="hidden" name="utility" value="<?= e($utility) ?>">

  <div class="card">
    <div class="form-grid">
      <div><label>Price per unit for <?= e(period_label($period)) ?> (KES)</label>
        <input type="number" step="0.01" min="0" name="price" id="mrPrice" required value="<?= e($price === null ? '' : number_format((float)$price, 2, '.', '')) ?>">
        <div class="help"><?= $usual !== null ? 'Usual price: ' . e(money($usual)) . ' (from ' . e($usualFrom) . '). ' : 'No usual price is set. Add one in Company settings or on the property. ' ?>A change here applies to this month only.</div>
      </div>
    </div>
  </div>

  <div class="card flush">
    <div class="table-wrap"><table class="readings">
      <tr><th>Unit</th><th>Tenant / buyer</th><th class="num">Previous</th><th class="num">Current</th><th>Meter replaced</th><th class="num">Units used</th><th class="num">Price</th><th class="num">Amount</th><th></th></tr>
      <?php foreach ($rows as $r):
        $id = (int)$r['id'];
        $p = is_array($posted[$id] ?? null) ? $posted[$id] : null;
        $replaced = $p ? !empty($p['replaced']) : (bool)$r['meter_replaced'];
        $locked = $r['last'] !== null;
        $prev = $p ? ($p['previous'] ?? '') : ($r['reading_id'] ? $r['previous_reading'] : ($r['last'] ?? ''));
        if ($locked && !$replaced) $prev = $r['last'];
        $cur = $p ? ($p['current'] ?? '') : ($r['reading_id'] ? $r['current_reading'] : '');
        $fmt = fn($v) => $v === '' || $v === null ? '' : rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.');
      ?>
        <tr data-row>
          <td><strong><?= e($r['unit_no']) ?></strong><?= $r['reading_id'] ? '<div class="small muted">Saved' . ($r['invoice_no'] ? ' · <a href="invoice.php?id=' . (int)$r['invoice_id'] . '" target="_blank">' . e($r['invoice_no']) . '</a>' : '') . '</div>' : '' ?></td>
          <td><?= e($r['client'] ?? '—') ?></td>
          <td class="num"><input class="mr-num" type="number" step="0.01" min="0" name="rows[<?= $id ?>][previous]" data-prev data-locked="<?= $locked ? e($fmt($r['last'])) : '' ?>" value="<?= e($fmt($prev)) ?>" <?= $locked && !$replaced ? 'readonly' : '' ?> <?= $locked ? '' : 'placeholder="Opening"' ?>></td>
          <td class="num"><input class="mr-num" type="number" step="0.01" min="0" name="rows[<?= $id ?>][current]" data-cur value="<?= e($fmt($cur)) ?>"></td>
          <td><label class="check"><input type="checkbox" name="rows[<?= $id ?>][replaced]" value="1" data-replaced <?= $replaced ? 'checked' : '' ?>> Replaced</label></td>
          <td class="num" data-used>—</td>
          <td class="num" data-price>—</td>
          <td class="num" data-amount>—</td>
          <td><?php if ($r['reading_id'] && is_admin()): ?><button class="btn sm danger" form="del-<?= (int)$r['reading_id'] ?>">Delete</button><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      <tfoot><tr><td colspan="5">Total</td><td class="num" id="mrUnits">—</td><td></td><td class="num" id="mrTotal">—</td><td></td></tr></tfoot>
    </table></div>
  </div>
  <p class="help">Previous reading comes from last month. For a new meter, type the opening reading. If a meter was changed, tick "Meter replaced" and type the new meter's starting figure. Units used of 0 add nothing to the invoice. Leave Current blank for a unit you have not read yet.</p>
  <div class="form-actions"><button class="btn gold">Save all readings</button></div>
</form>
<?php foreach ($rows as $r): if ($r['reading_id'] && is_admin()): ?>
<form method="post" id="del-<?= (int)$r['reading_id'] ?>" data-confirm="Delete the <?= e(strtolower($label)) ?> reading for unit <?= e($r['unit_no']) ?>? It will also be taken off the invoice.">
  <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['reading_id'] ?>">
  <input type="hidden" name="property" value="<?= $propertyId ?>"><input type="hidden" name="period" value="<?= e($period) ?>"><input type="hidden" name="utility" value="<?= e($utility) ?>">
</form>
<?php endif; endforeach; ?>
<?php page_end();
