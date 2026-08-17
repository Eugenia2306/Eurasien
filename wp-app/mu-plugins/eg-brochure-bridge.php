<?php
/**
 * Plugin Name: EG Brochure Bridge
 * Description: Send public brochure routes (Analysen, etc.) to the static site so logged-in WP chrome matches guests.
 * Author: d4rl1ngt0n
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Public brochure root (static site one level above /app/).
 */
function eg_brochure_base_url()
{
    if (function_exists('eg_brochure_base')) {
        return eg_brochure_base();
    }
    $home = untrailingslashit(home_url());
    if (substr($home, -4) === '/app') {
        return substr($home, 0, -4);
    }
    return $home;
}

/**
 * Absolute URL on the static brochure.
 */
function eg_brochure_public_url($path = '')
{
    $base = eg_brochure_base_url();
    $path = ltrim((string) $path, '/');
    return $path ? $base . '/' . $path : $base . '/';
}

/** Back-compat alias for older theme templates. */
if (!function_exists('eg_brochure_url')) {
    function eg_brochure_url($path = '')
    {
        return eg_brochure_public_url($path);
    }
}

/**
 * Map WP person slugs to static brochure profile paths.
 *
 * @return array<string, string>
 */
function eg_brochure_person_paths()
{
    return array(
        'alexander-rahr' => 'personen/alexander-rahr.html',
        'alexander-neu' => 'personen/alexander-neu.html',
        'christoph-polajner' => 'personen/christoph-polajner.html',
        'christian-wipperfuerth' => 'personen/christian-wipperfuerth.html',
        'andreas-schraps' => 'personen/andreas-schraps.html',
    );
}

/**
 * Public brochure pages that must not render under /app/ (broken theme stubs).
 *
 * @return array<string, string> WP page slug => brochure relative path
 */
function eg_brochure_page_paths()
{
    return array(
        'analysen' => 'analysen.html',
        'vorstand' => 'vorstand.html',
        'mission' => 'mission.html',
        'partner' => 'partner.html',
        'mitgliedschaft' => 'mitgliedschaft.html',
        'mitgliedschaft-vorteile' => 'mitgliedschaft-vorteile.html',
        'themen' => 'themen.html',
        'kultur' => 'kultur.html',
        'regionen' => 'regionen.html',
        'impressum' => 'impressum.html',
        'geopolitik' => 'themen/geopolitik.html',
        'energie' => 'themen/energie.html',
        'wirtschaft' => 'themen/wirtschaft.html',
        'wissenschaft' => 'themen/wissenschaft.html',
        'laender-gesellschaften' => 'laender-gesellschaften.html',
        'gesellschaftsnachrichten' => 'gesellschaftsnachrichten.html',
        'aufzeichnungen' => 'aufzeichnungen.html',
    );
}

/**
 * Redirect WP brochure stubs (and the unused PMPro levels table) to the static site.
 */
add_action('template_redirect', function () {
    if (is_admin()) {
        return;
    }

    $target = '';
    $uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
    $path = (string) wp_parse_url($uri, PHP_URL_PATH);

    /* Exact old stub path only (the Anmelden page must not be the site front page). */
    if (preg_match('#/app/anmelden/?$#', $path) || preg_match('#/anmelden/?$#', $path)) {
        $target = function_exists('eg_member_login_url')
            ? eg_member_login_url()
            : home_url('/login/?redirect_to=' . rawurlencode(home_url('/membership-account/')));
    } elseif (is_post_type_archive('eg_analyse')) {
        $target = eg_brochure_public_url('analysen.html');
    } elseif (is_post_type_archive('eg_event')) {
        $target = eg_brochure_public_url('veranstaltungen.html');
    } elseif (is_singular('eg_analyse')) {
        return;
    } elseif (is_singular('eg_event')) {
        /* Public list is the brochure; deep-link to the event card. */
        $target = eg_brochure_public_url('veranstaltungen.html');
        $obj = get_queried_object();
        if ($obj && !empty($obj->post_name)) {
            $target .= '#' . $obj->post_name;
        }
    } elseif (is_singular('eg_person')) {
        $person = get_queried_object();
        if ($person && !empty($person->post_name)) {
            $map = eg_brochure_person_paths();
            if (isset($map[$person->post_name])) {
                $target = eg_brochure_public_url($map[$person->post_name]);
            }
        }
    } elseif (is_page()) {
        $page = get_queried_object();
        if ($page && !empty($page->post_name)) {
            if ($page->post_name === 'membership-levels') {
                if (function_exists('eg_membership_signup_url')) {
                    $target = eg_membership_signup_url();
                } else {
                    $target = eg_brochure_public_url('mitgliedschaft.html') . '#membership-registration';
                }
            } else {
                $pages = eg_brochure_page_paths();
                if (isset($pages[$page->post_name])) {
                    $target = eg_brochure_public_url($pages[$page->post_name]);
                }
            }
        }
    }

    if ($target === '') {
        return;
    }

    // Preserve statement filter deep-links when present (Analysen only).
    if (strpos($target, 'analysen.html') !== false && !empty($_GET['format'])) {
        $format = sanitize_title(wp_unslash($_GET['format']));
        if ($format === 'stellungnahmen') {
            $target .= '#an-fmt-stellungnahmen';
        } elseif ($format === 'aktuelles') {
            $target .= '#an-fmt-aktuelles';
        }
    }

    if (function_exists('eg_member_handoff_redirect')) {
        eg_member_handoff_redirect($target);
    }
    if (!headers_sent()) {
        nocache_headers();
        header('Location: ' . $target, true, 302);
    }
    exit;
}, 5);

/**
 * Rewrite hard-coded WP menu items that still point at brochure stubs under /app/.
 */
add_filter('wp_nav_menu_objects', function ($items) {
    $page_map = eg_brochure_page_paths();
    $person_map = eg_brochure_person_paths();
    foreach ($items as $item) {
        if (empty($item->url)) {
            continue;
        }
        $item_path = wp_parse_url($item->url, PHP_URL_PATH);
        if (!is_string($item_path)) {
            continue;
        }
        $item_path = untrailingslashit($item_path);
        $slug = '';
        if (preg_match('#(?:^|/app)?/([^/]+)$#', $item_path, $m)) {
            $slug = $m[1];
        }
        if ($slug !== '' && isset($page_map[$slug])) {
            $item->url = eg_brochure_public_url($page_map[$slug]);
            continue;
        }
        if (preg_match('#(?:^|/app)?/personen/([^/]+)$#', $item_path, $m) && isset($person_map[$m[1]])) {
            $item->url = eg_brochure_public_url($person_map[$m[1]]);
        }
    }
    return $items;
}, 20);
