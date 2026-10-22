<?php
require_once __DIR__ . '/includes/bootstrap.php';

/**
 * Tiny JSON endpoint used by the navbar to refresh its notification numbers:
 *   counts.php?open=HIKE_ID  ->  {"ok":true,"requests":N,"chats":M}
 * "open" is the chat the visitor is looking at right now (its messages aren't counted).
 */
header('Content-Type: application/json');

$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}

$userId = (int) $user['id'];
echo json_encode([
    'ok'       => true,
    'requests' => pending_request_count($pdo, $userId),
    'chats'    => unread_chat_count($pdo, $userId, (int) ($_GET['open'] ?? 0)),
]);
