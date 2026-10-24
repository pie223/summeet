<?php
/**
 * Summeet - shared helpers
 */

function h(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function current_user(): ?array
{
    global $pdo;
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) {
        return null;
    }
    $cached = $user;
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        header('Location: login.php');
        exit;
    }
    return $user;
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

/**
 * Maps a political_orientation integer (-100 left .. 100 right) to a label.
 * Kept intentionally coarse - this is a slider people set for themselves,
 * not a quiz result, so broad self-described bands make more sense than
 * a precise score.
 */
function political_label(int $value): string
{
    if ($value <= -60) return 'Radical left';
    if ($value <= -20) return 'Left';
    if ($value <  20)  return 'Centrist';
    if ($value <  60)  return 'Right';
    return 'Alt-right';
}

function intent_label(string $intent): string
{
    return $intent === 'dating' ? 'Open to dating' : 'Open to meeting only';
}

function difficulty_label(string $difficulty): string
{
    $map = [
        'easy'     => 'Easy',
        'moderate' => 'Moderate',
        'hard'     => 'Hard',
        'extreme'  => 'Extreme',
    ];
    return $map[$difficulty] ?? ucfirst($difficulty);
}

function time_ago(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return floor($diff / 86400) . 'd ago';
}

/**
 * Safely decodes a hike's stored route into an array of [lat, lng] pairs.
 * Returns [] if nothing was drawn or the stored JSON is malformed.
 */
function hike_route_points($rawJson): array
{
    $points = json_decode((string) $rawJson, true);
    if (!is_array($points)) {
        return [];
    }
    $clean = [];
    foreach ($points as $p) {
        if (is_array($p) && count($p) === 2 && is_numeric($p[0]) && is_numeric($p[1])) {
            $clean[] = [(float) $p[0], (float) $p[1]];
        }
    }
    return $clean;
}

/**
 * Number of pending join requests waiting on hikes that this user organizes.
 * Shown as a small badge on the "Requests" link in the navbar.
 */
function pending_request_count(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM hike_requests r
        JOIN hikes h ON h.id = r.hike_id
        WHERE h.creator_id = ? AND r.status = 'pending'");
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Unread group-chat messages (written by other people) across every chat this user
 * belongs to. $excludeHikeId lets the page skip the chat that is currently open.
 */
function unread_chat_count(PDO $pdo, int $userId, int $excludeHikeId = 0): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM hike_messages m
        JOIN hikes h ON h.id = m.hike_id
        LEFT JOIN hike_chat_reads cr ON cr.hike_id = m.hike_id AND cr.user_id = ?
        WHERE m.user_id <> ?
          AND m.id > COALESCE(cr.last_read_id, 0)
          AND m.hike_id <> ?
          AND (h.creator_id = ?
               OR EXISTS (SELECT 1 FROM hike_requests r
                          WHERE r.hike_id = h.id AND r.user_id = ? AND r.status = 'approved'))");
    $stmt->execute([$userId, $userId, $excludeHikeId, $userId, $userId]);
    return (int) $stmt->fetchColumn();
}

/** Remember that this user has read the chat of $hikeId up to message $lastId. */
function mark_chat_read(PDO $pdo, int $hikeId, int $userId, int $lastId): void
{
    if ($lastId <= 0) {
        return;
    }
    $stmt = $pdo->prepare("INSERT INTO hike_chat_reads (hike_id, user_id, last_read_id) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE last_read_id = GREATEST(last_read_id, ?)");
    $stmt->execute([$hikeId, $userId, $lastId, $lastId]);
}

function hike_spots(PDO $pdo, int $hikeId, int $max): array
{
    $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM hike_requests WHERE hike_id = ? AND status = 'approved'");
    $stmt->execute([$hikeId]);
    $approved = (int) $stmt->fetch()['c'] + 1;   // +1: the organizer is always the first participant
    return [
        'approved' => $approved,
        'remaining' => max(0, $max - $approved),
        'full' => $approved >= $max,
    ];
}

function user_request_for_hike(PDO $pdo, int $hikeId, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM hike_requests WHERE hike_id = ? AND user_id = ?');
    $stmt->execute([$hikeId, $userId]);
    $r = $stmt->fetch();
    return $r ?: null;
}

function flash_set(string $message, string $type = 'info'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function flash_get(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

/**
 * True when the site is being shown inside the Summeet Android app (so the "Download" links,
 * which make no sense there, can be hidden). It recognises:
 *  - Android WebView (its browser identification contains "; wv)"),
 *  - an app that adds the word "SummeetApp" to its browser identification,
 *  - an app whose start address ends with ?app=1 (remembered for the rest of the visit).
 */
function in_app(): bool
{
    if (isset($_GET['app'])) {
        $_SESSION['in_app'] = $_GET['app'] === '1';
    }
    if (!empty($_SESSION['in_app'])) {
        return true;
    }
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return stripos($ua, '; wv)') !== false || stripos($ua, 'SummeetApp') !== false;
}

/** The app version the Android app announces in its browser identification ("SummeetApp/1.0.1"), or '' if unknown. */
function app_version(): string
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return preg_match('~SummeetApp/([0-9]+(?:\.[0-9]+)*)~', $ua, $m) ? $m[1] : '';
}

/**
 * The newest release published on GitHub: ['tag' => '1.0.1', 'version' => '1.0.1'] or null if it can't be read.
 * The answer is kept in a small file for a few hours, so pages never wait for GitHub.
 */
function latest_release(): ?array
{
    $repo  = 'pie223/summeet';
    $cache = sys_get_temp_dir() . '/summeet_latest_release.json';
    $saved = is_file($cache) ? json_decode((string) @file_get_contents($cache), true) : null;
    if (is_array($saved) && isset($saved['time']) && $saved['time'] > time() - (empty($saved['tag']) ? 600 : 6 * 3600)) {
        return empty($saved['tag']) ? null : ['tag' => $saved['tag'], 'version' => ltrim($saved['tag'], 'vV')];
    }
    $tag = '';
    $url = 'https://api.github.com/repos/' . $repo . '/releases/latest';
    $ctx = stream_context_create(['http' => ['timeout' => 3, 'header' => "User-Agent: Summeet\r\nAccept: application/vnd.github+json\r\n"]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false && function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 3, CURLOPT_USERAGENT => 'Summeet']);
        $raw = curl_exec($ch);
        curl_close($ch);
    }
    $data = $raw ? json_decode($raw, true) : null;
    if (is_array($data) && !empty($data['tag_name'])) {
        $tag = (string) $data['tag_name'];
    }
    @file_put_contents($cache, json_encode(['time' => time(), 'tag' => $tag]));
    return $tag === '' ? null : ['tag' => $tag, 'version' => ltrim($tag, 'vV')];
}