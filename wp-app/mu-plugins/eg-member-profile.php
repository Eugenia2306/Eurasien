<?php
/**
 * Plugin Name: EG Member Profile Display
 * Description: Show the member display name on My Account (not the immutable WordPress login username).
 * Author: d4rl1ngt0n
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Capture the PMPro profile bullets and replace Username (user_login)
 * with the member's editable display_name.
 */
add_action('pmpro_account_bullets_top', function () {
    ob_start();
}, 0);

add_action('pmpro_account_bullets_bottom', function () {
    $html = ob_get_clean();
    if ($html === false) {
        return;
    }

    $user = wp_get_current_user();
    if (!$user || empty($user->ID)) {
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        return;
    }

    $name  = $user->display_name !== '' ? $user->display_name : $user->user_login;
    $label = (strpos(determine_locale(), 'de') === 0)
        ? 'Anzeigename'
        : 'Display name';

    $class = 'pmpro_list_item';
    if (function_exists('pmpro_get_element_class')) {
        $class = pmpro_get_element_class('pmpro_list_item');
    }

    $replacement = sprintf(
        '<li class="%1$s"><strong>%2$s:</strong> %3$s</li>',
        esc_attr($class),
        esc_html($label),
        esc_html($name)
    );

    // Remove the stock Username row (login id is not user-editable on the front end).
    $html = preg_replace(
        '#<li\b[^>]*>\s*<strong>\s*Username\s*:</strong>\s*[^<]*</li>#i',
        $replacement,
        $html,
        1
    );

    echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}, 0);

/**
 * Make the profile edit field label clear: this is what My Account shows.
 */
add_filter('pmpro_member_profile_edit_user_object_fields', function ($fields) {
    if (!is_array($fields)) {
        return $fields;
    }
    if (isset($fields['display_name'])) {
        $fields['display_name'] = (strpos(determine_locale(), 'de') === 0)
            ? 'Anzeigename (sichtbar auf Mein Konto)'
            : 'Display name (shown on My Account)';
    }
    return $fields;
});
