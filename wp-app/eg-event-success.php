<?php
/**
 * Post-Stripe event booking confirmation + add to calendar.
 * URL: /app/eg-event-success.php?session_id=cs_...
 */
try {
    $wp_load = dirname(__FILE__) . '/wp-load.php';
    if (!is_readable($wp_load)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'wp-load missing';
        exit;
    }
    require_once $wp_load;

    if (function_exists('eg_event_handoff_render_success')) {
        eg_event_handoff_render_success();
    }

    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'event-success-plugin-missing';
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'exception';
}
