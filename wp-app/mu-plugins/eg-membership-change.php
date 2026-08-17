<?php
/**
 * Plugin Name: EG Membership Plan Change
 * Description: Upgrade instantly (1→2 via Stripe). Downgrade (2→1) at period end, then pay for Leserzugang.
 * Author: d4rl1ngt0n
 */

if (!defined('ABSPATH')) {
    exit;
}

const EG_PENDING_DOWNGRADE_META = 'eg_pending_downgrade';

/** @return int[] */
function eg_membership_change_allowed_levels()
{
    return array(1, 2);
}

/**
 * @return array<int, array{id:int,name:string,name_en:string,price:string,price_en:string,period:string,period_en:string}>
 */
function eg_membership_change_catalog()
{
    $catalog = array(
        1 => array(
            'id'        => 1,
            'name'      => 'Leserzugang',
            'name_en'   => 'Reader access',
            'price'     => '5 €',
            'price_en'  => '€5',
            'period'    => 'pro Monat',
            'period_en' => 'per month',
        ),
        2 => array(
            'id'        => 2,
            'name'      => 'Vereinsmitgliedschaft',
            'name_en'   => 'Association membership',
            'price'     => '120 €',
            'price_en'  => '€120',
            'period'    => 'pro Jahr',
            'period_en' => 'per year',
        ),
    );

    if (function_exists('pmpro_getLevel')) {
        foreach ($catalog as $id => $row) {
            $level = pmpro_getLevel($id);
            if (empty($level) || empty($level->id)) {
                continue;
            }
            if (!empty($level->name)) {
                $catalog[$id]['name'] = (string) $level->name;
            }
            if (isset($level->initial_payment) && $level->initial_payment !== '') {
                $amount = function_exists('pmpro_formatPrice')
                    ? pmpro_formatPrice($level->initial_payment)
                    : (string) $level->initial_payment . ' €';
                $catalog[$id]['price'] = $amount;
                $catalog[$id]['price_en'] = $amount;
            }
        }
    }

    return $catalog;
}

/**
 * @param int $user_id
 * @return int
 */
function eg_membership_change_current_level_id($user_id = 0)
{
    $user_id = $user_id ? (int) $user_id : get_current_user_id();
    if ($user_id <= 0 || !function_exists('pmpro_getMembershipLevelsForUser')) {
        return 0;
    }
    foreach ((array) pmpro_getMembershipLevelsForUser($user_id) as $level) {
        if (!empty($level->id)) {
            return (int) $level->id;
        }
    }
    return 0;
}

/**
 * @param int $from
 * @param int $to
 * @return string
 */
function eg_membership_change_direction($from, $to)
{
    $from = (int) $from;
    $to = (int) $to;
    if ($from <= 0) {
        return 'renew';
    }
    if ($from === 1 && $to === 2) {
        return 'upgrade';
    }
    if ($from === 2 && $to === 1) {
        return 'downgrade';
    }
    return 'switch';
}

function eg_membership_change_account_url($args = array())
{
    return add_query_arg($args, home_url('/membership-account/'));
}

/**
 * @param int $user_id
 * @return array{to_level:int,from_level:int,effective_ts:int,scheduled_ts:int,status:string}|null
 */
function eg_membership_change_get_pending($user_id = 0)
{
    $user_id = $user_id ? (int) $user_id : get_current_user_id();
    if ($user_id <= 0) {
        return null;
    }
    $raw = get_user_meta($user_id, EG_PENDING_DOWNGRADE_META, true);
    if (!is_array($raw) || empty($raw['to_level'])) {
        return null;
    }
    return array(
        'to_level'     => (int) $raw['to_level'],
        'from_level'   => isset($raw['from_level']) ? (int) $raw['from_level'] : 2,
        'effective_ts' => isset($raw['effective_ts']) ? (int) $raw['effective_ts'] : 0,
        'scheduled_ts' => isset($raw['scheduled_ts']) ? (int) $raw['scheduled_ts'] : 0,
        'status'       => isset($raw['status']) ? sanitize_key((string) $raw['status']) : 'scheduled',
    );
}

/**
 * @param int   $user_id
 * @param array $data
 */
function eg_membership_change_set_pending($user_id, $data)
{
    update_user_meta((int) $user_id, EG_PENDING_DOWNGRADE_META, $data);
}

function eg_membership_change_clear_pending($user_id)
{
    delete_user_meta((int) $user_id, EG_PENDING_DOWNGRADE_META);
}

/**
 * Next billing / period-end timestamp for the active membership.
 *
 * @param int $user_id
 * @return int Unix timestamp (0 if unknown)
 */
function eg_membership_change_period_end_ts($user_id)
{
    $user_id = (int) $user_id;
    $candidates = array();

    if (function_exists('pmpro_next_payment')) {
        $next = pmpro_next_payment($user_id);
        if (!empty($next)) {
            $ts = is_numeric($next) ? (int) $next : strtotime((string) $next);
            if ($ts && $ts > 0) {
                $candidates[] = $ts;
            }
        }
    }

    if (function_exists('pmpro_getMembershipLevelForUser')) {
        $level = pmpro_getMembershipLevelForUser($user_id);
        if ($level && !empty($level->enddate) && $level->enddate !== '0000-00-00 00:00:00') {
            $ts = strtotime((string) $level->enddate);
            /* Ignore far-future placeholders used for recurring open-ended levels. */
            if ($ts && $ts > time() && (int) gmdate('Y', $ts) < 2090) {
                $candidates[] = $ts;
            }
        }
    }

    $stripe_end = eg_membership_change_stripe_period_end_ts($user_id);
    if ($stripe_end > 0) {
        $candidates[] = $stripe_end;
    }

    $candidates = array_filter($candidates);
    if (!$candidates) {
        /* Verein is yearly: fall back to +1 year from now if nothing else is known. */
        return time() + YEAR_IN_SECONDS;
    }
    sort($candidates, SORT_NUMERIC);
    return (int) $candidates[0];
}

/**
 * @param int $user_id
 * @return MemberOrder|null
 */
function eg_membership_change_last_stripe_order($user_id)
{
    if (!class_exists('MemberOrder')) {
        return null;
    }
    $order = new MemberOrder();
    if (method_exists($order, 'getLastMemberOrder')) {
        $order->getLastMemberOrder($user_id, 'success');
    }
    if (empty($order->id)) {
        return null;
    }
    return $order;
}

/**
 * @param int $user_id
 * @return int
 */
function eg_membership_change_stripe_period_end_ts($user_id)
{
    $order = eg_membership_change_last_stripe_order($user_id);
    if (!$order || empty($order->subscription_transaction_id)) {
        return 0;
    }
    $sub = eg_membership_change_stripe_get_subscription((string) $order->subscription_transaction_id);
    if (!$sub || empty($sub['current_period_end'])) {
        return 0;
    }
    return (int) $sub['current_period_end'];
}

/**
 * Call Stripe REST (PMPro may not autoload StripeClient on every request).
 *
 * @param string               $method GET|POST
 * @param string               $path   e.g. subscriptions/sub_xxx
 * @param array<string,mixed>  $body
 * @return array<string,mixed>|null
 */
function eg_membership_change_stripe_request($method, $path, $body = array())
{
    $key = (string) get_option('pmpro_stripe_secretkey');
    if ($key === '' || $path === '') {
        return null;
    }
    $url = 'https://api.stripe.com/v1/' . ltrim($path, '/');
    $args = array(
        'method'  => strtoupper((string) $method),
        'timeout' => 30,
        'headers' => array(
            'Authorization' => 'Bearer ' . $key,
        ),
    );
    if (!empty($body) && $args['method'] !== 'GET') {
        $args['body'] = $body;
    }
    $response = wp_remote_request($url, $args);
    if (is_wp_error($response)) {
        return null;
    }
    $code = (int) wp_remote_retrieve_response_code($response);
    $data = json_decode((string) wp_remote_retrieve_body($response), true);
    if ($code < 200 || $code >= 300 || !is_array($data)) {
        return null;
    }
    return $data;
}

/**
 * @param string $subscription_id
 * @return array<string,mixed>|null
 */
function eg_membership_change_stripe_get_subscription($subscription_id)
{
    if ($subscription_id === '') {
        return null;
    }
    return eg_membership_change_stripe_request('GET', 'subscriptions/' . rawurlencode($subscription_id));
}

/**
 * Stop Verein auto-renewal; access continues until period end.
 *
 * @param int $user_id
 * @return bool
 */
function eg_membership_change_stripe_cancel_at_period_end($user_id)
{
    $order = eg_membership_change_last_stripe_order($user_id);
    if (!$order || empty($order->subscription_transaction_id)) {
        return false;
    }
    $data = eg_membership_change_stripe_request(
        'POST',
        'subscriptions/' . rawurlencode((string) $order->subscription_transaction_id),
        array('cancel_at_period_end' => 'true')
    );
    return is_array($data) && !empty($data['id']);
}

/**
 * Undo cancel_at_period_end if the member cancels the scheduled downgrade.
 *
 * @param int $user_id
 * @return bool
 */
function eg_membership_change_stripe_resume_subscription($user_id)
{
    $order = eg_membership_change_last_stripe_order($user_id);
    if (!$order || empty($order->subscription_transaction_id)) {
        return false;
    }
    $data = eg_membership_change_stripe_request(
        'POST',
        'subscriptions/' . rawurlencode((string) $order->subscription_transaction_id),
        array('cancel_at_period_end' => 'false')
    );
    return is_array($data) && !empty($data['id']);
}

/**
 * Keep level 2 active until effective_ts, then PMPro expiry can run.
 *
 * @param int $user_id
 * @param int $effective_ts
 */
function eg_membership_change_set_level_enddate($user_id, $effective_ts)
{
    global $wpdb;
    if (empty($wpdb->pmpro_memberships_users)) {
        return;
    }
    $user_id = (int) $user_id;
    $effective_ts = (int) $effective_ts;
    if ($user_id <= 0 || $effective_ts <= 0) {
        return;
    }
    $wpdb->update(
        $wpdb->pmpro_memberships_users,
        array('enddate' => gmdate('Y-m-d H:i:s', $effective_ts)),
        array(
            'user_id'       => $user_id,
            'status'        => 'active',
            'membership_id' => 2,
        ),
        array('%s'),
        array('%d', '%s', '%d')
    );
}

/**
 * Clear fixed enddate so recurring Verein can continue after undo.
 *
 * @param int $user_id
 */
function eg_membership_change_clear_level_enddate($user_id)
{
    global $wpdb;
    if (empty($wpdb->pmpro_memberships_users)) {
        return;
    }
    $wpdb->update(
        $wpdb->pmpro_memberships_users,
        array('enddate' => '0000-00-00 00:00:00'),
        array(
            'user_id'       => (int) $user_id,
            'status'        => 'active',
            'membership_id' => 2,
        ),
        array('%s'),
        array('%d', '%s', '%d')
    );
}

/**
 * Schedule 2→1: keep Verein until period end; no Stripe charge now.
 *
 * @param int $user_id
 * @return array{ok:bool,effective_ts:int,message?:string}
 */
function eg_membership_change_schedule_downgrade($user_id)
{
    $user_id = (int) $user_id;
    $current = eg_membership_change_current_level_id($user_id);
    if ($current !== 2) {
        return array('ok' => false, 'effective_ts' => 0, 'message' => 'not_verein');
    }

    $effective = eg_membership_change_period_end_ts($user_id);
    if ($effective <= time() + MINUTE_IN_SECONDS) {
        /* Period already over: ask for Leserzugang payment now. */
        eg_membership_change_set_pending(
            $user_id,
            array(
                'to_level'     => 1,
                'from_level'   => 2,
                'effective_ts' => time(),
                'scheduled_ts' => time(),
                'status'       => 'awaiting_payment',
            )
        );
        return array('ok' => true, 'effective_ts' => time(), 'message' => 'awaiting_payment');
    }

    eg_membership_change_stripe_cancel_at_period_end($user_id);
    eg_membership_change_set_level_enddate($user_id, $effective);
    eg_membership_change_set_pending(
        $user_id,
        array(
            'to_level'     => 1,
            'from_level'   => 2,
            'effective_ts' => $effective,
            'scheduled_ts' => time(),
            'status'       => 'scheduled',
        )
    );

    return array('ok' => true, 'effective_ts' => $effective, 'message' => 'scheduled');
}

/**
 * @param int $user_id
 * @return bool
 */
function eg_membership_change_cancel_scheduled_downgrade($user_id)
{
    $user_id = (int) $user_id;
    $pending = eg_membership_change_get_pending($user_id);
    if (!$pending || $pending['status'] !== 'scheduled') {
        return false;
    }
    if (eg_membership_change_current_level_id($user_id) === 2) {
        eg_membership_change_stripe_resume_subscription($user_id);
        eg_membership_change_clear_level_enddate($user_id);
    }
    eg_membership_change_clear_pending($user_id);
    return true;
}

/**
 * Mark pending downgrade ready for Leserzugang payment after Verein ends.
 *
 * @param int $user_id
 */
function eg_membership_change_mark_awaiting_payment($user_id)
{
    $pending = eg_membership_change_get_pending($user_id);
    if (!$pending) {
        $pending = array(
            'to_level'     => 1,
            'from_level'   => 2,
            'effective_ts' => time(),
            'scheduled_ts' => time(),
        );
    }
    $pending['status'] = 'awaiting_payment';
    $pending['effective_ts'] = isset($pending['effective_ts']) ? (int) $pending['effective_ts'] : time();
    eg_membership_change_set_pending($user_id, $pending);
}

/**
 * Daily + expiry: scheduled → awaiting_payment when Verein period is over.
 */
function eg_membership_change_process_due_downgrades()
{
    $user_ids = get_users(
        array(
            'meta_key'     => EG_PENDING_DOWNGRADE_META,
            'meta_compare' => 'EXISTS',
            'fields'       => 'ID',
            'number'       => 200,
        )
    );
    foreach ((array) $user_ids as $user_id) {
        $user_id = (int) $user_id;
        $pending = eg_membership_change_get_pending($user_id);
        if (!$pending || $pending['status'] !== 'scheduled') {
            continue;
        }
        $still_verein = (eg_membership_change_current_level_id($user_id) === 2);
        $due = ($pending['effective_ts'] > 0 && $pending['effective_ts'] <= time());
        if ($due || !$still_verein) {
            eg_membership_change_mark_awaiting_payment($user_id);
        }
    }
}

add_action('eg_membership_change_daily', 'eg_membership_change_process_due_downgrades');

add_action('init', function () {
    if (!wp_next_scheduled('eg_membership_change_daily')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'eg_membership_change_daily');
    }
});

add_action('pmpro_membership_post_membership_expiry', function ($user_id, $membership_id) {
    $user_id = (int) $user_id;
    $membership_id = (int) $membership_id;
    $pending = eg_membership_change_get_pending($user_id);
    if ($pending && (int) $pending['to_level'] === 1 && (int) $membership_id === 2) {
        eg_membership_change_mark_awaiting_payment($user_id);
    }
}, 10, 2);

/** Clear pending when they successfully land on either paid level. */
add_action('pmpro_after_change_membership_level', function ($level_id, $user_id) {
    $level_id = (int) $level_id;
    $user_id = (int) $user_id;
    if (in_array($level_id, array(1, 2), true)) {
        $pending = eg_membership_change_get_pending($user_id);
        if ($pending && (int) $pending['to_level'] === $level_id) {
            eg_membership_change_clear_pending($user_id);
        }
        if ($level_id === 2) {
            eg_membership_change_clear_pending($user_id);
        }
    }
}, 20, 2);

/**
 * POST handlers: upgrade (Stripe now), schedule downgrade, cancel schedule, pay L1.
 */
add_action('admin_post_eg_change_membership', 'eg_membership_change_handle_post');
add_action('admin_post_nopriv_eg_change_membership', 'eg_membership_change_handle_post');
add_action('admin_post_eg_cancel_pending_downgrade', 'eg_membership_change_handle_cancel_pending');
add_action('admin_post_nopriv_eg_cancel_pending_downgrade', 'eg_membership_change_handle_cancel_pending');

function eg_membership_change_handle_cancel_pending()
{
    if (!is_user_logged_in()) {
        wp_safe_redirect(home_url('/login/'));
        exit;
    }
    $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
    if (!$nonce || !wp_verify_nonce($nonce, 'eg_cancel_pending_downgrade')) {
        wp_safe_redirect(eg_membership_change_account_url(array('eg_change' => 'nonce')));
        exit;
    }
    eg_membership_change_cancel_scheduled_downgrade(get_current_user_id());
    wp_safe_redirect(eg_membership_change_account_url(array('eg_change' => 'pending_cancelled')));
    exit;
}

function eg_membership_change_handle_post()
{
    $login = add_query_arg(
        'redirect_to',
        eg_membership_change_account_url(),
        home_url('/login/')
    );

    if (!is_user_logged_in()) {
        wp_safe_redirect($login);
        exit;
    }

    $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
    if (!$nonce || !wp_verify_nonce($nonce, 'eg_change_membership')) {
        wp_safe_redirect(eg_membership_change_account_url(array('eg_change' => 'nonce')));
        exit;
    }

    $target = isset($_POST['level']) ? (int) $_POST['level'] : 0;
    if (!in_array($target, eg_membership_change_allowed_levels(), true)) {
        wp_safe_redirect(eg_membership_change_account_url(array('eg_change' => 'invalid')));
        exit;
    }

    $user_id = get_current_user_id();
    $current = eg_membership_change_current_level_id($user_id);
    $direction = eg_membership_change_direction($current, $target);
    $pending = eg_membership_change_get_pending($user_id);

    /* Paying for Leserzugang after scheduled Verein end. */
    if ($target === 1 && $pending && $pending['status'] === 'awaiting_payment') {
        eg_membership_change_redirect_to_stripe($user_id, 1);
    }

    if ($current === $target && !($pending && $pending['status'] === 'awaiting_payment' && $target === 1)) {
        wp_safe_redirect(eg_membership_change_account_url(array('eg_change' => 'same')));
        exit;
    }

    if ($direction === 'downgrade') {
        $ack = !empty($_POST['eg_change_ack']);
        if (!$ack) {
            wp_safe_redirect(
                eg_membership_change_account_url(
                    array(
                        'eg_change_confirm' => $target,
                        'eg_change'         => 'ack',
                    )
                )
            );
            exit;
        }
        $result = eg_membership_change_schedule_downgrade($user_id);
        if (empty($result['ok'])) {
            wp_safe_redirect(eg_membership_change_account_url(array('eg_change' => 'error')));
            exit;
        }
        if (!empty($result['message']) && $result['message'] === 'awaiting_payment') {
            wp_safe_redirect(eg_membership_change_account_url(array('eg_change' => 'awaiting_payment')));
            exit;
        }
        wp_safe_redirect(eg_membership_change_account_url(array('eg_change' => 'scheduled')));
        exit;
    }

    /* Upgrade / renew / other: Stripe Checkout now, instant access after pay. */
    if ($direction === 'upgrade') {
        eg_membership_change_clear_pending($user_id);
    }
    eg_membership_change_redirect_to_stripe($user_id, $target);
}

/**
 * @param int $user_id
 * @param int $level_id
 */
function eg_membership_change_redirect_to_stripe($user_id, $level_id)
{
    $cancel = eg_membership_change_account_url(array('eg_change' => 'cancelled'));
    if (!function_exists('eg_member_handoff_start_stripe_checkout')) {
        wp_safe_redirect(
            add_query_arg(array('level' => (int) $level_id), home_url('/membership-checkout/'))
        );
        exit;
    }
    $fallback = eg_member_handoff_start_stripe_checkout(
        (int) $user_id,
        (int) $level_id,
        array('cancel_url' => $cancel)
    );
    if ($fallback) {
        wp_safe_redirect($fallback);
        exit;
    }
    wp_safe_redirect(eg_membership_change_account_url(array('eg_change' => 'error')));
    exit;
}

add_action('pmpro_account_bullets_bottom', 'eg_membership_change_render_notices', 15);

function eg_membership_change_render_notices()
{
    if (!is_user_logged_in()) {
        return;
    }
    $code = isset($_GET['eg_change']) ? sanitize_key(wp_unslash($_GET['eg_change'])) : '';
    if ($code === '') {
        return;
    }

    $messages = array(
        'cancelled'          => array(
            'de' => 'Vorgang abgebrochen. Ihre bisherige Mitgliedschaft bleibt aktiv.',
            'en' => 'Cancelled. Your current membership stays active.',
        ),
        'same'               => array(
            'de' => 'Sie haben diesen Tarif bereits.',
            'en' => 'You already have this plan.',
        ),
        'nonce'              => array(
            'de' => 'Sicherheitsprüfung fehlgeschlagen. Bitte erneut versuchen.',
            'en' => 'Security check failed. Please try again.',
        ),
        'invalid'            => array(
            'de' => 'Ungültiger Tarif.',
            'en' => 'Invalid plan.',
        ),
        'ack'                => array(
            'de' => 'Bitte bestätigen Sie den Hinweis zum Downgrade.',
            'en' => 'Please confirm the downgrade notice.',
        ),
        'error'              => array(
            'de' => 'Aktion fehlgeschlagen. Bitte später erneut versuchen.',
            'en' => 'Action failed. Please try again later.',
        ),
        'scheduled'          => array(
            'de' => 'Downgrade geplant. Sie behalten die Vereinsmitgliedschaft bis zum Ende des bezahlten Zeitraums. Danach starten Sie den Leserzugang mit Zahlung.',
            'en' => 'Downgrade scheduled. You keep association membership until the paid period ends. Then you start reader access with payment.',
        ),
        'awaiting_payment'   => array(
            'de' => 'Ihr Vereinszeitraum ist beendet. Bitte zahlen Sie jetzt für den Leserzugang.',
            'en' => 'Your association period has ended. Please pay now for reader access.',
        ),
        'pending_cancelled'  => array(
            'de' => 'Geplantes Downgrade wurde storniert. Ihre Vereinsmitgliedschaft läuft weiter.',
            'en' => 'Scheduled downgrade cancelled. Your association membership continues.',
        ),
    );

    if (!isset($messages[$code])) {
        return;
    }

    echo '<div class="eg-plan-notice" role="status" style="margin:16px 0;padding:12px 14px;border:1px solid #c5d0db;background:#f4f7fa;color:#0b1f33;font:500 14px/1.45 system-ui,sans-serif">';
    echo '<span class="de">' . esc_html($messages[$code]['de']) . '</span>';
    echo '<span class="en" hidden>' . esc_html($messages[$code]['en']) . '</span>';
    echo '</div>';
}

add_action('pmpro_account_bullets_bottom', 'eg_membership_change_render_panel', 25);

function eg_membership_change_render_panel()
{
    if (!is_user_logged_in() || is_admin()) {
        return;
    }

    /* Refresh due state while viewing account. */
    eg_membership_change_process_due_downgrades_for_user(get_current_user_id());

    $catalog = eg_membership_change_catalog();
    $current = eg_membership_change_current_level_id();
    $pending = eg_membership_change_get_pending();
    $confirm = isset($_GET['eg_change_confirm']) ? (int) $_GET['eg_change_confirm'] : 0;
    if ($confirm && !in_array($confirm, eg_membership_change_allowed_levels(), true)) {
        $confirm = 0;
    }
    if ($confirm && $confirm === $current && !($pending && $pending['status'] === 'awaiting_payment')) {
        $confirm = 0;
    }

    $current_row = ($current && isset($catalog[$current])) ? $catalog[$current] : null;

    echo '<section id="eg-plan-change" class="eg-plan-change" style="margin:28px 0 8px;padding:20px 18px;border:1px solid #d5dee8;background:#fff;color:#0b1f33;font:400 15px/1.5 system-ui,sans-serif">';
    echo '<h2 style="margin:0 0 8px;font:700 1.25rem/1.3 system-ui,sans-serif">';
    echo '<span class="de">Mitgliedschaft ändern</span>';
    echo '<span class="en" hidden>Change membership</span>';
    echo '</h2>';

    if ($current_row) {
        echo '<p style="margin:0 0 16px;opacity:.9">';
        echo '<span class="de">Aktueller Tarif: <strong>' . esc_html($current_row['name']) . '</strong> ('
            . esc_html($current_row['price'] . ' ' . $current_row['period']) . ')</span>';
        echo '<span class="en" hidden>Current plan: <strong>' . esc_html($current_row['name_en']) . '</strong> ('
            . esc_html($current_row['price_en'] . ' ' . $current_row['period_en']) . ')</span>';
        echo '</p>';
    } else {
        echo '<p style="margin:0 0 16px;opacity:.9">';
        echo '<span class="de">Keine aktive Mitgliedschaft. Sie können einen Tarif wählen und bezahlen.</span>';
        echo '<span class="en" hidden>No active membership. Choose a plan and continue to payment.</span>';
        echo '</p>';
    }

    if ($pending) {
        eg_membership_change_render_pending_banner($pending, $catalog);
    }

    if ($confirm) {
        eg_membership_change_render_confirm($current, $confirm, $catalog, $pending);
    } else {
        eg_membership_change_render_choices($current, $catalog, $pending);
    }

    echo '<p style="margin:18px 0 0;font-size:13px;opacity:.8">';
    echo '<span class="de">Upgrade (Leser → Verein): sofortige Freischaltung nach Zahlung von 120 €. Downgrade (Verein → Leser): Sie behalten Verein bis Periodenende; die 5 €/Monat-Zahlung startet erst danach.</span>';
    echo '<span class="en" hidden>Upgrade (reader → association): instant access after paying €120. Downgrade (association → reader): keep association until period end; the €5/month payment starts only then.</span>';
    echo '</p>';
    echo '</section>';
}

/**
 * @param int $user_id
 */
function eg_membership_change_process_due_downgrades_for_user($user_id)
{
    $user_id = (int) $user_id;
    $pending = eg_membership_change_get_pending($user_id);
    if (!$pending || $pending['status'] !== 'scheduled') {
        return;
    }
    $still_verein = (eg_membership_change_current_level_id($user_id) === 2);
    $due = ($pending['effective_ts'] > 0 && $pending['effective_ts'] <= time());
    if ($due || !$still_verein) {
        eg_membership_change_mark_awaiting_payment($user_id);
    }
}

/**
 * @param array $pending
 * @param array $catalog
 */
function eg_membership_change_render_pending_banner($pending, $catalog)
{
    $date = $pending['effective_ts']
        ? date_i18n(get_option('date_format') . ' H:i', $pending['effective_ts'])
        : '';
    $target = isset($catalog[1]) ? $catalog[1]['name'] : 'Leserzugang';

    if ($pending['status'] === 'scheduled') {
        echo '<div style="margin:0 0 16px;padding:12px 14px;background:#fff7e8;border:1px solid #e6c98a">';
        echo '<p style="margin:0 0 10px">';
        echo '<span class="de"><strong>Downgrade geplant</strong> auf ' . esc_html($target)
            . ' ab <strong>' . esc_html($date) . '</strong>. Bis dahin bleibt die Vereinsmitgliedschaft aktiv. Keine Zahlung jetzt.</span>';
        echo '<span class="en" hidden><strong>Downgrade scheduled</strong> to reader access from <strong>'
            . esc_html($date) . '</strong>. Association stays active until then. No payment now.</span>';
        echo '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:0">';
        echo '<input type="hidden" name="action" value="eg_cancel_pending_downgrade" />';
        wp_nonce_field('eg_cancel_pending_downgrade');
        echo '<button type="submit" class="pmpro_btn" style="background:transparent;color:#0b1f33;border:1px solid #0b1f33;padding:8px 12px;font-weight:600;cursor:pointer">';
        echo '<span class="de">Downgrade stornieren</span><span class="en" hidden>Cancel downgrade</span>';
        echo '</button></form></div>';
        return;
    }

    if ($pending['status'] === 'awaiting_payment') {
        echo '<div style="margin:0 0 16px;padding:12px 14px;background:#eef6ff;border:1px solid #9bb8d6">';
        echo '<p style="margin:0 0 10px">';
        echo '<span class="de"><strong>Vereinszeitraum beendet.</strong> Starten Sie jetzt den Leserzugang (5 € / Monat) über Stripe.</span>';
        echo '<span class="en" hidden><strong>Association period ended.</strong> Start reader access (€5 / month) via Stripe now.</span>';
        echo '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:0">';
        echo '<input type="hidden" name="action" value="eg_change_membership" />';
        echo '<input type="hidden" name="level" value="1" />';
        wp_nonce_field('eg_change_membership');
        echo '<button type="submit" class="pmpro_btn" style="background:#0b1f33;color:#fff;border:0;padding:10px 14px;font-weight:600;cursor:pointer">';
        echo '<span class="de">Leserzugang bezahlen</span><span class="en" hidden>Pay for reader access</span>';
        echo '</button></form></div>';
    }
}

/**
 * @param int        $current
 * @param array      $catalog
 * @param array|null $pending
 */
function eg_membership_change_render_choices($current, $catalog, $pending)
{
    echo '<div class="eg-plan-choices" style="display:grid;gap:12px">';
    foreach (eg_membership_change_allowed_levels() as $id) {
        if (!isset($catalog[$id])) {
            continue;
        }
        $row = $catalog[$id];
        $is_current = ((int) $current === (int) $id);
        $direction = eg_membership_change_direction($current, $id);
        $downgrade_locked = ($direction === 'downgrade' && $pending && $pending['status'] === 'scheduled');

        echo '<div style="padding:14px 14px;border:1px solid ' . ($is_current ? '#0b1f33' : '#d5dee8') . ';background:' . ($is_current ? '#f4f7fa' : '#fff') . '">';
        echo '<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:baseline;justify-content:space-between">';
        echo '<div><strong style="display:block;font-size:1.05rem">';
        echo '<span class="de">' . esc_html($row['name']) . '</span>';
        echo '<span class="en" hidden>' . esc_html($row['name_en']) . '</span>';
        echo '</strong><span style="opacity:.85">';
        echo '<span class="de">' . esc_html($row['price'] . ' ' . $row['period']) . '</span>';
        echo '<span class="en" hidden>' . esc_html($row['price_en'] . ' ' . $row['period_en']) . '</span>';
        echo '</span></div>';

        if ($is_current) {
            echo '<span style="font-weight:700;color:#0b1f33"><span class="de">Aktuell</span><span class="en" hidden>Current</span></span>';
        } elseif ($downgrade_locked) {
            echo '<span style="opacity:.75"><span class="de">Bereits geplant</span><span class="en" hidden>Already scheduled</span></span>';
        } else {
            $label_de = 'Wechseln';
            $label_en = 'Switch';
            if ($direction === 'upgrade') {
                $label_de = 'Jetzt upgraden';
                $label_en = 'Upgrade now';
            } elseif ($direction === 'downgrade') {
                $label_de = 'Zum Periodenende wechseln';
                $label_en = 'Switch at period end';
            } elseif ($direction === 'renew') {
                $label_de = 'Jetzt wählen';
                $label_en = 'Choose plan';
            }
            if ($pending && $pending['status'] === 'awaiting_payment' && $id === 1) {
                $label_de = 'Leserzugang bezahlen';
                $label_en = 'Pay for reader access';
            }
            $url = esc_url(eg_membership_change_account_url(array('eg_change_confirm' => $id)));
            echo '<a class="pmpro_btn" href="' . $url . '" style="display:inline-block;text-decoration:none;background:#0b1f33;color:#fff;padding:10px 14px;font-weight:600">';
            echo '<span class="de">' . esc_html($label_de) . '</span>';
            echo '<span class="en" hidden>' . esc_html($label_en) . '</span>';
            echo '</a>';
        }
        echo '</div></div>';
    }
    echo '</div>';
}

/**
 * @param int        $current
 * @param int        $target
 * @param array      $catalog
 * @param array|null $pending
 */
function eg_membership_change_render_confirm($current, $target, $catalog, $pending)
{
    $target_row = $catalog[$target];
    $direction = eg_membership_change_direction($current, $target);
    if ($pending && $pending['status'] === 'awaiting_payment' && $target === 1) {
        $direction = 'pay_reader';
    }
    $back = esc_url(eg_membership_change_account_url() . '#eg-plan-change');
    $effective = eg_membership_change_period_end_ts(get_current_user_id());
    $date = $effective ? date_i18n(get_option('date_format'), $effective) : '';

    echo '<div class="eg-plan-confirm" style="padding:16px;border:1px solid #c5d0db;background:#f8fafc">';

    if ($direction === 'downgrade') {
        echo '<p style="margin:0 0 12px">';
        echo '<span class="de">Downgrade auf <strong>' . esc_html($target_row['name']) . '</strong> zum Periodenende'
            . ($date ? ' (<strong>' . esc_html($date) . '</strong>)' : '') . ' planen?</span>';
        echo '<span class="en" hidden>Schedule downgrade to <strong>' . esc_html($target_row['name_en']) . '</strong> at period end'
            . ($date ? ' (<strong>' . esc_html($date) . '</strong>)' : '') . '?</span>';
        echo '</p>';
        echo '<p style="margin:0 0 12px;padding:10px 12px;background:#fff7e8;border:1px solid #e6c98a">';
        echo '<span class="de"><strong>Keine Zahlung jetzt.</strong> Sie behalten Vereinszugang bis Periodenende. Danach zahlen Sie 5 € / Monat für den Leserzugang. Vereinsleistungen entfallen erst dann.</span>';
        echo '<span class="en" hidden><strong>No payment now.</strong> You keep association access until period end. Then you pay €5 / month for reader access. Association benefits end only then.</span>';
        echo '</p>';
    } elseif ($direction === 'upgrade') {
        echo '<p style="margin:0 0 12px">';
        echo '<span class="de">Upgrade auf <strong>' . esc_html($target_row['name']) . '</strong> ('
            . esc_html($target_row['price'] . ' ' . $target_row['period']) . '). Nach Zahlung sofortiger Vereinszugang, auch wenn der Leser-Monat noch läuft.</span>';
        echo '<span class="en" hidden>Upgrade to <strong>' . esc_html($target_row['name_en']) . '</strong> ('
            . esc_html($target_row['price_en'] . ' ' . $target_row['period_en']) . '). Instant association access after payment, even if the reader month is still running.</span>';
        echo '</p>';
    } elseif ($direction === 'pay_reader') {
        echo '<p style="margin:0 0 12px">';
        echo '<span class="de">Vereinszeitraum beendet. Weiter zur Zahlung für <strong>' . esc_html($target_row['name']) . '</strong> ('
            . esc_html($target_row['price'] . ' ' . $target_row['period']) . ').</span>';
        echo '<span class="en" hidden>Association period ended. Continue to pay for <strong>' . esc_html($target_row['name_en']) . '</strong> ('
            . esc_html($target_row['price_en'] . ' ' . $target_row['period_en']) . ').</span>';
        echo '</p>';
    } else {
        echo '<p style="margin:0 0 12px">';
        echo '<span class="de">Weiter zu <strong>' . esc_html($target_row['name']) . '</strong>?</span>';
        echo '<span class="en" hidden>Continue to <strong>' . esc_html($target_row['name_en']) . '</strong>?</span>';
        echo '</p>';
    }

    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:0">';
    echo '<input type="hidden" name="action" value="eg_change_membership" />';
    echo '<input type="hidden" name="level" value="' . esc_attr((string) $target) . '" />';
    wp_nonce_field('eg_change_membership');

    if ($direction === 'downgrade') {
        echo '<label style="display:flex;gap:10px;align-items:flex-start;margin:0 0 14px;font-size:14px">';
        echo '<input type="checkbox" name="eg_change_ack" value="1" required style="margin-top:3px" />';
        echo '<span><span class="de">Ich verstehe: Verein bleibt bis Periodenende; Leserzugang und 5 €-Zahlung starten danach.</span>';
        echo '<span class="en" hidden>I understand: association stays until period end; reader access and the €5 payment start afterwards.</span></span></label>';
    }

    $btn_de = 'Weiter zu Stripe';
    $btn_en = 'Continue to Stripe';
    if ($direction === 'downgrade') {
        $btn_de = 'Downgrade planen';
        $btn_en = 'Schedule downgrade';
    }

    echo '<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center">';
    echo '<button type="submit" class="pmpro_btn" style="background:#0b1f33;color:#fff;border:0;padding:10px 16px;font-weight:600;cursor:pointer">';
    echo '<span class="de">' . esc_html($btn_de) . '</span>';
    echo '<span class="en" hidden>' . esc_html($btn_en) . '</span>';
    echo '</button>';
    echo '<a href="' . $back . '" style="color:#0b1f33"><span class="de">Abbrechen</span><span class="en" hidden>Cancel</span></a>';
    echo '</div></form></div>';
}

add_filter('body_class', function ($classes) {
    if (is_user_logged_in() && function_exists('pmpro_hasMembershipLevel') && pmpro_hasMembershipLevel()) {
        $classes[] = 'eg-has-membership';
    }
    return $classes;
});
