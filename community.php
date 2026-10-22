<?php
require_once __DIR__ . '/includes/bootstrap.php';

/**
 * Community: one big chat that every person with an account can read and write in.
 *   community.php               -> the page (latest messages)
 *   community.php?after=N       -> JSON, messages newer than N (live updates)
 *   community.php?before=N      -> JSON, the next older batch (endless scrolling up)
 *   POST (ajax=1)               -> post a message
 */
$user = require_login();
$userId = (int) $user['id'];
$PAGE = 30;

$format = function (array $m) use ($userId): array {
    return [
        'id'   => (int) $m['id'],
        'name' => $m['display_name'],
        'mine' => (int) $m['user_id'] === $userId,
        'body' => $m['body'],
        'time' => date('j M H:i', strtotime($m['created_at'])),
    ];
};
$select = 'SELECT m.id, m.user_id, m.body, m.created_at, u.display_name
           FROM community_messages m JOIN users u ON u.id = m.user_id';

// ---------- Post ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = trim((string) ($_POST['body'] ?? ''));
    if ($body !== '' && mb_strlen($body) <= 1000) {
        $pdo->prepare('INSERT INTO community_messages (user_id, body) VALUES (?, ?)')->execute([$userId, $body]);
    }
    if (($_POST['ajax'] ?? '') === '1') {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }
    redirect('community.php');
}

// ---------- JSON: newer messages ----------
if (isset($_GET['after'])) {
    $stmt = $pdo->prepare($select . ' WHERE m.id > ? ORDER BY m.id ASC LIMIT 200');
    $stmt->execute([(int) $_GET['after']]);
    header('Content-Type: application/json');
    echo json_encode([
        'ok' => true, 'messages' => array_map($format, $stmt->fetchAll()),
        'requests' => pending_request_count($pdo, $userId),
        'chats' => unread_chat_count($pdo, $userId, 0),
    ]);
    exit;
}

// ---------- JSON: older messages ----------
if (isset($_GET['before'])) {
    $stmt = $pdo->prepare($select . ' WHERE m.id < ? ORDER BY m.id DESC LIMIT ' . ($PAGE + 1));
    $stmt->execute([(int) $_GET['before']]);
    $rows = $stmt->fetchAll();
    $more = count($rows) > $PAGE;
    $rows = array_reverse(array_slice($rows, 0, $PAGE));
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'messages' => array_map($format, $rows), 'more' => $more]);
    exit;
}

// ---------- Page: latest messages ----------
$stmt = $pdo->query($select . ' ORDER BY m.id DESC LIMIT ' . ($PAGE + 1));
$rows = $stmt->fetchAll();
$more = count($rows) > $PAGE;
$messages = array_map($format, array_reverse(array_slice($rows, 0, $PAGE)));
$firstId = $messages ? $messages[0]['id'] : 0;
$lastId  = $messages ? $messages[count($messages) - 1]['id'] : 0;

$pageTitle = 'Community';
require __DIR__ . '/includes/header.php';
?>

<div class="chat-head">
    <h1>Community</h1>
    <p class="muted">The chat where everyone on Summeet has access.</p>
</div>

<section class="card">
    <div id="chat-log" class="chat-log chat-log-lg"
         data-first="<?= (int) $firstId ?>" data-last="<?= (int) $lastId ?>" data-more="<?= $more ? '1' : '0' ?>">
        <p class="chat-empty" id="chat-empty"<?= $messages ? ' hidden' : '' ?>>Nobody has written yet. Say hi 👋</p>
        <?php foreach ($messages as $m): ?>
            <div class="chat-msg<?= $m['mine'] ? ' mine' : '' ?>">
                <div class="chat-meta"><?= $m['mine'] ? 'You' : h($m['name']) ?> · <?= h($m['time']) ?></div>
                <div class="chat-bubble"><?= h($m['body']) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    <form id="chat-form" method="post" action="community.php" class="chat-form">
        <input type="text" name="body" maxlength="1000" placeholder="Write to the community…" autocomplete="off" required>
        <button type="submit" class="btn btn-primary">Send</button>
    </form>
</section>

<script>
(function () {
    var log = document.getElementById('chat-log');
    var form = document.getElementById('chat-form');
    var input = form.querySelector('input[name="body"]');
    var empty = document.getElementById('chat-empty');
    var firstId = parseInt(log.dataset.first, 10) || 0;
    var lastId = parseInt(log.dataset.last, 10) || 0;
    var hasMore = log.dataset.more === '1';
    var loadingOlder = false;

    function scrollDown() { log.scrollTop = log.scrollHeight; }

    function build(m) {
        var wrap = document.createElement('div');
        wrap.className = 'chat-msg' + (m.mine ? ' mine' : '');
        var meta = document.createElement('div');
        meta.className = 'chat-meta';
        meta.textContent = (m.mine ? 'You' : m.name) + ' · ' + m.time;
        var bubble = document.createElement('div');
        bubble.className = 'chat-bubble';
        bubble.textContent = m.body;            // textContent: messages can never inject HTML
        wrap.appendChild(meta);
        wrap.appendChild(bubble);
        return wrap;
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
        fetch('community.php?after=' + lastId, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) return;
                if (window.summeetBadges) window.summeetBadges(data);
                if (data.messages && data.messages.length) {
                    var atBottom = log.scrollHeight - log.scrollTop - log.clientHeight < 60;
                    if (empty) empty.hidden = true;
                    data.messages.forEach(function (m) {
                        log.appendChild(build(m));
                        lastId = Math.max(lastId, m.id);
                        if (!firstId) firstId = m.id;
                    });
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

    // Older messages: endless scrolling upwards
    function loadOlder() {
        if (loadingOlder || !hasMore || !firstId) return;
        loadingOlder = true;
        fetch('community.php?before=' + firstId, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok) return;
                var prevHeight = log.scrollHeight;
                var anchor = empty ? empty.nextSibling : log.firstChild;   // older messages go above the first one
                d.messages.forEach(function (m) {
                    log.insertBefore(build(m), anchor);
                });
                if (d.messages.length) firstId = d.messages[0].id;
                hasMore = !!d.more;
                log.scrollTop = log.scrollHeight - prevHeight + log.scrollTop;   // keep the view where it was
            })
            .catch(function () { /* try again on the next scroll */ })
            .then(function () { loadingOlder = false; });
    }
    log.addEventListener('scroll', function () { if (log.scrollTop < 60) loadOlder(); });

    form.addEventListener('submit', function (e) {
        e.stopImmediatePropagation();           // skip the generic "Please wait…" button handler
        e.preventDefault();
        var text = input.value.trim();
        if (!text) return;
        var fd = new FormData(form);
        fd.append('ajax', '1');
        input.value = '';
        fetch('community.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function () { delay = BASE; poll(); setTimeout(scrollDown, 150); })
            .catch(function () { input.value = text; });
    });

    scrollDown();
    schedule();
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>