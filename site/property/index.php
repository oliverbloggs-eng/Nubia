<?php
require dirname(__DIR__) . '/inc/site.php';

// /listings.json: every published listing, for the app on the other pages.
if (($_GET['format'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Robots-Tag: noindex');
    echo site_json(site_listings());
    exit;
}

$slug = is_string($_GET['slug'] ?? null) ? $_GET['slug'] : '';
if ($slug === '') {
    header('Location: /find-property/', true, 301);
    exit;
}
$all = site_listings();
$a = null;
foreach ($all as $x) if ($x['slug'] === $slug) { $a = $x; break; }
if (!$a) {
    http_response_code(404);
    readfile(dirname(__DIR__) . '/404.html');
    exit;
}

$url = site_listing_url($a);
$title = $a['title'] . ' | Nubia KC Estates';
$desc = site_meta_description($a);
$cover = $a['photos'][0] ?? null;
$ld = ['@context' => 'https://schema.org', '@graph' => [
    json_decode(SITE_ORG_JSON, true),
    ['@type' => 'WebPage', '@id' => $url . '#webpage', 'url' => $url, 'name' => $title, 'description' => $desc,
     'isPartOf' => ['@id' => SITE_URL . '/#website'], 'about' => ['@id' => SITE_URL . '/#organization'], 'inLanguage' => 'en-KE'],
    array_filter(['@type' => 'RealEstateListing', '@id' => $url . '#listing', 'url' => $url, 'name' => $a['title'], 'description' => $desc,
     'image' => array_column($a['photos'], 'src') ?: null, 'datePosted' => $a['publishedAt'] ? date('Y-m-d', strtotime($a['publishedAt'])) : null,
     'offers' => ['@type' => 'Offer', 'price' => $a['price'], 'priceCurrency' => 'KES', 'availability' => 'https://schema.org/InStock',
                  'businessFunction' => $a['deal'] === 'Rent' ? 'http://purl.org/goodrelations/v1#LeaseOut' : 'http://purl.org/goodrelations/v1#Sell',
                  'seller' => ['@id' => SITE_URL . '/#organization']]]),
    ['@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => SITE_URL . '/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Find property', 'item' => SITE_URL . '/find-property/'],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $a['title'], 'item' => $url]]],
]];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<link rel="canonical" href="<?= e($url) ?>">
<meta name="robots" content="index, follow, max-image-preview:large">
<meta name="nubia-routed" content="1">
<meta name="theme-color" content="#0E1B33">
<link rel="icon" href="/favicon.ico" sizes="48x48">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
<link rel="manifest" href="/site.webmanifest">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Nubia KC Estates">
<meta property="og:locale" content="en_KE">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:url" content="<?= e($url) ?>">
<?php if ($cover): ?>
<meta property="og:image" content="<?= e($cover['src']) ?>">
<meta property="og:image:width" content="<?= (int)$cover['w'] ?>">
<meta property="og:image:height" content="<?= (int)$cover['h'] ?>">
<meta property="og:image:alt" content="<?= e($cover['alt']) ?>">
<?php else: ?>
<meta property="og:image" content="https://nubiakcestates.com/assets/og-image.png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="Nubia KC Estates Ltd logo">
<?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($title) ?>">
<meta name="twitter:description" content="<?= e($desc) ?>">
<meta name="twitter:image" content="<?= e($cover ? $cover['src'] : 'https://nubiakcestates.com/assets/og-image.png') ?>">
<script type="application/ld+json"><?= json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500&family=Hanken+Grotesk:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="/styles.css">
<link rel="preload" href="/app.html" as="fetch" crossorigin="anonymous">
<link rel="preload" href="/vendor/react-18.3.1.production.min.js" as="script">
<link rel="preload" href="/vendor/react-dom-18.3.1.production.min.js" as="script">
<link rel="preload" href="/support.js" as="script">
<style>*{box-sizing:border-box}body{margin:0;background:#FFFFFF;color:#14171F;font-family:'Hanken Grotesk',system-ui,sans-serif;-webkit-font-smoothing:antialiased}a{color:#0E1B33;text-decoration-color:#C9A45C;text-underline-offset:4px}x-dc{display:none}#ssr-d{display:none}@media (min-width:900px){#ssr{display:none}#ssr-d{display:block}}</style>
</head>
<body>
<div id="ssr">
<div style="min-height: 100vh; background: rgb(255, 255, 255); overflow-x: clip;">

  <header style="position: sticky; top: 0px; z-index: 1000; padding: 18px 20px 6px; background: transparent; backdrop-filter: none; transform: none; pointer-events: none; transition: transform 0.25s, background 0.25s;">
    <div style="pointer-events: auto; max-width: 1240px; margin: 0px auto; min-height: 60px; padding: 0px 8px 0px 20px; display: flex; align-items: center; justify-content: space-between; gap: 16px; background: rgba(14, 27, 51, 0.9); backdrop-filter: blur(14px); border: 1px solid rgba(201, 164, 92, 0.24); border-radius: 999px; box-shadow: rgba(8, 18, 38, 0.6) 0px 14px 36px -20px;">
      <a href="/" style="text-decoration: none; display: block;"><img width="288" height="100" src="/assets/logo-gold-288x100.webp" alt="Nubia KC Estates Ltd" style="display: block; height: 38px; width: auto;"></a>
      
      <div style="display: flex; align-items: center; gap: 8px;">
          <a href="/contact/" style="display: inline-flex; align-items: center; min-height: 44px; background: var(--gold-500); color: var(--navy-950); padding: 0px 16px; font: 600 12px var(--font-sans); letter-spacing: 0.08em; text-transform: uppercase; text-decoration: none; border-radius: 999px; white-space: nowrap;">Partner</a>
          <button aria-label="Menu" style="width: 44px; height: 44px; display: grid; place-content: center; gap: 5px; background: rgba(255, 255, 255, 0.06); border: 1px solid rgba(199, 208, 223, 0.3); border-radius: 999px; cursor: pointer;">
            <span style="display: block; width: 18px; height: 1.5px; background: var(--ivory); transform: none; transition: transform 0.24s;"></span>
            <span style="display: block; width: 18px; height: 1.5px; background: var(--ivory); opacity: 1; transition: opacity 0.24s;"></span>
            <span style="display: block; width: 18px; height: 1.5px; background: var(--ivory); transform: none; transition: transform 0.24s;"></span>
          </button>
        </div>
    </div>
  </header>
  

  

  

  

  
<?= site_listing_section($a, false) ?>
  

  

  

  
  
  
  

  <footer id="site-footer" style="background: rgb(14, 27, 51); color: rgb(199, 208, 223); border-radius: 32px 32px 0px 0px; margin-top: 16px;">
    <div style="max-width: 1240px; margin: 0px auto; padding: 44px clamp(20px, 5vw, 32px) 0px; display: grid; grid-template-columns: 1fr; gap: 0px;">
      <div style="display: grid; gap: 18px; align-content: start; padding-bottom: 28px;">
        <img width="288" height="100" src="/assets/logo-gold-288x100.webp" alt="Nubia KC Estates Ltd" style="display: block; height: 60px; width: auto;">
        <p style="margin: 0px; max-width: 300px; font: 400 15px/1.6 var(--font-sans); color: rgb(199, 208, 223);">Property management for landlords in Kenya and the diaspora</p>
        
      </div>
      <div style="display: grid; align-content: start; gap: 0px; border-top: 1px solid rgba(199, 208, 223, 0.14);">
        <button aria-expanded="false" style="display: flex; justify-content: space-between; align-items: center; width: 100%; min-height: 52px; background: none; border: 0px; padding: 0px; cursor: pointer; font: 600 12px var(--font-sans); letter-spacing: 0.14em; color: rgb(226, 203, 151); text-transform: uppercase; text-align: left;"><span>Company</span><span aria-hidden="true" style="font: 300 22px/1 var(--font-sans); color: rgb(201, 164, 92);"><span class="sc-interp">+</span></span></button>
        
      </div>
      <div style="display: grid; align-content: start; gap: 0px; border-top: 1px solid rgba(199, 208, 223, 0.14);">
        <button aria-expanded="false" style="display: flex; justify-content: space-between; align-items: center; width: 100%; min-height: 52px; background: none; border: 0px; padding: 0px; cursor: pointer; font: 600 12px var(--font-sans); letter-spacing: 0.14em; color: rgb(226, 203, 151); text-transform: uppercase; text-align: left;"><span>Services</span><span aria-hidden="true" style="font: 300 22px/1 var(--font-sans); color: rgb(201, 164, 92);"><span class="sc-interp">+</span></span></button>
        
      </div>
      <div style="display: grid; align-content: start; gap: 0px; border-top: 1px solid rgba(199, 208, 223, 0.14); padding-top: 0px;">
        <span style="display: flex; align-items: center; min-height: 52px; font: 600 12px var(--font-sans); letter-spacing: 0.14em; color: rgb(226, 203, 151); text-transform: uppercase;">Contact</span>
        <div style="display: grid; gap: 2px;">
          <a href="https://www.google.com/maps/search/?api=1&amp;query=Crescent%20Business%20Centre%2C%20Parklands%2C%20Nairobi" target="_blank" rel="noopener" class="scp0" style="display: flex; align-items: center; min-height: 40px; font: 400 15px var(--font-sans); color: rgb(199, 208, 223); text-decoration: none;">Crescent Business Centre, Parklands, Nairobi</a>
          <a href="tel:+254703277912" class="scp0" style="display: flex; align-items: center; min-height: 40px; font: 400 15px var(--font-sans); color: rgb(199, 208, 223); text-decoration: none;">+254 703 277 912</a>
        </div>
      </div>
    </div>
    <div style="max-width: 1240px; margin: 20px auto 0px; padding: 0px clamp(20px, 5vw, 32px);">
      <div style="border-top: 1px solid rgba(251, 248, 242, 0.12); padding: 18px 0px 22px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 8px 24px;">
        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 4px 16px; font: 400 13px var(--font-sans); color: rgb(174, 185, 203);">
          <span>© 2026 Nubia KC Estates Ltd. All rights reserved.</span>
          <a href="/privacy/" class="scp0" style="display: inline-flex; align-items: center; min-height: 44px; color: rgb(174, 185, 203); text-decoration: none;">Privacy Policy</a>
          <a href="/terms/" class="scp0" style="display: inline-flex; align-items: center; min-height: 44px; color: rgb(174, 185, 203); text-decoration: none;">Terms</a>
        </div>
        <a class="scpf" href="https://system.nubiakcestates.com/" rel="nofollow" style="min-height: 44px; display: inline-flex; align-items: center; font: 400 12px var(--font-sans); color: rgb(127, 139, 160); text-decoration: none;">Staff login</a>
      </div>
    </div>
  </footer>

  

  </div>
</div>
<div id="ssr-d">
<div style="min-height: 100vh; background: rgb(255, 255, 255); overflow-x: clip;">

  <header style="position: sticky; top: 0px; z-index: 1000; padding: 16px 24px; background: transparent; backdrop-filter: none; transform: none; pointer-events: none; transition: transform 0.25s, background 0.25s;">
    <div style="pointer-events: auto; max-width: 1392px; margin: 0px auto; min-height: 64px; padding: 0px clamp(28px, 3.4vw, 48px); display: flex; align-items: center; justify-content: space-between; gap: 16px; background: rgba(14, 27, 51, 0.9); backdrop-filter: blur(14px); border: 1px solid rgba(201, 164, 92, 0.24); border-radius: 999px; box-shadow: rgba(8, 18, 38, 0.6) 0px 14px 36px -20px;">
      <a href="/" style="text-decoration: none; display: block;"><img width="288" height="100" src="/assets/logo-gold-288x100.webp" alt="Nubia KC Estates Ltd" style="display: block; height: 44px; width: auto;"></a>
      <nav style="display: flex; gap: 28px; align-items: center; flex-wrap: wrap;">
        <a href="/" class="scp0" style="font: 500 14px var(--font-sans); color: rgb(199, 208, 223); text-decoration: none; padding: 6px 0px; border-bottom: 1px solid transparent;">Home</a>
        <a href="/about/" class="scp0" style="font: 500 14px var(--font-sans); color: rgb(199, 208, 223); text-decoration: none; padding: 6px 0px; border-bottom: 1px solid transparent;">About us</a>
        <a href="/find-property/" class="scp0" style="font: 500 14px var(--font-sans); color: rgb(226, 203, 151); text-decoration: none; padding: 6px 0px; border-bottom: 1px solid rgb(201, 164, 92);">Find property</a>
        <a href="/blog/" class="scp0" style="font: 500 14px var(--font-sans); color: rgb(199, 208, 223); text-decoration: none; padding: 6px 0px; border-bottom: 1px solid transparent;">Blog</a>
        <a href="/contact/" class="scp0" style="font: 500 14px var(--font-sans); color: rgb(199, 208, 223); text-decoration: none; padding: 6px 0px; border-bottom: 1px solid transparent;">Contact</a>
        <a href="/contact/" class="scp1" style="display: inline-flex; align-items: center; min-height: 44px; background: var(--gold-500); color: var(--navy-950); padding: 0px 20px; font: 600 12px var(--font-sans); letter-spacing: 0.08em; text-transform: uppercase; text-decoration: none; border-radius: 999px; white-space: nowrap; flex-shrink: 0;">Partner with us</a>
      </nav>
      
    </div>
  </header>
<?= site_listing_section($a, true) ?>
  

  

  

  
  
  
  

  <footer id="site-footer" style="background: rgb(14, 27, 51); color: rgb(199, 208, 223); border-radius: 32px 32px 0px 0px; margin-top: 24px;">
    <div style="max-width: 1240px; margin: 0px auto; padding: clamp(56px, 6vw, 80px) clamp(20px, 5vw, 32px) 0px; display: grid; grid-template-columns: minmax(0px, 1.4fr) repeat(3, minmax(0px, 1fr)); gap: clamp(28px, 4vw, 56px);">
      <div style="display: grid; gap: 18px; align-content: start; padding-bottom: 0px;">
        <img width="288" height="100" src="/assets/logo-gold-288x100.webp" alt="Nubia KC Estates Ltd" style="display: block; height: 60px; width: auto;">
        <p style="margin: 0px; max-width: 300px; font: 400 15px/1.6 var(--font-sans); color: rgb(199, 208, 223);">Property management for landlords in Kenya and the diaspora</p>
        
      </div>
      <div style="display: grid; align-content: start; gap: 14px; border-top-width: medium; border-top-style: none; border-top-color: currentcolor;">
        <button aria-expanded="true" style="display: flex; justify-content: space-between; align-items: center; width: 100%; min-height: auto; background: none; border: 0px; padding: 0px; cursor: default; font: 600 12px var(--font-sans); letter-spacing: 0.14em; color: rgb(226, 203, 151); text-transform: uppercase; text-align: left;"><span>Company</span></button>
        <nav style="display: grid; padding-bottom: 0px;"><a href="/about/" class="scp0" style="display: flex; align-items: center; min-height: 36px; font: 400 15px var(--font-sans); color: rgb(199, 208, 223); text-decoration: none;">About Us</a><a href="/find-property/" class="scp0" style="display: flex; align-items: center; min-height: 36px; font: 400 15px var(--font-sans); color: rgb(199, 208, 223); text-decoration: none;">Find Property</a><a href="/blog/" class="scp0" style="display: flex; align-items: center; min-height: 36px; font: 400 15px var(--font-sans); color: rgb(199, 208, 223); text-decoration: none;">Blog</a><a href="/contact/" class="scp0" style="display: flex; align-items: center; min-height: 36px; font: 400 15px var(--font-sans); color: rgb(199, 208, 223); text-decoration: none;">Contact</a><a href="/careers/" class="scp0" style="display: flex; align-items: center; min-height: 36px; font: 400 15px var(--font-sans); color: rgb(199, 208, 223); text-decoration: none;">Careers</a></nav>
      </div>
      <div style="display: grid; align-content: start; gap: 14px; border-top-width: medium; border-top-style: none; border-top-color: currentcolor;">
        <button aria-expanded="true" style="display: flex; justify-content: space-between; align-items: center; width: 100%; min-height: auto; background: none; border: 0px; padding: 0px; cursor: default; font: 600 12px var(--font-sans); letter-spacing: 0.14em; color: rgb(226, 203, 151); text-transform: uppercase; text-align: left;"><span>Services</span></button>
        <nav style="display: grid; padding-bottom: 0px;"><a href="/#services" class="scp0" style="display: flex; align-items: center; min-height: 36px; font: 400 15px var(--font-sans); color: rgb(199, 208, 223); text-decoration: none;">Tenant Management</a><a href="/#services" class="scp0" style="display: flex; align-items: center; min-height: 36px; font: 400 15px var(--font-sans); color: rgb(199, 208, 223); text-decoration: none;">Financial Management</a><a href="/#services" class="scp0" style="display: flex; align-items: center; min-height: 36px; font: 400 15px var(--font-sans); color: rgb(199, 208, 223); text-decoration: none;">Property Maintenance</a><a href="/contact/" class="scp0" style="display: flex; align-items: center; min-height: 36px; font: 400 15px var(--font-sans); color: rgb(199, 208, 223); text-decoration: none;">Partner With Us</a></nav>
      </div>
      <div style="display: grid; align-content: start; gap: 14px; border-top-width: medium; border-top-style: none; border-top-color: currentcolor; padding-top: 0px;">
        <span style="display: flex; align-items: center; min-height: auto; font: 600 12px var(--font-sans); letter-spacing: 0.14em; color: rgb(226, 203, 151); text-transform: uppercase;">Contact</span>
        <div style="display: grid; gap: 2px;">
          <a href="https://www.google.com/maps/search/?api=1&amp;query=Crescent%20Business%20Centre%2C%20Parklands%2C%20Nairobi" target="_blank" rel="noopener" class="scp0" style="display: flex; align-items: center; min-height: 36px; font: 400 15px var(--font-sans); color: rgb(199, 208, 223); text-decoration: none;">Crescent Business Centre, Parklands, Nairobi</a>
          <a href="tel:+254703277912" class="scp0" style="display: flex; align-items: center; min-height: 36px; font: 400 15px var(--font-sans); color: rgb(199, 208, 223); text-decoration: none;">+254 703 277 912</a>
        </div>
      </div>
    </div>
    <div style="max-width: 1240px; margin: clamp(40px, 5vw, 64px) auto 0px; padding: 0px clamp(20px, 5vw, 32px);">
      <div style="border-top: 1px solid rgba(251, 248, 242, 0.12); padding: 18px 0px 22px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 8px 24px;">
        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 4px 16px; font: 400 13px var(--font-sans); color: rgb(174, 185, 203);">
          <span>© 2026 Nubia KC Estates Ltd. All rights reserved.</span>
          <a href="/privacy/" class="scp0" style="display: inline-flex; align-items: center; min-height: 44px; color: rgb(174, 185, 203); text-decoration: none;">Privacy Policy</a>
          <a href="/terms/" class="scp0" style="display: inline-flex; align-items: center; min-height: 44px; color: rgb(174, 185, 203); text-decoration: none;">Terms</a>
        </div>
        <a class="scpf" href="https://system.nubiakcestates.com/" rel="nofollow" style="min-height: 44px; display: inline-flex; align-items: center; font: 400 12px var(--font-sans); color: rgb(127, 139, 160); text-decoration: none;">Staff login</a>
      </div>
    </div>
  </footer>

  

  </div>
</div>
<script>window.NUBIA_LISTINGS = <?= site_json($all) ?>;</script>
<script>
(function(){
  function add(src){ var s=document.createElement('script'); s.src=src; s.async=false; document.body.appendChild(s); }
  fetch('/app.html').then(function(r){ if(!r.ok) throw 0; return r.text(); }).then(function(t){
    var d=new DOMParser().parseFromString(t,'text/html'), x=d.querySelector('x-dc'), sc=d.querySelector('script[data-dc-script]');
    document.body.appendChild(document.importNode(x,true));
    var s2=document.createElement('script'); for(var i=0;i<sc.attributes.length;i++) s2.setAttribute(sc.attributes[i].name, sc.attributes[i].value); s2.textContent=sc.textContent; document.body.appendChild(s2);
    add('/vendor/react-18.3.1.production.min.js'); add('/vendor/react-dom-18.3.1.production.min.js'); add('/support.js');
  }).catch(function(){});
})();
</script>
</body>
</html>
