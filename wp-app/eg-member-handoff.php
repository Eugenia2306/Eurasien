<?php
/**
 * Brochure → membership checkout handoff.
 * URL: /app/eg-member-handoff.php
 *
 * Browser form POST (redirect=1): create/login user, Set-Cookie, 302 to PMPro checkout.
 * JSON POST: returns { ok, checkoutUrl } for fetch clients.
 */
try {
    $wp_load = dirname(__FILE__) . '/wp-load.php';
    if (!is_readable($wp_load)) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo '{"ok":false,"error":"wp-load-missing"}';
        exit;
    }
    require_once $wp_load;

    if (function_exists('eg_member_handoff_handle')) {
        eg_member_handoff_handle();
    }

    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo '{"ok":false,"error":"handoff-plugin-missing"}';
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo '{"ok":false,"error":"exception"}';
}
