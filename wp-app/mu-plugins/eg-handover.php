<?php
/**
 * Plugin Name: EG Handover
 * Description: Tidy WordPress for the website team: old WordPress pages of analyses, people, videos, regions and topics forward (301) to the real website pages; menus that lead nowhere are hidden for Redakteure; a start box on the dashboard with the most important links.
 * Author: d4rl1ngt0n
 */

if (!defined('ABSPATH')) {
    exit;
}

function eg_ho_public($path = '')
{
    return 'https://eurasiengesellschaft.de/' . ltrim((string) $path, '/');
}

/* ---------- forward old WordPress pages to the website (runs before eg-brochure-bridge) ---------- */

add_action('template_redirect', static function () {
    if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return;
    }
    $target = '';
    if (is_singular('eg_analyse')) {
        $id = get_queried_object_id();
        $fmts = taxonomy_exists('eg_format') ? wp_get_object_terms($id, 'eg_format', array('fields' => 'slugs')) : array();
        if (!is_wp_error($fmts) && array_intersect(array('positionen', 'dossiers', 'studien'), (array) $fmts)) {
            return; // members-only analyses keep their WordPress page (members area)
        }
        $anchor = (string) get_post_meta($id, 'eg_feed_id', true);
        $target = eg_ho_public('analysen.html') . ($anchor !== '' ? '#' . rawurlencode($anchor) : '');
    } elseif (is_singular('eg_person')) {
        $slug = (string) get_post_field('post_name', get_queried_object_id());
        $target = eg_ho_public($slug !== '' ? 'personen/' . $slug . '.html' : 'vorstand.html');
    } elseif (is_post_type_archive('eg_person')) {
        $target = eg_ho_public('vorstand.html');
    } elseif (is_singular('eg_recording') || is_post_type_archive('eg_recording')) {
        $target = eg_ho_public('mediathek.html');
    } elseif (is_tax('eg_region')) {
        $term = get_queried_object();
        $key = $term ? (string) get_term_meta($term->parent ? $term->parent : $term->term_id, 'eg_key', true) : '';
        $target = eg_ho_public('regionen.html') . ($key !== '' ? '#' . $key : '');
    } elseif (is_tax('eg_topic')) {
        $term = get_queried_object();
        $map = array(
            'geopolitik' => 'themen/geopolitik.html', 'energie' => 'themen/energie.html', 'wirtschaft' => 'themen/wirtschaft.html',
            'wissenschaft' => 'themen/wissenschaft.html', 'kultur' => 'kultur.html', 'lander-gesellschaften' => 'laender-gesellschaften.html',
        );
        $target = eg_ho_public($term && isset($map[$term->slug]) ? $map[$term->slug] : 'themen.html');
    } elseif (is_category() || is_tag() || is_author() || is_date() || is_singular('post')) { // author pages would also show login names
        $target = eg_ho_public('analysen.html');
    }
    if ($target !== '') {
        wp_redirect($target, 301, 'Eurasien Gesellschaft');
        exit;
    }
}, 5);

/* ---------- admin: hide what leads nowhere (Redakteure; administrators keep everything) ---------- */

add_action('admin_menu', static function () {
    if (current_user_can('manage_options')) {
        return;
    }
    $hide = array(
        'edit.php',                               // Beiträge: not shown on the website
        'edit.php?post_type=eg_material',         // Materialien: unused LinkedIn import
        'edit.php?post_type=elementor_library',   // Elementor templates
        'elementor',
        'edit.php?post_type=e-floating-buttons',
        'edit.php?post_type=tribe_events',        // second calendar; the website uses "Veranstaltungen" (eg_event)
        'edit-comments.php',
        'tools.php',
        'edit.php?post_type=page',                // WordPress pages: login/account pages, not the website texts
    );
    foreach ($hide as $slug) {
        remove_menu_page($slug);
    }
}, 999);

/* Opening a hidden screen directly shows a short note instead of a dead end. */
add_action('current_screen', static function ($screen) {
    if (current_user_can('manage_options') || !$screen) {
        return;
    }
    $dead = array('post' => 'Beiträge', 'eg_material' => 'Materialien', 'elementor_library' => 'Elementor-Vorlagen', 'tribe_events' => 'diesen Kalender');
    if ($screen->base === 'edit' || $screen->base === 'post') {
        $type = (string) $screen->post_type;
        if (isset($dead[$type])) {
            wp_die('<h1>Hier bitte nichts anlegen</h1><p>' . esc_html($dead[$type]) . ' erscheinen nicht auf der Website. Veranstaltungen, Analysen, Aufzeichnungen (Videos), Personen und die Texte der Website haben eigene Menüpunkte links.</p><p><a class="button button-primary" href="' . esc_url(admin_url()) . '">Zum Dashboard</a></p>', 'Nicht verwendet', array('response' => 200));
        }
    }
});

/* ---------- dashboard: start box ---------- */

add_action('wp_dashboard_setup', static function () {
    wp_add_dashboard_widget('eg_ho_start', 'Website der Eurasien Gesellschaft', static function () {
        $links = array(
            array('edit.php?post_type=eg_event', 'Veranstaltungen', 'Termine und Rückblicke: erscheinen im Kalender, auf der Startseite und der Veranstaltungsseite.'),
            array('edit.php?post_type=eg_analyse', 'Analysen', 'Stellungnahmen, LinkedIn-Beiträge, Mitteilungen; mit Regionen für die Regionen-Seite.'),
            array('edit.php?post_type=eg_recording', 'Aufzeichnungen', 'Videos für die Mediathek und die Regionen-Seite.'),
            array('edit.php?post_type=eg_person', 'Personen', 'Vorstand, Profilseiten und Publikationen.'),
            array('admin.php?page=eg-texte', 'Website → Texte', 'Feste Texte: Startseite, Über uns, Vorstand, Mitgliedschaft, Impressum, Datenschutz.'),
            array('admin.php?page=eg-website', 'Website → Logo', 'Logo der Website.'),
        );
        echo '<p>Alles, was hier gespeichert wird, ist nach wenigen Sekunden auf <a href="' . esc_url(eg_ho_public()) . '" target="_blank" rel="noopener">eurasiengesellschaft.de</a> sichtbar.</p><ul style="margin:0">';
        foreach ($links as $l) {
            echo '<li style="margin:0 0 10px"><a href="' . esc_url(admin_url($l[0])) . '"><strong>' . esc_html($l[1]) . '</strong></a><br><span style="color:#646970">' . esc_html($l[2]) . '</span></li>';
        }
        echo '</ul><p style="color:#646970">Englische Felder sind freiwillig: Bleiben sie leer, zeigt die englische Seite den deutschen Text.</p>';
    });
});
