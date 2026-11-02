<?php
// sitemap.xml: the fixed pages below, then one entry per published listing.
// .htaccess serves this file at /sitemap.xml.
require __DIR__ . '/inc/site.php';
header('Content-Type: application/xml; charset=utf-8');
// Printed from PHP so servers with short_open_tag on do not read "<?xml" as code.
echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
  <url><loc>https://nubiakcestates.com/</loc><lastmod>2026-10-09</lastmod></url>
  <url><loc>https://nubiakcestates.com/about/</loc><lastmod>2026-10-09</lastmod><image:image><image:loc>https://nubiakcestates.com/assets/about/nubia-founder-1200x1500.webp</image:loc></image:image><image:image><image:loc>https://nubiakcestates.com/assets/about/nubia-ceo-v2-800x1000.webp</image:loc></image:image><image:image><image:loc>https://nubiakcestates.com/assets/about/nubia-director-v2-1200x1500.webp</image:loc></image:image></url>
  <url><loc>https://nubiakcestates.com/find-property/</loc><lastmod>2026-10-09</lastmod></url>
  <url><loc>https://nubiakcestates.com/contact/</loc><lastmod>2026-10-09</lastmod></url>
  <url><loc>https://nubiakcestates.com/blog/</loc><lastmod>2026-10-09</lastmod></url>
  <url><loc>https://nubiakcestates.com/blog/how-to-invest-in-real-estate-in-kenya/</loc><lastmod>2026-09-30</lastmod></url>
  <url><loc>https://nubiakcestates.com/careers/</loc><lastmod>2026-10-09</lastmod></url>
  <url><loc>https://nubiakcestates.com/privacy/</loc><lastmod>2026-10-09</lastmod></url>
  <url><loc>https://nubiakcestates.com/terms/</loc><lastmod>2026-10-09</lastmod></url>
<?php foreach (site_listings() as $a): ?>
  <url><loc><?= e(site_listing_url($a)) ?></loc><lastmod><?= e(date("Y-m-d", strtotime($a["updatedAt"]))) ?></lastmod><?php foreach ($a["photos"] as $p): ?><image:image><image:loc><?= e($p["src"]) ?></image:loc></image:image><?php endforeach; ?></url>
<?php endforeach; ?>
</urlset>
