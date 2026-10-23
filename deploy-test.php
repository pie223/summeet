<?php
// Temporary test: shows whether the automatic upload from GitHub reached the server.
// DELETE this file when you are done testing.
$version = 1;     // <- change this number, push to GitHub, then reload this page
header('Content-Type: text/plain; charset=utf-8');
echo "Deploy test, version: {$version}\n";
echo "This file was last written on the server at: " . date('Y-m-d H:i:s', filemtime(__FILE__)) . " (server time)\n";
echo "Server time now: " . date('Y-m-d H:i:s') . "\n";
