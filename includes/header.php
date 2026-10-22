<?php
/** Expects $pageTitle to optionally be set before include. */
$user = current_user();
$pageTitle = $pageTitle ?? 'Summeet';
$flash = flash_get();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, interactive-widget=resizes-content">
<title><?= h($pageTitle) ?> · Summeet</title>
<link rel="stylesheet" href="assets/css/style.css?v=<?= (int) @filemtime(__DIR__ . '/../assets/css/style.css') ?>">

<?php if (!empty($needsMap)): ?>
<!-- Maps: Leaflet + OpenStreetMap tiles (no API key required).
     Loaded from unpkg first; if that CDN is blocked/unreachable, we fall
     back to jsDelivr automatically so maps still work on more networks. -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    if (typeof L === 'undefined') {
        document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">');
        document.write('<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"><' + '/script>');
    }
</script>
<?php endif; ?>
<script>
    // Shared guard used by every page that embeds a map: shows a clear
    // message instead of a silent blank box if Leaflet never loaded
    // (e.g. no internet access on the visitor's browser, or both CDNs blocked).
    function summeetMapReady(el) {
        if (typeof L === 'undefined') {
            el.innerHTML = '<div class="map-fallback">🗺️ Map couldn\'t load. '
                + 'This needs your browser to reach openstreetmap.org and unpkg.com/jsdelivr.net '
                + '— check your internet connection or any ad-blocker/firewall, then reload.</div>';
            return false;
        }
        return true;
    }
</script>
</head>
<body>
<header class="topbar" id="topbar">
    <div class="topbar-inner">
        <a class="brand" href="index.php">
            <span class="brand-mark">▲</span> Summeet
        </a>
        <?php if ($user): ?>
        <?php
        $openChatId   = basename($_SERVER['PHP_SELF']) === 'chat.php' ? (int) ($_GET['id'] ?? 0) : 0;
        $pendingCount = pending_request_count($pdo, (int) $user['id']);
        $unreadChats  = unread_chat_count($pdo, (int) $user['id'], $openChatId);
        ?>
        <button type="button" class="nav-toggle" id="nav-toggle" aria-label="Menu" aria-expanded="false">☰</button>
        <nav class="mainnav mainnav-menu">
            <a href="hikes.php" class="<?= basename($_SERVER['PHP_SELF']) === 'hikes.php' ? 'active' : '' ?>">Hikes</a>
            <a href="requests.php" class="<?= basename($_SERVER['PHP_SELF']) === 'requests.php' ? 'active' : '' ?>">Requests<span class="nav-badge" id="nav-badge-requests" title="Pending requests"<?= $pendingCount > 0 ? '' : ' hidden' ?>><?= $pendingCount > 99 ? '99+' : $pendingCount ?></span></a>
            <a href="chat.php" class="<?= basename($_SERVER['PHP_SELF']) === 'chat.php' ? 'active' : '' ?>">Chats<span class="nav-badge nav-badge-top" id="nav-badge-chats" title="New messages"<?= $unreadChats > 0 ? '' : ' hidden' ?>><?= $unreadChats > 99 ? '99+' : $unreadChats ?></span></a>
            <a href="community.php" class="<?= basename($_SERVER['PHP_SELF']) === 'community.php' ? 'active' : '' ?>">Community</a>
            <a href="feedback.php" class="<?= basename($_SERVER['PHP_SELF']) === 'feedback.php' ? 'active' : '' ?>">Feedback</a>
            <a href="download.php" class="<?= basename($_SERVER['PHP_SELF']) === 'download.php' ? 'active' : '' ?>">Download</a>
        </nav>
        <div class="navuser">
            <a href="profile.php" class="navuser-name<?= basename($_SERVER['PHP_SELF']) === 'profile.php' ? ' active' : '' ?>" title="Your profile"><?= h($user['display_name']) ?></a>
            <a href="logout.php" class="btn btn-ghost btn-sm">Log out</a>
        </div>
        <?php else: ?>
        <nav class="mainnav">
            <a href="download.php" class="<?= basename($_SERVER['PHP_SELF']) === 'download.php' ? 'active' : '' ?>">Download</a>
            <a href="login.php">Log in</a>
            <a href="register.php" class="btn btn-primary btn-sm">Sign up</a>
        </nav>
        <?php endif; ?>
    </div>
</header>
<?php if ($user): ?>
<script>
// Phone menu: the ☰ button opens and closes the navbar links.
(function () {
    var bar = document.getElementById('topbar'), btn = document.getElementById('nav-toggle');
    if (!bar || !btn) return;
    btn.addEventListener('click', function () {
        var open = bar.classList.toggle('open');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        btn.textContent = open ? '✕' : '☰';
    });
})();

// Keeps the navbar numbers fresh without reloading the page.
// Gentle on battery and server: it pauses while the tab is hidden, slows down while
// nothing changes, and stays quiet on pages (chats) that refresh the numbers themselves.
(function () {
    var req = document.getElementById('nav-badge-requests');
    var chat = document.getElementById('nav-badge-chats');
    if (!req || !chat) return;
    var openChat = <?= (int) $openChatId ?>;
    var BASE = 10000, MAX = 60000, delay = BASE, last = '', timer = null;
    function show(el, n) {
        if (n > 0) { el.textContent = n > 99 ? '99+' : n; el.hidden = false; }
        else { el.hidden = true; }
    }
    // Pages that poll on their own (group chats, Community) call this with their JSON.
    window.summeetBadges = function (d) {
        if (!d) return;
        if (typeof d.requests === 'number') show(req, d.requests);
        if (typeof d.chats === 'number') show(chat, d.chats);
    };
    function schedule() {
        clearTimeout(timer);
        if (!document.hidden) timer = setTimeout(tick, delay);
    }
    function tick() {
        if (window.summeetOwnPolling || document.hidden) { schedule(); return; }
        fetch('counts.php?open=' + openChat, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || d.ok !== true) return;
                var sig = d.requests + ',' + d.chats;
                delay = sig === last ? Math.min(Math.round(delay * 1.5), MAX) : BASE;
                last = sig;
                window.summeetBadges(d);
            })
            .catch(function () { delay = Math.min(delay * 2, MAX); })
            .then(schedule);
    }
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) { clearTimeout(timer); }
        else { delay = BASE; tick(); }
    });
    schedule();
})();
</script>
<?php endif; ?>
<main class="wrap">
    <?php if ($flash): ?>
        <div class="flash flash-<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
    <?php endif; ?>