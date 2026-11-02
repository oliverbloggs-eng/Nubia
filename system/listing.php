<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
require_once NUBIA_SHARED . '/images.php';
require_once NUBIA_SHARED . '/listings.php';

/** The statuses this user may move a listing to, as status => button label. */
function listing_next_statuses(array $l): array
{
    $s = $l['status'];
    if (!is_admin()) {
        return ['draft' => ['review' => 'Submit for review'], 'review' => ['draft' => 'Back to draft']][$s] ?? [];
    }
    $next = [];
    if ($s === 'draft') $next['review'] = 'Submit for review';
    if ($s !== 'published') $next['published'] = 'Publish';
    if ($s !== 'let' && $l['purpose'] === 'rent') $next['let'] = 'Mark as let';
    if ($s !== 'sold' && $l['purpose'] === 'sale') $next['sold'] = 'Mark as sold';
    if ($s !== 'draft') $next['draft'] = 'Back to draft';
    if ($s !== 'archived') $next['archived'] = 'Archive';
    return $next;
}

function str_post(string $k): string { $v = post($k); return is_string($v) ? $v : ''; }

$id = (int)(is_post() ? post('id') : get('id'));
$listing = $id ? listing_find($id) : null;
if ($id && !$listing) {
    flash('That listing no longer exists.', 'error');
    redirect('listings.php');
}
$errors = [];
$form = $listing ?? [];

if (is_post()) {
    $action = post('action');
    $back = 'listing.php?id=' . $id;

    // Every change except a status move needs edit rights on this listing.
    if ($listing && $action !== 'status' && $action !== 'delete' && !can_edit_listing($listing)) {
        flash('This listing is live or closed, so only an administrator can change it.', 'error');
        redirect($back);
    }

    if ($action === 'save') {
        $title = str_post('title');
        $purpose = array_key_exists(str_post('purpose'), LISTING_PURPOSES) ? str_post('purpose') : 'rent';
        $type = array_key_exists(str_post('type'), LISTING_TYPES) ? str_post('type') : 'apartment';
        $area = str_post('area');
        $address = str_post('address');
        $priceRaw = str_replace([',', ' '], '', str_post('price'));
        $price = num($priceRaw);
        $size = str_replace([',', ' '], '', str_post('size_sqm'));
        $summary = str_replace("\r\n", "\n", str_post('summary'));
        $description = str_replace("\r\n", "\n", str_post('description'));
        $features = implode("\n", array_filter(array_map('trim', preg_split('/\R/', str_post('features'))), fn($f) => $f !== ''));
        $slugInput = str_post('slug');

        if ($title === '') $errors[] = 'Title is required.';
        if (mb_strlen($title) > 160) $errors[] = 'Title must be 160 characters or fewer.';
        if ($area === '') $errors[] = 'Area is required.';
        if (mb_strlen($area) > 80) $errors[] = 'Area must be 80 characters or fewer.';
        if (mb_strlen($address) > 200) $errors[] = 'Address must be 200 characters or fewer.';
        if (mb_strlen($summary) > 300) $errors[] = 'Summary must be 300 characters or fewer.';
        if (strlen($description) > 65000) $errors[] = 'Description is too long.';
        if (strlen($features) > 65000) $errors[] = 'Features list is too long.';
        if ($priceRaw !== '' && (!is_numeric($priceRaw) || $price < 0 || $price > 999999999999)) $errors[] = 'Enter the price as a number.';
        if ($size !== '' && (!is_numeric($size) || (float)$size < 0 || (float)$size > 99999999)) $errors[] = 'Enter the size in square metres as a number.';
        $rooms = [];
        foreach (['bedrooms' => 'Bedrooms', 'bathrooms' => 'Bathrooms'] as $k => $label) {
            $v = str_post($k);
            if ($v !== '' && (!ctype_digit($v) || (int)$v > 255)) $errors[] = "$label must be a whole number.";
            $rooms[$k] = $v === '' ? null : (int)$v;
        }

        // A linked unit decides the property, so the two can never disagree.
        $unitId = (int)str_post('unit_id');
        $propertyId = (int)str_post('property_id');
        if ($unitId) {
            $unit = one('SELECT id, property_id FROM units WHERE id = ?', [$unitId]);
            $unitId = $unit ? (int)$unit['id'] : 0;
            if ($unit) $propertyId = (int)$unit['property_id'];
        }
        if (!$unitId && $propertyId && !val('SELECT id FROM properties WHERE id = ?', [$propertyId])) $propertyId = 0;

        if ($errors) {
            $form = $_POST + $form;
        } else {
            $slug = listing_unique_slug($slugInput !== '' ? $slugInput : $title, $id);
            // Only an administrator can vouch for the title; a staff save keeps whatever was set.
            $wasVerified = (int)($listing['title_verified'] ?? 0);
            $verified = is_admin() ? (post('title_verified') ? 1 : 0) : $wasVerified;
            $data = [$slug, $title, $purpose, $type, $area, nullable($address), $price, $rooms['bedrooms'], $rooms['bathrooms'],
                     $size === '' ? null : round((float)$size, 2), nullable($summary), nullable($description), nullable($features),
                     $propertyId ?: null, $unitId ?: null, post('featured') ? 1 : 0, $verified, user()['id']];
            if ($listing) {
                q('UPDATE listings SET slug=?, title=?, purpose=?, type=?, area=?, address=?, price=?, bedrooms=?, bathrooms=?, size_sqm=?, summary=?, description=?, features=?, property_id=?, unit_id=?, featured=?, title_verified=?, updated_by=? WHERE id=?', [...$data, $id]);
                log_action("Edited listing $title");
                if ($verified !== $wasVerified) log_action(($verified ? 'Marked title verified: ' : 'Removed title verified from ') . $title);
                flash('Listing saved.');
            } else {
                q('INSERT INTO listings (slug, title, purpose, type, area, address, price, bedrooms, bathrooms, size_sqm, summary, description, features, property_id, unit_id, featured, title_verified, updated_by, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [...$data, user()['id']]);
                $id = (int)db()->lastInsertId();
                log_action("Created listing $title");
                if ($verified) log_action("Marked title verified: $title");
                flash('Listing saved as a draft. Add photos below.');
            }
            redirect('listing.php?id=' . $id);
        }
    }

    if (!$listing && $action !== 'save') {
        redirect('listings.php');
    }

    if ($action === 'status') {
        $to = str_post('status');
        $photoCount = (int)val('SELECT COUNT(*) FROM listing_photos WHERE listing_id = ?', [$id]);
        if (!array_key_exists($to, listing_next_statuses($listing))) {
            flash('You cannot make that change to this listing.', 'error');
        } elseif ($to === 'published' && ($photoCount === 0 || (float)$listing['price'] <= 0)) {
            flash('A listing needs a price and at least one photo before it can be published.', 'error');
        } else {
            if ($to === 'published') {
                q('UPDATE listings SET status = ?, published_at = NOW(), updated_by = ? WHERE id = ?', [$to, user()['id'], $id]);
            } else {
                q('UPDATE listings SET status = ?, updated_by = ? WHERE id = ?', [$to, user()['id'], $id]);
            }
            log_action('Listing ' . $listing['title'] . ': ' . $listing['status'] . ' to ' . $to);
            flash('Listing is now: ' . LISTING_STATUSES[$to] . '.');
        }
        redirect($back);
    }

    if ($action === 'delete') {
        if (!is_admin()) {
            flash('Only an administrator can do that.', 'error');
            redirect($back);
        }
        $paths = [];
        foreach (listing_photos($id) as $p) array_push($paths, $p['path_lg'], $p['path_sm'], $p['path_th']);
        q('DELETE FROM listing_photos WHERE listing_id = ?', [$id]);
        q('DELETE FROM listings WHERE id = ?', [$id]);
        nubia_image_delete($paths);
        @rmdir(rtrim((string)nubia_config('uploads_dir'), '/') . '/listings/' . $id);
        log_action('Deleted listing ' . $listing['title']);
        flash('Listing deleted.');
        redirect('listings.php');
    }

    if ($action === 'upload') {
        $f = $_FILES['photos'] ?? null;
        $count = is_array($f['name'] ?? null) ? count($f['name']) : 0;
        if (!$count) flash('Choose at least one photo.', 'error');
        for ($i = 0; $i < $count; $i++) {
            $file = ['name' => $f['name'][$i], 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
            try {
                $img = nubia_image_save($file, 'listings/' . $id, $listing['slug']);
            } catch (RuntimeException $ex) {
                flash($file['name'] . ': ' . $ex->getMessage(), 'error');
                continue;
            }
            $next = (int)val('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM listing_photos WHERE listing_id = ?', [$id]);
            q('INSERT INTO listing_photos (listing_id, path_lg, path_sm, path_th, width, height, alt, sort_order) VALUES (?,?,?,?,?,?,?,?)',
              [$id, $img['files']['lg']['path'], $img['files']['sm']['path'], $img['files']['th']['path'], $img['width'], $img['height'], mb_substr($listing['title'], 0, 200), $next]);
            $photoId = (int)db()->lastInsertId();
            q('UPDATE listings SET cover_photo_id = COALESCE(cover_photo_id, ?), updated_by = ? WHERE id = ?', [$photoId, user()['id'], $id]);
            log_action('Added a photo to listing ' . $listing['title']);
            flash('Added photo: ' . $file['name']);
        }
        redirect($back . '#photos');
    }

    if ($action === 'photos') {
        // One form holds every photo, so a move or cover change also keeps typed alt text.
        $photos = listing_photos($id);
        $alts = is_array(post('alt')) ? post('alt') : [];
        foreach ($photos as $p) {
            $alt = $alts[$p['id']] ?? null;
            if (is_string($alt) && trim($alt) !== (string)$p['alt']) {
                q('UPDATE listing_photos SET alt = ? WHERE id = ?', [nullable(mb_substr(trim($alt), 0, 200)), $p['id']]);
            }
        }
        [$do, $photoId] = array_pad(explode(':', str_post('do'), 2), 2, 0);
        $ids = array_map('intval', array_column($photos, 'id'));
        $at = array_search((int)$photoId, $ids, true);
        if ($at !== false) {
            if ($do === 'cover') {
                q('UPDATE listings SET cover_photo_id = ? WHERE id = ?', [$ids[$at], $id]);
            } elseif ($do === 'up' || $do === 'down') {
                $swap = $at + ($do === 'up' ? -1 : 1);
                if (isset($ids[$swap])) {
                    [$ids[$at], $ids[$swap]] = [$ids[$swap], $ids[$at]];
                    foreach ($ids as $n => $pid) q('UPDATE listing_photos SET sort_order = ? WHERE id = ?', [$n, $pid]);
                }
            }
        }
        q('UPDATE listings SET updated_by = ? WHERE id = ?', [user()['id'], $id]);
        log_action('Edited photos of listing ' . $listing['title']);
        flash('Photos saved.');
        redirect($back . '#photos');
    }

    if ($action === 'photo_delete') {
        if (!is_admin()) {
            flash('Only an administrator can do that.', 'error');
            redirect($back . '#photos');
        }
        $p = one('SELECT * FROM listing_photos WHERE id = ? AND listing_id = ?', [(int)post('photo_id'), $id]);
        if ($p) {
            q('DELETE FROM listing_photos WHERE id = ?', [$p['id']]);
            nubia_image_delete([$p['path_lg'], $p['path_sm'], $p['path_th']]);
            if ((int)$listing['cover_photo_id'] === (int)$p['id']) {
                $first = listing_photos($id)[0]['id'] ?? null;
                q('UPDATE listings SET cover_photo_id = ? WHERE id = ?', [$first, $id]);
            }
            log_action('Deleted a photo from listing ' . $listing['title']);
            flash('Photo deleted.');
        }
        redirect($back . '#photos');
    }
}

$canEdit = !$listing || can_edit_listing($listing);
$properties = array_column(all('SELECT id, name FROM properties ORDER BY name'), 'name', 'id');
$units = all('SELECT u.id, u.unit_no, p.name property FROM units u JOIN properties p ON p.id = u.property_id ORDER BY p.name, u.unit_no');
$unitOptions = [];
foreach ($units as $u) $unitOptions[$u['id']] = $u['property'] . ' · ' . $u['unit_no'];
$areaList = array_unique(array_merge(['Nairobi', 'Kilimani', 'Westlands', 'Ngong Road', 'Thika', 'Juja', "Murang'a"], array_column(all('SELECT DISTINCT area FROM listings ORDER BY area'), 'area')));
$photos = $listing ? listing_photos($id) : [];

page_start($listing ? 'Edit listing' : 'Add a listing', 'listings');
section_head($listing ? $listing['title'] : 'New listing',
    $listing ? badge(LISTING_STATUSES[$listing['status']]) . ($listing['published_at'] ? ' <span class="small">Published ' . fdate($listing['published_at']) . '</span>' : '') : 'Save the details first, then add photos',
    '<a class="btn ghost" href="listings.php">All listings</a>');

foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach;

if ($listing && !$canEdit): ?><div class="alert alert-info">This listing is live or closed, so only an administrator can change it.</div><?php endif;

$next = $listing ? listing_next_statuses($listing) : [];
if ($next): ?>
<div class="card status-card">
  <div><h3>Status</h3><p class="small muted">Save any changes below before changing the status.</p></div>
  <div class="actions">
    <?php foreach ($next as $to => $label): ?>
      <form method="post" class="inline" <?= $to === 'archived' ? 'data-confirm="Archive this listing? It will leave the website."' : '' ?>><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$id ?>"><input type="hidden" name="status" value="<?= e($to) ?>"><button class="btn sm <?= in_array($to, ['published', 'review'], true) ? 'gold' : 'ghost' ?>"><?= e($label) ?></button></form>
    <?php endforeach; ?>
    <?php if (is_admin()): ?>
      <form method="post" class="inline" data-confirm="Delete this listing and all its photos? This cannot be undone."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$id ?>"><button class="btn sm danger">Delete</button></form>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<form class="card" method="post">
  <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$id ?>">
  <fieldset class="plain" <?= $canEdit ? '' : 'disabled' ?>>
  <div class="form-grid">
    <div class="full"><label>Title</label><input name="title" required maxlength="160" value="<?= e($form['title'] ?? '') ?>" placeholder="2 bedroom apartment in Kilimani"></div>
    <div><label>Purpose</label><select name="purpose"><?= options(LISTING_PURPOSES, $form['purpose'] ?? 'rent', true) ?></select></div>
    <div><label>Type</label><select name="type"><?= options(LISTING_TYPES, $form['type'] ?? 'apartment', true) ?></select></div>
    <div><label>Area</label><input name="area" required maxlength="80" list="areaList" value="<?= e($form['area'] ?? '') ?>"><datalist id="areaList"><?php foreach ($areaList as $a): ?><option value="<?= e($a) ?>"></option><?php endforeach; ?></datalist></div>
    <div><label>Address <span class="opt">(optional)</span></label><input name="address" maxlength="200" value="<?= e($form['address'] ?? '') ?>"></div>
    <div><label>Price (KES) <span class="opt">(monthly for rent, total for sale)</span></label><input name="price" inputmode="decimal" value="<?= e($form['price'] ?? '') ?>"></div>
    <div><label>Size in square metres <span class="opt">(optional)</span></label><input name="size_sqm" inputmode="decimal" value="<?= e($form['size_sqm'] ?? '') ?>"></div>
    <div><label>Bedrooms <span class="opt">(optional)</span></label><input name="bedrooms" inputmode="numeric" value="<?= e($form['bedrooms'] ?? '') ?>"></div>
    <div><label>Bathrooms <span class="opt">(optional)</span></label><input name="bathrooms" inputmode="numeric" value="<?= e($form['bathrooms'] ?? '') ?>"></div>
    <div class="full"><label>Summary <span class="opt">(up to 300 characters, shown on listing cards and in search results)</span></label><textarea name="summary" maxlength="300"><?= e($form['summary'] ?? '') ?></textarea></div>
    <div class="full"><label>Description <span class="opt">(optional)</span></label><textarea name="description" style="min-height:180px"><?= e($form['description'] ?? '') ?></textarea></div>
    <div class="full"><label>Features <span class="opt">(one per line)</span></label><textarea name="features" placeholder="Borehole water&#10;Backup generator&#10;Two parking spaces"><?= e($form['features'] ?? '') ?></textarea></div>
    <div><label>Linked property <span class="opt">(optional)</span></label><select name="property_id"><option value="">None</option><?= options($properties, $form['property_id'] ?? '', true) ?></select></div>
    <div><label>Linked unit <span class="opt">(optional)</span></label><select name="unit_id"><option value="">None</option><?= options($unitOptions, $form['unit_id'] ?? '', true) ?></select></div>
    <div class="full"><label>Web address <span class="opt">(optional, made from the title if left empty)</span></label><input name="slug" maxlength="110" value="<?= e($form['slug'] ?? '') ?>"><div class="help">Letters, numbers and dashes. The website will show this listing at /property/this-address.</div></div>
    <div class="full"><label class="check"><input type="checkbox" name="featured" value="1" <?= !empty($form['featured']) ? 'checked' : '' ?>> Featured (shown first on the website)</label></div>
    <?php if (is_admin()): ?>
    <div class="full"><label class="check"><input type="checkbox" name="title_verified" value="1" <?= !empty($form['title_verified']) ? 'checked' : '' ?>> Title verified (shows the "Title Verified" badge and the "What we verified" box on the website)</label><div class="help">Tick only after the title deed search, owner identity check and on-site inspection are done.</div></div>
    <?php else: ?>
    <div class="full"><p class="small muted" style="margin:0">Title verified: <?= !empty($form['title_verified']) ? 'yes' : 'no' ?>. Only an administrator can change this.</p></div>
    <?php endif; ?>
  </div>
  <?php if ($canEdit): ?><div class="form-actions"><a class="btn ghost" href="listings.php">Cancel</a><button class="btn gold"><?= $listing ? 'Save changes' : 'Save draft' ?></button></div><?php endif; ?>
  </fieldset>
</form>

<?php if ($listing): ?>
<div class="card" id="photos">
  <h3>Photos</h3>
  <?php if ($canEdit): ?>
    <form id="photoUpload" method="post" enctype="multipart/form-data" class="upload-row">
      <?= csrf_field() ?><input type="hidden" name="action" value="upload"><input type="hidden" name="id" value="<?= (int)$id ?>">
      <div style="flex:1;min-width:220px"><label>Add photos</label><input type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" required><div class="help">JPG, PNG or WebP, up to 8 MB each. Choose several at once.</div></div>
      <div><button class="btn"><?= icon('plus') ?>Upload</button></div>
    </form>
    <p class="small" id="photoProgress" role="status"></p>
  <?php endif; ?>
  <?php if (!$photos): ?><p class="muted">No photos yet. A listing needs at least one before it can be published.</p><?php else: ?>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="photos"><input type="hidden" name="id" value="<?= (int)$id ?>">
    <?php // Pressing Enter in an alt text box should only save, not press the first photo's buttons. ?>
    <button name="do" value="save" hidden></button>
    <fieldset class="plain" <?= $canEdit ? '' : 'disabled' ?>>
    <div class="photo-grid">
      <?php foreach ($photos as $n => $p): $isCover = (int)$listing['cover_photo_id'] === (int)$p['id']; ?>
        <div class="photo <?= $isCover ? 'is-cover' : '' ?>">
          <img src="<?= e(nubia_upload_url($p['path_th'])) ?>" alt="<?= e($p['alt']) ?>" loading="lazy">
          <label>Description for screen readers and search</label>
          <input name="alt[<?= (int)$p['id'] ?>]" maxlength="200" value="<?= e($p['alt']) ?>">
          <?php if ($canEdit): ?>
          <div class="photo-actions">
            <?php if ($isCover): ?><span class="badge badge-gold">Cover</span><?php else: ?><button class="btn sm ghost" name="do" value="cover:<?= (int)$p['id'] ?>">Set cover</button><?php endif; ?>
            <?php if ($n > 0): ?><button class="btn sm ghost" name="do" value="up:<?= (int)$p['id'] ?>">Earlier</button><?php endif; ?>
            <?php if ($n < count($photos) - 1): ?><button class="btn sm ghost" name="do" value="down:<?= (int)$p['id'] ?>">Later</button><?php endif; ?>
            <?php if (is_admin()): ?><button class="btn sm danger" form="deletePhoto<?= (int)$p['id'] ?>">Delete</button><?php endif; ?>
          </div>
          <?php elseif ($isCover): ?><div class="photo-actions"><span class="badge badge-gold">Cover</span></div><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if ($canEdit): ?><div class="form-actions"><button class="btn gold" name="do" value="save">Save photo descriptions</button></div><?php endif; ?>
    </fieldset>
  </form>
  <?php if ($canEdit && is_admin()) foreach ($photos as $p): ?>
    <form id="deletePhoto<?= (int)$p['id'] ?>" method="post" data-confirm="Delete this photo?"><?= csrf_field() ?><input type="hidden" name="action" value="photo_delete"><input type="hidden" name="id" value="<?= (int)$id ?>"><input type="hidden" name="photo_id" value="<?= (int)$p['id'] ?>"></form>
  <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php page_end();
