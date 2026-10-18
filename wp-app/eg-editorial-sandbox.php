<?php
/**
 * Editorial sandbox. Login required. Not linked from the public site.
 * URL: /app/eg-editorial-sandbox.php
 */
$wp_load = __DIR__ . '/wp-load.php';
if (!is_readable($wp_load)) {
    header('Content-Type: text/plain; charset=utf-8', true, 500);
    echo 'wp-load missing';
    exit;
}
require_once $wp_load;

header('X-Robots-Tag: noindex, nofollow, noarchive', true);

if (!function_exists('eg_editorial_sandbox_render')) {
    header('Content-Type: text/plain; charset=utf-8', true, 500);
    echo 'Editorial plugin missing';
    exit;
}

eg_editorial_sandbox_render();
