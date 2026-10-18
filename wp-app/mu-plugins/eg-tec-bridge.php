<?php
/**
 * Plugin Name: EG TEC Bridge
 * Description: Keep The Events Calendar dates/tickets in sync with the public Veranstaltungen page, and send Get Tickets to Stripe.
 * Author: d4rl1ngt0n
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Normalize titles so brochure and TEC copies of the same event can be matched.
 */
function eg_tec_normalize_title($title)
{
    $title = html_entity_decode(wp_strip_all_tags((string) $title), ENT_QUOTES, 'UTF-8');
    $title = str_replace(array('„', '“', '”', '«', '»', '"', "'", '’', '‚'), '', $title);
    $title = strtolower($title);
    return preg_replace('/[^a-z0-9]+/', '', $title);
}

/**
 * Whether a TEC event currently has a sellable ticket.
 */
function eg_tec_event_has_tickets($tribe_id)
{
    $tribe_id = (int) $tribe_id;
    if ($tribe_id < 1) {
        return false;
    }

    if (class_exists('Tribe__Tickets__Tickets') && method_exists('Tribe__Tickets__Tickets', 'get_all_event_tickets')) {
        $tickets = Tribe__Tickets__Tickets::get_all_event_tickets($tribe_id);
        if (is_array($tickets) && $tickets) {
            return true;
        }
    }

    $found = get_posts(
        array(
            'post_type'      => array('tec_tc_ticket', 'tribe_rsvp_tickets', 'product'),
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => array(
                'relation' => 'OR',
                array(
                    'key'   => '_tec_tickets_commerce_event',
                    'value' => $tribe_id,
                ),
                array(
                    'key'   => '_tribe_wooticket_for_event',
                    'value' => $tribe_id,
                ),
            ),
        )
    );

    return !empty($found);
}

/**
 * Y-m-d / H:i parts from a TEC datetime string.
 *
 * @return array{date:string,time:string}
 */
function eg_tec_split_datetime($raw, $fallback_time = '19:00')
{
    $raw = trim((string) $raw);
    $date = '';
    $time = $fallback_time;
    if (preg_match('/^(\d{4}-\d{2}-\d{2})(?:[ T](\d{2}:\d{2}))?/', $raw, $m)) {
        $date = $m[1];
        if (!empty($m[2])) {
            $time = $m[2];
        }
    }
    return array('date' => $date, 'time' => $time);
}

/**
 * Brochure event ID mapped to a TEC event.
 */
function eg_tec_find_brochure_for_tribe($tribe_id)
{
    $tribe_id = (int) $tribe_id;
    if ($tribe_id < 1) {
        return 0;
    }

    $mapped = (int) get_post_meta($tribe_id, '_eg_brochure_event_id', true);
    if ($mapped && get_post_type($mapped) === 'eg_event') {
        return $mapped;
    }

    $tribe = get_post($tribe_id);
    if (!$tribe instanceof WP_Post) {
        return 0;
    }

    $norm = eg_tec_normalize_title($tribe->post_title);
    $events = get_posts(
        array(
            'post_type'      => 'eg_event',
            'post_status'    => array('publish', 'draft', 'future', 'pending'),
            'posts_per_page' => 250,
        )
    );
    foreach ($events as $event) {
        if (!$event instanceof WP_Post) {
            continue;
        }
        if ($norm !== '' && eg_tec_normalize_title($event->post_title) === $norm) {
            return (int) $event->ID;
        }
        $stored = (int) get_post_meta($event->ID, '_eg_tribe_event_id', true);
        if ($stored === $tribe_id) {
            return (int) $event->ID;
        }
    }

    return 0;
}

/**
 * TEC event ID mapped to a brochure event.
 */
function eg_tec_find_tribe_for_brochure($brochure_id)
{
    $brochure_id = (int) $brochure_id;
    if ($brochure_id < 1) {
        return 0;
    }

    $mapped = (int) get_post_meta($brochure_id, '_eg_tribe_event_id', true);
    if ($mapped && get_post_type($mapped) === 'tribe_events') {
        return $mapped;
    }

    $reverse = get_posts(
        array(
            'post_type'      => 'tribe_events',
            'post_status'    => array('publish', 'future', 'draft', 'pending', 'private'),
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_key'       => '_eg_brochure_event_id',
            'meta_value'     => $brochure_id,
        )
    );
    if ($reverse) {
        return (int) $reverse[0];
    }

    $brochure = get_post($brochure_id);
    if (!$brochure instanceof WP_Post) {
        return 0;
    }
    $norm = eg_tec_normalize_title($brochure->post_title);
    if ($norm === '') {
        return 0;
    }

    $events = get_posts(
        array(
            'post_type'      => 'tribe_events',
            'post_status'    => array('publish', 'future', 'draft', 'pending', 'private'),
            'posts_per_page' => 100,
        )
    );
    foreach ($events as $event) {
        if ($event instanceof WP_Post && eg_tec_normalize_title($event->post_title) === $norm) {
            return (int) $event->ID;
        }
    }

    return 0;
}

/**
 * Remember the pairing both ways.
 */
function eg_tec_link_events($brochure_id, $tribe_id)
{
    $brochure_id = (int) $brochure_id;
    $tribe_id = (int) $tribe_id;
    if ($brochure_id < 1 || $tribe_id < 1) {
        return;
    }
    update_post_meta($brochure_id, '_eg_tribe_event_id', $tribe_id);
    update_post_meta($tribe_id, '_eg_brochure_event_id', $brochure_id);
}

/**
 * Copy TEC start/end/price/tickets onto the public brochure event.
 */
function eg_tec_upsert_brochure_from_tribe($tribe_id)
{
    static $busy = false;
    if ($busy) {
        return 0;
    }

    $tribe_id = (int) $tribe_id;
    $tribe = get_post($tribe_id);
    if (!$tribe instanceof WP_Post || $tribe->post_type !== 'tribe_events') {
        return 0;
    }
    if (in_array($tribe->post_status, array('trash', 'auto-draft'), true)) {
        return 0;
    }

    $busy = true;
    $brochure_id = eg_tec_find_brochure_for_tribe($tribe_id);
    $start = eg_tec_split_datetime(get_post_meta($tribe_id, '_EventStartDate', true), '19:00');
    $end = eg_tec_split_datetime(get_post_meta($tribe_id, '_EventEndDate', true), '21:00');
    $cost = get_post_meta($tribe_id, '_EventCost', true);
    $price = ($cost !== '' && $cost !== null) ? round((float) $cost, 2) : null;
    $has_tickets = eg_tec_event_has_tickets($tribe_id);

    $location = '';
    if (function_exists('tribe_get_full_address')) {
        $location = trim(wp_strip_all_tags((string) tribe_get_full_address($tribe_id)));
    }
    if ($location === '' && function_exists('tribe_get_venue')) {
        $location = trim(wp_strip_all_tags((string) tribe_get_venue($tribe_id)));
    }

    if (!$brochure_id) {
        $slug = sanitize_title((string) $tribe->post_name);
        if ($slug !== '' && strpos($slug, 'ev-') !== 0) {
            $slug = 'ev-' . $slug;
        }
        $brochure_id = wp_insert_post(
            array(
                'post_type'    => 'eg_event',
                'post_status'  => 'publish',
                'post_title'   => $tribe->post_title,
                'post_name'    => $slug,
                'post_content' => $tribe->post_content,
            ),
            true
        );
        if (is_wp_error($brochure_id) || !$brochure_id) {
            $busy = false;
            return 0;
        }
        $brochure_id = (int) $brochure_id;
    }

    if ($start['date'] !== '') {
        update_post_meta($brochure_id, 'eg_event_start', $start['date']);
        update_post_meta($brochure_id, 'eg_event_time_start', $start['time']);
    }
    if ($end['time'] !== '') {
        update_post_meta($brochure_id, 'eg_event_time_end', $end['time']);
    }
    if ($price !== null) {
        update_post_meta($brochure_id, 'eg_event_price', $price);
    }
    if ($has_tickets || ($price !== null && $price > 0)) {
        update_post_meta($brochure_id, 'eg_event_bookable', true);
    }
    if ($location !== '') {
        $existing_loc = (string) get_post_meta($brochure_id, 'eg_event_location', true);
        if ($existing_loc === '') {
            update_post_meta($brochure_id, 'eg_event_location', $location);
        }
    }

    eg_tec_link_events($brochure_id, $tribe_id);
    $busy = false;
    return $brochure_id;
}

/**
 * Copy brochure date/price back onto the linked TEC event.
 */
function eg_tec_sync_tribe_from_brochure($brochure_id)
{
    static $busy = false;
    if ($busy) {
        return;
    }
    $brochure_id = (int) $brochure_id;
    $tribe_id = eg_tec_find_tribe_for_brochure($brochure_id);
    if ($tribe_id < 1) {
        return;
    }

    $date = (string) get_post_meta($brochure_id, 'eg_event_start', true);
    $t_start = (string) get_post_meta($brochure_id, 'eg_event_time_start', true);
    $t_end = (string) get_post_meta($brochure_id, 'eg_event_time_end', true);
    if ($date === '') {
        return;
    }
    if (!preg_match('/^\d{2}:\d{2}/', $t_start)) {
        $t_start = '19:00';
    }
    if (!preg_match('/^\d{2}:\d{2}/', $t_end)) {
        $t_end = '21:00';
    }

    $busy = true;
    $start = $date . ' ' . $t_start . ':00';
    $end = $date . ' ' . $t_end . ':00';
    update_post_meta($tribe_id, '_EventStartDate', $start);
    update_post_meta($tribe_id, '_EventEndDate', $end);

    $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('Europe/Berlin');
    try {
        $start_dt = new DateTimeImmutable($start, $tz);
        $end_dt = new DateTimeImmutable($end, $tz);
        update_post_meta($tribe_id, '_EventStartDateUTC', $start_dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
        update_post_meta($tribe_id, '_EventEndDateUTC', $end_dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
    } catch (Exception $e) {
        // Keep local datetimes even if UTC conversion fails.
    }

    $price = get_post_meta($brochure_id, 'eg_event_price', true);
    if ($price !== '' && $price !== null) {
        update_post_meta($tribe_id, '_EventCost', (string) $price);
    }
    eg_tec_link_events($brochure_id, $tribe_id);
    $busy = false;
}

/**
 * Scheduled TEC posts 404 for buyers. Events must stay published so tickets can be sold before the start date.
 */
function eg_tec_publish_if_scheduled($tribe_id)
{
    static $busy = false;
    if ($busy) {
        return;
    }
    $post = get_post((int) $tribe_id);
    if (!$post instanceof WP_Post || $post->post_type !== 'tribe_events') {
        return;
    }
    if ($post->post_status !== 'future') {
        return;
    }
    $busy = true;
    wp_update_post(
        array(
            'ID'            => (int) $tribe_id,
            'post_status'   => 'publish',
            'post_date'     => current_time('mysql'),
            'post_date_gmt' => current_time('mysql', true),
            'edit_date'     => true,
        )
    );
    $busy = false;
}

add_action('save_post_tribe_events', function ($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (wp_is_post_revision($post_id)) {
        return;
    }
    eg_tec_publish_if_scheduled($post_id);
    eg_tec_upsert_brochure_from_tribe($post_id);
}, 40);

add_action('tribe_events_update_meta', function ($event_id) {
    eg_tec_publish_if_scheduled($event_id);
    eg_tec_upsert_brochure_from_tribe($event_id);
}, 20);

add_action('updated_post_meta', function ($meta_id, $object_id, $meta_key) {
    if (!in_array($meta_key, array('_EventStartDate', '_EventEndDate', '_EventCost'), true)) {
        return;
    }
    if (get_post_type($object_id) !== 'tribe_events') {
        return;
    }
    eg_tec_upsert_brochure_from_tribe($object_id);
}, 20, 3);

add_action('save_post_eg_event', function ($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (wp_is_post_revision($post_id)) {
        return;
    }
    eg_tec_sync_tribe_from_brochure($post_id);
}, 120);

/**
 * Public JSON uses the TEC date when a matching calendar event exists.
 */
add_filter('eg_event_feed_item', function ($item, $post) {
    if (!is_array($item) || !$post instanceof WP_Post) {
        return $item;
    }

    $tribe_id = eg_tec_find_tribe_for_brochure((int) $post->ID);
    if ($tribe_id < 1) {
        return $item;
    }

    $start = eg_tec_split_datetime(get_post_meta($tribe_id, '_EventStartDate', true), $item['time_start'] ?? '19:00');
    $end = eg_tec_split_datetime(get_post_meta($tribe_id, '_EventEndDate', true), $item['time_end'] ?? '21:00');
    if ($start['date'] !== '') {
        $item['date'] = $start['date'];
        $item['time_start'] = $start['time'];
    }
    if (!empty($end['time'])) {
        $item['time_end'] = $end['time'];
    }

    $cost = get_post_meta($tribe_id, '_EventCost', true);
    if ($cost !== '' && $cost !== null) {
        $item['price'] = (float) $cost;
    }

    $today = function_exists('wp_date') ? wp_date('Y-m-d') : gmdate('Y-m-d');
    $item['status'] = (!empty($item['date']) && $item['date'] >= $today) ? 'upcoming' : 'past';
    $bookable = !empty($item['bookable'])
        || (bool) get_post_meta($post->ID, 'eg_event_bookable', true)
        || eg_tec_event_has_tickets($tribe_id)
        || (!empty($item['price']) && (float) $item['price'] > 0);
    $item['bookable'] = $bookable && $item['status'] === 'upcoming';

    return $item;
}, 10, 2);

/**
 * Brochure permalink for a TEC event (used by Get Tickets + frontend redirects).
 */
function eg_tec_brochure_url_for_tribe($tribe_id, $open_tickets = false)
{
    $hash = '';
    $brochure_id = eg_tec_find_brochure_for_tribe($tribe_id);
    if ($brochure_id) {
        $name = get_post_field('post_name', $brochure_id);
        if (is_string($name) && $name !== '') {
            $hash = $name;
        }
    }
    $query = $open_tickets ? array('eg_tickets' => '1') : array();
    if (function_exists('eg_event_handoff_brochure_events_url')) {
        return eg_event_handoff_brochure_events_url($hash, $query);
    }
    if (function_exists('eg_brochure_public_url')) {
        $url = eg_brochure_public_url('veranstaltungen.html');
        if ($query) {
            $url = add_query_arg($query, $url);
        }
        if ($hash !== '') {
            $url .= '#' . $hash;
        }
        return $url;
    }
    return home_url('/');
}

/**
 * Send TEC archives and single events to the public brochure calendar.
 */
add_action('template_redirect', function () {
    if (is_admin()) {
        return;
    }

    $uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
    $path = (string) wp_parse_url($uri, PHP_URL_PATH);
    if (preg_match('#tickets-checkout|tickets/success|attendee-registration|tec-tickets#i', $path)) {
        return;
    }

    $target = '';
    if (is_singular('tribe_events')) {
        $obj = get_queried_object();
        $id = ($obj instanceof WP_Post) ? (int) $obj->ID : 0;
        $target = eg_tec_brochure_url_for_tribe($id, true);
    } elseif (is_post_type_archive('tribe_events')) {
        $target = function_exists('eg_brochure_public_url')
            ? eg_brochure_public_url('veranstaltungen.html')
            : home_url('/');
    } elseif (function_exists('tribe_is_event_query') && tribe_is_event_query() && !is_singular()) {
        /* Month/list/day views under /app/events/ */
        $target = function_exists('eg_brochure_public_url')
            ? eg_brochure_public_url('veranstaltungen.html')
            : home_url('/');
    }

    if ($target === '') {
        return;
    }

    if (function_exists('eg_member_handoff_redirect')) {
        eg_member_handoff_redirect($target);
    }
    if (!headers_sent()) {
        nocache_headers();
        header('Location: ' . $target, true, 302);
    }
    exit;
}, 4);

/**
 * If Event Tickets still renders Get Tickets, send buyers to the brochure Stripe form.
 */
add_filter('tribe_tickets_ticket_email_post_link', function ($link, $post_id) {
    if (get_post_type($post_id) === 'tribe_events') {
        return eg_tec_brochure_url_for_tribe($post_id, true);
    }
    return $link;
}, 20, 2);

add_filter('post_type_link', function ($permalink, $post) {
    if ($post instanceof WP_Post && $post->post_type === 'tribe_events' && !is_admin()) {
        return eg_tec_brochure_url_for_tribe((int) $post->ID, true);
    }
    return $permalink;
}, 20, 2);
