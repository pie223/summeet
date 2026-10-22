<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

// ---------- CSRF protection ----------
// Every POST must carry the secret token of the visitor's session, so another website
// cannot make a logged-in visitor's browser submit forms to Summeet.
// The token field is added to every <form method="post"> automatically (see below).
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sentToken = (string) ($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf'], $sentToken)) {
        http_response_code(400);
        if (($_POST['ajax'] ?? '') === '1') {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Session expired, reload the page.']);
        } else {
            header('Content-Type: text/html; charset=utf-8');
            echo 'Your session expired or the form was not valid. Please go back, reload the page and try again.';
        }
        exit;
    }
}
$csrfToken = $_SESSION['csrf'];
ob_start(function (string $html) use ($csrfToken): string {
    $field = '<input type="hidden" name="csrf" value="' . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '">';
    return preg_replace('/(<form\b[^>]*\bmethod\s*=\s*["\']post["\'][^>]*>)/i', '$1' . $field, $html) ?? $html;
});
