<?php
/**
 * Public Analysen JSON for the brochure.
 * URL: /app/eg-analysen.json.php
 */
$wp_load = __DIR__ . '/wp-load.php';
if (!is_readable($wp_load)) {
    header('Content-Type: application/json; charset=utf-8', true, 500);
    echo '{"items":[],"error":"wp-load"}';
    exit;
}
require_once $wp_load;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
nocache_headers();

if (!function_exists('eg_editorial_feed_payload')) {
    echo wp_json_encode(array('items' => array(), 'error' => 'plugin-missing'));
    exit;
}

echo wp_json_encode(eg_editorial_feed_payload());
exit;
