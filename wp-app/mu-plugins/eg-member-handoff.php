<?php
/**
 * Plugin Name: EG Member Handoff
 * Description: Accept brochure membership applications, create/login the WP user, redirect to PMPro checkout (payment, not a second signup form).
 * Author: d4rl1ngt0n
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Map plan keys from the static site to PMPro level IDs.
 */
function eg_member_handoff_level_id($plan)
{
    $plan = strtolower(sanitize_key((string) $plan));
    if (in_array($plan, array('expert-yearly', 'expert', 'verein', 'association', '2'), true)) {
        return 2;
    }
    return 1;
}

/**
 * Build a unique username from an email address.
 */
function eg_member_handoff_username_from_email($email)
{
    $local = sanitize_user(current(explode('@', (string) $email, 2)), true);
    if ($local === '' || strlen($local) < 3) {
        $local = 'member';
    }
    $base = substr($local, 0, 40);
    $candidate = $base;
    $i = 1;
    while (username_exists($candidate)) {
        $candidate = substr($base, 0, 35) . $i;
        $i++;
        if ($i > 200) {
            $candidate = 'member' . wp_generate_password(6, false, false);
            break;
        }
    }
    return $candidate;
}

/**
 * Persist brochure application fields on the user for board review.
 */
function eg_member_handoff_store_application($user_id, $payload)
{
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return;
    }
    $clean = array(
        'plan'   => isset($payload['plan']) ? sanitize_text_field((string) $payload['plan']) : '',
        'level'  => isset($payload['level']) ? (int) $payload['level'] : 0,
        'at'     => gmdate('c'),
        'fields' => array(),
    );
    $fields = array();
    if (!empty($payload['fields']) && is_array($payload['fields'])) {
        $fields = $payload['fields'];
    } elseif (!empty($payload['fields_json'])) {
        $decoded = json_decode(wp_unslash((string) $payload['fields_json']), true);
        if (is_array($decoded)) {
            $fields = $decoded;
        }
    }
    foreach ($fields as $key => $value) {
        $k = sanitize_key((string) $key);
        if ($k === '' || in_array($k, array('mr-pw', 'mr-pw2', 'password', 'password2', 'pass'), true)) {
            continue;
        }
        if (is_scalar($value)) {
            $clean['fields'][$k] = sanitize_textarea_field((string) $value);
        }
    }
    update_user_meta($user_id, 'eg_membership_application', $clean);
    update_user_meta($user_id, 'eg_membership_application_at', time());
}

/**
 * JSON body helper.
 */
function eg_member_handoff_read_json()
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return array();
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : array();
}

/**
 * Build checkout URL with a one-time login token (fallback only).
 */
function eg_member_handoff_checkout_url($level, $user_id)
{
    $level = (int) $level;
    $user_id = (int) $user_id;
    $token = bin2hex(random_bytes(16));
    set_transient('eg_hoff_' . $token, $user_id, 10 * MINUTE_IN_SECONDS);
    return add_query_arg(
        array(
            'level'   => $level,
            'eg_auth' => $token,
            'eg_go'   => 'stripe',
        ),
        home_url('/membership-checkout/')
    );
}

/**
 * Create a PMPro order and send the member straight to Stripe Checkout.
 * On success this function exits via Stripe redirect.
 * On failure it returns a fallback URL (or empty string).
 *
 * @param int   $user_id  WordPress user ID.
 * @param int   $level_id PMPro level ID.
 * @param array $args     Optional. cancel_url overrides Stripe cancel destination
 *                        (default: brochure form for new signups).
 */
function eg_member_handoff_start_stripe_checkout($user_id, $level_id, $args = array())
{
    $user_id = (int) $user_id;
    $level_id = (int) $level_id;
    $args = wp_parse_args(
        is_array($args) ? $args : array(),
        array(
            'cancel_url' => '',
        )
    );
    if ($user_id <= 0 || $level_id <= 0) {
        return '';
    }
    if (!class_exists('MemberOrder') || !function_exists('pmpro_getLevel')) {
        return eg_member_handoff_checkout_url($level_id, $user_id);
    }

    eg_member_handoff_login_user($user_id, false);
    eg_member_handoff_set_pay_session($user_id);

    $level = pmpro_getLevel($level_id);
    if (empty($level) || empty($level->id)) {
        return eg_member_handoff_checkout_url($level_id, $user_id);
    }

    /* PMPro reads these during getMembershipLevelAtCheckout(). */
    $_REQUEST['level'] = $level_id;
    $_GET['level'] = $level_id;

    global $pmpro_level, $current_user, $pmpro_currency;
    $pmpro_level = $level;
    $current_user = wp_get_current_user();

    $user = get_userdata($user_id);
    $first = get_user_meta($user_id, 'first_name', true);
    $last = get_user_meta($user_id, 'last_name', true);
    $name = trim($first . ' ' . $last);
    if ($name === '' && $user) {
        $name = $user->display_name;
    }

    $order = new MemberOrder();
    $order->user_id = $user_id;
    $order->membership_id = (int) $level->id;
    $order->gateway = 'stripe';
    $order->payment_type = 'Stripe';
    $order->Email = $user ? $user->user_email : '';
    $order->billing = new stdClass();
    $order->billing->name = $name;
    $order->billing->street = '';
    $order->billing->street2 = '';
    $order->billing->city = '';
    $order->billing->state = '';
    $order->billing->country = '';
    $order->billing->zip = '';
    $order->billing->phone = '';

    $order->subtotal = function_exists('pmpro_round_price')
        ? pmpro_round_price($level->initial_payment)
        : (float) $level->initial_payment;
    $order->tax = function_exists('pmpro_round_price')
        ? pmpro_round_price($order->getTax(true))
        : 0;
    $order->total = function_exists('pmpro_round_price')
        ? pmpro_round_price($order->subtotal + $order->tax)
        : ($order->subtotal + $order->tax);

    if (method_exists($order, 'setGateway')) {
        $order->setGateway();
    }
    if (method_exists($order, 'getMembershipLevelAtCheckout')) {
        $order->getMembershipLevelAtCheckout();
    }

    if (has_filter('pmpro_checkout_order')) {
        $order = apply_filters('pmpro_checkout_order', $order);
    }

    $cancel_url = is_string($args['cancel_url']) ? $args['cancel_url'] : '';
    if ($cancel_url === '') {
        $cancel_url = eg_member_handoff_brochure_url(($level_id === 2) ? 'expert' : 'reader', array());
    }

    /* Prefer Stripe-hosted cancel/success URLs that fit signup vs plan-change. */
    add_filter('pmpro_stripe_checkout_session_parameters', function ($params) use ($cancel_url) {
        if (is_array($params)) {
            $params['cancel_url'] = $cancel_url;
        }
        return $params;
    }, 5);

    /*
     * MemberOrder::process() → Stripe gateway → creates Checkout Session and
     * wp_redirect() to checkout.stripe.com, then exit.
     */
    if (method_exists($order, 'process')) {
        $order->process();
    }

    /* Still here: Stripe session failed. Fall back to PMPro checkout page. */
    return eg_member_handoff_checkout_url($level_id, $user_id);
}

/**
 * Respond either as JSON or as a browser redirect (form POST).
 * Successful browser posts go straight to Stripe Checkout (no PMPro middle page).
 */
function eg_member_handoff_respond($wants_redirect, $payload, $http_status = 200)
{
    $ok = !empty($payload['ok']);
    $level = !empty($payload['level']) ? (int) $payload['level'] : 1;
    if ($level !== 2) {
        $level = 1;
    }
    $plan = ($level === 2) ? 'expert' : 'reader';
    $user_id = !empty($payload['user_id']) ? (int) $payload['user_id'] : get_current_user_id();

    if ($wants_redirect) {
        if ($ok && $user_id > 0) {
            $fallback = eg_member_handoff_start_stripe_checkout($user_id, $level);
            if ($fallback) {
                eg_member_handoff_redirect($fallback);
            }
            eg_member_handoff_redirect(eg_member_handoff_brochure_url($plan, array('eg_handoff' => 'stripe')));
        }
        $err = !empty($payload['error']) ? sanitize_key((string) $payload['error']) : 'error';
        eg_member_handoff_redirect(eg_member_handoff_brochure_url($plan, array('eg_handoff' => $err)));
    }

    /* JSON clients still get a checkout URL (auto-stripe token). */
    if ($ok && empty($payload['checkoutUrl']) && $user_id > 0) {
        $payload['checkoutUrl'] = eg_member_handoff_checkout_url($level, $user_id);
    }

    status_header((int) $http_status);
    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    echo wp_json_encode($payload);
    exit;
}

/**
 * Log the user in and fire WP login hooks.
 * Avoid rotating the auth session when this user is already current.
 * Session rotation invalidates the PMPro checkout nonce ("Nonce security check failed").
 */
function eg_member_handoff_login_user($user_id, $force_new_session = false)
{
    $user_id = (int) $user_id;
    $user = get_user_by('id', $user_id);
    if (!$user) {
        return false;
    }
    if (!$force_new_session && is_user_logged_in() && get_current_user_id() === $user_id) {
        return true;
    }
    if (!$force_new_session && !is_user_logged_in()) {
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true, is_ssl());
        return true;
    }
    wp_clear_auth_cookie();
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true, is_ssl());
    do_action('wp_login', $user->user_login, $user);
    return true;
}

/**
 * Main handler: create or authenticate user, return checkout URL or redirect.
 */
function eg_member_handoff_handle()
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        status_header(204);
        exit;
    }

    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        eg_member_handoff_respond(false, array('ok' => false, 'error' => 'method'), 405);
    }

    $data = eg_member_handoff_read_json();
    if (!$data && !empty($_POST)) {
        $data = wp_unslash($_POST);
    }

    $wants_redirect = !empty($data['redirect']) || !empty($_POST['redirect']);

    $email = isset($data['email']) ? sanitize_email((string) $data['email']) : '';
    $first = isset($data['first_name']) ? sanitize_text_field((string) $data['first_name']) : '';
    $last  = isset($data['last_name']) ? sanitize_text_field((string) $data['last_name']) : '';
    $plan  = isset($data['plan']) ? (string) $data['plan'] : 'reader-monthly';
    $level = isset($data['level']) ? (int) $data['level'] : eg_member_handoff_level_id($plan);
    if ($level !== 2) {
        $level = eg_member_handoff_level_id($plan);
    }

    $password = isset($data['password']) ? (string) $data['password'] : '';
    $password2 = isset($data['password2']) ? (string) $data['password2'] : '';

    if (!is_email($email)) {
        eg_member_handoff_respond($wants_redirect, array(
            'ok'      => false,
            'error'   => 'email',
            'message' => 'Please enter a valid email address.',
        ), 400);
    }

    if ($password !== '' && $password2 !== '' && $password !== $password2) {
        eg_member_handoff_respond($wants_redirect, array(
            'ok'      => false,
            'error'   => 'password_mismatch',
            'message' => 'Passwords do not match.',
        ), 400);
    }

    if ($password !== '' && strlen($password) < 6) {
        eg_member_handoff_respond($wants_redirect, array(
            'ok'      => false,
            'error'   => 'password_short',
            'message' => 'Password must be at least 6 characters.',
        ), 400);
    }

    $checkout = add_query_arg(
        array('level' => $level),
        home_url('/membership-checkout/')
    );

    $app_payload = array(
        'plan'   => $plan,
        'level'  => $level,
        'fields' => isset($data['fields']) ? $data['fields'] : $data,
    );

    $existing_id = email_exists($email);
    $generated_password = false;

    if ($existing_id) {
        $user = get_user_by('id', (int) $existing_id);
        if (!$user) {
            eg_member_handoff_respond($wants_redirect, array('ok' => false, 'error' => 'user'), 500);
        }

        /* Brochure form is authoritative: apply the submitted password and continue to payment. */
        if ($password !== '') {
            wp_set_password($password, (int) $user->ID);
        } elseif (!is_user_logged_in() || get_current_user_id() !== (int) $user->ID) {
            eg_member_handoff_respond($wants_redirect, array(
                'ok'      => false,
                'error'   => 'exists',
                'message' => 'An account with this email already exists. Enter your password to continue.',
                'level'   => $level,
            ), 409);
        }

        eg_member_handoff_login_user((int) $user->ID, true);
        eg_member_handoff_store_application((int) $user->ID, $app_payload);
        if ($first) {
            update_user_meta($user->ID, 'first_name', $first);
        }
        if ($last) {
            update_user_meta($user->ID, 'last_name', $last);
        }
        eg_member_handoff_respond($wants_redirect, array(
            'ok'          => true,
            'checkoutUrl' => eg_member_handoff_checkout_url($level, (int) $user->ID),
            'created'     => false,
            'level'       => $level,
            'user_id'     => (int) $user->ID,
        ));
    }

    /* New account */
    if ($password === '') {
        $password = wp_generate_password(16, true, false);
        $generated_password = true;
    }

    $username = eg_member_handoff_username_from_email($email);
    $user_id = wp_insert_user(array(
        'user_login'   => $username,
        'user_email'   => $email,
        'user_pass'    => $password,
        'first_name'   => $first,
        'last_name'    => $last,
        'display_name' => trim($first . ' ' . $last) !== '' ? trim($first . ' ' . $last) : $username,
        'role'         => 'subscriber',
    ));

    if (is_wp_error($user_id)) {
        eg_member_handoff_respond($wants_redirect, array(
            'ok'      => false,
            'error'   => 'create',
            'message' => $user_id->get_error_message(),
            'level'   => $level,
        ), 500);
    }

    eg_member_handoff_store_application((int) $user_id, $app_payload);
    eg_member_handoff_login_user((int) $user_id, true);

    if ($generated_password && function_exists('wp_new_user_notification')) {
        wp_new_user_notification((int) $user_id, null, 'user');
    }

    eg_member_handoff_respond($wants_redirect, array(
        'ok'          => true,
        'checkoutUrl' => eg_member_handoff_checkout_url($level, (int) $user_id),
        'created'     => true,
        'generated'   => $generated_password,
        'level'       => $level,
        'user_id'     => (int) $user_id,
    ));
}

add_action('wp_ajax_eg_member_handoff', 'eg_member_handoff_handle');
add_action('wp_ajax_nopriv_eg_member_handoff', 'eg_member_handoff_handle');

/**
 * Brochure membership form URL (static site one level above /app/).
 */
function eg_member_handoff_brochure_url($plan = 'reader', $extra = array())
{
    $home = untrailingslashit(home_url());
    $base = (substr($home, -4) === '/app') ? substr($home, 0, -4) : $home;
    $plan = ($plan === 'expert' || $plan === 'verein' || (int) $plan === 2) ? 'expert' : 'reader';
    $args = array_merge(
        array(
            'plan' => $plan,
            'v'    => 'pmpro15',
        ),
        is_array($extra) ? $extra : array()
    );
    return add_query_arg($args, $base . '/mitgliedschaft.html') . '#membership-registration';
}

/**
 * Redirect to brochure without wp_safe_redirect blocking paths outside /app/.
 */
function eg_member_handoff_redirect($url)
{
    if (!$url) {
        return;
    }
    if (!headers_sent()) {
        nocache_headers();
        header('Location: ' . $url, true, 302);
    }
    exit;
}

/**
 * The brochure form is the only signup UI. Never show PMPro account fields.
 */
add_filter('pmpro_skip_account_fields', '__return_true', 99);

/**
 * Short-lived pay-session cookie so checkout POST still works if the WP
 * auth cookie is delayed/dropped by the browser/CDN.
 */
function eg_member_handoff_pay_cookie_name()
{
    return 'eg_pay_session';
}

function eg_member_handoff_pay_cookie_secret()
{
    if (defined('AUTH_KEY') && AUTH_KEY) {
        return (string) AUTH_KEY;
    }
    return wp_salt('auth');
}

function eg_member_handoff_set_pay_session($user_id)
{
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return;
    }
    $exp = time() + 20 * MINUTE_IN_SECONDS;
    $payload = $user_id . '|' . $exp;
    $sig = hash_hmac('sha256', $payload, eg_member_handoff_pay_cookie_secret());
    $val = rawurlencode($payload . '|' . $sig);
    $secure = is_ssl();
    /* Path / so brochure + /app/ share it. */
    setcookie(eg_member_handoff_pay_cookie_name(), $val, array(
        'expires'  => $exp,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ));
    $_COOKIE[eg_member_handoff_pay_cookie_name()] = $val;
}

function eg_member_handoff_user_from_pay_session()
{
    $raw = isset($_COOKIE[eg_member_handoff_pay_cookie_name()])
        ? rawurldecode((string) $_COOKIE[eg_member_handoff_pay_cookie_name()])
        : '';
    if ($raw === '' || substr_count($raw, '|') < 2) {
        return 0;
    }
    $parts = explode('|', $raw);
    if (count($parts) < 3) {
        return 0;
    }
    $user_id = (int) $parts[0];
    $exp = (int) $parts[1];
    $sig = (string) $parts[2];
    if ($user_id <= 0 || $exp < time()) {
        return 0;
    }
    $payload = $user_id . '|' . $exp;
    $expect = hash_hmac('sha256', $payload, eg_member_handoff_pay_cookie_secret());
    if (!hash_equals($expect, $sig)) {
        return 0;
    }
    return $user_id;
}

/**
 * Restore brochure pay-session BEFORE PMPro preheaders verify the checkout nonce.
 */
add_action('init', function () {
    if (is_admin()) {
        return;
    }
    $pay_uid = eg_member_handoff_user_from_pay_session();
    if ($pay_uid <= 0) {
        return;
    }
    if (!is_user_logged_in()) {
        eg_member_handoff_login_user($pay_uid, false);
    }
    /*
     * If checkout is being submitted and this pay-session matches the user,
     * replace a stale form nonce with a fresh one for the current session.
     * Stale nonces happen when a prior soft-login rotated cookies after the page rendered.
     */
    $submitting = !empty($_REQUEST['submit-checkout']) || !empty($_REQUEST['submit-checkout_x']);
    if (
        $submitting
        && is_user_logged_in()
        && get_current_user_id() === $pay_uid
    ) {
        $fresh = wp_create_nonce('pmpro_checkout_nonce');
        $_REQUEST['pmpro_checkout_nonce'] = $fresh;
        $_POST['pmpro_checkout_nonce'] = $fresh;
    }
}, 0);

/**
 * Consume one-time eg_auth token, log in, set pay-session, redirect to a clean
 * checkout URL (no eg_auth). Clean URL avoids spent-token guest bounces on submit.
 */
add_action('template_redirect', function () {
    if (empty($_GET['eg_auth']) || is_admin()) {
        return;
    }
    $token = sanitize_text_field(wp_unslash($_GET['eg_auth']));
    if ($token === '') {
        return;
    }
    $user_id = get_transient('eg_hoff_' . $token);
    if (!$user_id) {
        return;
    }
    delete_transient('eg_hoff_' . $token);
    /* Already logged in by handoff cookie when possible; do not rotate session. */
    eg_member_handoff_login_user((int) $user_id, false);
    eg_member_handoff_set_pay_session((int) $user_id);
    $level = isset($_GET['level']) ? (int) $_GET['level'] : 1;
    /* Fallback URL with eg_go=stripe: skip PMPro UI and jump to Stripe. */
    if (!empty($_GET['eg_go']) && $_GET['eg_go'] === 'stripe') {
        $fallback = eg_member_handoff_start_stripe_checkout((int) $user_id, $level);
        if ($fallback) {
            eg_member_handoff_redirect($fallback);
        }
    }
    $clean = add_query_arg(array('level' => $level), home_url('/membership-checkout/'));
    eg_member_handoff_redirect($clean);
}, 2);

/**
 * Guests who hit /membership-checkout/ without a handoff session go back to the brochure form.
 */
add_action('template_redirect', function () {
    if (is_admin()) {
        return;
    }
    if (!is_user_logged_in()) {
        $pay_uid = eg_member_handoff_user_from_pay_session();
        if ($pay_uid > 0) {
            eg_member_handoff_login_user($pay_uid);
        }
    }
    if (is_user_logged_in()) {
        return;
    }
    if (!empty($_GET['eg_auth'])) {
        $level = isset($_REQUEST['level']) ? (int) $_REQUEST['level'] : 1;
        $plan = ($level === 2) ? 'expert' : 'reader';
        eg_member_handoff_redirect(eg_member_handoff_brochure_url($plan, array('eg_handoff' => 'session')));
    }
    $is_checkout = false;
    if (function_exists('pmpro_is_checkout')) {
        $is_checkout = (bool) pmpro_is_checkout();
    }
    if (!$is_checkout) {
        global $post;
        if (!$post || empty($post->post_name) || $post->post_name !== 'membership-checkout') {
            return;
        }
        $is_checkout = true;
    }
    if (!$is_checkout) {
        return;
    }
    $level = isset($_REQUEST['level']) ? (int) $_REQUEST['level'] : 1;
    $plan = ($level === 2) ? 'expert' : 'reader';
    eg_member_handoff_redirect(eg_member_handoff_brochure_url($plan, array()));
}, 4);

/**
 * Custom Stripe CTA (input value, not button textContent).
 */
add_filter('pmpro_checkout_default_submit_button', function ($show) {
    if ((string) get_option('pmpro_stripe_payment_flow') !== 'checkout') {
        return $show;
    }
    ?>
    <p id="eg-stripe-hosted-note" style="margin:0 0 16px">
        <strong>Zahlung bei Stripe.</strong>
        Nach dem Klick werden Sie zur sicheren Stripe-Zahlungsseite weitergeleitet.<br>
        <span style="opacity:.85">You will complete payment on the secure Stripe page.</span>
    </p>
    <span id="pmpro_submit_span">
        <input type="hidden" name="submit-checkout" value="1" />
        <input type="submit" id="pmpro_btn-submit" class="pmpro_btn pmpro_btn-submit-checkout" value="Weiter zu Stripe / Continue to Stripe" />
    </span>
    <?php
    return false;
}, 20);

/**
 * Checkout chrome: hide duplicate account UI; for hosted Stripe Checkout hide leftover onsite card fields.
 */
add_action('wp_enqueue_scripts', function () {
    if (is_admin()) {
        return;
    }
    $is_checkout = false;
    if (function_exists('pmpro_is_checkout')) {
        $is_checkout = pmpro_is_checkout();
    }
    if (!$is_checkout) {
        global $post;
        if (!$post || empty($post->post_name) || $post->post_name !== 'membership-checkout') {
            return;
        }
    }
    $hosted = ((string) get_option('pmpro_stripe_payment_flow') === 'checkout');
    $css = '.pmpro_checkout .pmpro_asterisk,'
        . '.pmpro_checkout .pmpro_asterisk abbr,'
        . '.pmpro_checkout label .pmpro_asterisk,'
        . '.pmpro_checkout .pmpro_required:after,'
        . '#pmpro_form .pmpro_asterisk{color:#c62828!important;font-weight:700}'
        . '#pmpro_user_fields,'
        . '#pmpro_account_loggedin + #pmpro_user_fields,'
        . '.pmpro_checkout-field-username,'
        . '.pmpro_checkout-field-password,'
        . '.pmpro_checkout-field-password2,'
        . '.pmpro_checkout-field-bemail,'
        . '.pmpro_checkout-field-bconfirmemail,'
        . '.pmpro_form_field-username,'
        . '.pmpro_form_field-password,'
        . '.pmpro_form_field-password2,'
        . '.pmpro_form_field-bemail,'
        . '.pmpro_form_field-bconfirmemail,'
        . '#pmpro_form .pmpro_checkout-fields.pmpro_checkout-fields-account,'
        . 'div.pmpro_checkout_box-user{display:none!important}';
    if ($hosted) {
        $css .= '#pmpro_payment_information_fields{display:none!important}';
    }
    wp_register_style('eg-pmpro-required', false, array(), 'pmpro14');
    wp_enqueue_style('eg-pmpro-required');
    wp_add_inline_style('eg-pmpro-required', $css);
}, 40);

add_action('wp_footer', function () {
    if (is_admin() || !is_user_logged_in()) {
        return;
    }
    $is_checkout = false;
    if (function_exists('pmpro_is_checkout')) {
        $is_checkout = pmpro_is_checkout();
    }
    if (!$is_checkout) {
        global $post;
        if (!$post || empty($post->post_name) || $post->post_name !== 'membership-checkout') {
            return;
        }
    }
    $user = wp_get_current_user();
    $level = isset($_REQUEST['level']) ? (int) $_REQUEST['level'] : 1;
    $payload = array(
        'email'      => $user->user_email,
        'first_name' => get_user_meta($user->ID, 'first_name', true),
        'last_name'  => get_user_meta($user->ID, 'last_name', true),
        'username'   => $user->user_login,
        'hosted'     => ((string) get_option('pmpro_stripe_payment_flow') === 'checkout'),
        'action'     => home_url('/membership-checkout/?level=' . $level),
    );
    echo '<script id="eg-member-checkout-prefill">(function(){var d='
        . wp_json_encode($payload)
        . ';var form=document.getElementById("pmpro_form");'
        . 'function set(sel,val){if(!val)return;var el=document.querySelector(sel);if(el&&!el.value){el.value=val;}}'
        . 'set("#bfirstname",d.first_name);set("#blastname",d.last_name);'
        . 'set("#bemail",d.email);set("#bconfirmemail",d.email);'
        . 'var nodes=document.querySelectorAll("h2,h3,.pmpro_checkout-field-heading");'
        . 'for(var i=0;i<nodes.length;i++){var t=(nodes[i].textContent||"").toLowerCase();'
        . 'if(t.indexOf("account information")>=0||t.indexOf("kontoinformationen")>=0){'
        . 'var box=nodes[i].closest(".pmpro_checkout_box,section,fieldset,div");'
        . 'if(box){box.style.display="none";}}}'
        . 'function refreshNonce(cb){'
        . 'if(!window.fetch){if(cb)cb();return;}'
        . 'fetch((window.pmpro&&pmpro.ajaxurl)||"/app/wp-admin/admin-ajax.php",{'
        . 'method:"POST",credentials:"same-origin",'
        . 'headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},'
        . 'body:"action=pmpro_get_checkout_nonce"'
        . '}).then(function(r){return r.text();}).then(function(n){'
        . 'n=(n||"").trim();'
        . 'var el=document.getElementById("pmpro_checkout_nonce")||(form&&form.querySelector("[name=pmpro_checkout_nonce]"));'
        . 'if(el&&n&&n.length>8){el.value=n;}'
        . 'if(cb)cb();'
        . '}).catch(function(){if(cb)cb();});'
        . '}'
        . 'refreshNonce();'
        . 'if(form){'
        . 'form.setAttribute("action",d.action);form.action=d.action;form.method="post";'
        . 'form.querySelectorAll("[required]").forEach(function(el){'
        . 'var hidden=el.disabled||el.getAttribute("aria-hidden")==="true";'
        . 'var box=el.closest("#pmpro_user_fields,#pmpro_payment_information_fields,[hidden]");'
        . 'if(box||hidden||(el.offsetParent===null&&el.type!=="hidden")){el.removeAttribute("required");}'
        . '});'
        . 'form.addEventListener("submit",function(e){'
        . 'var js=form.querySelector("[name=javascriptok],#checkjavascript,[name=checkjavascript]");'
        . 'if(js){js.value="1";}'
        . 'form.querySelectorAll("[required]").forEach(function(el){el.removeAttribute("required");});'
        . '});'
        . '}'
        . 'var btn=document.getElementById("pmpro_btn-submit");'
        . 'if(btn){if(btn.tagName==="INPUT"){btn.value="Weiter zu Stripe / Continue to Stripe";}'
        . 'else{btn.textContent="Weiter zu Stripe / Continue to Stripe";}'
        . 'btn.addEventListener("click",function(ev){'
        . 'if(!form)return;'
        . 'if(btn.getAttribute("data-eg-nonce-ok")==="1"){return;}'
        . 'ev.preventDefault();'
        . 'refreshNonce(function(){btn.setAttribute("data-eg-nonce-ok","1");'
        . 'if(typeof form.requestSubmit==="function"){form.requestSubmit(btn);}else{HTMLFormElement.prototype.submit.call(form);}'
        . '});'
        . '});'
        . '}'
        . '})();</script>';
}, 40);

/* Never cache membership checkout HTML (stale nonces). */
add_action('template_redirect', function () {
    $is_checkout = function_exists('pmpro_is_checkout') ? pmpro_is_checkout() : false;
    if (!$is_checkout) {
        global $post;
        if (!$post || empty($post->post_name) || $post->post_name !== 'membership-checkout') {
            return;
        }
    }
    if (!headers_sent()) {
        nocache_headers();
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
}, 0);
