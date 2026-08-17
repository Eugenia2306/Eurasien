<?php
/**
 * Public auth status for the static brochure (same-origin under /app/).
 * Prefer this over admin-ajax for simple cookie checks.
 */
if (!defined('ABSPATH')) {
    $wp_load = __DIR__ . '/wp-load.php';
    if (!is_readable($wp_load)) {
        header('Content-Type: application/json; charset=utf-8', true, 500);
        echo '{"loggedIn":false,"error":"wp-load"}';
        exit;
    }
    require_once $wp_load;
}

nocache_headers();
header('Content-Type: application/json; charset=utf-8');

if (function_exists('eg_auth_status_response')) {
    eg_auth_status_response();
}

/* Fallback if mu-plugin not loaded yet. */
if (is_user_logged_in()) {
    $user = wp_get_current_user();
    $level_ids = array();
    if (function_exists('pmpro_getMembershipLevelsForUser')) {
        foreach ((array) pmpro_getMembershipLevelsForUser($user->ID) as $level) {
            if (!empty($level->id)) {
                $level_ids[] = (int) $level->id;
            }
        }
    }
    $has_membership = (function_exists('pmpro_hasMembershipLevel') && pmpro_hasMembershipLevel())
        || count($level_ids) > 0;
    echo wp_json_encode(
        array(
            'loggedIn'      => true,
            'email'         => $user->user_email,
            'display'       => $user->display_name,
            'hasMembership' => (bool) $has_membership,
            'levelIds'      => $level_ids,
            'logoutUrl'     => function_exists('eg_member_logout_url') ? eg_member_logout_url('/') : wp_logout_url('/'),
            'accountUrl'    => home_url('/membership-account/'),
            'membersUrl'    => home_url('/mitglieder/positionen/'),
        )
    );
    exit;
}

echo wp_json_encode(
    array(
        'loggedIn' => false,
        'loginUrl' => function_exists('eg_member_login_url')
            ? eg_member_login_url()
            : add_query_arg(
                'redirect_to',
                home_url('/membership-account/'),
                home_url('/login/')
            ),
    )
);
exit;
