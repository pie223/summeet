<?php
// Test page: shows what the site sees. Open it in the app and in a browser, then delete this file.
require_once __DIR__ . '/includes/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');
echo "In the app:        " . (in_app() ? 'yes' : 'no') . "\n";
echo "App version:       " . (app_version() ?: '(not announced)') . "\n";
$r = latest_release();
echo "Latest on GitHub:  " . ($r ? $r['version'] : '(could not be read)') . "\n";
echo "\nBrowser identification:\n" . ($_SERVER['HTTP_USER_AGENT'] ?? '') . "\n";
