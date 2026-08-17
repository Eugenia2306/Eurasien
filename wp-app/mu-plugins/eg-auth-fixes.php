<?php
/**
 * Plugin Name: EG Auth Fixes
 * Description: Fix login redirects and gated-page CTAs for Paid Memberships Pro under /app/.
 * Author: d4rl1ngt0n
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Member login URL (PMPro /login/), always with a safe redirect_to.
 *
 * @param string $redirect Absolute or path redirect after login.
 */
function eg_member_login_url($redirect = '')
{
    if (!$redirect) {
        $redirect = home_url('/membership-account/');
    }
    return add_query_arg(
        'redirect_to',
        $redirect,
        home_url('/login/')
    );
}

/**
 * Reading settings mistakenly used the old "Anmelden" stub as the WP front page,
 * so Login links resolved to /app/ and looked broken. Clear that front-page setting
 * once (do not make the login page the front page; that breaks the login form).
 */
add_action('init', function () {
    $front_id = (int) get_option('page_on_front');
    $front = $front_id ? get_post($front_id) : null;
    $slug = $front ? $front->post_name : '';

    /* Re-fix if front is still the broken anmelden stub OR login (bad prior fix). */
    if ($slug !== 'anmelden' && $slug !== 'login') {
        if ((int) get_option('eg_fixed_anmelden_front', 0) !== 2) {
            update_option('eg_fixed_anmelden_front', 2, false);
        }
        return;
    }

    update_option('show_on_front', 'posts');
    update_option('page_on_front', 0);
    update_option('eg_fixed_anmelden_front', 2, false);
}, 2);

/**
 * After successful login, land on the membership account (not the WP/static home).
 */
add_filter('login_redirect', function ($redirect_to, $requested, $user) {
    if (is_wp_error($user) || empty($user->ID)) {
        return $redirect_to;
    }
    $account = home_url('/membership-account/');
    if (empty($requested) || $requested === home_url('/') || $requested === home_url()) {
        return $account;
    }
    // Keep deep-links to gated pages when present.
    if (is_string($requested) && strpos($requested, home_url('/mitglieder/')) === 0) {
        return $requested;
    }
    if (empty($redirect_to) || $redirect_to === admin_url() || $redirect_to === home_url('/') || $redirect_to === home_url()) {
        return $account;
    }
    return $redirect_to;
}, 99, 3);

add_filter('pmpro_login_redirect_url', function ($url) {
    return home_url('/membership-account/');
}, 99);

/**
 * Make the login form post to wp-login.php with a safe redirect_to.
 */
add_action('login_form', function () {
    // Ensure redirect_to field exists for core form; PMPro may use its own form.
}, 5);

add_filter('login_form_bottom', function ($content) {
    $redirect = isset($_REQUEST['redirect_to']) ? esc_url_raw(wp_unslash($_REQUEST['redirect_to'])) : home_url('/membership-account/');
    if (!$redirect) {
        $redirect = home_url('/membership-account/');
    }
    return $content . '<input type="hidden" name="redirect_to" value="' . esc_attr($redirect) . '" />';
});

/**
 * Brochure registration form (skip PMPro membership-levels table).
 */
function eg_membership_signup_url($plan = '')
{
    if (function_exists('eg_member_handoff_brochure_url')) {
        return eg_member_handoff_brochure_url($plan ? $plan : 'reader', array());
    }
    if (function_exists('eg_brochure_public_url')) {
        $url = eg_brochure_public_url('mitgliedschaft.html');
        if ($plan === 'expert' || $plan === 'verein' || $plan === '2') {
            $url = add_query_arg('plan', 'expert', $url);
        } elseif ($plan === 'reader' || $plan === '1') {
            $url = add_query_arg('plan', 'reader', $url);
        }
        return $url . '#membership-registration';
    }
    return '/mitgliedschaft.html#membership-registration';
}

/**
 * Stronger non-member message with Anmelden + Mitglied werden.
 */
add_filter('pmpro_non_member_text_filter', function ($text) {
    $login = esc_url(wp_login_url(get_permalink()));
    $signup = esc_url(eg_membership_signup_url());
    $bi = static function ($de, $en) {
        if (function_exists('eg_bi')) {
            return eg_bi($de, $en);
        }
        return '<span class="de">' . esc_html($de) . '</span><span class="en" hidden>' . esc_html($en) . '</span>';
    };
    $extra = '<p class="eg-gate-cta" style="margin-top:1.25rem">'
        . '<a class="pmpro_btn" style="margin-right:10px" href="' . $login . '">' . $bi('Anmelden', 'Log In') . '</a>'
        . '<a class="pmpro_btn" href="' . $signup . '">' . $bi('Mitglied werden', 'Become a member') . '</a>'
        . '</p>';
    return $text . $extra;
}, 20);

/**
 * Public site root (brochure), not the WordPress /app/ home.
 */
function eg_public_home_url()
{
    if (function_exists('eg_brochure_base_url')) {
        return trailingslashit(eg_brochure_base_url());
    }
    $home = untrailingslashit(home_url());
    if (substr($home, -4) === '/app') {
        return substr($home, 0, -4) . '/';
    }
    return trailingslashit($home);
}

/**
 * Resolve post-logout destination. Avoid wp_safe_redirect('/') which becomes /app/.
 *
 * @param string $redirect Requested redirect.
 * @return string Absolute URL on this host.
 */
function eg_member_logout_destination($redirect = '/')
{
    $public = eg_public_home_url();
    if ($redirect === '' || $redirect === '/' || $redirect === home_url('/') || $redirect === home_url()) {
        return $public;
    }
    $candidate = esc_url_raw($redirect);
    if (!$candidate) {
        return $public;
    }
    /* Allow brochure paths and /app/ account paths on the same host. */
    $host = wp_parse_url(home_url(), PHP_URL_HOST);
    $cand_host = wp_parse_url($candidate, PHP_URL_HOST);
    if ($cand_host && $host && strtolower((string) $cand_host) !== strtolower((string) $host)) {
        return $public;
    }
    if (strpos($candidate, '/') === 0) {
        $scheme = is_ssl() ? 'https://' : 'http://';
        return $scheme . $host . $candidate;
    }
    return $candidate;
}

/**
 * Send the browser to a URL (brochure root is outside /app/, so avoid wp_safe_redirect).
 *
 * @param string $url Absolute URL.
 */
function eg_member_logout_redirect($url)
{
    $url = $url ? $url : eg_public_home_url();
    if (function_exists('eg_member_handoff_redirect')) {
        eg_member_handoff_redirect($url);
    }
    if (!headers_sent()) {
        nocache_headers();
        header('Location: ' . $url, true, 302);
    }
    exit;
}

/**
 * One-click logout URL (nonce + redirect back to the public site).
 */
function eg_member_logout_url($redirect = '/')
{
    if (!$redirect) {
        $redirect = '/';
    }
    return wp_nonce_url(
        add_query_arg(
            array(
                'eg_logout'   => '1',
                'redirect_to' => $redirect,
            ),
            home_url('/')
        ),
        'eg-logout'
    );
}

/**
 * Handle /app/?eg_logout=1… and /app/login/?action=logout immediately.
 */
add_action('init', function () {
    $eg = !empty($_GET['eg_logout']);
    $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';
    $on_login_page = false;
    if (!empty($_SERVER['REQUEST_URI'])) {
        $path = (string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH);
        $on_login_page = (bool) preg_match('#/login/?$#', $path) || (bool) preg_match('#/wp-login\.php$#', $path);
    }
    if (!$eg && !($action === 'logout' && $on_login_page)) {
        return;
    }

    $dest = eg_member_logout_destination(
        !empty($_REQUEST['redirect_to']) ? wp_unslash($_REQUEST['redirect_to']) : '/'
    );

    if (is_user_logged_in()) {
        wp_logout();
    }

    eg_member_logout_redirect($dest);
}, 5);

/**
 * Skip the default "Do you really want to log out?" interstitial.
 * Any /wp-login.php?action=logout hit logs out and returns to the site.
 */
add_action('login_init', function () {
    $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';
    if ($action !== 'logout') {
        return;
    }

    $dest = eg_member_logout_destination(
        !empty($_REQUEST['redirect_to']) ? wp_unslash($_REQUEST['redirect_to']) : '/'
    );

    if (is_user_logged_in()) {
        wp_logout();
    }

    eg_member_logout_redirect($dest);
}, 0);

/**
 * Keep password reset on the branded /app/login/ page (not raw wp-login.php UI).
 */
add_action('login_init', function () {
    $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : 'login';
    $login_page = home_url('/login/');
    $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';

    // After requesting a reset, show the PMPro confirmation on /login/.
    if (isset($_GET['checkemail']) && $method === 'GET') {
        wp_safe_redirect(
            add_query_arg(
                array(
                    'checkemail' => sanitize_text_field(wp_unslash($_GET['checkemail'])),
                ),
                $login_page
            )
        );
        exit;
    }

    // GET lost-password screen -> branded form.
    if ($action === 'lostpassword' && $method === 'GET') {
        wp_safe_redirect(add_query_arg('action', 'reset_pass', $login_page));
        exit;
    }

    // Email reset link clicks (GET) -> branded set-new-password form.
    if (in_array($action, array('rp', 'resetpass'), true) && $method === 'GET' && empty($_POST)) {
        $args = array('action' => 'rp');
        if (!empty($_GET['key'])) {
            $args['key'] = sanitize_text_field(wp_unslash($_GET['key']));
        }
        if (!empty($_GET['login'])) {
            $args['login'] = sanitize_text_field(wp_unslash($_GET['login']));
        }
        if (!empty($_GET['error'])) {
            $args['error'] = sanitize_text_field(wp_unslash($_GET['error']));
        }
        wp_safe_redirect(add_query_arg($args, $login_page));
        exit;
    }
}, 1);

add_filter('lostpassword_redirect', function () {
    return home_url('/login/?checkemail=confirm');
});

/**
 * Always point password-reset emails at /app/login/ (PMPro only does this
 * when pmpro_login_form_used is present; our lost-password form posts to wp-login).
 */
add_filter('retrieve_password_message', function ($message, $key, $user_login) {
    $login_url = home_url('/login/');
    $replacements = array(
        network_site_url('wp-login.php'),
        site_url('wp-login.php'),
        home_url('wp-login.php'),
    );
    foreach ($replacements as $from) {
        if (!$from) {
            continue;
        }
        if (strpos($login_url, '?') !== false) {
            $message = str_replace($from . '?', $login_url . '&', $message);
        }
        $message = str_replace($from, $login_url, $message);
    }
    return $message;
}, 99, 3);

/**
 * Hostinger often drops mail() when From is not on the hosting domain.
 */
add_filter('wp_mail_from', function ($email) {
    $admin = get_option('admin_email');
    if (is_email($admin)) {
        return $admin;
    }
    $host = wp_parse_url(home_url(), PHP_URL_HOST);
    return $host ? 'noreply@' . $host : $email;
});

add_filter('wp_mail_from_name', function () {
    return 'Eurasien Gesellschaft';
});

/**
 * Lightweight auth status for the static brochure (same-origin cookies under /app/).
 */
add_action('wp_ajax_eg_auth_status', 'eg_auth_status_response');
add_action('wp_ajax_nopriv_eg_auth_status', 'eg_auth_status_response');

function eg_auth_status_response()
{
    nocache_headers();
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        $level_ids = array();
        if (function_exists('pmpro_getMembershipLevelsForUser')) {
            foreach ((array) pmpro_getMembershipLevelsForUser($user->ID) as $level) {
                if (!empty($level->id)) {
                    $level_ids[] = (int) $level->id;
                }
            }
        }
        $has_membership = (function_exists('pmpro_hasMembershipLevel') && pmpro_hasMembershipLevel())
            || count($level_ids) > 0;
        wp_send_json(
            array(
                'loggedIn'      => true,
                'email'         => $user->user_email,
                'display'       => $user->display_name,
                'hasMembership' => (bool) $has_membership,
                'levelIds'      => $level_ids,
                'logoutUrl'     => eg_member_logout_url('/'),
                'accountUrl'    => home_url('/membership-account/'),
                'membersUrl'    => home_url('/mitglieder/positionen/'),
            )
        );
    }
    wp_send_json(
        array(
            'loggedIn' => false,
            'loginUrl' => add_query_arg(
                'redirect_to',
                home_url('/membership-account/'),
                home_url('/login/')
            ),
        )
    );
}

/**
 * Visible chrome on /app/ pages: account / login / logout strip.
 */
add_action('wp_body_open', function () {
    if (is_admin()) {
        return;
    }

    $bi = static function ($de, $en) {
        if (function_exists('eg_bi')) {
            return eg_bi($de, $en);
        }
        return '<span class="de">' . esc_html($de) . '</span><span class="en" hidden>' . esc_html($en) . '</span>';
    };

    $account = esc_url(home_url('/membership-account/'));
    $signup = esc_url(eg_membership_signup_url());
    $login = esc_url(wp_login_url(home_url('/membership-account/')));
    $logout = esc_url(eg_member_logout_url('/'));
    echo '<div class="eg-app-bar" style="background:#0b1f33;color:#fff;padding:10px 16px;display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;font:600 14px/1.3 system-ui,sans-serif">';
    echo '<a href="/" style="color:#fff;text-decoration:none">' . $bi('Eurasien Gesellschaft · Mitglieder', 'Eurasien Gesellschaft · Members') . '</a>';
    echo '<nav style="display:flex;flex-wrap:wrap;gap:14px;align-items:center">';
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        $has_membership = function_exists('pmpro_hasMembershipLevel') && pmpro_hasMembershipLevel();
        echo '<span style="opacity:.85">' . esc_html($user->display_name ? $user->display_name : $user->user_email) . '</span>';
        echo '<a href="' . $account . '" style="color:#ffd77f;text-decoration:none">' . $bi('Mein Konto', 'My Account') . '</a>';
        if ($has_membership) {
            echo '<a href="' . esc_url(home_url('/mitglieder/positionen/')) . '" style="color:#fff;text-decoration:none">' . $bi('Mitgliederbereich', "Members' Area") . '</a>';
            echo '<a href="' . esc_url($account . '#eg-plan-change') . '" style="color:#fff;text-decoration:none">' . $bi('Plan ändern', 'Change plan') . '</a>';
        } else {
            echo '<a href="' . $signup . '" style="color:#fff;text-decoration:none">' . $bi('Mitglied werden', 'Become a member') . '</a>';
        }
        echo '<a href="' . $logout . '" style="color:#fff;text-decoration:none">' . $bi('Abmelden', 'Logout') . '</a>';
    } else {
        echo '<a href="' . $login . '" style="color:#ffd77f;text-decoration:none">' . $bi('Anmelden', 'Log In') . '</a>';
        echo '<a href="' . $signup . '" style="color:#fff;text-decoration:none">' . $bi('Mitglied werden', 'Become a member') . '</a>';
    }
    echo '<a href="/" style="color:#b7c6d6;text-decoration:none">' . $bi('Zur Website', 'To the website') . '</a>';
    echo '</nav></div>';
}, 5);

/**
 * Ensure restricted pages use default template content visibility.
 */
add_filter('body_class', function ($classes) {
    $classes[] = 'eg-app-runtime';
    return $classes;
});
