<?php
/**
 * Plugin Name: EG Bootstrap (mu-plugin)
 * Description: Creates membership products, German Woo page slugs helpers, and gated page stubs for Eurasien Gesellschaft.
 * Author: d4rl1ngt0n
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', function () {
    add_management_page(
        'EG Bootstrap',
        'EG Bootstrap',
        'manage_options',
        'eg-bootstrap',
        'eg_bootstrap_render_page'
    );
});

function eg_bootstrap_render_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $notice = '';
    if (isset($_POST['eg_bootstrap_run']) && check_admin_referer('eg_bootstrap_run')) {
        $notice = eg_bootstrap_run();
    }

    $ids = get_option('eg_product_ids', array());
    echo '<div class="wrap"><h1>EG Bootstrap</h1>';
    if ($notice) {
        echo '<div class="notice notice-success"><p>' . esc_html($notice) . '</p></div>';
    }
    echo '<p>Creates WooCommerce products (if missing), membership helper pages, and stores product IDs in <code>eg_product_ids</code>.</p>';
    echo '<p>Requires WooCommerce active. Subscriptions + Memberships plugins should be active for full plan linking.</p>';
    if (!empty($ids)) {
        echo '<h2>Product IDs</h2><pre>' . esc_html(wp_json_encode($ids, JSON_PRETTY_PRINT)) . '</pre>';
        echo '<p>Paste into static <code>assets/js/app-urls.js</code>:</p>';
        echo '<pre>EG_APP.setProductIds({ reader: '
            . intval($ids['reader'] ?? 0)
            . ', verein: '
            . intval($ids['verein'] ?? 0)
            . ', eventTicket: '
            . intval($ids['eventTicket'] ?? 0)
            . ' });</pre>';
    }
    echo '<form method="post">';
    wp_nonce_field('eg_bootstrap_run');
    echo '<p><button class="button button-primary" name="eg_bootstrap_run" value="1">Run bootstrap</button></p>';
    echo '</form></div>';
}

function eg_bootstrap_run()
{
    if (!class_exists('WooCommerce')) {
        return 'WooCommerce is not active.';
    }

    $catalog = array(
        'reader' => array(
            'name' => 'Leserzugang',
            'sku' => 'eg-leser-monthly',
            'price' => '5',
            'subscription' => true,
            'period' => 'month',
            'interval' => 1,
        ),
        'verein' => array(
            'name' => 'Vereinsmitgliedschaft',
            'sku' => 'eg-verein-yearly',
            'price' => '120',
            'subscription' => true,
            'period' => 'year',
            'interval' => 1,
        ),
        'eventTicket' => array(
            'name' => 'Veranstaltungsticket',
            'sku' => 'eg-event-ticket',
            'price' => '10',
            'subscription' => false,
        ),
    );

    $ids = get_option('eg_product_ids', array());

    foreach ($catalog as $key => $item) {
        $existing = wc_get_product_id_by_sku($item['sku']);
        if ($existing) {
            $ids[$key] = $existing;
            continue;
        }

        $product = null;
        if (!empty($item['subscription']) && class_exists('WC_Product_Subscription')) {
            $product = new WC_Product_Subscription();
            $product->set_props(array(
                'name' => $item['name'],
                'sku' => $item['sku'],
                'regular_price' => $item['price'],
                'virtual' => true,
                'status' => 'publish',
            ));
            $product->update_meta_data('_subscription_price', $item['price']);
            $product->update_meta_data('_subscription_period', $item['period']);
            $product->update_meta_data('_subscription_period_interval', $item['interval']);
            $product->update_meta_data('_subscription_length', 0);
        } else {
            $product = new WC_Product_Simple();
            $product->set_props(array(
                'name' => $item['name'],
                'sku' => $item['sku'],
                'regular_price' => $item['price'],
                'virtual' => true,
                'status' => 'publish',
            ));
            if (!empty($item['subscription'])) {
                $product->set_description(
                    'Install WooCommerce Subscriptions, then convert this product to a subscription ('
                    . $item['price'] . ' EUR / ' . $item['period'] . ').'
                );
            }
        }
        $id = $product->save();
        $ids[$key] = $id;
    }

    update_option('eg_product_ids', $ids);

    $parent_id = eg_bootstrap_ensure_page('Mitglieder', 'mitglieder', 0);
    eg_bootstrap_ensure_page('Positionen', 'positionen', $parent_id, 'eg-content-positionen.html');
    eg_bootstrap_ensure_page('Dossiers', 'dossiers', $parent_id, 'eg-content-dossiers.html');
    eg_bootstrap_ensure_page('Studien', 'studien', $parent_id, 'eg-content-studien.html');
    eg_bootstrap_ensure_page('Mitgliedschaft (Shop)', 'mitgliedschaft', 0, 'eg-content-mitgliedschaft.html');

    flush_rewrite_rules(false);

    return 'Bootstrap complete. Link each subscription product to its Membership plan in Memberships -> Products. Restrict the three Mitglieder pages to Leserzugang or Vereinsmitgliedschaft.';
}

function eg_bootstrap_ensure_page($title, $slug, $parent = 0, $content_file = '')
{
    $existing = get_page_by_path(($parent ? 'mitglieder/' : '') . $slug);
    if ($parent && $slug !== 'mitglieder') {
        $parent_post = get_post($parent);
        if ($parent_post) {
            $existing = get_page_by_path($parent_post->post_name . '/' . $slug);
        }
    }
    if ($existing) {
        return (int) $existing->ID;
    }

    $content = '<p>Mitgliedsinhalt. Bitte in WordPress pflegen und mit WooCommerce Memberships schützen.</p>';
    if ($content_file) {
        $path = WP_CONTENT_DIR . '/mu-plugins/eg-content/' . $content_file;
        if (is_readable($path)) {
            $content = file_get_contents($path);
        }
    }

    $id = wp_insert_post(array(
        'post_title' => $title,
        'post_name' => $slug,
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_parent' => $parent,
        'post_content' => $content,
    ));
    return (int) $id;
}
