<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
require_once NUBIA_SHARED . '/images.php';
require_once NUBIA_SHARED . '/listings.php';

if (is_post()) {
    if (post('action') === 'star') {
        $l = listing_find((int)post('id'));
        if ($l && can_edit_listing($l)) {
            q('UPDATE listings SET featured = ?, updated_by = ? WHERE id = ?', [$l['featured'] ? 0 : 1, user()['id'], $l['id']]);
            log_action(($l['featured'] ? 'Removed Featured from listing ' : 'Marked listing Featured: ') . $l['title']);
        } else {
            flash('You cannot change that listing.', 'error');
        }
    }
    redirect('listings.php?' . (string)post('back'));
}

$status = array_key_exists(get('status'), LISTING_STATUSES) ? get('status') : '';
$purpose = array_key_exists(get('purpose'), LISTING_PURPOSES) ? get('purpose') : '';
$area = is_string(get('area')) ? get('area') : '';
$search = is_string(get('q')) ? get('q') : '';

$where = ['1=1']; $params = [];
if ($purpose) { $where[] = 'l.purpose = ?'; $params[] = $purpose; }
if ($area !== '') { $where[] = 'l.area = ?'; $params[] = $area; }
if ($search !== '') { $where[] = '(l.title LIKE ? OR l.area LIKE ? OR l.address LIKE ?)'; array_push($params, "%$search%", "%$search%", "%$search%"); }

// The chips count everything the other filters match, so each number is what a click shows.
$counts = array_column(all('SELECT l.status, COUNT(*) n FROM listings l WHERE ' . implode(' AND ', $where) . ' GROUP BY l.status', $params), 'n', 'status');
if ($status) { $where[] = 'l.status = ?'; $params[] = $status; }
$rows = all(LISTING_SELECT . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY l.updated_at DESC, l.id DESC', $params);
$areas = array_column(all('SELECT DISTINCT area FROM listings ORDER BY area'), 'area');

$query = array_filter(['q' => $search, 'purpose' => $purpose, 'area' => $area, 'status' => $status], fn($v) => $v !== '');
$chip = fn(string $s) => 'listings.php?' . http_build_query(array_filter(['status' => $s] + $query, fn($v) => $v !== ''));

page_start('Listings', 'listings');
section_head('Property listings', 'Listings appear on the website once an administrator publishes them', '<a class="btn gold" href="listing.php">' . icon('plus') . 'Add a listing</a>');
?>
<div class="chips">
  <a class="chip <?= $status === '' ? 'on' : '' ?>" href="<?= e($chip('')) ?>">All <span><?= (int)array_sum($counts) ?></span></a>
  <?php foreach (LISTING_STATUSES as $key => $label): ?>
    <a class="chip <?= $status === $key ? 'on' : '' ?>" href="<?= e($chip($key)) ?>"><?= e($label) ?> <span><?= (int)($counts[$key] ?? 0) ?></span></a>
  <?php endforeach; ?>
</div>

<form class="filters card" method="get">
  <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
  <div style="flex:1"><label>Search</label><input name="q" value="<?= e($search) ?>" placeholder="Title, area or address"></div>
  <div><label>Purpose</label><select name="purpose"><option value="">Rent and sale</option><?= options(LISTING_PURPOSES, $purpose, true) ?></select></div>
  <div><label>Area</label><select name="area"><option value="">All areas</option><?= options($areas, $area) ?></select></div>
  <div><button class="btn ghost">Filter</button></div>
</form>

<div class="card flush">
  <?php if (!$rows): ?><div class="empty">No listings match.</div><?php else: ?>
  <div class="table-wrap"><table>
    <tr><th></th><th>Listing</th><th>Purpose</th><th>Status</th><th class="num">Price</th><th>Updated</th><th></th></tr>
    <?php foreach ($rows as $l): ?>
      <tr>
        <td class="thumb-cell"><?php if ($l['cover_th']): ?><img class="thumb" src="<?= e(nubia_upload_url($l['cover_th'])) ?>" alt=""><?php else: ?><span class="thumb thumb-empty small muted">No photo</span><?php endif; ?></td>
        <td>
          <?php if (can_edit_listing($l)): ?>
            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="star"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><input type="hidden" name="back" value="<?= e(http_build_query($query)) ?>"><button class="star <?= $l['featured'] ? 'on' : '' ?>" title="<?= $l['featured'] ? 'Remove Featured' : 'Mark as Featured' ?>" aria-label="<?= $l['featured'] ? 'Remove Featured' : 'Mark as Featured' ?>"><?= icon('star') ?></button></form>
          <?php elseif ($l['featured']): ?>
            <span class="star on" title="Featured"><?= icon('star') ?></span>
          <?php endif; ?>
          <a href="listing.php?id=<?= (int)$l['id'] ?>"><strong><?= e($l['title']) ?></strong></a>
          <div class="small muted"><?= e(LISTING_TYPES[$l['type']] ?? '') ?> · <?= e($l['area']) ?></div>
        </td>
        <td><?= badge(LISTING_PURPOSES[$l['purpose']] ?? '') ?></td>
        <td><?= badge(LISTING_STATUSES[$l['status']] ?? '') ?></td>
        <td class="num"><?= money($l['price']) ?><?= $l['purpose'] === 'rent' ? '<div class="small muted">per month</div>' : '' ?></td>
        <td class="nowrap"><?= fdate($l['updated_at']) ?></td>
        <td><div class="row-actions"><a class="btn sm ghost" href="listing.php?id=<?= (int)$l['id'] ?>"><?= can_edit_listing($l) ? 'Edit' : 'View' ?></a></div></td>
      </tr>
    <?php endforeach; ?>
  </table></div><?php endif; ?>
</div>
<?php page_end();
