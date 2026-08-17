<?php
/**
 * Plugin Name: EG PMPro Stripe Sync
 * Description: Keep Paid Memberships Pro Stripe API keys in sync with WooCommerce Stripe keys and use hosted Stripe Checkout.
 * Author: d4rl1ngt0n
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Copy WooCommerce Stripe keys into the option names PMPro 3.x actually reads.
 * Payment flow is hosted Stripe Checkout (not onsite card fields).
 */
function eg_pmpro_sync_stripe_keys_from_woo($force = false)
{
    if (!class_exists('PMProGateway_stripe') && !defined('PMPRO_VERSION')) {
        return false;
    }

    $woo = get_option('woocommerce_stripe_settings', array());
    if (!is_array($woo)) {
        return false;
    }

    $pk_test = isset($woo['test_publishable_key']) ? trim((string) $woo['test_publishable_key']) : '';
    $sk_test = isset($woo['test_secret_key']) ? trim((string) $woo['test_secret_key']) : '';
    $pk_live = isset($woo['publishable_key']) ? trim((string) $woo['publishable_key']) : '';
    $sk_live = isset($woo['secret_key']) ? trim((string) $woo['secret_key']) : '';

    $testmode = (($woo['testmode'] ?? 'yes') === 'yes');
    $pk = $testmode ? $pk_test : $pk_live;
    $sk = $testmode ? $sk_test : $sk_live;

    if (strlen($pk) < 80 || strlen($sk) < 80) {
        return false;
    }
    if (strpos($pk, 'pk_') !== 0 || strpos($sk, 'sk_') !== 0) {
        return false;
    }

    $current_pk = (string) get_option('pmpro_stripe_publishablekey');
    $current_sk = (string) get_option('pmpro_stripe_secretkey');
    $flow = (string) get_option('pmpro_stripe_payment_flow');

    if (!$force && $current_pk === $pk && $current_sk === $sk && $flow === 'checkout') {
        return true;
    }

    update_option('pmpro_gateway', 'stripe');
    update_option('pmpro_gateway_environment', $testmode ? 'sandbox' : 'live');
    update_option('pmpro_stripe_publishablekey', $pk);
    update_option('pmpro_stripe_secretkey', $sk);
    update_option('pmpro_stripe_payment_flow', 'checkout');

    return true;
}

add_action('plugins_loaded', function () {
    eg_pmpro_sync_stripe_keys_from_woo(false);
}, 30);

add_action('update_option_woocommerce_stripe_settings', function () {
    eg_pmpro_sync_stripe_keys_from_woo(true);
}, 20);

add_action('admin_init', function () {
    if (!current_user_can('manage_options')) {
        return;
    }
    if (isset($_GET['eg_pmpro_stripe_sync']) && $_GET['eg_pmpro_stripe_sync'] === '1') {
        eg_pmpro_sync_stripe_keys_from_woo(true);
    }
}, 5);
