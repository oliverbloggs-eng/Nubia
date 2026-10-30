<?php
// Page shell: sidebar, top bar, flash messages, footer.

function icon(string $name): string
{
    $p = [
        'dashboard' => '<path d="M3 13h8V3H3zM13 21h8V11h-8zM3 21h8v-6H3zM13 3v6h8V3z"/>',
        'building'  => '<path d="M4 21V5l8-3v19M12 21V9l8 3v9M2 21h20M7 8h2M7 12h2M7 16h2M15 14h2M15 17h2"/>',
        'door'      => '<path d="M5 21V3h11v18M3 21h18M13 12h.01M16 5h3v16"/>',
        'users'     => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.6-3.6 3.3-5.5 6.5-5.5s5.9 1.9 6.5 5.5M16 4.6a3.5 3.5 0 0 1 0 6.8M18 14.8c2 .8 3.2 2.5 3.5 5.2"/>',
        'invoice'   => '<path d="M6 2h9l4 4v16H6zM14 2v5h5M9 12h7M9 16h7M9 8h3"/>',
        'cash'      => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 10v4M18 10v4"/>',
        'report'    => '<path d="M4 20V4M4 20h16M8 16v-4M12 16V8M16 16v-6"/>',
        'owner'     => '<path d="M3 11l9-7 9 7M5 10v10h14V10M10 20v-5h4v5"/>',
        'alert'     => '<path d="M12 3l10 18H2zM12 10v5M12 18h.01"/>',
        'wrench'    => '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z"/>',
        'settings'  => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'shield'    => '<path d="M12 2l8 3v6c0 5-3.5 9-8 11-4.5-2-8-6-8-11V5z"/>',
        'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'menu'      => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'plus'      => '<path d="M12 5v14M5 12h14"/>',
        'print'     => '<path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v8H6z"/>',
        'download'  => '<path d="M12 3v12M7 10l5 5 5-5M5 21h14"/>',
        'whatsapp'  => '<path d="M3 21l1.6-4.8A8.5 8.5 0 1 1 8 19.5z"/><path d="M9 9.5c0 3 2.5 5.5 5.5 5.5l1-1.5-2-1-1 1c-1-.5-2-1.5-2.5-2.5l1-1-1-2z"/>',
        'arrow'     => '<path d="M5 12h14M13 6l6 6-6 6"/>',
    ][$name] ?? '';
    return '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

function page_start(string $title, string $active = ''): void
{
    $u = user();
    $nav = [
        'Manage' => [
            ['index.php', 'dashboard', 'Dashboard', 'dashboard'],
            ['properties.php', 'properties', 'Properties', 'building'],
            ['units.php', 'units', 'Units', 'door'],
            ['clients.php', 'clients', 'Clients', 'users'],
        ],
        'Money' => [
            ['billing.php', 'billing', 'Monthly billing', 'invoice'],
            ['payments.php', 'payments', 'Payments', 'cash'],
            ['arrears.php', 'arrears', 'Arrears', 'alert'],
            ['statement.php', 'statement', 'Master statement', 'report'],
            ['owner_statement.php', 'owner', 'Owner statements', 'owner'],
        ],
        'Operations' => [
            ['maintenance.php', 'maintenance', 'Maintenance', 'wrench'],
        ],
    ];
    if (is_admin()) {
        $nav['Admin'] = [
            ['settings.php', 'settings', 'Company settings', 'settings'],
            ['users.php', 'users', 'Staff accounts', 'shield'],
        ];
    }
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · Nubia KC Estates</title>
<link rel="icon" href="assets/mark.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/app.css?v=1">
</head>
<body>
<div class="shell">
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="index.php">
      <img src="assets/mark.svg" alt="" width="34" height="34">
      <span><strong>Nubia KC</strong><small>Estates Ltd</small></span>
    </a>
    <nav>
      <?php foreach ($nav as $group => $items): ?>
        <div class="nav-group"><?= e($group) ?></div>
        <?php foreach ($items as [$href, $key, $label, $ic]): ?>
          <a href="<?= $href ?>" class="<?= $active === $key ? 'active' : '' ?>"><?= icon($ic) ?><span><?= e($label) ?></span></a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-foot">
      <a class="who" href="account.php" style="text-decoration:none"><strong><?= e($u['name'] ?? '') ?></strong><small><?= e(ucfirst($u['role'] ?? '')) ?> · My account</small></a>
      <form method="post" action="logout.php"><?= csrf_field() ?><button class="signout" type="submit"><?= icon('logout') ?>Sign out</button></form>
    </div>
  </aside>
  <div class="scrim" id="scrim"></div>
  <main class="main">
    <header class="topbar">
      <button class="menu-btn" id="menuBtn" aria-label="Open menu"><?= icon('menu') ?></button>
      <h1><?= e($title) ?></h1>
      <span class="today"><?= date('D, d M Y') ?></span>
    </header>
    <div class="content">
      <?php foreach (take_flash() as [$type, $msg]): ?>
        <div class="alert alert-<?= e($type) ?>"><?= e($msg) ?></div>
      <?php endforeach; ?>
<?php
}

function page_end(): void
{
    ?>
    </div>
    <footer class="foot">© <?= date('Y') ?> Nubia KC Estates Ltd · Management System</footer>
  </main>
</div>
<script src="assets/app.js?v=1"></script>
</body>
</html>
<?php
}

/** Header row for a page section: title, optional subtitle, optional action buttons (raw HTML). */
function section_head(string $title, string $sub = '', string $actions = ''): void
{
    echo '<div class="section-head"><div><h2>' . e($title) . '</h2>' . ($sub ? '<p>' . $sub . '</p>' : '') . '</div>';
    if ($actions) echo '<div class="actions">' . $actions . '</div>';
    echo '</div>';
}

function badge(string $text): string
{
    $map = [
        'Available' => 'green', 'Occupied' => 'navy', 'Sold' => 'gold', 'Dormant' => 'grey',
        'Active' => 'green', 'Tenant' => 'navy', 'Owner' => 'gold', 'Buyer' => 'blue',
        'Open' => 'red', 'In progress' => 'gold', 'Done' => 'green',
        'Urgent' => 'red', 'Normal' => 'grey', 'Low' => 'grey', 'Rent' => 'navy', 'Sale' => 'gold',
    ];
    return '<span class="badge badge-' . ($map[$text] ?? 'grey') . '">' . e($text) . '</span>';
}

function options(array $list, $selected, bool $assoc = false): string
{
    $out = '';
    foreach ($list as $k => $v) {
        $value = $assoc ? $k : $v;
        $out .= '<option value="' . e($value) . '"' . ((string)$value === (string)$selected ? ' selected' : '') . '>' . e($v) . '</option>';
    }
    return $out;
}

function period_options(string $selected, int $back = 18, int $ahead = 2): string
{
    $out = '';
    for ($i = $ahead; $i >= -$back; $i--) {
        $p = date('Y-m', strtotime("first day of $i month"));
        $out .= '<option value="' . $p . '"' . ($p === $selected ? ' selected' : '') . '>' . period_label($p) . '</option>';
    }
    return $out;
}
