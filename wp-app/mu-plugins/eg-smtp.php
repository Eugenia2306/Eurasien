<?php
/**
 * Plugin Name: EG SMTP
 * Description: Hostinger SMTP for WordPress mail (password reset, memberships).
 * Author: d4rl1ngt0n
 */

if (!defined('ABSPATH')) {
    exit;
}

// Keep credentials outside the mu-plugins root so WP does not auto-load them
// (and so example files cannot redefine constants first).
$config = __DIR__ . '/eg-private/smtp-config.php';
if (is_readable($config)) {
    require_once $config;
}

/**
 * Send all wp_mail() traffic through Hostinger SMTP when credentials exist.
 * If our SMTP config is missing, force PHP mail() so other plugins cannot leave
 * a broken SMTP auth setup that fails with "Could not authenticate".
 */
add_action('phpmailer_init', function ($phpmailer) {
    $configured = defined('EG_SMTP_USER') && EG_SMTP_USER !== ''
        && defined('EG_SMTP_PASS') && EG_SMTP_PASS !== '';

    if ($configured) {
        $phpmailer->isSMTP();
        $phpmailer->Host       = defined('EG_SMTP_HOST') ? EG_SMTP_HOST : 'smtp.hostinger.com';
        $phpmailer->SMTPAuth   = true;
        $phpmailer->Port       = defined('EG_SMTP_PORT') ? (int) EG_SMTP_PORT : 465;
        $phpmailer->Username   = EG_SMTP_USER;
        $phpmailer->Password   = EG_SMTP_PASS;
        $phpmailer->SMTPSecure = defined('EG_SMTP_SECURE') ? EG_SMTP_SECURE : 'ssl';
        $phpmailer->From       = EG_SMTP_USER;
        $phpmailer->FromName   = defined('EG_SMTP_FROM_NAME') ? EG_SMTP_FROM_NAME : 'Eurasien Gesellschaft';
        $phpmailer->Sender     = EG_SMTP_USER;
        return;
    }

    // Undo broken SMTP left by another plugin/host integration.
    $phpmailer->isMail();
    $phpmailer->SMTPAuth = false;
}, 999);

add_filter('wp_mail_from', function ($email) {
    if (defined('EG_SMTP_USER') && is_email(EG_SMTP_USER)) {
        return EG_SMTP_USER;
    }
    return $email;
}, 20);

add_filter('wp_mail_from_name', function ($name) {
    if (defined('EG_SMTP_FROM_NAME') && EG_SMTP_FROM_NAME !== '') {
        return EG_SMTP_FROM_NAME;
    }
    return $name;
}, 20);

/**
 * Log password-reset URLs so staging can recover when inbox delivery fails.
 * File: wp-content/.eg-mail-debug.log (not web-served; also blocked below).
 */
add_filter('retrieve_password_message', function ($message, $key, $user_login) {
    $url = add_query_arg(
        array(
            'action' => 'rp',
            'key'    => $key,
            'login'  => $user_login,
        ),
        home_url('/login/')
    );
    $line = sprintf("[%s] password_reset user=%s url=%s\n", gmdate('c'), $user_login, $url);
    $log  = WP_CONTENT_DIR . '/.eg-mail-debug.log';
    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
    @file_put_contents($log, $line, FILE_APPEND | LOCK_EX);
    return $message;
}, 100, 3);

add_action('wp_mail_failed', function ($wp_error) {
    $msg = is_wp_error($wp_error) ? $wp_error->get_error_message() : 'unknown mail failure';
    $line = sprintf("[%s] wp_mail_failed %s\n", gmdate('c'), $msg);
    $log  = WP_CONTENT_DIR . '/.eg-mail-debug.log';
    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
    @file_put_contents($log, $line, FILE_APPEND | LOCK_EX);
});

/**
 * Block direct HTTP access to the debug log.
 */
add_action('init', function () {
    $htaccess = WP_CONTENT_DIR . '/.htaccess';
    $marker   = '# EG mail debug log deny';
    if (!is_dir(WP_CONTENT_DIR)) {
        return;
    }
    $rule = $marker . "\n<FilesMatch \"^\\.eg-mail-debug\\.log$\">\nRequire all denied\n</FilesMatch>\n";
    if (!file_exists($htaccess)) {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        @file_put_contents($htaccess, $rule);
        return;
    }
    $existing = @file_get_contents($htaccess);
    if ($existing !== false && strpos($existing, $marker) === false) {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        @file_put_contents($htaccess, "\n" . $rule, FILE_APPEND);
    }
}, 1);

/**
 * Admin: SMTP status + test send.
 */
add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) {
        return;
    }
    $configured = defined('EG_SMTP_USER') && EG_SMTP_USER !== '' && defined('EG_SMTP_PASS') && EG_SMTP_PASS !== '';
    if ($configured) {
        return;
    }
    echo '<div class="notice notice-error"><p><strong>Eurasien email:</strong> SMTP is not configured. Password reset and membership emails will not reach inboxes. Add Hostinger mailbox credentials in <code>wp-content/mu-plugins/eg-private/smtp-config.php</code> (see <code>wp-app/SMTP_SETUP.txt</code>).</p></div>';
});

add_action('admin_menu', function () {
    add_management_page(
        'EG Mail Test',
        'EG Mail Test',
        'manage_options',
        'eg-mail-test',
        'eg_mail_test_page'
    );
});

function eg_mail_test_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $configured = defined('EG_SMTP_USER') && EG_SMTP_USER !== '' && defined('EG_SMTP_PASS') && EG_SMTP_PASS !== '';
    $result     = '';

    if ($configured && isset($_POST['eg_mail_test']) && check_admin_referer('eg_mail_test')) {
        $to = isset($_POST['eg_mail_to']) ? sanitize_email(wp_unslash($_POST['eg_mail_to'])) : '';
        if (!is_email($to)) {
            $to = get_option('admin_email');
        }
        $ok = wp_mail($to, 'Eurasien Gesellschaft mail test', "Test message from EG SMTP at " . gmdate('c'));
        $result = $ok
            ? '<div class="notice notice-success"><p>Test sent to ' . esc_html($to) . '. Check inbox and spam.</p></div>'
            : '<div class="notice notice-error"><p>Test failed. See <code>wp-content/.eg-mail-debug.log</code>.</p></div>';
    }

    echo '<div class="wrap"><h1>EG Mail Test</h1>';
    echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    if (!$configured) {
        echo '<p><strong>SMTP is not configured.</strong> Create <code>mu-plugins/eg-private/smtp-config.php</code> from <code>wp-app/eg-smtp-config.example.php</code>.</p>';
    } else {
        echo '<p>SMTP user: <code>' . esc_html(EG_SMTP_USER) . '</code></p>';
        echo '<form method="post">';
        wp_nonce_field('eg_mail_test');
        echo '<p><label>Send test to <input type="email" name="eg_mail_to" value="' . esc_attr(get_option('admin_email')) . '" class="regular-text"></label></p>';
        echo '<p><button class="button button-primary" name="eg_mail_test" value="1">Send test email</button></p>';
        echo '</form>';
    }
    echo '</div>';
}
