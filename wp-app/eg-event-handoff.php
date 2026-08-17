<?php
/**
 * Brochure → event ticket Stripe Checkout.
 * URL: /app/eg-event-handoff.php
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

    if (function_exists('eg_event_handoff_handle')) {
        eg_event_handoff_handle();
    }

    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'event-handoff-plugin-missing';
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'exception';
}
