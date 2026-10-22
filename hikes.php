<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_login();

// View toggle: list (default) or map. Preserved through filter changes.
$view = ($_GET['view'] ?? 'list') === 'map' ? 'map' : 'list';
$needsMap = $view === 'map';          // only the map view needs the map library
$perPage = 12;                        // the list grows by this many each time "Show more" is used
$page = max(1, min(50, (int) ($_GET['page'] ?? 1)));
$limit = $view === 'map' ? 300 : $perPage * $page;

$difficulty = $_GET['difficulty'] ?? '';
$search = trim($_GET['q'] ?? '');

$sql = "SELECT h.*, u.display_name, u.username FROM hikes h
        JOIN users u ON u.id = h.creator_id
        WHERE h.hike_date >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
$params = [];

if ($difficulty !== '' && in_array($difficulty, ['easy', 'moderate', 'hard', 'extreme'], true)) {
    $sql .= ' AND h.difficulty = ?';
    $params[] = $difficulty;
}
if ($search !== '') {
    $sql .= ' AND (h.title LIKE ? OR h.mountain_name LIKE ? OR h.region LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
$sql .= ' ORDER BY h.hike_date ASC LIMIT ' . ($limit + 1);   // one extra row tells us if there are more

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$hikes = $stmt->fetchAll();
$hasMore = count($hikes) > $limit;
if ($hasMore) array_pop($hikes);

// Approved participants for all shown hikes in ONE query (instead of one query per hike).
$approvedByHike = [];
if ($hikes) {
    $ids = array_map(fn($x) => (int) $x['id'], $hikes);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $q = $pdo->prepare("SELECT hike_id, COUNT(*) AS c FROM hike_requests WHERE status = 'approved' AND hike_id IN ($in) GROUP BY hike_id");
    $q->execute($ids);
    foreach ($q->fetchAll() as $row) $approvedByHike[(int) $row['hike_id']] = (int) $row['c'];
}
$spotsFor = function (array $h) use ($approvedByHike): array {
    $approved = $approvedByHike[(int) $h['id']] ?? 0;
    $max = (int) $h['max_participants'];
    return ['approved' => $approved, 'remaining' => max(0, $max - $approved), 'full' => $approved >= $max];
};

$queryWithout = function (array $overrides) {
    return http_build_query(array_merge($_GET, $overrides));
};

// Data for the map view: one entry per hike that actually has a drawn route,
// using its first point as the marker/popup anchor and the full route for the line.
$hikesForMap = [];
foreach ($hikes as $h) {
    $pts = hike_route_points($h['route_points']);
    if (count($pts) < 1) continue;
    $spots = $spotsFor($h);
    $hikesForMap[] = [
        'id' => (int) $h['id'],
        'title' => $h['title'],
        'mountain' => $h['mountain_name'],
        'difficulty' => difficulty_label($h['difficulty']),
        'date' => date('D, j M', strtotime($h['hike_date'])),
        'organizer' => $h['display_name'],
        'spots' => $spots['full'] ? 'Full' : $spots['remaining'] . ' spot' . ($spots['remaining'] === 1 ? '' : 's') . ' left',
        'points' => $pts,
        'url' => 'hike.php?id=' . (int) $h['id'],
    ];
}

$pageTitle = 'Hikes';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1>Upcoming hikes</h1>
    <a href="create_hike.php" class="btn btn-primary">+ Suggest a hike</a>
</div>

<form method="get" class="filters">
    <input type="text" name="q" placeholder="Search mountain, region, title…" value="<?= h($search) ?>">
    <select name="difficulty">
        <option value="">Any difficulty</option>
        <?php foreach (['easy' => 'Easy', 'moderate' => 'Moderate', 'hard' => 'Hard', 'extreme' => 'Extreme'] as $val => $label): ?>
            <option value="<?= $val ?>" <?= $difficulty === $val ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
    </select>
    <input type="hidden" name="view" value="<?= h($view) ?>">
    <button type="submit" class="btn btn-ghost">Filter</button>
</form>

<div class="view-toggle" role="tablist">
    <a href="?<?= h($queryWithout(['view' => 'list'])) ?>" class="view-toggle-btn <?= $view === 'list' ? 'active' : '' ?>">📋 Description</a>
    <a href="?<?= h($queryWithout(['view' => 'map'])) ?>" class="view-toggle-btn <?= $view === 'map' ? 'active' : '' ?>">🗺️ Map</a>
</div>

<?php if (!$hikes): ?>
    <div class="empty-state">
        <p>No hikes match yet. Be the first to <a href="create_hike.php">suggest one</a>.</p>
    </div>
<?php elseif ($view === 'map'): ?>
    <?php if (!$hikesForMap): ?>
        <div class="empty-state"><p>None of the matching hikes have a drawn route yet.</p></div>
    <?php else: ?>
        <div id="hikes-overview-map" class="map-box map-box-lg" data-hikes='<?= h(json_encode($hikesForMap)) ?>'></div>
        <p class="muted small">Click a route to see hike details. Green = start, red = end.</p>
    <?php endif; ?>
<?php else: ?>
<div class="hike-grid">
    <?php foreach ($hikes as $h): $spots = $spotsFor($h); ?>
        <a class="hike-card" href="hike.php?id=<?= (int) $h['id'] ?>">
            <div class="hike-card-top">
                <span class="badge badge-<?= h($h['difficulty']) ?>"><?= h(difficulty_label($h['difficulty'])) ?></span>
                <span class="hike-date"><?= h(date('D, j M', strtotime($h['hike_date']))) ?></span>
            </div>
            <h3><?= h($h['title']) ?></h3>
            <p class="hike-mountain">⛰️ <?= h($h['mountain_name']) ?><?= $h['region'] ? ' · ' . h($h['region']) : '' ?></p>
            <p class="hike-desc"><?= h(mb_strimwidth($h['description'], 0, 120, '…')) ?></p>
            <div class="hike-card-bottom">
                <span class="hike-organizer"><?= h($h['display_name']) ?></span>
                <span class="hike-spots <?= $spots['full'] ? 'spots-full' : '' ?>">
                    <?= $spots['full'] ? 'Full' : $spots['remaining'] . ' spot' . ($spots['remaining'] === 1 ? '' : 's') . ' left' ?>
                </span>
            </div>
        </a>
    <?php endforeach; ?>
</div>
<?php if ($hasMore): ?>
    <p style="text-align:center; margin-top:1.2rem;">
        <a class="btn btn-ghost" href="?<?= h($queryWithout(['page' => $page + 1])) ?>">Show more hikes</a>
    </p>
<?php endif; ?>
<?php endif; ?>

<?php if ($view === 'map' && $hikesForMap): ?>
<script>
(function () {
    var cssv = getComputedStyle(document.documentElement);
    function css(n) { return cssv.getPropertyValue(n).trim(); }
    var el = document.getElementById('hikes-overview-map');
    if (!el || !summeetMapReady(el)) return;
    var hikes = JSON.parse(el.dataset.hikes);
    var map = L.map(el).setView([45.8, 9.5], 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    var allBounds = [];
    hikes.forEach(function (hike) {
        var pts = hike.points;
        allBounds = allBounds.concat(pts);
        var popupHtml = '<strong>' + hike.title + '</strong><br>' +
            '⛰️ ' + hike.mountain + '<br>' +
            hike.difficulty + ' · ' + hike.date + '<br>' +
            hike.organizer + ' · ' + hike.spots + '<br>' +
            '<a href="' + hike.url + '">View hike →</a>';

        if (pts.length >= 2) {
            var line = L.polyline(pts, { color: css('--map-line'), weight: 4 }).addTo(map);
            line.bindPopup(popupHtml);
        }
        L.circleMarker(pts[0], {
            radius: 7, color: css('--map-outline'), fillColor: css('--map-start'), fillOpacity: 1, weight: 2
        }).addTo(map).bindPopup(popupHtml);

        if (pts.length >= 2) {
            L.circleMarker(pts[pts.length - 1], {
                radius: 7, color: css('--map-outline'), fillColor: css('--map-end'), fillOpacity: 1, weight: 2
            }).addTo(map).bindPopup(popupHtml);
        }
    });

    if (allBounds.length) {
        map.fitBounds(allBounds, { padding: [30, 30] });
    }
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>