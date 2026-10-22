<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_login();

// Incoming: requests to hikes I created
$incoming = $pdo->prepare("SELECT r.*, h.title, h.mountain_name, h.hike_date, h.max_participants,
        u.display_name, u.username, u.age, u.gender, u.intent, u.political_orientation, u.bio
    FROM hike_requests r
    JOIN hikes h ON h.id = r.hike_id
    JOIN users u ON u.id = r.user_id
    WHERE h.creator_id = ? AND r.status = 'pending'
    ORDER BY r.created_at ASC");
$incoming->execute([$user['id']]);
$incoming = $incoming->fetchAll();

// Outgoing: my requests to other people's hikes
$outgoing = $pdo->prepare("SELECT r.*, h.title, h.mountain_name, h.hike_date, u.display_name AS organizer_name
    FROM hike_requests r
    JOIN hikes h ON h.id = r.hike_id
    JOIN users u ON u.id = h.creator_id
    WHERE r.user_id = ? AND r.status != 'withdrawn'
    ORDER BY r.created_at DESC");
$outgoing->execute([$user['id']]);
$outgoing = $outgoing->fetchAll();

$pageTitle = 'Requests';
require __DIR__ . '/includes/header.php';
?>

<h1>Requests</h1>

<section class="card">
    <h2>Waiting on your approval (<?= count($incoming) ?>)</h2>
    <?php if (!$incoming): ?>
        <p class="muted small">Nothing pending. When someone asks to join one of your hikes, it'll show up here.</p>
    <?php else: ?>
        <ul class="request-list">
            <?php foreach ($incoming as $r): ?>
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
                    <p class="muted small">wants to join <a href="hike.php?id=<?= (int) $r['hike_id'] ?>"><strong><?= h($r['title']) ?></strong></a> on <?= h(date('j M', strtotime($r['hike_date']))) ?></p>
                    <?php if ($r['message']): ?><p class="request-message">“<?= h($r['message']) ?>”</p><?php endif; ?>
                    <div class="request-actions">
                        <form method="post" action="hike.php?id=<?= (int) $r['hike_id'] ?>">
                            <input type="hidden" name="action" value="approve"><input type="hidden" name="request_id" value="<?= (int) $r['id'] ?>">
                            <button type="submit" class="btn btn-primary btn-sm">Approve</button>
                        </form>
                        <form method="post" action="hike.php?id=<?= (int) $r['hike_id'] ?>">
                            <input type="hidden" name="action" value="reject"><input type="hidden" name="request_id" value="<?= (int) $r['id'] ?>">
                            <button type="submit" class="btn btn-ghost btn-sm">Decline</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Your requests to other hikes</h2>
    <?php if (!$outgoing): ?>
        <p class="muted small">You haven't asked to join any hikes yet. <a href="hikes.php">Browse hikes →</a></p>
    <?php else: ?>
        <ul class="mini-list">
            <?php foreach ($outgoing as $r): ?>
                <li>
                    <a href="hike.php?id=<?= (int) $r['hike_id'] ?>"><?= h($r['title']) ?></a>
                    <span class="muted small"><?= h($r['mountain_name']) ?> · with <?= h($r['organizer_name']) ?> · <?= h(date('j M', strtotime($r['hike_date']))) ?></span>
                    <span class="status-pill status-<?= h($r['status']) ?>"><?= h(ucfirst($r['status'])) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
