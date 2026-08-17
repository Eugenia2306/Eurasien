<?php
/**
 * Plugin Name: EG Classic Payments Settings
 * Description: Classic (non-React) payment + Stripe settings for hosts where WooCommerce admin React panels stay blank.
 * Author: d4rl1ngt0n
 */

if (!defined('ABSPATH')) {
    exit;
}

function eg_is_payments_main_settings_screen(): bool
{
    if (!is_admin()) {
        return false;
    }
    $page = isset($_GET['page']) ? (string) $_GET['page'] : '';
    $tab = isset($_GET['tab']) ? (string) $_GET['tab'] : '';
    $section = isset($_GET['section']) ? (string) $_GET['section'] : '';
    return $page === 'wc-settings' && $tab === 'checkout' && ($section === '' || $section === 'main');
}

function eg_is_stripe_settings_screen(): bool
{
    if (!is_admin()) {
        return false;
    }
    $page = isset($_GET['page']) ? (string) $_GET['page'] : '';
    $tab = isset($_GET['tab']) ? (string) $_GET['tab'] : '';
    $section = isset($_GET['section']) ? (string) $_GET['section'] : '';
    return ($page === 'wc-settings' && $tab === 'checkout' && $section === 'stripe')
        || ($page === 'eg-stripe-settings');
}

function eg_get_stripe_settings_array(): array
{
    $s = get_option('woocommerce_stripe_settings', array());
    return is_array($s) ? $s : array();
}

function eg_save_stripe_settings_from_post(): string
{
    if (!current_user_can('manage_woocommerce')) {
        return 'Forbidden';
    }
    check_admin_referer('eg_stripe_classic_save');

    $s = eg_get_stripe_settings_array();

    $s['enabled'] = isset($_POST['eg_stripe_enabled']) ? 'yes' : 'no';
    $s['testmode'] = isset($_POST['eg_stripe_testmode']) ? 'yes' : 'no';
    $s['pmc_enabled'] = 'no'; // required when using API keys instead of Woo OAuth
    $s['title'] = isset($_POST['eg_stripe_title'])
        ? sanitize_text_field(wp_unslash($_POST['eg_stripe_title']))
        : 'Kreditkarte / Credit card';
    $s['description'] = isset($_POST['eg_stripe_description'])
        ? sanitize_text_field(wp_unslash($_POST['eg_stripe_description']))
        : '';
    $s['capture'] = 'yes';
    $s['payment_request'] = 'no';
    $s['saved_cards'] = 'no';
    $s['logging'] = 'yes';
    $s['upe_checkout_experience_accepted_payments'] = array('card');

    $test_pk = isset($_POST['eg_test_pk']) ? trim(wp_unslash($_POST['eg_test_pk'])) : '';
    $test_sk = isset($_POST['eg_test_sk']) ? trim(wp_unslash($_POST['eg_test_sk'])) : '';
    $live_pk = isset($_POST['eg_live_pk']) ? trim(wp_unslash($_POST['eg_live_pk'])) : '';
    $live_sk = isset($_POST['eg_live_sk']) ? trim(wp_unslash($_POST['eg_live_sk'])) : '';

    // Keep existing secret if the password field was left blank (masked)
    if ($test_pk !== '') {
        $s['test_publishable_key'] = $test_pk;
    }
    if ($test_sk !== '' && strpos($test_sk, '••••') === false) {
        $s['test_secret_key'] = $test_sk;
    }
    if ($live_pk !== '') {
        $s['publishable_key'] = $live_pk;
    }
    if ($live_sk !== '' && strpos($live_sk, '••••') === false) {
        $s['secret_key'] = $live_sk;
    }

    // Do NOT auto-copy PMPro Connect keys. They break Woo card payments
    // (platform-owned PaymentMethod / pm_0... errors).

    unset($s['test_connection_type'], $s['connection_type'], $s['test_refresh_token'], $s['refresh_token']);

    $warnings = array();
    $active_pk = (($s['testmode'] ?? 'yes') === 'yes')
        ? (string) ($s['test_publishable_key'] ?? '')
        : (string) ($s['publishable_key'] ?? '');
    if ($active_pk !== '' && strlen($active_pk) < 80) {
        $s['enabled'] = 'no';
        $warnings[] = 'Publishable key looks like a Stripe Connect restricted key (len '
            . strlen($active_pk)
            . '). Card payments were left disabled. Paste keys from Stripe Dashboard → Developers → API keys (pk_test_ / sk_test_ usually ~100+ chars).';
    }

    update_option('woocommerce_stripe_settings', $s);

    // Keep BACS as offline fallback (always ensure it stays on when checkbox set)
    $bacs = get_option('woocommerce_bacs_settings', array());
    if (!is_array($bacs)) {
        $bacs = array();
    }
    if (isset($_POST['eg_bacs_enabled'])) {
        $bacs['enabled'] = 'yes';
        $bacs['title'] = 'Ueberweisung / Bank transfer';
        update_option('woocommerce_bacs_settings', $bacs);
    }

    delete_transient('wc_stripe_account_data_test');
    delete_transient('wc_stripe_account_data_live');
    delete_option('woocommerce_stripe_pmc_fallback_id_test');

    if ($warnings) {
        return implode(' ', $warnings);
    }
    return 'Saved. Hard-refresh checkout and retry a test card payment.';
}

function eg_mask_key(string $key): string
{
    if ($key === '') {
        return '';
    }
    $prefix = substr($key, 0, 12);
    return $prefix . str_repeat('•', 8) . ' (len ' . strlen($key) . ')';
}

function eg_render_stripe_classic_form(string $notice = ''): void
{
    $s = eg_get_stripe_settings_array();
    $enabled = (($s['enabled'] ?? '') === 'yes');
    $testmode = (($s['testmode'] ?? 'yes') === 'yes');
    $title = (string) ($s['title'] ?? 'Kreditkarte / Credit card');
    $description = (string) ($s['description'] ?? '');
    $test_pk = (string) ($s['test_publishable_key'] ?? '');
    $test_sk = (string) ($s['test_secret_key'] ?? '');
    $live_pk = (string) ($s['publishable_key'] ?? '');
    $live_sk = (string) ($s['secret_key'] ?? '');
    $pmc = (string) ($s['pmc_enabled'] ?? '(unset)');

    $tools_url = admin_url('tools.php?page=eg-stripe-settings');
    $stripe_wc_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=stripe');

    echo '<div class="eg-stripe-classic wrap" style="max-width:820px">';
    if ($notice) {
        $cls = (stripos($notice, 'Broken') !== false || stripos($notice, 'disabled') !== false)
            ? 'notice-error'
            : 'notice-success';
        echo '<div class="notice ' . esc_attr($cls) . '"><p>' . esc_html($notice) . '</p></div>';
    }
    echo '<h2>Stripe settings (classic)</h2>';
    echo '<p>WooCommerce React settings panels are blank on this host. Use this form instead. ';
    echo 'Bookmark: <a href="' . esc_url($tools_url) . '">Tools → EG Stripe</a></p>';
    echo '<p><strong>Status:</strong> enabled=' . esc_html($enabled ? 'yes' : 'no');
    echo ' | testmode=' . esc_html($testmode ? 'yes' : 'no');
    echo ' | pmc_enabled=' . esc_html($pmc) . ' (must be <code>no</code> with API keys)</p>';

    if ($test_pk !== '' && strlen($test_pk) < 80) {
        echo '<div class="notice notice-error"><p><strong>Broken test publishable key.</strong> Current key length is '
            . (int) strlen($test_pk)
            . '. PMPro Stripe Connect keys cannot charge WooCommerce cards (platform-owned PaymentMethod errors). '
            . 'Get standard keys from <a href="https://dashboard.stripe.com/test/apikeys" target="_blank" rel="noopener">Stripe Dashboard → Developers → API keys</a> '
            . '(Reveal test key). Until then, use bank transfer at checkout.</p></div>';
    }

    echo '<form method="post">';
    wp_nonce_field('eg_stripe_classic_save');
    echo '<table class="form-table" role="presentation">';

    echo '<tr><th>Enable Stripe</th><td><label><input type="checkbox" name="eg_stripe_enabled" value="1" ' . checked($enabled, true, false) . '> Accept card payments</label></td></tr>';
    echo '<tr><th>Test mode</th><td><label><input type="checkbox" name="eg_stripe_testmode" value="1" ' . checked($testmode, true, false) . '> Use test keys (4242...)</label></td></tr>';
    echo '<tr><th>Title</th><td><input type="text" class="regular-text" name="eg_stripe_title" value="' . esc_attr($title) . '"></td></tr>';
    echo '<tr><th>Description</th><td><input type="text" class="regular-text" name="eg_stripe_description" value="' . esc_attr($description) . '"></td></tr>';

    echo '<tr><th>Test publishable key</th><td><input type="text" class="large-text" name="eg_test_pk" value="' . esc_attr($test_pk) . '" placeholder="pk_test_..." autocomplete="off"></td></tr>';
    echo '<tr><th>Test secret key</th><td><input type="password" class="large-text" name="eg_test_sk" value="" placeholder="' . esc_attr($test_sk ? eg_mask_key($test_sk) : 'sk_test_...') . '" autocomplete="new-password"><p class="description">Leave blank to keep the current secret.</p></td></tr>';

    echo '<tr><th>Live publishable key</th><td><input type="text" class="large-text" name="eg_live_pk" value="' . esc_attr($live_pk) . '" placeholder="pk_live_..." autocomplete="off"></td></tr>';
    echo '<tr><th>Live secret key</th><td><input type="password" class="large-text" name="eg_live_sk" value="" placeholder="' . esc_attr($live_sk ? eg_mask_key($live_sk) : 'sk_live_...') . '" autocomplete="new-password"><p class="description">Leave blank to keep the current secret.</p></td></tr>';

    echo '<tr><th>Also enable bank transfer</th><td><label><input type="checkbox" name="eg_bacs_enabled" value="1" checked> Keep Überweisung / BACS as fallback</label></td></tr>';

    echo '</table>';
    echo '<p class="submit"><button type="submit" name="eg_stripe_classic_save" class="button button-primary">Save Stripe settings</button> ';
    echo '<a class="button" href="' . esc_url($stripe_wc_url) . '">Woo Stripe page</a></p>';
    echo '</form>';

    echo '<hr><h3>Where to get working keys</h3>';
    echo '<ol>';
    echo '<li>Open <a href="https://dashboard.stripe.com/test/apikeys" target="_blank" rel="noopener">dashboard.stripe.com/test/apikeys</a></li>';
    echo '<li>Copy <strong>Publishable key</strong> (<code>pk_test_...</code>) and <strong>Secret key</strong> (<code>sk_test_...</code>)</li>';
    echo '<li>Paste them above, enable Stripe, save, then hard-refresh checkout</li>';
    echo '<li>Test with card <code>4242 4242 4242 4242</code></li>';
    echo '</ol>';
    echo '<p><em>Do not paste PMPro Stripe Connect keys here.</em> Those work for memberships only and break Woo card checkout.</p>';

    echo '<h3>Go live (real cards)</h3>';
    echo '<ol>';
    echo '<li>Finish DNS so the shop runs on <code>www.eurasien-gesellschaft.org/app/</code></li>';
    echo '<li>Open <a href="https://dashboard.stripe.com/apikeys" target="_blank" rel="noopener">dashboard.stripe.com/apikeys</a> (live mode)</li>';
    echo '<li>Paste <code>pk_live_...</code> and <code>sk_live_...</code> above</li>';
    echo '<li>Uncheck <strong>Test mode</strong>, Save, hard-refresh checkout</li>';
    echo '<li>Place one small real order and confirm thank-you, download, and email</li>';
    echo '</ol>';
    echo '<p>Full checklist: <a href="' . esc_url(admin_url('tools.php?page=eg-go-live')) . '">Tools → EG Go Live</a></p>';

    $pm_pk = (string) get_option('pmpro_sandbox_stripe_connect_publishablekey', '');
    echo '<p class="description">PMPro sandbox publishable key on this site: <code>'
        . esc_html($pm_pk ? eg_mask_key($pm_pk) : '(empty)')
        . '</code> (Connect restricted; not usable for Woo.)</p>';

    echo '<p style="margin-top:24px"><a href="' . esc_url(home_url('/shop/')) . '" target="_blank" rel="noopener">Open shop</a> · ';
    echo '<a href="' . esc_url(home_url('/checkout/')) . '" target="_blank" rel="noopener">Open checkout</a></p>';
    echo '</div>';
}

add_action('admin_menu', function () {
    add_management_page(
        'EG Stripe',
        'EG Stripe',
        'manage_woocommerce',
        'eg-stripe-settings',
        function () {
            $notice = '';
            if (isset($_POST['eg_stripe_classic_save'])) {
                $notice = eg_save_stripe_settings_from_post();
            }
            eg_render_stripe_classic_form($notice);
        }
    );
});

add_action('admin_head', function () {
    if (eg_is_payments_main_settings_screen()) {
        echo '<style id="eg-classic-payments-css">
#experimental_wc_settings_payments_main,
#experimental_wc_settings_payments_offline,
div[id^="wc_settings_ui_checkout"]{display:none!important}
.eg-pay-classic{max-width:960px;margin:16px 0 32px}
.eg-pay-classic h2{margin:0 0 8px}
.eg-pay-classic .eg-pay-note{color:#50575e;margin:0 0 16px}
.eg-pay-classic table{border-collapse:collapse;width:100%;background:#fff}
.eg-pay-classic th,.eg-pay-classic td{border:1px solid #c3c4c7;padding:10px 12px;text-align:left;vertical-align:middle}
.eg-pay-classic th{background:#f6f7f7}
.eg-pay-classic .eg-status-on{color:#008a20;font-weight:600}
.eg-pay-classic .eg-status-off{color:#646970}
</style>';
    }
    if (eg_is_stripe_settings_screen()) {
        echo '<style id="eg-stripe-classic-css">
#wc-stripe-account-settings-container,
#wc-stripe-new-account-container,
#wc-stripe-account-settings-container *,
.wc-stripe-account-settings-container{min-height:0!important}
#wc-stripe-account-settings-container:empty,
#wc-stripe-new-account-container:empty{display:none!important}
</style>';
    }
});

add_action('woocommerce_settings_checkout', function () {
    if (!current_user_can('manage_woocommerce') || !function_exists('WC')) {
        return;
    }

    // Main payments list fallback
    if (eg_is_payments_main_settings_screen()) {
        $gateways = WC()->payment_gateways()->payment_gateways();
        $tools = admin_url('tools.php?page=eg-stripe-settings');
        $stripe_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=stripe');

        echo '<div class="eg-pay-classic">';
        echo '<h2>Payment methods (classic)</h2>';
        echo '<p class="eg-pay-note">React payment panels are blank on this host. Use classic links below, or <a href="' . esc_url($tools) . '"><strong>Tools → EG Stripe</strong></a>.</p>';
        echo '<p><a class="button button-primary" href="' . esc_url($tools) . '">EG Stripe settings</a> ';
        echo '<a class="button" href="' . esc_url($stripe_url) . '">Woo Stripe page</a></p>';
        echo '<table><thead><tr><th>Method</th><th>Status</th><th>Action</th></tr></thead><tbody>';
        $seen = array();
        foreach ($gateways as $gateway) {
            if (!$gateway || empty($gateway->id) || strpos($gateway->id, 'stripe_') === 0) {
                continue;
            }
            if (isset($seen[$gateway->id])) {
                continue;
            }
            $seen[$gateway->id] = true;
            $enabled = ($gateway->enabled === 'yes');
            $title = $gateway->get_method_title() ?: $gateway->get_title();
            $manage = ($gateway->id === 'stripe')
                ? $tools
                : admin_url('admin.php?page=wc-settings&tab=checkout&section=' . rawurlencode($gateway->id));
            echo '<tr><td><strong>' . esc_html($title) . '</strong><br><code>' . esc_html($gateway->id) . '</code></td>';
            echo '<td class="' . ($enabled ? 'eg-status-on' : 'eg-status-off') . '">' . ($enabled ? 'Enabled' : 'Disabled') . '</td>';
            echo '<td><a class="button' . ($gateway->id === 'stripe' ? ' button-primary' : '') . '" href="' . esc_url($manage) . '">Manage</a></td></tr>';
        }
        echo '</tbody></table></div>';
        return;
    }

    // Stripe section fallback form
    if (isset($_GET['section']) && (string) $_GET['section'] === 'stripe') {
        $notice = '';
        if (isset($_POST['eg_stripe_classic_save'])) {
            $notice = eg_save_stripe_settings_from_post();
        }
        eg_render_stripe_classic_form($notice);
    }
}, 5);

// Admin notice pointing to classic Stripe tools on any blank Woo settings experience
add_action('admin_notices', function () {
    if (!current_user_can('manage_woocommerce')) {
        return;
    }
    $page = isset($_GET['page']) ? (string) $_GET['page'] : '';
    if ($page !== 'wc-settings' && $page !== 'wc-admin') {
        return;
    }
    $url = admin_url('tools.php?page=eg-stripe-settings');
    echo '<div class="notice notice-warning"><p><strong>EG:</strong> If WooCommerce settings panels stay grey/blank, manage Stripe here: ';
    echo '<a href="' . esc_url($url) . '">Tools → EG Stripe</a>.</p></div>';
});
