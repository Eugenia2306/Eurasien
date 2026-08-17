<?php
/**
 * Plugin Name: EG PMP Pages
 * Description: Creates and restricts Mitglieder pages; upload locked member documents.
 * Author: d4rl1ngt0n
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', function () {
    add_management_page(
        'EG Member Pages',
        'EG Member Pages',
        'manage_options',
        'eg-pmp-pages',
        'eg_pmp_pages_render'
    );
});

function eg_pmp_pages_render()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $notice = '';
    if (isset($_POST['eg_pmp_pages_run']) && check_admin_referer('eg_pmp_pages_run')) {
        $notice = eg_pmp_pages_run();
    }
    if (isset($_POST['eg_pmp_upload']) && check_admin_referer('eg_pmp_upload')) {
        $notice = eg_pmp_handle_upload();
    }

    $types = function_exists('eg_member_docs_types') ? eg_member_docs_types() : array(
        'positionen' => array('title' => 'Positionen'),
        'dossiers'   => array('title' => 'Dossiers'),
        'studien'    => array('title' => 'Studien'),
    );
    $level_ids = function_exists('eg_member_docs_level_ids') ? eg_member_docs_level_ids() : array(1, 2);

    echo '<div class="wrap"><h1>EG Member Pages</h1>';
    if ($notice) {
        echo '<div class="notice notice-success"><p>' . esc_html($notice) . '</p></div>';
    }

    echo '<h2>1. Pages + membership lock</h2>';
    echo '<p>Creates <code>/mitglieder/{positionen|dossiers|studien}/</code>, inserts document shortcodes, and requires levels: <code>' . esc_html(implode(', ', $level_ids)) . '</code>.</p>';
    echo '<form method="post">';
    wp_nonce_field('eg_pmp_pages_run');
    echo '<p><button class="button button-primary" name="eg_pmp_pages_run" value="1">Create / update &amp; lock pages</button></p>';
    echo '</form>';

    echo '<h2>2. Upload locked documents</h2>';
    echo '<p>Files go into PMPro’s protected folder (not public Media Library URLs). Only paid members can download.</p>';
    echo '<form method="post" enctype="multipart/form-data">';
    wp_nonce_field('eg_pmp_upload');
    echo '<p><label>Section <select name="eg_doc_type">';
    foreach ($types as $slug => $meta) {
        echo '<option value="' . esc_attr($slug) . '">' . esc_html($meta['title']) . '</option>';
    }
    echo '</select></label></p>';
    echo '<p><label>File <input type="file" name="eg_doc_file" accept=".pdf,.doc,.docx,.odt,.txt,.zip,.png,.jpg,.jpeg" required></label></p>';
    echo '<p><button class="button button-primary" name="eg_pmp_upload" value="1">Upload locked file</button></p>';
    echo '</form>';

    if (function_exists('eg_member_docs_list_files') && function_exists('eg_member_docs_ensure_dirs')) {
        eg_member_docs_ensure_dirs();
        echo '<h2>3. Current locked files</h2>';
        foreach (array_keys($types) as $slug) {
            $files = eg_member_docs_list_files($slug);
            echo '<h3>' . esc_html($types[$slug]['title']) . ' <small>(' . count($files) . ')</small></h3>';
            if (!$files) {
                echo '<p><em>None yet.</em></p>';
                continue;
            }
            echo '<ul>';
            foreach ($files as $file) {
                echo '<li>' . esc_html($file['name']) . ' · ' . esc_html(size_format((int) $file['size'])) . '</li>';
            }
            echo '</ul>';
        }
    }

    echo '<p><a href="' . esc_url(home_url('/mitglieder/positionen/')) . '" target="_blank" rel="noopener">Open Positionen</a> · ';
    echo '<a href="' . esc_url(home_url('/mitglieder/dossiers/')) . '" target="_blank" rel="noopener">Dossiers</a> · ';
    echo '<a href="' . esc_url(home_url('/mitglieder/studien/')) . '" target="_blank" rel="noopener">Studien</a></p>';
    echo '</div>';
}

function eg_pmp_pages_run()
{
    $parent_id = eg_pmp_ensure_page(
        'Mitglieder',
        'mitglieder',
        0,
        '<p><span class="de">Mitgliederbereich der Eurasien Gesellschaft e. V.</span><span class="en" hidden>Members area of Eurasien Gesellschaft e. V.</span></p><p><a href="/app/mitglieder/positionen/"><span class="de">Positionen</span><span class="en" hidden>Positions</span></a> · <a href="/app/mitglieder/dossiers/"><span class="de">Dossiers</span><span class="en" hidden>Dossiers</span></a> · <a href="/app/mitglieder/studien/"><span class="de">Studien</span><span class="en" hidden>Studies</span></a></p>'
    );

    $pages = array(
        'positionen' => array(
            'title'   => 'Positionen',
            'content' => eg_pmp_page_body('positionen'),
        ),
        'dossiers' => array(
            'title'   => 'Dossiers',
            'content' => eg_pmp_page_body('dossiers'),
        ),
        'studien' => array(
            'title'   => 'Studien',
            'content' => eg_pmp_page_body('studien'),
        ),
    );

    $ids = array('parent' => $parent_id);
    $level_ids = function_exists('eg_member_docs_level_ids') ? eg_member_docs_level_ids() : array(1, 2);

    foreach ($pages as $slug => $meta) {
        $ids[$slug] = eg_pmp_ensure_page($meta['title'], $slug, $parent_id, $meta['content']);
        eg_pmp_restrict_page($ids[$slug], $level_ids);
    }

    if (function_exists('eg_member_docs_ensure_dirs')) {
        eg_member_docs_ensure_dirs();
    }

    update_option('eg_pmp_page_ids', $ids);
    update_option('eg_member_docs_level_ids', $level_ids);
    flush_rewrite_rules(false);

    return 'Pages ready and locked to levels [' . implode(', ', $level_ids) . ']. IDs: ' . wp_json_encode($ids);
}

function eg_pmp_page_body($type)
{
    $types = function_exists('eg_member_docs_types') ? eg_member_docs_types() : array();
    $meta  = isset($types[$type]) ? $types[$type] : array('title' => ucfirst($type), 'intro' => '');
    $title = esc_html($meta['title']);
    $intro = esc_html($meta['intro']);
    return "<p><strong>{$title}</strong></p><p>{$intro}</p>\n\n[eg_member_docs type=\"{$type}\"]\n";
}

function eg_pmp_restrict_page($page_id, $level_ids)
{
    $page_id = (int) $page_id;
    $level_ids = array_values(array_unique(array_map('intval', (array) $level_ids)));
    if (!$page_id || !$level_ids) {
        return;
    }
    if (function_exists('pmpro_update_post_level_restrictions')) {
        pmpro_update_post_level_restrictions($page_id, $level_ids);
        return;
    }
    // Fallback: write PMPro memberships_pages table.
    global $wpdb;
    if (empty($wpdb->pmpro_memberships_pages)) {
        return;
    }
    $wpdb->delete($wpdb->pmpro_memberships_pages, array('page_id' => $page_id), array('%d'));
    foreach ($level_ids as $level_id) {
        $wpdb->insert(
            $wpdb->pmpro_memberships_pages,
            array(
                'membership_id' => $level_id,
                'page_id'       => $page_id,
            ),
            array('%d', '%d')
        );
    }
}

function eg_pmp_handle_upload()
{
    $type = isset($_POST['eg_doc_type']) ? sanitize_key(wp_unslash($_POST['eg_doc_type'])) : '';
    $types = function_exists('eg_member_docs_types') ? eg_member_docs_types() : array();
    if (!isset($types[$type])) {
        return 'Unknown section.';
    }
    if (empty($_FILES['eg_doc_file']) || empty($_FILES['eg_doc_file']['tmp_name'])) {
        return 'No file received.';
    }
    if (!function_exists('pmpro_get_restricted_file_path')) {
        return 'PMPro restricted files API missing. Update Paid Memberships Pro.';
    }

    eg_member_docs_ensure_dirs();
    $dest_dir = pmpro_get_restricted_file_path($type);
    if (!$dest_dir) {
        return 'Could not resolve restricted folder.';
    }

    $original = sanitize_file_name(wp_unslash($_FILES['eg_doc_file']['name']));
    if ($original === '') {
        return 'Invalid file name.';
    }

    $check = wp_check_filetype_and_ext($_FILES['eg_doc_file']['tmp_name'], $original);
    $allowed = array('pdf', 'doc', 'docx', 'odt', 'txt', 'zip', 'png', 'jpg', 'jpeg');
    $ext = strtolower((string) pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        return 'File type not allowed.';
    }

    $target = trailingslashit($dest_dir) . $original;
    if (!move_uploaded_file($_FILES['eg_doc_file']['tmp_name'], $target)) {
        return 'Upload failed.';
    }
    return 'Uploaded ' . $original . ' to locked ' . $type . ' folder.';
}

function eg_pmp_ensure_page($title, $slug, $parent = 0, $content = '')
{
    $path = $parent ? 'mitglieder/' . $slug : $slug;
    $existing = get_page_by_path($path);
    if ($existing) {
        wp_update_post(array(
            'ID'           => $existing->ID,
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => 'publish',
            'post_parent'  => $parent,
        ));
        return (int) $existing->ID;
    }

    return (int) wp_insert_post(array(
        'post_title'   => $title,
        'post_name'    => $slug,
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_parent'  => $parent,
        'post_content' => $content,
    ));
}
