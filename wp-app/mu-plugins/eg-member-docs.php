<?php
/**
 * Plugin Name: EG Member Docs
 * Description: Membership-gated Positionen / Dossiers / Studien libraries (Analyse posts + restricted files).
 * Author: d4rl1ngt0n
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Paid level IDs that unlock member documents.
 */
function eg_member_docs_level_ids()
{
    $cached = get_option('eg_member_docs_level_ids');
    if (is_array($cached) && $cached) {
        return array_map('intval', $cached);
    }

    $ids = array();
    if (function_exists('pmpro_getAllLevels')) {
        foreach ((array) pmpro_getAllLevels(true, true) as $level) {
            $name = isset($level->name) ? strtolower($level->name) : '';
            if (
                strpos($name, 'leser') !== false
                || strpos($name, 'verein') !== false
                || strpos($name, 'reader') !== false
                || strpos($name, 'member') !== false
            ) {
                $ids[] = (int) $level->id;
            }
        }
    }
    if (!$ids) {
        $ids = array(1, 2);
    }
    return $ids;
}

function eg_member_docs_types()
{
    return array(
        'positionen' => array(
            'title'       => 'Positionen',
            'title_en'    => 'Positions',
            'intro'       => 'Grundsatz- und Positionspapiere der Eurasien Gesellschaft e. V.',
            'intro_en'    => 'Position papers of Eurasien Gesellschaft e. V.',
            'format_name' => 'Positionen',
            'term_slugs'  => array('positionen', 'positions'),
        ),
        'dossiers' => array(
            'title'       => 'Dossiers',
            'title_en'    => 'Dossiers',
            'intro'       => 'Länder- und Themendossiers für Mitglieder.',
            'intro_en'    => 'Country and thematic dossiers for members.',
            'format_name' => 'Dossiers',
            'term_slugs'  => array('dossiers'),
        ),
        'studien' => array(
            'title'       => 'Studien',
            'title_en'    => 'Studies',
            'intro'       => 'Studien und Fachgutachten für Mitglieder.',
            'intro_en'    => 'Studies and expert reports for members.',
            'format_name' => 'Studien',
            'term_slugs'  => array('studien', 'studies'),
        ),
    );
}

function eg_member_docs_user_can_access()
{
    if (!is_user_logged_in()) {
        return false;
    }
    if (current_user_can('manage_options')) {
        return true;
    }
    if (!function_exists('pmpro_hasMembershipLevel')) {
        return false;
    }
    return pmpro_hasMembershipLevel(eg_member_docs_level_ids()) || pmpro_hasMembershipLevel();
}

/**
 * Ensure Format taxonomy terms exist for the three locked libraries.
 */
function eg_member_docs_ensure_format_terms()
{
    if (!taxonomy_exists('eg_format')) {
        return;
    }
    foreach (eg_member_docs_types() as $slug => $meta) {
        if (!term_exists($slug, 'eg_format')) {
            wp_insert_term($meta['format_name'], 'eg_format', array('slug' => $slug));
        }
    }
}

add_action('init', 'eg_member_docs_ensure_format_terms', 30);

/**
 * Safety net: any active member can open EG mitglieder pages.
 */
add_filter('pmpro_has_membership_access_filter', function ($hasaccess, $post, $user, $post_membership_levels) {
    if ($hasaccess || !$post || !$user || empty($user->ID)) {
        return $hasaccess;
    }
    $ids = get_option('eg_pmp_page_ids');
    if (!is_array($ids) || !$ids) {
        return $hasaccess;
    }
    $page_ids = array_map('intval', array_values($ids));
    if (!in_array((int) $post->ID, $page_ids, true)) {
        return $hasaccess;
    }
    if (function_exists('pmpro_hasMembershipLevel') && pmpro_hasMembershipLevel()) {
        return true;
    }
    return $hasaccess;
}, 20, 4);

/**
 * Allow paid members to download files from our restricted folders.
 */
add_filter('pmpro_can_access_restricted_file', function ($can_access, $file_dir, $file = '') {
    $types = array_keys(eg_member_docs_types());
    if (!in_array($file_dir, $types, true)) {
        return $can_access;
    }
    return eg_member_docs_user_can_access();
}, 10, 3);

function eg_member_docs_ensure_dirs()
{
    if (!function_exists('pmpro_get_restricted_file_path')) {
        return array();
    }
    $paths = array();
    foreach (array_keys(eg_member_docs_types()) as $dir) {
        $path = pmpro_get_restricted_file_path($dir);
        if ($path) {
            if (!file_exists($path)) {
                wp_mkdir_p($path);
            }
            $paths[$dir] = $path;
        }
    }
    return $paths;
}

function eg_member_docs_list_files($type)
{
    if (!function_exists('pmpro_get_restricted_file_path')) {
        return array();
    }
    $dir = pmpro_get_restricted_file_path($type);
    if (!$dir || !is_dir($dir)) {
        return array();
    }
    $files = array();
    foreach (scandir($dir) as $name) {
        if ($name === '.' || $name === '..' || $name[0] === '.') {
            continue;
        }
        $full = trailingslashit($dir) . $name;
        if (!is_file($full)) {
            continue;
        }
        $files[] = array(
            'name'  => $name,
            'size'  => filesize($full),
            'mtime' => filemtime($full),
            'url'   => add_query_arg(
                array(
                    'pmpro_restricted_file'     => $name,
                    'pmpro_restricted_file_dir' => $type,
                ),
                home_url('/')
            ),
        );
    }
    usort($files, function ($a, $b) {
        return $b['mtime'] <=> $a['mtime'];
    });
    return $files;
}

/**
 * Published Analyse posts assigned to this Format (Positionen / Dossiers / Studien).
 */
function eg_member_docs_list_posts($type)
{
    $types = eg_member_docs_types();
    if (!isset($types[$type]) || !post_type_exists('eg_analyse') || !taxonomy_exists('eg_format')) {
        return array();
    }

    eg_member_docs_ensure_format_terms();

    $slugs = $types[$type]['term_slugs'];
    $query = new WP_Query(
        array(
            'post_type'              => 'eg_analyse',
            'post_status'            => 'publish',
            'posts_per_page'         => 100,
            'orderby'                => 'date',
            'order'                  => 'DESC',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'tax_query'              => array(
                array(
                    'taxonomy' => 'eg_format',
                    'field'    => 'slug',
                    'terms'    => $slugs,
                ),
            ),
        )
    );

    $posts = array();
    foreach ($query->posts as $post) {
        $posts[] = array(
            'id'      => (int) $post->ID,
            'title'   => get_the_title($post),
            'excerpt' => wp_trim_words(get_the_excerpt($post), 28, '…'),
            'date'    => get_the_date('', $post),
            'url'     => get_permalink($post),
        );
    }
    return $posts;
}

/**
 * True when an Analyse post uses a paid Format.
 */
function eg_member_docs_post_is_gated($post_id)
{
    if (!taxonomy_exists('eg_format')) {
        return false;
    }
    $slugs = wp_get_post_terms((int) $post_id, 'eg_format', array('fields' => 'slugs'));
    if (is_wp_error($slugs) || !$slugs) {
        return false;
    }
    $locked = array();
    foreach (eg_member_docs_types() as $meta) {
        foreach ($meta['term_slugs'] as $slug) {
            $locked[] = $slug;
        }
    }
    return (bool) array_intersect($slugs, $locked);
}

/**
 * Block guests from reading gated Analyse singles; send them to the library gate.
 */
add_action('template_redirect', function () {
    if (!is_singular('eg_analyse')) {
        return;
    }
    $post_id = get_queried_object_id();
    if (!$post_id || !eg_member_docs_post_is_gated($post_id)) {
        return;
    }
    if (eg_member_docs_user_can_access()) {
        return;
    }

    $slugs = wp_get_post_terms($post_id, 'eg_format', array('fields' => 'slugs'));
    $hub = 'positionen';
    foreach (eg_member_docs_types() as $type => $meta) {
        if (array_intersect((array) $slugs, $meta['term_slugs'])) {
            $hub = $type;
            break;
        }
    }
    wp_safe_redirect(home_url('/mitglieder/' . $hub . '/'), 302);
    exit;
}, 8);

function eg_member_docs_styles()
{
    return <<<'CSS'
.eg-member-docs{max-width:760px;margin:0 auto 2rem;color:#0b2338}
.eg-member-docs__intro{margin:0 0 1.25rem;color:#4a5d73;font-size:1.05rem;line-height:1.55}
.eg-member-docs__nav{display:flex;flex-wrap:wrap;gap:8px 18px;margin:0 0 1.5rem;padding-bottom:12px;border-bottom:1px solid rgba(11,35,56,.12)}
.eg-member-docs__nav a{color:#0032a0;text-decoration:none;font-weight:600;font-size:.95rem}
.eg-member-docs__nav a:hover{text-decoration:underline}
.eg-member-docs__nav a.is-active{color:#0b2338;text-decoration:underline;text-underline-offset:4px}
.eg-member-docs__section{margin:0 0 2rem}
.eg-member-docs__h{font-size:1.05rem;margin:0 0 12px;color:#0b2338}
.eg-member-docs__list{list-style:none;padding:0;margin:0}
.eg-member-docs__item{padding:14px 0;border-bottom:1px solid rgba(11,35,56,.1);display:flex;flex-wrap:wrap;gap:10px 20px;align-items:baseline;justify-content:space-between}
.eg-member-docs__name{font-weight:700;color:#0b2338;word-break:break-word;text-decoration:none}
.eg-member-docs__name:hover{color:#c8102e}
.eg-member-docs__excerpt{display:block;margin-top:6px;color:#4a5d73;font-size:.95em;line-height:1.45;font-weight:400}
.eg-member-docs__meta{display:block;margin-top:4px;opacity:.7;font-size:.9em;color:#4a5d73}
.eg-member-docs__dl{display:inline-flex;align-items:center;gap:6px;color:#0032a0;font-weight:600;text-decoration:none;white-space:nowrap}
.eg-member-docs__dl:hover{text-decoration:underline;color:#c8102e}
.eg-member-docs__empty{color:#4a5d73;line-height:1.55}
.eg-member-docs__admin{margin-top:.75rem;font-size:.9rem;opacity:.85}
html:not([data-eg-lang="en"]) .eg-member-docs .en{display:none!important}
html[data-eg-lang="en"] .eg-member-docs .de{display:none!important}
CSS;
}

/**
 * Shortcode: [eg_member_docs type="positionen"]
 */
add_shortcode('eg_member_docs', function ($atts) {
    $atts = shortcode_atts(array('type' => 'positionen'), $atts, 'eg_member_docs');
    $type = sanitize_key($atts['type']);
    $types = eg_member_docs_types();
    if (!isset($types[$type])) {
        return '';
    }

    if (!eg_member_docs_user_can_access()) {
        return '';
    }

    eg_member_docs_ensure_dirs();
    eg_member_docs_ensure_format_terms();
    $meta  = $types[$type];
    $posts = eg_member_docs_list_posts($type);
    $files = eg_member_docs_list_files($type);

    ob_start();
    echo '<style id="eg-member-docs-css">' . eg_member_docs_styles() . '</style>';
    echo '<div class="eg-member-docs" data-type="' . esc_attr($type) . '">';
    echo '<p class="eg-member-docs__intro"><span class="de">' . esc_html($meta['intro']) . '</span><span class="en" hidden>' . esc_html($meta['intro_en']) . '</span></p>';
    echo '<nav class="eg-member-docs__nav" aria-label="Mitgliederbereich">';
    foreach ($types as $slug => $info) {
        $url = home_url('/mitglieder/' . $slug . '/');
        $active = $slug === $type ? ' is-active' : '';
        echo '<a class="' . esc_attr(trim($active)) . '" href="' . esc_url($url) . '">';
        echo '<span class="de">' . esc_html($info['title']) . '</span>';
        echo '<span class="en" hidden>' . esc_html($info['title_en']) . '</span>';
        echo '</a>';
    }
    echo '</nav>';

    echo '<div class="eg-member-docs__section">';
    echo '<h2 class="eg-member-docs__h"><span class="de">Beiträge</span><span class="en" hidden>Articles</span></h2>';
    if (!$posts) {
        echo '<p class="eg-member-docs__empty"><span class="de">Noch keine Beiträge in diesem Format. Legen Sie unter Analysen einen Beitrag an und wählen Sie das Format „' . esc_html($meta['format_name']) . '“.</span><span class="en" hidden>No articles in this format yet. Create an Analyse post and assign the Format “' . esc_html($meta['title_en']) . '”.</span></p>';
        if (current_user_can('manage_options')) {
            echo '<p class="eg-member-docs__admin"><em>Admin: Analysen → Add New → Formate → ' . esc_html($meta['format_name']) . '</em></p>';
        }
    } else {
        echo '<ul class="eg-member-docs__list">';
        foreach ($posts as $item) {
            echo '<li class="eg-member-docs__item">';
            echo '<div><a class="eg-member-docs__name" href="' . esc_url($item['url']) . '">' . esc_html($item['title']) . '</a>';
            if (!empty($item['excerpt'])) {
                echo '<span class="eg-member-docs__excerpt">' . esc_html($item['excerpt']) . '</span>';
            }
            echo '<span class="eg-member-docs__meta">' . esc_html($item['date']) . '</span></div>';
            echo '<a class="eg-member-docs__dl" href="' . esc_url($item['url']) . '"><span class="de">Lesen</span><span class="en" hidden>Read</span></a>';
            echo '</li>';
        }
        echo '</ul>';
    }
    echo '</div>';

    echo '<div class="eg-member-docs__section">';
    echo '<h2 class="eg-member-docs__h"><span class="de">Dateien</span><span class="en" hidden>Files</span></h2>';
    if (!$files) {
        echo '<p class="eg-member-docs__empty"><span class="de">Noch keine Dateien hinterlegt.</span><span class="en" hidden>No files uploaded yet.</span></p>';
        if (current_user_can('manage_options')) {
            echo '<p class="eg-member-docs__admin"><em>Admin: Tools → EG Member Pages to upload PDFs.</em></p>';
        }
    } else {
        echo '<ul class="eg-member-docs__list">';
        foreach ($files as $file) {
            $size = size_format((int) $file['size']);
            $date = date_i18n(get_option('date_format'), (int) $file['mtime']);
            echo '<li class="eg-member-docs__item">';
            echo '<div><span class="eg-member-docs__name">' . esc_html($file['name']) . '</span>';
            echo '<span class="eg-member-docs__meta">' . esc_html($date) . ' · ' . esc_html($size) . '</span></div>';
            echo '<a class="eg-member-docs__dl" href="' . esc_url($file['url']) . '"><span class="de">Öffnen / Download</span><span class="en" hidden>Open / Download</span></a>';
            echo '</li>';
        }
        echo '</ul>';
    }
    echo '</div>';

    echo '</div>';
    return ob_get_clean();
});
