<?php
declare(strict_types=1);

// ---------------------------------------------------------------
// Listings on the public website. The pages are a pre-built snapshot
// (for search engines and first paint) that the app in /app.html then
// takes over. These helpers build both: the listing data the app reads
// (window.NUBIA_LISTINGS or /listings.json) and the snapshot markup,
// copied from the app's own design so the two look the same.
// ---------------------------------------------------------------

require __DIR__ . '/shared.php';

const SITE_URL = 'https://nubiakcestates.com';
const SITE_WA = '254703277912';

// The same price bands and bedroom choices as the app's filters.
const SITE_PRICE_BANDS = [
    'r1' => ['Rent', 0, 30000], 'r2' => ['Rent', 30000, 70000], 'r3' => ['Rent', 70000, 150000], 'r4' => ['Rent', 150000, INF],
    's1' => ['Sale', 0, 1e7], 's2' => ['Sale', 1e7, 2.5e7], 's3' => ['Sale', 2.5e7, 5e7], 's4' => ['Sale', 5e7, INF],
];
const SITE_BEDS = ['0', '1', '2', '3', '4'];

// The company entry used in every page's structured data (as on the existing pages).
const SITE_ORG_JSON = '{"@type":"RealEstateAgent","@id":"https://nubiakcestates.com/#organization","name":"Nubia KC Estates Ltd","url":"https://nubiakcestates.com/","logo":"https://nubiakcestates.com/assets/logo-gold.png","image":"https://nubiakcestates.com/assets/og-image.png","description":"Property management, lettings and sales for landlords in Kenya and the diaspora. Vetted tenants, M-Pesa and bank rent collection, and clear monthly statements.","telephone":"+254703277912","email":"info@nubiakcestates.co.ke","address":{"@type":"PostalAddress","streetAddress":"Crescent Business Centre","addressLocality":"Parklands, Nairobi","addressRegion":"Nairobi","addressCountry":"KE"},"areaServed":[{"@type":"Place","name":"Kilimani, Kenya"},{"@type":"Place","name":"Westlands, Kenya"},{"@type":"Place","name":"Ngong Road, Kenya"},{"@type":"Place","name":"Juja, Kenya"},{"@type":"Place","name":"Thika, Kenya"},{"@type":"Place","name":"Murang\'a, Kenya"}]}';

/** Every published listing in app form, featured first then newest. Empty if the database is not ready. */
function site_listings(): array
{
    static $list = null;
    if ($list !== null) return $list;
    try {
        $rows = listings_public();
        $photos = listing_photos_for(array_column($rows, 'id'));
    } catch (PDOException $ex) {
        error_log('Nubia listings: ' . $ex->getMessage());
        return $list = [];
    }
    $list = [];
    foreach ($rows as $rank => $l) $list[] = site_listing_app($l, $photos[(int)$l['id']] ?? [], $rank);
    return $list;
}

/** One listing in the shape the app uses. */
function site_listing_app(array $l, array $photos, int $rank): array
{
    // The cover photo always comes first.
    usort($photos, fn($a, $b) => ((int)$b['id'] === (int)$l['cover_photo_id']) <=> ((int)$a['id'] === (int)$l['cover_photo_id']));
    $pics = array_map(fn($p) => [
        'src' => nubia_upload_url($p['path_lg']), 'sm' => nubia_upload_url($p['path_sm']),
        'alt' => (string)($p['alt'] ?: $l['title']), 'w' => (int)$p['width'], 'h' => (int)$p['height'],
    ], $photos);
    $features = array_values(array_filter(array_map('trim', preg_split('/\R/', (string)$l['features'])), fn($f) => $f !== ''));
    return [
        'id' => (int)$l['id'], 'slug' => $l['slug'], 'rank' => $rank, 'featured' => (bool)$l['featured'],
        'verified' => !empty($l['title_verified']),
        'title' => $l['title'], 'location' => $l['area'], 'address' => (string)$l['address'],
        'category' => LISTING_TYPES[$l['type']] ?? 'Property', 'typeKey' => $l['type'],
        'deal' => $l['purpose'] === 'sale' ? 'Sale' : 'Rent',
        'beds' => $l['bedrooms'] === null ? null : (int)$l['bedrooms'],
        'baths' => $l['bathrooms'] === null ? null : (int)$l['bathrooms'],
        'size' => $l['size_sqm'] === null ? null : (float)$l['size_sqm'],
        'price' => (float)$l['price'], 'status' => 'Available', 'published' => true,
        'image' => $pics[0]['src'] ?? '', 'thumb' => $pics[0]['sm'] ?? '', 'photos' => $pics,
        'summary' => (string)$l['summary'], 'description' => (string)($l['description'] ?: $l['summary']),
        'features' => $features, 'publishedAt' => (string)$l['published_at'], 'updatedAt' => (string)$l['updated_at'],
    ];
}

/** Filters from the address bar, checked against the allowed values. Same names the app writes. */
function site_filters(array $q): array
{
    $s = fn($k) => is_string($q[$k] ?? null) ? trim($q[$k]) : '';
    $deal = ['rent' => 'Rent', 'sale' => 'Sale'][$s('purpose')] ?? 'All';
    $price = array_key_exists($s('price'), SITE_PRICE_BANDS) && ($deal === 'All' || SITE_PRICE_BANDS[$s('price')][0] === $deal) ? $s('price') : 'any';
    return [
        'deal' => $deal,
        'loc' => $s('area'),
        'type' => array_key_exists($s('type'), LISTING_TYPES) ? $s('type') : '',
        'price' => $price,
        'beds' => in_array($s('bedrooms'), SITE_BEDS, true) ? $s('bedrooms') : 'any',
    ];
}

/** The listings that match, using the same rules as the app. */
function site_filter(array $list, array $f): array
{
    $band = SITE_PRICE_BANDS[$f['price']] ?? null;
    return array_values(array_filter($list, function ($a) use ($f, $band) {
        if ($f['deal'] !== 'All' && $a['deal'] !== $f['deal']) return false;
        if ($f['loc'] !== '' && $a['location'] !== $f['loc']) return false;
        if ($f['type'] !== '' && $a['typeKey'] !== $f['type']) return false;
        if ($band && ($a['price'] < $band[1] || $a['price'] >= $band[2])) return false;
        if ($f['beds'] === '0' && $a['beds'] !== 0) return false;
        if ($f['beds'] !== 'any' && $f['beds'] !== '0' && (int)$a['beds'] < (int)$f['beds']) return false;
        return true;
    }));
}

/** JSON that is safe inside a <script> tag. */
function site_json($data): string
{
    return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}

function site_listing_url(array $a): string { return SITE_URL . '/property/' . $a['slug']; }
function site_price(array $a): string { return 'KES ' . number_format($a['price'], 0); }
function site_beds(array $a): string { return $a['beds'] === 0 ? 'Studio' : $a['beds'] . ' bed'; }
function site_num($v): string { return rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.'); }
function site_bg(string $url): string { return 'url(&quot;' . e($url) . '&quot;) center center / cover no-repeat'; }

/** "Showing 3 homes to let" — the app's wording. */
function site_result_text(int $n, string $deal): string
{
    $noun = $deal === 'Rent' ? ($n === 1 ? 'home to let' : 'homes to let') : ($deal === 'Sale' ? ($n === 1 ? 'property for sale' : 'properties for sale') : ($n === 1 ? 'property' : 'properties'));
    return 'Showing ' . $n . ' ' . $noun;
}

/** One property card, as the find-property page draws it. $desk picks the wide-screen copy. */
function site_card(array $a, bool $desk): string
{
    $img = $a['thumb'] ?: $a['image'];
    $pic = $img
        ? '<div role="img" aria-label="' . e($a['title']) . '" style="position: absolute; inset: 0px; background: ' . site_bg($img) . '; transform: scale(1); transition: transform 0.6s;"></div>'
        : '<span style="position: absolute; inset: 0px; display: grid; place-content: center; justify-items: center; gap: 12px; padding: 0px 10px; background: linear-gradient(165deg, rgb(251, 248, 242) 0%, rgb(243, 238, 227) 50%, rgb(231, 223, 206) 100%); transform: scale(1); transition: transform 0.6s;"><img width="112" height="156" src="/assets/mark-gold-112x156.webp" alt="" draggable="false" style="width: ' . ($desk ? '76' : '64') . 'px; height: auto; opacity: 0.3;"><span style="font: 600 10px/1.5 var(--font-sans); letter-spacing: 0.2em; color: rgb(142, 108, 47); text-transform: uppercase; text-align: center;">Photographs coming soon</span></span>';
    $font = 'font-style: ; font-variant-ligatures: ; font-variant-caps: ; font-variant-numeric: lining-nums; font-variant-east-asian: ; font-variant-alternates: ; font-variant-position: ; font-variant-emoji: ; font-weight: ; font-stretch: ; font-size: ; line-height: ; font-family: ; font-optical-sizing: ; font-size-adjust: ; font-kerning: ; font-feature-settings: ; font-variation-settings: ; font-language-override: ; color: rgb(14, 27, 51);';
    $tag = 'display: inline-flex; align-items: center; min-height: 28px; padding: 0px 12px; border-radius: 999px; background: rgba(255, 255, 255, 0.72); backdrop-filter: blur(10px); color: rgb(14, 27, 51); font: 600 12px/1 var(--font-sans); white-space: nowrap;';
    $icon = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="%s"></path></svg>';
    return '
          <article style="scroll-snap-align: start; display: grid; gap: 16px; align-content: start; cursor: pointer; min-width: 0px;">
            <div style="aspect-ratio: 4 / 5; border-radius: 24px; overflow: hidden; position: relative; background: rgb(231, 223, 206);">
              ' . $pic . '
              <div style="position: absolute; top: 14px; left: 14px; right: 72px; display: flex; flex-wrap: wrap; gap: 6px;">
' . ($a['verified'] ? '                <span style="' . $tag . '">Title Verified</span>
' : '') . '                <span style="' . $tag . '"><span class="sc-interp">' . ($a['deal'] === 'Rent' ? 'To let' : 'For sale') . '</span></span>
              </div>
              <span aria-hidden="true" style="position: absolute; top: 0px; right: 0px; padding: 0px 0px 8px 8px; background: rgb(255, 255, 255); border-bottom-left-radius: 24px;">
                <span style="position: absolute; top: 0px; left: -20px; width: 20px; height: 20px; background: radial-gradient(circle at 0px 100%, transparent 19.5px, rgb(255, 255, 255) 20px);"></span>
                <span style="position: absolute; bottom: -20px; right: 0px; width: 20px; height: 20px; background: radial-gradient(circle at 0px 100%, transparent 19.5px, rgb(255, 255, 255) 20px);"></span>
                <span style="width: 44px; height: 44px; display: grid; place-items: center; color: rgb(14, 27, 51);"><svg width="24" height="10" viewBox="0 0 24 10" fill="none" aria-hidden="true" style="transform: none; transition: transform 0.3s;"><path d="M0 5h22.5M18.5 1l4 4-4 4" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"></path></svg></span>
              </span>
            </div>
            <div style="display: grid; gap: 8px; padding: 0px 4px;">
              <span style="font: 600 11.5px var(--font-sans); letter-spacing: 0.16em; color: rgb(142, 108, 47); text-transform: uppercase;"><span class="sc-interp">' . e($a['location']) . '</span></span>
              <h3 style="margin: 0px; ' . $font . '"><a href="/property/' . e($a['slug']) . '" style="color: inherit; text-decoration: none;"><span class="sc-interp">' . e($a['title']) . '</span></a></h3>
              <div style="display: flex; flex-wrap: wrap; gap: 6px 18px; font: 400 14px var(--font-sans); color: rgba(14, 27, 51, 0.75);">
' . ($a['beds'] !== null ? '                <span style="display: inline-flex; align-items: center; gap: 7px;">' . sprintf($icon, 'M2 4v16M2 8h18a2 2 0 0 1 2 2v10M2 17h20M6 8v9') . '<span class="sc-interp">' . e(site_beds($a)) . '</span></span>
' : '') . ($a['baths'] !== null ? '                <span style="display: inline-flex; align-items: center; gap: 7px;">' . sprintf($icon, 'M9 6 6.5 3.5a1.5 1.5 0 0 0-1-.5C4.7 3 4 3.7 4 4.5V17a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5M10 5 8 7M2 12h20M7 19v2M17 19v2') . '<span class="sc-interp">' . e(site_num($a['baths'])) . '</span> bath</span>
' : '') . ($a['size'] !== null ? '                <span style="display: inline-flex; align-items: center; gap: 7px;">' . sprintf($icon, 'M8 3H5a2 2 0 0 0-2 2v3M21 8V5a2 2 0 0 0-2-2h-3M3 16v3a2 2 0 0 0 2 2h3M16 21h3a2 2 0 0 0 2-2v-3') . '<span class="sc-interp">' . e(site_num($a['size'])) . '</span> m²</span>
' : '') . '              </div>
              <span style="' . $font . ' margin-top: 2px;"><span class="sc-interp">' . e(site_price($a)) . '</span><span style="font-weight: 400; font-size: 13px; color: rgba(14, 27, 51, 0.75);"><span class="sc-interp">' . ($a['deal'] === 'Rent' ? ' / month' : '') . '</span></span></span>
            </div>
          </article>
          ';
}

/** The app's "no matches" box, for the snapshot. */
function site_no_results(): string
{
    return '<div style="padding: 48px 24px; border-radius: 24px; background: rgb(243, 238, 227); display: grid; justify-items: center; gap: 12px; text-align: center;"><p style="margin: 0px; font: 400 28px var(--font-display); color: rgb(14, 27, 51);">No homes match these filters</p><p style="margin: 0px; font: 400 15px var(--font-sans); color: rgba(14, 27, 51, 0.75);">Try a different location or price range.</p></div>';
}

/** Rows for the details grid: label => value. Empty values are left out. */
function site_specs(array $a): array
{
    $specs = ['Type' => $a['category'], 'Bedrooms' => $a['beds'] === null ? null : ($a['beds'] === 0 ? 'Studio' : (string)$a['beds']),
              'Bathrooms' => $a['baths'] === null ? null : (string)$a['baths'], 'Size' => $a['size'] === null ? null : site_num($a['size']) . ' m²',
              'Status' => $a['status'], 'Location' => $a['location']];
    return array_filter($specs, fn($v) => $v !== null && $v !== '');
}

function site_wa_link(array $a): string
{
    return 'https://wa.me/' . SITE_WA . '?text=' . rawurlencode("Hello Nubia KC Estates, I'm interested in " . $a['title'] . ' (' . $a['location'] . '). ' . site_listing_url($a));
}

/** Short text for the meta description: the summary, or the start of the description. */
function site_meta_description(array $a): string
{
    $t = trim(preg_replace('/\s+/', ' ', $a['summary'] ?: $a['description']));
    if ($t === '') $t = $a['category'] . ($a['deal'] === 'Rent' ? ' to let' : ' for sale') . ' in ' . $a['location'] . ', ' . site_price($a) . ($a['deal'] === 'Rent' ? ' a month' : '') . '.';
    if (mb_strlen($t) > 160) $t = rtrim(mb_substr($t, 0, 157)) . '…';
    return $t;
}

/** The listing page body: gallery, details, features and the enquiry panel, as the app draws it. */
function site_listing_section(array $a, bool $desk): string
{
    $v = $desk
        ? ['mt' => '-96px', 'top' => '96px', 'w' => 'calc(100% - 48px)', 'pgap' => '24px', 'gap' => '32px', 'cols' => 'minmax(0px, 1fr) 400px', 'colGap' => '48px', 'h1' => '48px', 'price' => '24px', 'specCols' => 'repeat(3, minmax(0px, 1fr))', 'mapAR' => '16 / 7', 'asidePos' => 'sticky', 'asidePad' => '28px', 'cardPad' => '32px']
        : ['mt' => '-84px', 'top' => '92px', 'w' => 'calc(100% - 24px)', 'pgap' => '16px', 'gap' => '24px', 'cols' => 'minmax(0px, 1fr)', 'colGap' => '28px', 'h1' => '34px', 'price' => '21px', 'specCols' => 'repeat(2, minmax(0px, 1fr))', 'mapAR' => '4 / 3', 'asidePos' => 'static', 'asidePad' => '22px 20px', 'cardPad' => '24px 22px'];
    $grad = 'linear-gradient(165deg, rgb(251, 248, 242) 0%, rgb(243, 238, 227) 50%, rgb(231, 223, 206) 100%)';
    $soon = fn(int $w) => '<span style="position: absolute; inset: 0px; display: grid; place-content: center; justify-items: center; gap: 12px; padding: 0px 10px; background: ' . $grad . ';"><img width="112" height="156" src="/assets/mark-gold-112x156.webp" alt="" draggable="false" style="width: ' . $w . 'px; height: auto; opacity: 0.3;"><span style="font: 600 10px/1.5 var(--font-sans); letter-spacing: 0.2em; color: rgb(142, 108, 47); text-transform: uppercase; text-align: center;">Photographs coming soon</span></span>';
    $tag = 'display: inline-flex; align-items: center; min-height: 28px; padding: 0px 12px; border-radius: 999px; background: rgba(255, 255, 255, 0.72); backdrop-filter: blur(10px); color: rgb(14, 27, 51); font: 600 12px/1 var(--font-sans); white-space: nowrap;';
    $tags = '<div style="position: absolute; top: ' . ($desk ? '16px; left: 16px' : '12px; left: 12px') . '; display: flex; flex-wrap: wrap; gap: 6px;"><span style="' . $tag . '">' . e($a['category']) . '</span>' . ($a['verified'] ? '<span style="' . $tag . '">Title Verified</span>' : '') . '</div>';
    $photo = fn(array $p) => '<div role="img" aria-label="' . e($p['alt']) . '" style="position: absolute; inset: 0px; background: ' . site_bg($p['src']) . ';"></div>';
    $main = $a['photos'][0] ?? null;

    if ($desk) {
        $thumbs = '';
        for ($i = 1; $i <= 4; $i++) {
            $p = $a['photos'][$i] ?? null;
            $thumbs .= '<div style="position: relative; border-radius: 20px; overflow: hidden; background: ' . $grad . '; display: grid; place-items: center;"><img width="112" height="156" src="/assets/mark-gold-112x156.webp" alt="" style="width: 32px; height: auto; opacity: 0.25;">' . ($p ? $photo($p) : '') . '</div>';
        }
        $gallery = '<div style="display: grid; grid-template-columns: minmax(0px, 2fr) minmax(0px, 1fr); gap: 12px;"><div style="position: relative; aspect-ratio: 4 / 3; border-radius: 24px; overflow: hidden; background: rgb(231, 223, 206);">' . $soon(72) . ($main ? $photo($main) : '') . $tags . '</div><div style="display: grid; grid-template-columns: 1fr 1fr; grid-template-rows: 1fr 1fr; gap: 12px;">' . $thumbs . '</div></div>';
    } else {
        $rest = '';
        foreach (array_slice($a['photos'], 1) as $p) $rest .= '<div style="scroll-snap-align: start; position: relative; aspect-ratio: 4 / 3; border-radius: 24px; overflow: hidden;">' . $soon(48) . $photo($p) . '</div>';
        $gallery = '<div style="display: grid; grid-auto-flow: column; grid-auto-columns: 88%; gap: 10px; overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; margin-right: -12px; padding-right: 12px;"><div style="scroll-snap-align: start; position: relative; aspect-ratio: 4 / 3; border-radius: 24px; overflow: hidden; background: rgb(231, 223, 206);">' . $soon(56) . ($main ? $photo($main) : '') . $tags . '</div>' . $rest . '</div>';
    }

    $check = '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" style="flex-shrink: 0; margin-top: 3px;"><path d="M3 8.5l3.2 3L13 4.5" stroke="#C9A45C" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
    $li = fn(string $t) => '<li style="display: flex; gap: 10px; font: 400 16px/1.5 var(--font-sans); color: rgb(20, 23, 31);">' . $check . e($t) . '</li>';
    $specs = '';
    foreach (site_specs($a) as $k => $val) $specs .= '<div style="display: grid; gap: 4px; padding: 16px 18px; border-radius: 18px; background: rgb(243, 238, 227);"><dt style="font: 600 11px var(--font-sans); letter-spacing: 0.14em; text-transform: uppercase; color: rgb(107, 102, 92);">' . e($k) . '</dt><dd style="margin: 0px; font: 400 22px/1.2 var(--font-display); font-variant-numeric: lining-nums; color: rgb(14, 27, 51);">' . e($val) . '</dd></div>';
    $features = $a['features'] ? '<div style="display: grid; gap: 12px;"><h2 style="margin: 0px; font: 400 30px/1.1 var(--font-display); color: rgb(14, 27, 51);">Features</h2><ul style="margin: 0px; padding: 0px; list-style: none; display: grid; gap: 12px;">' . implode('', array_map($li, $a['features'])) . '</ul></div>' : '';
    $verified = implode('', array_map($li, ['Title deed search at the Lands Registry', 'Owner identity and authority to let or sell', 'On-site inspection by our team']));
    $arrow = '<svg data-arrow="1" width="24" height="10" viewBox="0 0 24 10" fill="none" aria-hidden="true" style="flex-shrink: 0; transition: transform 0.3s cubic-bezier(0.22, 0.61, 0.36, 1);"><path d="M0 5h22.5M18.5 1l4 4-4 4" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"></path></svg>';

    return '
  <div style="margin-top: ' . $v['mt'] . '; padding-top: ' . $v['top'] . ';">
  <section style="box-sizing: border-box; width: ' . $v['w'] . '; max-width: 1392px; margin: 0px auto ' . $v['pgap'] . '; display: grid; gap: ' . $v['gap'] . ';">
    <a href="/find-property/" style="justify-self: start; display: inline-flex; align-items: center; gap: 10px; min-height: 44px; padding: 0px 18px; border-radius: 999px; background: rgb(243, 238, 227); font: 600 13.5px var(--font-sans); color: rgb(14, 27, 51); text-decoration: none;"><svg width="16" height="10" viewBox="0 0 24 10" fill="none" aria-hidden="true"><path d="M24 5H1.5M5.5 1l-4 4 4 4" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"></path></svg>All properties</a>
    ' . $gallery . '
    <div style="display: grid; grid-template-columns: ' . $v['cols'] . '; gap: ' . $v['colGap'] . '; align-items: start;">
      <div style="display: grid; gap: ' . $v['gap'] . '; min-width: 0px;">
        <div style="display: grid; gap: 12px;">
          <span style="font: 600 12px var(--font-sans); letter-spacing: 0.16em; color: rgb(142, 108, 47); text-transform: uppercase;">' . e($a['location']) . ' · ' . e($a['category']) . '</span>
          <h1 style="margin: 0px; font: 400 ' . $v['h1'] . '/1.1 var(--font-display); font-variant-numeric: lining-nums; color: rgb(14, 27, 51); text-wrap: balance;">' . e($a['title']) . '</h1>
          <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px;">
            <span style="font: 600 ' . $v['price'] . ' var(--font-sans); font-variant-numeric: lining-nums; color: rgb(14, 27, 51);">' . e(site_price($a)) . '<span style="font-weight: 400; font-size: 15px; color: rgba(14, 27, 51, 0.75);">' . ($a['deal'] === 'Rent' ? ' / month' : '') . '</span></span>
          </div>' . ($a['deal'] === 'Sale' ? '
          <a href="/contact/" style="justify-self: start; display: inline-flex; align-items: center; min-height: 44px; font: 500 14px/1.4 var(--font-sans); color: rgb(14, 27, 51); text-decoration: underline; text-decoration-color: rgb(201, 164, 92); text-underline-offset: 4px;">Flexible payment options available: Rent to Own · Tenant Purchase · Mortgage</a>' : '') . '
        </div>
        <dl style="margin: 0px; display: grid; grid-template-columns: ' . $v['specCols'] . '; gap: 10px;">' . $specs . '</dl>
        ' . ($a['description'] !== '' ? '<div style="display: grid; gap: 12px;"><h2 style="margin: 0px; font: 400 30px/1.1 var(--font-display); color: rgb(14, 27, 51);">About this property</h2><p style="margin: 0px; font: 400 17px/1.7 var(--font-sans); color: rgb(20, 23, 31); text-wrap: pretty; white-space: pre-line;">' . e($a['description']) . '</p></div>' : '') . '
        ' . $features . '
        ' . ($a['verified'] ? '<div style="display: grid; gap: 14px; padding: ' . $v['cardPad'] . '; border-radius: 24px; background: rgb(243, 238, 227);"><h2 style="margin: 0px; font: 400 28px/1.1 var(--font-display); color: rgb(14, 27, 51);">What we verified</h2><ul style="margin: 0px; padding: 0px; list-style: none; display: grid; gap: 12px;">' . $verified . '</ul></div>' : '') . '
        <div style="display: grid; gap: 12px;"><h2 style="margin: 0px; font: 400 30px/1.1 var(--font-display); color: rgb(14, 27, 51);">Location</h2><div style="aspect-ratio: ' . $v['mapAR'] . '; border-radius: 24px; background: ' . $grad . '; display: grid; place-content: center; justify-items: center; gap: 10px;"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#8E6C2F" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0zM12 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"></path></svg><span style="font: 500 15px var(--font-sans); color: rgb(14, 27, 51);">' . e($a['location']) . '</span><span style="font: 600 10px var(--font-sans); letter-spacing: 0.2em; color: rgb(142, 108, 47); text-transform: uppercase;">Map coming soon</span></div></div>
      </div>
      <aside style="position: ' . $v['asidePos'] . '; top: 112px; display: grid; gap: 14px; padding: ' . $v['asidePad'] . '; background: rgb(255, 255, 255); border-radius: 24px; box-shadow: rgba(14, 27, 51, 0.08) 0px 8px 24px;">
        <h2 style="margin: 0px; font: 400 28px/1.15 var(--font-display); color: rgb(14, 27, 51);">Enquire about this home</h2>
        <a href="' . e(site_wa_link($a)) . '" target="_blank" rel="noopener" data-btn="1" style="display: inline-flex; align-items: center; justify-content: center; gap: 12px; min-height: 48px; padding: 0px 24px; border: 0px; border-radius: 999px; background: rgb(14, 27, 51); color: rgb(251, 248, 242); font: 600 14.5px var(--font-sans); text-decoration: none; text-align: center; box-sizing: border-box; cursor: pointer; transition: transform 0.3s cubic-bezier(0.22, 0.61, 0.36, 1), box-shadow 0.3s; width: 100%;">Enquire on WhatsApp' . $arrow . '</a>
        <a href="tel:+254703277912" style="display: flex; align-items: center; justify-content: center; min-height: 44px; font: 500 15px var(--font-sans); color: rgb(14, 27, 51); text-decoration: none;">Or call +254 703 277 912</a>
      </aside>
    </div>
  </section>
  </div>
';
}
