<?php
/**
 * Host prefers PHP for /. Serve the static brochure homepage.
 * No-store headers help Hostinger CDN stop serving a stale WordPress home.
 */
$path = __DIR__ . DIRECTORY_SEPARATOR . 'index.html';
if (!is_readable($path)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'index.html missing';
    exit;
}
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('CDN-Cache-Control: no-store');
header('Platform-Cache: no-cache');
readfile($path);
