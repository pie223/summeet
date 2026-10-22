<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$hikeId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT h.*, u.display_name, u.username, u.bio AS creator_bio,
        u.age AS creator_age, u.gender AS creator_gender, u.intent AS creator_intent,
        u.political_orientation AS creator_orientation
    FROM hikes h JOIN users u ON u.id = h.creator_id WHERE h.id = ?");
$stmt->execute([$hikeId]);
$hike = $stmt->fetch();

if (!$hike) {
    http_response_code(404);
    $pageTitle = 'Hike not found';
    require __DIR__ . '/includes/header.php';
    echo '<div class="empty-state"><p>That hike doesn\'t exist (any more).</p><a class="btn btn-primary" href="hikes.php">Back to hikes</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$isOwner = $hike['creator_id'] === $user['id'];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'request_join' && !$isOwner) {
        $existing = user_request_for_hike($pdo, $hikeId, $user['id']);
        if (!$existing) {
            $msg = trim($_POST['message'] ?? '');
            $stmt = $pdo->prepare("INSERT INTO hike_requests (hike_id, user_id, status, message) VALUES (?, ?, 'pending', ?)");
            $stmt->execute([$hikeId, $user['id'], $msg]);
            flash_set('Request sent! ' . $hike['display_name'] . ' will review it.', 'success');
        } elseif ($existing['status'] === 'withdrawn' || $existing['status'] === 'rejected') {
            $stmt = $pdo->prepare("UPDATE hike_requests SET status='pending', message=?, created_at=NOW() WHERE id = ?");
            $stmt->execute([trim($_POST['message'] ?? ''), $existing['id']]);
            flash_set('Request sent again.', 'success');
        }
        redirect('hike.php?id=' . $hikeId);
    }

    if ($action === 'withdraw_request' && !$isOwner) {
        $stmt = $pdo->prepare("UPDATE hike_requests SET status='withdrawn' WHERE hike_id = ? AND user_id = ?");
        $stmt->execute([$hikeId, $user['id']]);
        flash_set('Request withdrawn.', 'info');
        redirect('hike.php?id=' . $hikeId);
    }

    if (($action === 'approve' || $action === 'reject') && $isOwner) {
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $spots = hike_spots($pdo, $hikeId, (int) $hike['max_participants']);
        if ($action === 'approve' && $spots['full']) {
            flash_set('That hike is already full.', 'error');
        } else {
            $status = $action === 'approve' ? 'approved' : 'rejected';
            $stmt = $pdo->prepare("UPDATE hike_requests SET status = ? WHERE id = ? AND hike_id = ?");
            $stmt->execute([$status, $requestId, $hikeId]);
            flash_set($action === 'approve' ? 'Request approved. See you on the trail!' : 'Request declined.', 'info');
        }
        redirect('hike.php?id=' . $hikeId);
    }

    if ($action === 'delete_hike' && $isOwner) {
        $stmt = $pdo->prepare('DELETE FROM hikes WHERE id = ?');
        $stmt->execute([$hikeId]);
        flash_set('Hike deleted.', 'info');
        redirect('hikes.php');
    }
}

$spots = hike_spots($pdo, $hikeId, (int) $hike['max_participants']);
$myRequest = $isOwner ? null : user_request_for_hike($pdo, $hikeId, $user['id']);

$requests = [];
if ($isOwner) {
    $stmt = $pdo->prepare("SELECT r.*, u.display_name, u.username, u.age, u.gender, u.intent, u.political_orientation, u.bio
        FROM hike_requests r JOIN users u ON u.id = r.user_id
        WHERE r.hike_id = ? AND r.status != 'withdrawn' ORDER BY
        CASE r.status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END, r.created_at ASC");
    $stmt->execute([$hikeId]);
    $requests = $stmt->fetchAll();
}

$pageTitle = $hike['title'];
$needsMap = count(hike_route_points($hike['route_points'])) >= 2;   // map library only if there is a route to show
require __DIR__ . '/includes/header.php';
?>

<div class="hike-detail">
    <div class="hike-detail-main card">
        <div class="hike-card-top">
            <span class="badge badge-<?= h($hike['difficulty']) ?>"><?= h(difficulty_label($hike['difficulty'])) ?></span>
            <span class="hike-date"><?= h(date('l, j F Y', strtotime($hike['hike_date']))) ?></span>
        </div>
        <h1><?= h($hike['title']) ?></h1>
        <p class="hike-mountain">⛰️ <?= h($hike['mountain_name']) ?><?= $hike['region'] ? ' · ' . h($hike['region']) : '' ?></p>
        <p class="hike-full-desc"><?= nl2br(h($hike['description'])) ?></p>

        <?php $routePoints = hike_route_points($hike['route_points']); ?>
        <div class="form-group">
            <label>Route</label>
            <?php if (count($routePoints) >= 2): ?>
                <div id="hike-route-map" class="map-box" data-points='<?= h(json_encode($routePoints)) ?>'></div>
            <?php else: ?>
                <p class="muted small">The organizer hasn't drawn a route for this hike yet.</p>
            <?php endif; ?>
        </div>

        <div class="hike-meta-row">
            <div class="hike-meta-item">
                <strong><?= (int) $hike['min_participants'] ?>–<?= (int) $hike['max_participants'] ?></strong>
                <span>group size</span>
            </div>
            <div class="hike-meta-item">
                <strong><?= $spots['approved'] ?>/<?= (int) $hike['max_participants'] ?></strong>
                <span>joined</span>
            </div>
            <div class="hike-meta-item">
                <strong><?= $spots['full'] ? 'Full' : $spots['remaining'] ?></strong>
                <span><?= $spots['full'] ? '' : 'spots left' ?></span>
            </div>
        </div>

        <?php if (!$isOwner): ?>
            <div class="join-box">
                <?php if (!$myRequest || in_array($myRequest['status'], ['withdrawn', 'rejected'], true)): ?>
                    <?php if ($spots['full']): ?>
                        <p class="muted">This hike is full, but you could ask anyway in case a spot opens up.</p>
                    <?php endif; ?>
                    <form method="post" class="form">
                        <input type="hidden" name="action" value="request_join">
                        <div class="form-group">
                            <label for="message">Say something to the organizer (optional)</label>
                            <textarea id="message" name="message" rows="3" placeholder="e.g. I've done similar routes before, happy to bring snacks!"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg">🙋 Ask to enter this hike</button>
                    </form>
                    <?php if ($myRequest && $myRequest['status'] === 'rejected'): ?>
                        <p class="muted small">Your previous request was declined — you can try again.</p>
                    <?php endif; ?>
                <?php elseif ($myRequest['status'] === 'pending'): ?>
                    <p class="status-line status-pending">⏳ Your request is pending approval from <?= h($hike['display_name']) ?>.</p>
                    <form method="post"><input type="hidden" name="action" value="withdraw_request">
                        <button type="submit" class="btn btn-ghost btn-sm">Withdraw request</button>
                    </form>
                <?php elseif ($myRequest['status'] === 'approved'): ?>
                    <p class="status-line status-approved">✅ You're in! See you on the trail.</p>
                    <p><a class="btn btn-primary btn-sm" href="chat.php?id=<?= (int) $hikeId ?>">💬 Open group chat</a></p>
                    <form method="post"><input type="hidden" name="action" value="withdraw_request">
                        <button type="submit" class="btn btn-ghost btn-sm">Withdraw / can't make it</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="join-box">
                <p class="muted">This is your hike. Manage requests below.</p>
                <p><a class="btn btn-primary btn-sm" href="chat.php?id=<?= (int) $hikeId ?>">💬 Open group chat</a></p>
                <form method="post" onsubmit="return confirm('Delete this hike? This cannot be undone.');">
                    <input type="hidden" name="action" value="delete_hike">
                    <button type="submit" class="btn btn-danger btn-sm">Delete hike</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <aside class="hike-detail-side">
        <div class="card">
            <h2>Organized by</h2>
            <a class="mini-profile" href="#">
                <span>
                    <strong><?= h($hike['display_name']) ?></strong><br>
                    <span class="muted small">@<?= h($hike['username']) ?> · <?= (int) $hike['creator_age'] ?> · <?= h(ucfirst($hike['creator_gender'])) ?></span>
                </span>
            </a>
            <p class="tag-row">
                <span class="tag"><?= h(intent_label($hike['creator_intent'])) ?></span>
                <span class="tag tag-political"><?= h(political_label((int) $hike['creator_orientation'])) ?></span>
            </p>
            <?php if ($hike['creator_bio']): ?><p class="muted small"><?= h($hike['creator_bio']) ?></p><?php endif; ?>
        </div>

        <?php if ($isOwner): ?>
        <div class="card">
            <h2>Requests to join (<?= count($requests) ?>)</h2>
            <?php if (!$requests): ?>
                <p class="muted small">No one has asked to join yet.</p>
            <?php else: ?>
                <ul class="request-list">
                    <?php foreach ($requests as $r): ?>
                        <li class="request-item">
                            <div class="mini-profile">
                                <span>
                                    <strong><?= h($r['display_name']) ?></strong>
                                    <span class="muted small">· <?= (int) $r['age'] ?> · <?= h(ucfirst($r['gender'])) ?></span><br>
                                    <span class="tag-row">
                                        <span class="tag"><?= h(intent_label($r['intent'])) ?></span>
                                        <span class="tag tag-political"><?= h(political_label((int) $r['political_orientation'])) ?></span>
                                    </span>
                                </span>
                            </div>
                            <?php if ($r['message']): ?><p class="request-message">“<?= h($r['message']) ?>”</p><?php endif; ?>
                            <?php if ($r['bio']): ?><p class="muted small"><?= h($r['bio']) ?></p><?php endif; ?>

                            <?php if ($r['status'] === 'pending'): ?>
                                <div class="request-actions">
                                    <form method="post"><input type="hidden" name="action" value="approve"><input type="hidden" name="request_id" value="<?= (int) $r['id'] ?>">
                                        <button type="submit" class="btn btn-primary btn-sm">Approve</button>
                                    </form>
                                    <form method="post"><input type="hidden" name="action" value="reject"><input type="hidden" name="request_id" value="<?= (int) $r['id'] ?>">
                                        <button type="submit" class="btn btn-ghost btn-sm">Decline</button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <span class="status-pill status-<?= h($r['status']) ?>"><?= h(ucfirst($r['status'])) ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </aside>
</div>

<?php if (count($routePoints) >= 2): ?>
<script>
(function () {
    var cssv = getComputedStyle(document.documentElement);
    function css(n) { return cssv.getPropertyValue(n).trim(); }
    var el = document.getElementById('hike-route-map');
    if (!el || !summeetMapReady(el)) return;
    var points = JSON.parse(el.dataset.points);
    var map = L.map(el, { scrollWheelZoom: false }).setView(points[0], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    var line = L.polyline(points, { color: css('--map-line'), weight: 4 }).addTo(map);
    points.forEach(function (p, i) {
        var isEndpoint = (i === 0 || i === points.length - 1);
        L.circleMarker(p, {
            radius: isEndpoint ? 7 : 5,
            color: css('--map-outline'),
            fillColor: i === 0 ? css('--map-start') : (isEndpoint ? css('--map-end') : css('--map-mid')),
            fillOpacity: 1,
            weight: 2
        }).addTo(map);
    });
    map.fitBounds(line.getBounds(), { padding: [30, 30] });
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>