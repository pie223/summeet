<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$errors = [];
$values = [
    'title' => '',
    'mountain_name' => '',
    'region' => '',
    'description' => '',
    'difficulty' => 'moderate',
    'hike_date' => '',
    'min_participants' => 3,
    'max_participants' => 4,
    'route_points' => '[]',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $default) {
        if (isset($_POST[$key])) $values[$key] = trim((string) $_POST[$key]);
    }
    $values['min_participants'] = (int) $values['min_participants'];
    $values['max_participants'] = (int) $values['max_participants'];

    if ($values['title'] === '') $errors[] = 'Give the hike a title.';
    if ($values['mountain_name'] === '') $errors[] = 'Which mountain / area is this on?';
    if (!in_array($values['difficulty'], ['easy', 'moderate', 'hard', 'extreme'], true)) $errors[] = 'Choose a valid difficulty.';
    $dateTs = strtotime($values['hike_date']);
    if (!$dateTs || $dateTs < strtotime('today')) $errors[] = 'Pick a valid, upcoming date.';
    if ($values['min_participants'] < 3) $errors[] = 'Minimum group size must be at least 3 (you + 2 other people).';
    if ($values['max_participants'] < $values['min_participants']) $errors[] = 'Max participants must be at least the minimum.';
    if ($values['max_participants'] > 30) $errors[] = 'Max participants seems too high — keep it realistic for a hike.';

    $routePoints = hike_route_points($values['route_points']);
    if (count($routePoints) < 2) {
        $errors[] = 'Draw the hike route on the map — click at least two points along the trail.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare("INSERT INTO hikes
            (creator_id, title, mountain_name, region, description, difficulty, hike_date, min_participants, max_participants, route_points)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user['id'], $values['title'], $values['mountain_name'], $values['region'],
            $values['description'], $values['difficulty'], date('Y-m-d', $dateTs),
            $values['min_participants'], $values['max_participants'], json_encode($routePoints),
        ]);
        $newId = (int) $pdo->lastInsertId();
        flash_set('Your hike is live. People can now ask to join.', 'success');
        redirect('hike.php?id=' . $newId);
    }
}

$pageTitle = 'Suggest a hike';
$needsMap = true;   // the route drawing map
require __DIR__ . '/includes/header.php';
?>

<section class="card card-narrow">
    <h1>Suggest a hike</h1>
    <p class="muted">Set the group size for the vibe you want — a tight 3-4, or a bigger group hike.</p>

    <?php if ($errors): ?>
        <div class="flash flash-error">
            <ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form method="post" class="form">
        <div class="form-group">
            <label for="title">Hike title</label>
            <input type="text" id="title" name="title" value="<?= h($values['title']) ?>" placeholder="e.g. Sunrise ridge walk" required>
        </div>

        <div class="form-row two-col">
            <div class="form-group">
                <label for="mountain_name">Mountain / trail</label>
                <input type="text" id="mountain_name" name="mountain_name" value="<?= h($values['mountain_name']) ?>" placeholder="e.g. Gran Paradiso" required>
            </div>
            <div class="form-group">
                <label for="region">Region (optional)</label>
                <input type="text" id="region" name="region" value="<?= h($values['region']) ?>" placeholder="e.g. Aosta Valley, IT">
            </div>
        </div>

        <div class="form-row two-col">
            <div class="form-group">
                <label for="hike_date">Date</label>
                <input type="date" id="hike_date" name="hike_date" value="<?= h($values['hike_date']) ?>" min="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label for="difficulty">Difficulty</label>
                <select id="difficulty" name="difficulty">
                    <?php foreach (['easy' => 'Easy', 'moderate' => 'Moderate', 'hard' => 'Hard', 'extreme' => 'Extreme'] as $val => $label): ?>
                        <option value="<?= $val ?>" <?= $values['difficulty'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row two-col">
            <div class="form-group">
                <label for="min_participants">Minimum group size</label>
                <input type="number" id="min_participants" name="min_participants" min="3" max="30" value="<?= h((string) $values['min_participants']) ?>" required>
                <p class="field-hint">Includes you. The minimum is 3 (you + 2 others).</p>
            </div>
            <div class="form-group">
                <label for="max_participants">Maximum group size</label>
                <input type="number" id="max_participants" name="max_participants" min="3" max="30" value="<?= h((string) $values['max_participants']) ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="5" placeholder="Pace, gear needed, meeting point, what kind of people you're hoping join…"><?= h($values['description']) ?></textarea>
        </div>

        <div class="form-group">
            <label>Draw the route</label>
            <p class="field-hint">Click on the map to drop points along the trail, in order — start to finish. This exact route is what people will see on the hike page.</p>
            <div id="route-map" class="map-box"></div>
            <div class="map-controls">
                <button type="button" id="undo-point" class="btn btn-ghost btn-sm">Undo last point</button>
                <button type="button" id="clear-route" class="btn btn-ghost btn-sm">Clear route</button>
                <span id="route-point-count" class="muted small"></span>
            </div>
            <input type="hidden" name="route_points" id="route_points_input" value="<?= h($values['route_points']) ?>">
        </div>

        <button type="submit" class="btn btn-primary btn-lg">Publish hike</button>
    </form>
</section>

<script>
(function () {
    var cssv = getComputedStyle(document.documentElement);
    function css(n) { return cssv.getPropertyValue(n).trim(); }
    var mapEl = document.getElementById('route-map');
    if (!summeetMapReady(mapEl)) return;

    var input = document.getElementById('route_points_input');
    var countLabel = document.getElementById('route-point-count');
    var points = [];
    try {
        var initial = JSON.parse(input.value || '[]');
        if (Array.isArray(initial)) points = initial;
    } catch (e) { points = []; }

    var map = L.map(mapEl).setView([45.8, 7.5], 8);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    var line = L.polyline([], { color: css('--map-line'), weight: 4 }).addTo(map);
    var markers = [];

    function redraw() {
        markers.forEach(function (m) { map.removeLayer(m); });
        markers = [];
        var latlngs = points.map(function (p) { return [p[0], p[1]]; });
        line.setLatLngs(latlngs);
        points.forEach(function (p, i) {
            var isEndpoint = (i === 0 || i === points.length - 1);
            var marker = L.circleMarker([p[0], p[1]], {
                radius: isEndpoint ? 7 : 5,
                color: css('--map-outline'),
                fillColor: i === 0 ? css('--map-start') : (isEndpoint ? css('--map-end') : css('--map-mid')),
                fillOpacity: 1,
                weight: 2
            }).addTo(map);
            markers.push(marker);
        });
        input.value = JSON.stringify(points);
        countLabel.textContent = points.length === 0
            ? 'No points yet — click the map to start.'
            : points.length + ' point' + (points.length === 1 ? '' : 's') + ' (green = start, red = end)';
    }

    map.on('click', function (e) {
        points.push([e.latlng.lat, e.latlng.lng]);
        redraw();
    });

    document.getElementById('undo-point').addEventListener('click', function () {
        points.pop();
        redraw();
    });
    document.getElementById('clear-route').addEventListener('click', function () {
        points = [];
        redraw();
    });

    // If re-showing the form after a validation error, restore the drawn route and view.
    redraw();
    if (points.length) {
        map.fitBounds(line.getBounds(), { padding: [30, 30] });
    }
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>