<?php
/**
 * Optional: load product IDs into a REST-less inline hint for editors.
 * Primary config remains static-site assets/js/app-urls.js after bootstrap.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_footer', function () {
    if (!is_admin()) {
        $ids = get_option('eg_product_ids', array());
        if (empty($ids)) {
            return;
        }
        // Expose IDs for any theme scripts; static brochure uses its own app-urls.js copy.
        echo '<script>window.EG_WP_PRODUCT_IDS=' . wp_json_encode($ids) . ';</script>';
    }
});
