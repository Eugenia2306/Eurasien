<?php
/**
 * Plugin Name: EG REST Fields
 * Description: Lets the registered fields of events, analyses, recordings and people be read and written through the REST API (needed for imports and admin tools). Hides the generic "Custom Fields" box this would add to the edit screens.
 * Author: d4rl1ngt0n
 */

if (!defined('ABSPATH')) {
    exit;
}

function eg_rest_field_types()
{
    return array('eg_event', 'eg_analyse', 'eg_recording', 'eg_person');
}

/* REST only exposes registered meta when the post type supports custom fields. Runs after the theme registers the types. */
add_action('init', static function () {
    foreach (eg_rest_field_types() as $type) {
        if (post_type_exists($type)) {
            add_post_type_support($type, 'custom-fields');
        }
    }
}, 99);

/* Editors use the dedicated boxes; the raw custom-fields table would only confuse. */
add_action('add_meta_boxes', static function () {
    foreach (eg_rest_field_types() as $type) {
        remove_meta_box('postcustom', $type, 'normal');
    }
}, 99);
