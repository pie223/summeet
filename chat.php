<?php
require_once __DIR__ . '/includes/bootstrap.php';

/**
 * Group chat for a hike.
 *   chat.php            -> list of the chats you belong to
 *   chat.php?id=HIKE    -> the group chat of that hike
 *   chat.php?id=HIKE&after=N  -> (used by the page's JavaScript) JSON with messages newer than N
 *
 * Members of a hike's chat = the organizer + everyone whose request was approved.
 */
$user = require_login();
$userId = (int) $user['id'];
$hikeId = (int) ($_GET['id'] ?? 0);

// ---------- Chat list ----------
if ($hikeId === 0) {
    $stmt = $pdo->prepare("SELECT h.id, h.title, h.mountain_name, h.hike_date,
            (SELECT COUNT(*) FROM hike_requests r WHERE r.hike_id = h.id AND r.status = 'approved') + 1 AS members,
            (SELECT m.body FROM hike_messages m WHERE m.hike_id = h.id ORDER BY m.id DESC LIMIT 1) AS last_body,
            (SELECT COUNT(*) FROM hike_messages m2
                LEFT JOIN hike_chat_reads cr ON cr.hike_id = m2.hike_id AND cr.user_id = ?
                WHERE m2.hike_id = h.id AND m2.user_id <> ? AND m2.id > COALESCE(cr.last_read_id, 0)) AS unread
        FROM hikes h
        WHERE h.creator_id = ?
           OR EXISTS (SELECT 1 FROM hike_requests r WHERE r.hike_id = h.id AND r.user_id = ? AND r.status = 'approved')
        ORDER BY h.hike_date ASC");
    $stmt->execute([$userId, $userId, $userId, $userId]);
    $chats = $stmt->fetchAll();

    $pageTitle = 'Chats';
    require __DIR__ . '/includes/header.php';
    ?>
    <h1>Group chats</h1>
    <section class="card">
        <?php if (!$chats): ?>
            <p class="muted">No group chats yet. Once you organize a hike, or you're approved on someone else's hike, its group chat shows up here.</p>
            <p><a class="btn btn-primary" href="hikes.php">Browse hikes</a></p>
        <?php else: ?>
            <ul class="chat-list">
                <?php foreach ($chats as $c): ?>
                    <li>
                        <a href="chat.php?id=<?= (int) $c['id'] ?>">
                            <span class="chat-list-title"><?= h($c['title']) ?></span><?php if ((int) $c['unread'] > 0): ?><span class="nav-badge chat-unread" title="Unread messages"><?= (int) $c['unread'] > 99 ? '99+' : (int) $c['unread'] ?></span><?php endif; ?>
                            <span class="muted small"> · <?= h($c['mountain_name']) ?> · <?= h(date('j M', strtotime($c['hike_date']))) ?> · <?= (int) $c['members'] ?> members</span><br>
                            <span class="muted small"><?= $c['last_body'] !== null ? h(mb_strimwidth($c['last_body'], 0, 90, '…')) : 'No messages yet' ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

// ---------- One hike's chat: check membership ----------
$stmt = $pdo->prepare('SELECT * FROM hikes WHERE id = ?');
$stmt->execute([$hikeId]);
$hike = $stmt->fetch();

$isMember = false;
if ($hike) {
    if ((int) $hike['creator_id'] === $userId) {
        $isMember = true;
    } else {
        $stmt = $pdo->prepare("SELECT 1 FROM hike_requests WHERE hike_id = ? AND user_id = ? AND status = 'approved'");
        $stmt->execute([$hikeId, $userId]);
        $isMember = (bool) $stmt->fetchColumn();
    }
}

$wantsJson = isset($_GET['after']) || (($_POST['ajax'] ?? '') === '1');

if (!$hike || !$isMember) {
    if ($wantsJson) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Not a member of this chat']);
        exit;
    }
    http_response_code(403);
    $pageTitle = 'Group chat';
    require __DIR__ . '/includes/header.php';
    echo '<div class="empty-state"><p>This chat is only for the hike\'s organizer and approved members.</p><a class="btn btn-primary" href="hikes.php">Back to hikes</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// ---------- Leave the chat ----------
// Organizer: same as "Delete hike" (the hike and its chat are removed for everyone).
// Participant: same as "Withdraw / can't make it" (you leave the hike and its chat).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'leave_chat') {
    if ((int) $hike['creator_id'] === $userId) {
        $pdo->prepare('DELETE FROM hikes WHERE id = ?')->execute([$hikeId]);
        flash_set('Hike deleted.', 'info');
    } else {
        $pdo->prepare("UPDATE hike_requests SET status='withdrawn' WHERE hike_id = ? AND user_id = ?")->execute([$hikeId, $userId]);
        flash_set('You left the chat and the hike.', 'info');
    }
    redirect('chat.php');
}

// ---------- Post a message ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = trim((string) ($_POST['body'] ?? ''));
    if ($body !== '' && mb_strlen($body) <= 1000) {
        $stmt = $pdo->prepare('INSERT INTO hike_messages (hike_id, user_id, body) VALUES (?, ?, ?)');
        $stmt->execute([$hikeId, $userId, $body]);
    }
    if ($wantsJson) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }
    redirect('chat.php?id=' . $hikeId);
}

// ---------- Messages ----------
$formatMessage = function (array $m) use ($userId): array {
    return [
        'id'   => (int) $m['id'],
        'name' => $m['display_name'],
        'mine' => (int) $m['user_id'] === $userId,
        'body' => $m['body'],
        'time' => date('j M H:i', strtotime($m['created_at'])),
    ];
};

if (isset($_GET['after'])) {
    $stmt = $pdo->prepare('SELECT m.id, m.user_id, m.body, m.created_at, u.display_name
        FROM hike_messages m JOIN users u ON u.id = m.user_id
        WHERE m.hike_id = ? AND m.id > ? ORDER BY m.id ASC LIMIT 200');
    $stmt->execute([$hikeId, (int) $_GET['after']]);
    $newMessages = array_map($formatMessage, $stmt->fetchAll());
    if ($newMessages) {
        mark_chat_read($pdo, $hikeId, $userId, $newMessages[count($newMessages) - 1]['id']);
    }
    header('Content-Type: application/json');
    echo json_encode([
        'ok' => true, 'messages' => $newMessages,
        // navbar numbers ride along, so this page needs no extra requests for them
        'requests' => pending_request_count($pdo, $userId),
        'chats' => unread_chat_count($pdo, $userId, $hikeId),
    ]);
    exit;
}

// Initial render: the latest 200 messages, oldest first.
$stmt = $pdo->prepare('SELECT m.id, m.user_id, m.body, m.created_at, u.display_name
    FROM hike_messages m JOIN users u ON u.id = m.user_id
    WHERE m.hike_id = ? ORDER BY m.id DESC LIMIT 200');
$stmt->execute([$hikeId]);
$messages = array_map($formatMessage, array_reverse($stmt->fetchAll()));
$lastId = $messages ? $messages[count($messages) - 1]['id'] : 0;
mark_chat_read($pdo, $hikeId, $userId, $lastId);   // opening the chat = everything read

// Members: organizer first, then approved participants.
$stmt = $pdo->prepare("SELECT u.display_name, 1 AS is_organizer FROM users u WHERE u.id = ?
    UNION ALL
    SELECT u.display_name, 0 FROM hike_requests r JOIN users u ON u.id = r.user_id
    WHERE r.hike_id = ? AND r.status = 'approved'");
$stmt->execute([(int) $hike['creator_id'], $hikeId]);
$members = $stmt->fetchAll();

$pageTitle = 'Chat · ' . $hike['title'];
require __DIR__ . '/includes/header.php';
?>

<div class="chat-head">
    <a href="chat.php" class="small">← All chats</a>
    <h1><?= h($hike['title']) ?></h1>
    <p class="muted small">⛰️ <?= h($hike['mountain_name']) ?> · <?= h(date('D, j M Y', strtotime($hike['hike_date']))) ?> ·
        <a href="hike.php?id=<?= $hikeId ?>">View hike</a></p>
</div>

<div class="chat-layout">
    <section class="card">
        <div id="chat-log" class="chat-log" data-last="<?= (int) $lastId ?>">
            <?php if (!$messages): ?>
                <p class="chat-empty" id="chat-empty">No messages yet. Say hi 👋</p>
            <?php endif; ?>
            <?php foreach ($messages as $m): ?>
                <div class="chat-msg<?= $m['mine'] ? ' mine' : '' ?>">
                    <div class="chat-meta"><?= $m['mine'] ? 'You' : h($m['name']) ?> · <?= h($m['time']) ?></div>
                    <div class="chat-bubble"><?= h($m['body']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <form id="chat-form" method="post" action="chat.php?id=<?= $hikeId ?>" class="chat-form">
            <input type="text" name="body" maxlength="1000" placeholder="Write a message…" autocomplete="off" required>
            <button type="submit" class="btn btn-primary">Send</button>
        </form>
    </section>

    <aside class="card">
        <h2>Members (<?= count($members) ?>)</h2>
        <ul class="chat-members">
            <?php foreach ($members as $mem): ?>
                <li><?= h($mem['display_name']) ?><?= (int) $mem['is_organizer'] === 1 ? ' <span class="tag">organizer</span>' : '' ?></li>
            <?php endforeach; ?>
        </ul>
        <p class="muted small">Everyone approved on this hike is in the chat.</p>
        <form method="post" action="chat.php?id=<?= $hikeId ?>"
              onsubmit="return confirm('<?= (int) $hike['creator_id'] === $userId ? 'Leave the chat? As the organizer this deletes the hike for everyone and cannot be undone.' : 'Leave the chat? You will also withdraw from this hike.' ?>');">
            <input type="hidden" name="action" value="leave_chat">
            <button type="submit" class="btn btn-danger btn-sm">Leave chat</button>
        </form>
    </aside>
</div>

<script>
(function () {
    var log = document.getElementById('chat-log');
    var form = document.getElementById('chat-form');
    var input = form.querySelector('input[name="body"]');
    var url = 'chat.php?id=<?= $hikeId ?>';
    var lastId = parseInt(log.dataset.last, 10) || 0;

    function scrollDown() { log.scrollTop = log.scrollHeight; }

    function addMessage(m) {
        var empty = document.getElementById('chat-empty');
        if (empty) empty.remove();
        var wrap = document.createElement('div');
        wrap.className = 'chat-msg' + (m.mine ? ' mine' : '');
        var meta = document.createElement('div');
        meta.className = 'chat-meta';
        meta.textContent = (m.mine ? 'You' : m.name) + ' · ' + m.time;
        var bubble = document.createElement('div');
        bubble.className = 'chat-bubble';
        bubble.textContent = m.body;          // textContent: messages can never inject HTML
        wrap.appendChild(meta);
        wrap.appendChild(bubble);
        log.appendChild(wrap);
        lastId = Math.max(lastId, m.id);
    }

    // Live updates: gentle on battery and server. Pauses while the tab is hidden,
    // slows down while nobody writes, and speeds up again on activity.
    var BASE = 4000, MAX = 20000, delay = BASE, timer = null, busy = false;
    window.summeetOwnPolling = true;        // this page refreshes the navbar numbers itself
    function schedule() {
        clearTimeout(timer);
        if (!document.hidden) timer = setTimeout(poll, delay);
    }
    function poll() {
        if (busy) return;
        busy = true;
        clearTimeout(timer);
        fetch(url + '&after=' + lastId, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) return;
                if (window.summeetBadges) window.summeetBadges(data);
                if (data.messages && data.messages.length) {
                    var atBottom = log.scrollHeight - log.scrollTop - log.clientHeight < 60;
                                        data.messages.forEach(addMessage);
                    if (atBottom) scrollDown();
                    delay = BASE;
                } else {
                    delay = Math.min(Math.round(delay * 1.5), MAX);
                }
            })
            .catch(function () { delay = Math.min(delay * 2, MAX); })
            .then(function () { busy = false; schedule(); });
    }
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) { clearTimeout(timer); }
        else { delay = BASE; poll(); }
    });

    form.addEventListener('submit', function (e) {
        e.stopImmediatePropagation();         // skip the generic "Please wait…" button handler
        e.preventDefault();
        var text = input.value.trim();
        if (!text) return;
        var fd = new FormData(form);
        fd.append('ajax', '1');
        input.value = '';
        fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function () { delay = BASE; poll(); setTimeout(scrollDown, 150); })
            .catch(function () { input.value = text; });
    });

    scrollDown();
    schedule();
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>