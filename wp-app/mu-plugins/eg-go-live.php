<?php
/**
 * Plugin Name: EG Go Live
 * Description: Staging checklist: Woo tidy, Stripe readiness, cutover steps, memberships, mail.
 * Author: d4rl1ngt0n
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', function () {
    add_management_page(
        'EG Go Live',
        'EG Go Live',
        'manage_options',
        'eg-go-live',
        'eg_go_live_render'
    );
});

/**
 * Keep Woo setup / coming-soon dismissed on staging and production.
 */
add_action('admin_init', function () {
    if (get_option('eg_woo_setup_dismissed') === 'yes') {
        return;
    }
    update_option('woocommerce_coming_soon', 'no');
    update_option('woocommerce_store_pages_only', 'no');
    update_option('woocommerce_show_marketplace_suggestions', 'no');
    $hidden = get_option('woocommerce_task_list_hidden_lists', array());
    if (!is_array($hidden)) {
        $hidden = array();
    }
    foreach (array('setup', 'extended') as $list) {
        if (!in_array($list, $hidden, true)) {
            $hidden[] = $list;
        }
    }
    update_option('woocommerce_task_list_hidden_lists', $hidden);
    update_option('eg_woo_setup_dismissed', 'yes');
}, 20);

function eg_go_live_status(): array
{
    $s = get_option('woocommerce_stripe_settings', array());
    if (!is_array($s)) {
        $s = array();
    }
    $testmode = (($s['testmode'] ?? 'yes') === 'yes');
    $pk_test = (string) ($s['test_publishable_key'] ?? '');
    $sk_test = (string) ($s['test_secret_key'] ?? '');
    $pk_live = (string) ($s['publishable_key'] ?? '');
    $sk_live = (string) ($s['secret_key'] ?? '');

    $smtp_ok = defined('EG_SMTP_USER') && EG_SMTP_USER !== ''
        && defined('EG_SMTP_PASS') && EG_SMTP_PASS !== '';

    $home = home_url('/');
    $is_prod_host = (bool) preg_match('/eurasien-gesellschaft\.org/i', $home);

    $levels = array();
    if (function_exists('pmpro_getAllLevels')) {
        $all = pmpro_getAllLevels(true, true);
        if (is_array($all)) {
            foreach ($all as $lvl) {
                $levels[] = array(
                    'id' => $lvl->id ?? null,
                    'name' => $lvl->name ?? '',
                );
            }
        }
    }

    $member_paths = array(
        'mitglieder',
        'mitglieder/positionen',
        'mitglieder/dossiers',
        'mitglieder/studien',
        'membership-account',
        'membership-levels',
        'membership-checkout',
        'login',
    );
    $pages = array();
    foreach ($member_paths as $path) {
        $p = get_page_by_path($path);
        $pages[$path] = $p ? array(
            'id' => $p->ID,
            'len' => strlen((string) $p->post_content),
            'url' => get_permalink($p),
        ) : null;
    }

    return array(
        'woo_coming_soon' => get_option('woocommerce_coming_soon'),
        'stripe_enabled' => (($s['enabled'] ?? '') === 'yes'),
        'stripe_testmode' => $testmode,
        'stripe_test_ok' => strlen($pk_test) >= 80 && strlen($sk_test) >= 80,
        'stripe_live_keys' => strlen($pk_live) >= 80 && strlen($sk_live) >= 80,
        'smtp_configured' => $smtp_ok,
        'smtp_user' => $smtp_ok ? EG_SMTP_USER : '',
        'home' => $home,
        'siteurl' => site_url('/'),
        'is_ssl' => is_ssl(),
        'is_prod_host' => $is_prod_host,
        'blogname' => get_option('blogname'),
        'admin_email' => get_option('admin_email'),
        'pmpro' => defined('PMPRO_VERSION') ? PMPRO_VERSION : null,
        'levels' => $levels,
        'pages' => $pages,
        'product_272' => function_exists('wc_get_product') ? wc_get_product(272) : null,
    );
}

function eg_go_live_render()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $notice = '';
    if (isset($_POST['eg_go_live_action']) && check_admin_referer('eg_go_live')) {
        $action = sanitize_key(wp_unslash($_POST['eg_go_live_action']));
        if ($action === 'dismiss_woo') {
            update_option('woocommerce_coming_soon', 'no');
            update_option('woocommerce_store_pages_only', 'no');
            update_option('woocommerce_show_marketplace_suggestions', 'no');
            $hidden = get_option('woocommerce_task_list_hidden_lists', array());
            if (!is_array($hidden)) {
                $hidden = array();
            }
            foreach (array('setup', 'extended') as $list) {
                if (!in_array($list, $hidden, true)) {
                    $hidden[] = $list;
                }
            }
            update_option('woocommerce_task_list_hidden_lists', $hidden);
            update_option('eg_woo_setup_dismissed', 'yes');
            $notice = 'Woo setup banner dismissed and coming soon disabled.';
        } elseif ($action === 'refresh_member_pages' && function_exists('eg_pmp_pages_run')) {
            $notice = eg_pmp_pages_run();
        } elseif ($action === 'rename_site') {
            update_option('blogname', 'Eurasien Gesellschaft');
            $notice = 'Site title set to Eurasien Gesellschaft.';
        } elseif ($action === 'mail_test') {
            $to = isset($_POST['eg_mail_to']) ? sanitize_email(wp_unslash($_POST['eg_mail_to'])) : '';
            if (!is_email($to)) {
                $to = get_option('admin_email');
            }
            $ok = wp_mail($to, 'EG Go Live mail test', 'Mail test from ' . home_url('/') . ' at ' . gmdate('c'));
            $notice = $ok
                ? 'Test email sent to ' . $to . '. Check inbox and spam.'
                : 'Test email failed. Check Tools → EG Mail Test and wp-content/.eg-mail-debug.log.';
        }
    }

    $st = eg_go_live_status();
    $p272 = $st['product_272'];

    echo '<div class="wrap"><h1>EG Go Live</h1>';
    if ($notice) {
        echo '<div class="notice notice-success"><p>' . esc_html($notice) . '</p></div>';
    }

    echo '<h2>1. Store admin</h2><ul>';
    echo '<li>Coming soon: <code>' . esc_html((string) $st['woo_coming_soon']) . '</code> (want <code>no</code>)</li>';
    echo '<li>On-hold bank orders are left alone until payment arrives</li>';
    echo '</ul><form method="post">';
    wp_nonce_field('eg_go_live');
    echo '<input type="hidden" name="eg_go_live_action" value="dismiss_woo">';
    echo '<p><button class="button">Re-dismiss Woo setup banner</button></p></form>';

    echo '<h2>2. Payments (Stripe)</h2><ul>';
    echo '<li>Enabled: ' . ($st['stripe_enabled'] ? 'yes' : 'no') . '</li>';
    echo '<li>Mode: <strong>' . ($st['stripe_testmode'] ? 'TEST' : 'LIVE') . '</strong></li>';
    echo '<li>Test keys OK: ' . ($st['stripe_test_ok'] ? 'yes' : 'no') . '</li>';
    echo '<li>Live keys present: ' . ($st['stripe_live_keys'] ? 'yes' : 'no') . '</li>';
    echo '</ul>';
    echo '<p><a class="button button-primary" href="' . esc_url(admin_url('tools.php?page=eg-stripe-settings')) . '">Open EG Stripe</a></p>';
    echo '<ol>';
    echo '<li>Stay on test while the host is <code>eurasia.uwzghana.com</code></li>';
    echo '<li>On production: paste <code>pk_live_</code> / <code>sk_live_</code>, uncheck Test mode, Save</li>';
    echo '<li>Place one small real card order, confirm thank-you + download + email</li>';
    echo '<li>Keep bank transfer enabled as fallback</li>';
    echo '</ol>';

    echo '<h2>3. Production cutover</h2><ul>';
    echo '<li>Current home: <code>' . esc_html($st['home']) . '</code></li>';
    echo '<li>SSL: ' . ($st['is_ssl'] ? 'yes' : 'no') . '</li>';
    echo '<li>Production host detected: ' . ($st['is_prod_host'] ? 'yes' : 'no') . '</li>';
    echo '<li>Site title: ' . esc_html((string) $st['blogname']) . '</li>';
    echo '</ul>';
    echo '<ol>';
    echo '<li>Point <code>www.eurasien-gesellschaft.org</code> document root at Hostinger <code>public_html/eurasia/</code> (brochure + <code>/app/</code>)</li>';
    echo '<li>In WP: set WordPress Address and Site Address to <code>https://www.eurasien-gesellschaft.org/app</code></li>';
    echo '<li>Purge Hostinger CDN / cache</li>';
    echo '<li>Confirm brochure <code>/</code> and shop <code>/app/shop/</code></li>';
    echo '<li>Switch Stripe to live keys after DNS works</li>';
    echo '</ol>';
    echo '<form method="post">';
    wp_nonce_field('eg_go_live');
    echo '<input type="hidden" name="eg_go_live_action" value="rename_site">';
    echo '<p><button class="button">Set site title to Eurasien Gesellschaft</button></p></form>';

    echo '<h2>4. Memberships, content, email</h2><ul>';
    echo '<li>PMPro: ' . esc_html($st['pmpro'] ? ('v' . $st['pmpro']) : 'missing') . '</li>';
    foreach ($st['levels'] as $lvl) {
        echo '<li>Level ' . esc_html((string) $lvl['id']) . ': ' . esc_html((string) $lvl['name']) . '</li>';
    }
    foreach ($st['pages'] as $path => $info) {
        if ($info) {
            echo '<li><a href="' . esc_url($info['url']) . '" target="_blank" rel="noopener">' . esc_html($path) . '</a> (' . (int) $info['len'] . ' chars)</li>';
        } else {
            echo '<li>' . esc_html($path) . ': <strong>missing</strong></li>';
        }
    }
    if ($p272) {
        echo '<li>Sample book #272: ' . esc_html($p272->get_name())
            . ' · virtual=' . ($p272->is_virtual() ? 'yes' : 'no')
            . ' · downloadable=' . ($p272->is_downloadable() ? 'yes' : 'no')
            . ' · files=' . count($p272->get_downloads()) . '</li>';
    }
    echo '<li>SMTP configured: ' . ($st['smtp_configured'] ? ('yes (' . esc_html($st['smtp_user']) . ')') : 'no') . '</li>';
    echo '</ul>';

    echo '<form method="post" style="margin-bottom:12px">';
    wp_nonce_field('eg_go_live');
    echo '<input type="hidden" name="eg_go_live_action" value="refresh_member_pages">';
    echo '<p><button class="button button-primary">Refresh / lock member pages</button></p></form>';

    echo '<form method="post">';
    wp_nonce_field('eg_go_live');
    echo '<input type="hidden" name="eg_go_live_action" value="mail_test">';
    echo '<p><label>Mail test to <input type="email" name="eg_mail_to" class="regular-text" value="' . esc_attr((string) $st['admin_email']) . '"></label> ';
    echo '<button class="button">Send test email</button></p></form>';

    echo '<p>Also: <a href="' . esc_url(admin_url('tools.php?page=eg-pmp-pages')) . '">EG Member Pages</a> · ';
    echo '<a href="' . esc_url(admin_url('tools.php?page=eg-mail-test')) . '">EG Mail Test</a> · ';
    echo '<a href="' . esc_url(admin_url('tools.php?page=eg-stripe-settings')) . '">EG Stripe</a></p>';
    echo '</div>';
}
