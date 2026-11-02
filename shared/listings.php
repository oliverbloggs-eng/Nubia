<?php
declare(strict_types=1);

// ---------------------------------------------------------------
// Listing queries used by both the website and the management system.
// Include after db.php. The website must only use the published
// functions: listing_by_slug() and listings_public().
// ---------------------------------------------------------------

const LISTING_STATUSES = ['draft' => 'Draft', 'review' => 'In review', 'published' => 'Published', 'let' => 'Let', 'sold' => 'Sold', 'archived' => 'Archived'];
const LISTING_PURPOSES = ['rent' => 'Rent', 'sale' => 'Sale'];
const LISTING_TYPES = ['apartment' => 'Apartment', 'house' => 'House', 'townhouse' => 'Townhouse', 'land' => 'Land', 'commercial' => 'Commercial'];

const LISTING_SELECT = 'SELECT l.*, c.path_lg AS cover_lg, c.path_sm AS cover_sm, c.path_th AS cover_th, c.alt AS cover_alt
    FROM listings l LEFT JOIN listing_photos c ON c.id = l.cover_photo_id';

/** Any listing, whatever its status. Not for public pages. */
function listing_find(int $id): ?array
{
    return one(LISTING_SELECT . ' WHERE l.id = ?', [$id]);
}

/** A published listing by its web address, or null. */
function listing_by_slug(string $slug): ?array
{
    return one(LISTING_SELECT . " WHERE l.slug = ? AND l.status = 'published'", [$slug]);
}

/**
 * Published listings, featured first, then newest first.
 * $filters (all optional): purpose, type, area, min_price, max_price, bedrooms (at least).
 */
function listings_public(array $filters = []): array
{
    $where = ["l.status = 'published'"];
    $params = [];
    foreach (['purpose' => 'l.purpose = ?', 'type' => 'l.type = ?', 'area' => 'l.area = ?'] as $key => $sql) {
        if (is_string($filters[$key] ?? null) && $filters[$key] !== '') { $where[] = $sql; $params[] = $filters[$key]; }
    }
    foreach (['min_price' => 'l.price >= ?', 'max_price' => 'l.price <= ?', 'bedrooms' => 'l.bedrooms >= ?'] as $key => $sql) {
        if (is_numeric($filters[$key] ?? null)) { $where[] = $sql; $params[] = (float)$filters[$key]; }
    }
    return all(LISTING_SELECT . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY l.featured DESC, l.published_at DESC, l.id DESC', $params);
}

/** Photos of a listing in display order. */
function listing_photos(int $id): array
{
    return all('SELECT * FROM listing_photos WHERE listing_id = ? ORDER BY sort_order, id', [$id]);
}

/** Photos of several listings in one query, as listing id => photos in display order. */
function listing_photos_for(array $ids): array
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids) return [];
    $out = array_fill_keys($ids, []);
    $rows = all('SELECT * FROM listing_photos WHERE listing_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY listing_id, sort_order, id', $ids);
    foreach ($rows as $p) $out[(int)$p['listing_id']][] = $p;
    return $out;
}

/** A slug made from $title that no other listing uses. $ignoreId is the listing being edited. */
function listing_unique_slug(string $title, int $ignoreId = 0): string
{
    $base = slugify($title, 110);
    $slug = $base;
    for ($n = 2; val('SELECT COUNT(*) FROM listings WHERE slug = ? AND id <> ?', [$slug, $ignoreId]); $n++) {
        $slug = $base . '-' . $n;
    }
    return $slug;
}
