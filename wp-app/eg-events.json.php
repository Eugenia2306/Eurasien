<?php
/**
 * Public events JSON for the brochure Veranstaltungen page.
 * URL: /app/eg-events.json.php
 */
$wp_load = __DIR__ . '/wp-load.php';
if (!is_readable($wp_load)) {
    header('Content-Type: application/json; charset=utf-8', true, 500);
    echo '{"events":[],"error":"wp-load"}';
    exit;
}
require_once $wp_load;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');

if (!function_exists('eg_event_admin_feed_payload')) {
    echo wp_json_encode(array('events' => array(), 'error' => 'plugin-missing'));
    exit;
}

echo wp_json_encode(eg_event_admin_feed_payload());
exit;
