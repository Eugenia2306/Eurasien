<?php
/**
 * Plugin Name: EG Render Engine
 * Description: Fills the marked areas of the static pages with WordPress content on the server (events list, homepage preview and news card, Event JSON-LD, llms.txt events). Used by /eg-render.php at the site root. Port of tools/prerender_events.py.
 * Author: d4rl1ngt0n
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('EgRenderNotFound')) {
    /** Thrown when a page (e.g. a profile) does not exist: the root script answers 404. */
    class EgRenderNotFound extends RuntimeException
    {
    }
}

/** Site root (the static site), one level above /app/. */
function eg_render_site_root()
{
    return dirname(rtrim(ABSPATH, '/\\'));
}

/** File touched on every content change; the root script compares cache age against it. */
function eg_render_stamp_file()
{
    return WP_CONTENT_DIR . '/eg-render-stamp';
}

function eg_render_touch_stamp()
{
    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_touch
    @touch(eg_render_stamp_file());
}

foreach (array('save_post', 'deleted_post', 'trashed_post', 'untrashed_post', 'edited_term', 'created_term', 'delete_term') as $eg_render_hook) {
    add_action($eg_render_hook, 'eg_render_touch_stamp');
}
unset($eg_render_hook);

/* ---------- helpers (same output as the Python / JS renderers) ---------- */

function eg_r_esc($s)
{
    return str_replace(array('&', '<', '>', '"'), array('&amp;', '&lt;', '&gt;', '&quot;'), (string) $s);
}

function eg_r_bi($de, $en)
{
    $de = (string) $de;
    $en = (string) $en !== '' ? (string) $en : $de;
    return '<span class="de">' . eg_r_esc($de) . '</span><span class="en" hidden>' . eg_r_esc($en) . '</span>';
}

function eg_r_bi_html($de, $en)
{
    $de = (string) $de;
    $en = (string) $en !== '' ? (string) $en : $de;
    return '<div class="de">' . $de . '</div><div class="en" hidden>' . $en . '</div>';
}

function eg_r_meta_item($kind, $html)
{
    static $ic = array(
        'loc'  => '<path d="M20 10c0 4.99-5.54 10.19-7.4 11.8a1 1 0 0 1-1.2 0C9.54 20.19 4 14.99 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>',
        'date' => '<path d="M8 2v4M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
        'time' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'who'  => '<path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><path d="M12 19v3"/>',
    );
    return '<span class="ev__mi ev__mi--' . $kind . '"><svg class="ev__ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $ic[$kind] . '</svg>' . $html . '</span>';
}

/** Google Maps link only for a real place (street or venue), not a bare city name. */
function eg_r_map_link($loc, $inner)
{
    $q = trim((string) $loc);
    if (!preg_match('/\d|,/u', $q)) {
        return $inner;
    }
    return '<a class="ev__map" href="https://www.google.com/maps/search/?api=1&amp;query=' . rawurlencode($q)
        . '" target="_blank" rel="noopener" title="Google Maps" style="color:inherit;text-decoration:underline;text-decoration-color:rgba(0,50,160,.35);text-underline-offset:2px">' . $inner . '</a>';
}

function eg_r_when_items($when_de, $when_en)
{
    $de = explode(' · ', (string) $when_de);
    $en = explode(' · ', (string) $when_en);
    $out = eg_r_meta_item('date', eg_r_bi($de[0], $en[0]));
    if (count($de) > 1) {
        $out .= eg_r_meta_item('time', eg_r_bi($de[1], count($en) > 1 ? $en[1] : $de[1]));
    }
    return $out;
}

/** @return array{0:int,1:int,2:int}|null */
function eg_r_ymd($date)
{
    $p = explode('-', (string) $date);
    if (count($p) < 3 || !is_numeric($p[0]) || !is_numeric($p[1]) || !is_numeric(substr($p[2], 0, 2))) {
        return null;
    }
    return array((int) $p[0], (int) $p[1], (int) substr($p[2], 0, 2));
}

function eg_r_months($lang, $short = false)
{
    if ($lang === 'en') {
        return array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');
    }
    return $short
        ? array('Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez')
        : array('Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember');
}

/** @return array{0:string,1:string} */
function eg_r_format_when($ev)
{
    $p = eg_r_ymd($ev['date'] ?? '');
    if (!$p) {
        return array('', '');
    }
    list($y, $m, $d) = $p;
    $wd = (int) gmdate('w', gmmktime(12, 0, 0, $m, $d, $y));
    $wd_de = array('Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag');
    $wd_en = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
    $md = eg_r_months('de');
    $me = eg_r_months('en');
    $base_de = sprintf('%s, %d. %s %d', $wd_de[$wd], $d, $md[$m - 1], $y);
    $base_en = sprintf('%s, %d %s %d', $wd_en[$wd], $d, $me[$m - 1], $y);
    if (!empty($ev['time_tba']) && ($ev['status'] ?? '') !== 'upcoming') {
        return array($base_de, $base_en);
    }
    if (!empty($ev['time_tba'])) {
        return array($base_de . ' · Uhrzeit folgt', $base_en . ' · Time to be announced');
    }
    $t = substr((string) (($ev['time_start'] ?? '') !== '' ? $ev['time_start'] : '19:00'), 0, 5);
    $parts = explode(':', $t);
    $th = (int) $parts[0];
    $tm = isset($parts[1]) ? $parts[1] : '00';
    if ($th === 0) {
        $ampm = '12:' . $tm . ' am';
    } elseif ($th < 12) {
        $ampm = $th . ':' . $tm . ' am';
    } elseif ($th === 12) {
        $ampm = '12:' . $tm . ' pm';
    } else {
        $ampm = ($th - 12) . ':' . $tm . ' pm';
    }
    return array($base_de . ' · ' . $t . ' Uhr', $base_en . ' · ' . $ampm);
}

function eg_r_safe_url($u)
{
    return is_string($u) && preg_match('#^https?://#i', $u) ? $u : '';
}

function eg_r_num($v)
{
    if ($v === null || $v === '') {
        return '0';
    }
    if (is_float($v) && floor($v) == $v) {
        return (string) (int) $v;
    }
    return (string) $v;
}

/** Plain text from HTML, optionally shortened at a word boundary. */
function eg_r_plain($s, $limit = 0)
{
    $s = preg_replace('/<[^>]+>/', ' ', (string) $s);
    $s = preg_replace('/[\s\x{00A0}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}]+/u', ' ', $s);
    $s = trim(html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($limit && mb_strlen($s) > $limit) {
        $cut = mb_substr($s, 0, $limit);
        $sp = mb_strrpos($cut, ' ');
        if ($sp !== false) {
            $cut = mb_substr($cut, 0, $sp);
        }
        $s = preg_replace('/[ ,;:–-]+$/u', '', $cut) . ' …';
    }
    return $s;
}

/** Upcoming soonest first, then past newest first (stable). */
function eg_r_ordered($events)
{
    $up = array();
    $past = array();
    foreach ($events as $i => $e) {
        if (($e['status'] ?? '') === 'upcoming') {
            $up[] = array($i, $e);
        } else {
            $past[] = array($i, $e);
        }
    }
    usort($up, static function ($a, $b) {
        $c = strcmp((string) ($a[1]['date'] ?? ''), (string) ($b[1]['date'] ?? ''));
        return $c !== 0 ? $c : $a[0] - $b[0];
    });
    usort($past, static function ($a, $b) {
        $c = strcmp((string) ($b[1]['date'] ?? ''), (string) ($a[1]['date'] ?? ''));
        return $c !== 0 ? $c : $a[0] - $b[0];
    });
    return array_merge(array_column($up, 1), array_column($past, 1));
}

/* ---------- events ---------- */

function eg_r_berlin_today()
{
    $now = new DateTime('now', new DateTimeZone('Europe/Berlin'));
    return $now->format('Y-m-d');
}

/** WordPress events plus the static archive (descriptions, hidden ids, extra events). */
function eg_render_events()
{
    if (!function_exists('eg_event_admin_feed_payload')) {
        return array();
    }
    $payload = eg_event_admin_feed_payload();
    $events = isset($payload['events']) && is_array($payload['events']) ? $payload['events'] : array();
    $file = eg_render_site_root() . '/assets/data/event-archive.json';
    $arch = is_readable($file) ? json_decode((string) file_get_contents($file), true) : null;
    if (!is_array($arch)) {
        return $events;
    }
    $hidden = array_flip(isset($arch['hidden_ids']) && is_array($arch['hidden_ids']) ? $arch['hidden_ids'] : array());
    $events = array_values(array_filter($events, static function ($e) use ($hidden) {
        return !isset($hidden[$e['id'] ?? '']);
    }));
    $desc = isset($arch['descriptions']) && is_array($arch['descriptions']) ? $arch['descriptions'] : array();
    foreach ($events as &$ev) {
        $a = $desc[$ev['id'] ?? ''] ?? null;
        if (!is_array($a) || (!empty($a['date']) && $a['date'] !== ($ev['date'] ?? ''))) {
            continue;
        }
        if (!empty($a['report_url']) && empty($ev['report_url'])) {
            $ev['report_url'] = $a['report_url'];
        }
        if (!empty($ev['body_html']) || !empty($ev['body_html_en'])) {
            continue;
        }
        $ev['body_html'] = (string) ($a['de'] ?? '');
        $ev['body_html_en'] = (string) ($a['en'] ?? '');
    }
    unset($ev);
    $have = array_flip(array_map(static function ($e) {
        return $e['id'] ?? '';
    }, $events));
    $added = false;
    $today = eg_r_berlin_today();
    foreach ((isset($arch['extra_events']) && is_array($arch['extra_events']) ? $arch['extra_events'] : array()) as $x) {
        if (is_array($x) && !empty($x['id']) && !isset($have[$x['id']])) {
            $x['status'] = ((string) ($x['date'] ?? '')) >= $today ? 'upcoming' : 'past';
            $events[] = $x;
            $added = true;
        }
    }
    if ($added) {
        usort($events, static function ($a, $b) {
            return strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? ''));
        });
    }
    return $events;
}

function eg_r_date_box($p)
{
    $md = eg_r_months('de', true);
    return '<div class="ev__date"><div class="ev__d">' . ($p ? sprintf('%02d', $p[2]) : '')
        . '</div><div class="ev__m">' . eg_r_esc($p ? $md[$p[1] - 1] : '')
        . '</div><div class="ev__y">' . eg_r_esc($p ? $p[0] : '') . '</div></div>';
}

function eg_r_event_card($ev)
{
    $p = eg_r_ymd($ev['date'] ?? '');
    list($when_de, $when_en) = eg_r_format_when($ev);
    $status = ($ev['status'] ?? '') === 'upcoming' ? 'upcoming' : 'past';
    $badge_class = $status === 'upcoming' ? 'badge badge--up' : 'badge badge--past';
    $attrs = implode(' ', array(
        'class="ev"',
        'id="' . eg_r_esc($ev['id'] ?? '') . '"',
        'data-date="' . eg_r_esc($ev['date'] ?? '') . '"',
        'data-status="' . $status . '"',
        'data-type="' . eg_r_esc(!empty($ev['type']) ? $ev['type'] : 'lecture') . '"',
        'data-price="' . eg_r_esc(eg_r_num($ev['price'] ?? null)) . '"',
        'data-time-start="' . eg_r_esc(!empty($ev['time_start']) ? $ev['time_start'] : '19:00') . '"',
        'data-time-end="' . eg_r_esc(!empty($ev['time_end']) ? $ev['time_end'] : '21:00') . '"',
        'data-location="' . eg_r_esc($ev['location'] ?? '') . '"',
        'data-bookable="' . (!empty($ev['bookable']) ? '1' : '0') . '"',
    ));
    $loc = (string) ($ev['location'] ?? '');
    $loc_en = (string) ($ev['location_en'] ?? '');
    $meta = '';
    if ($loc !== '' || $loc_en !== '') {
        $meta .= eg_r_meta_item('loc', eg_r_map_link($loc !== '' ? $loc : $loc_en, eg_r_bi($loc, $loc_en)));
    }
    if ($when_de !== '') {
        $meta .= eg_r_when_items($when_de, $when_en);
    }
    if (!empty($ev['speaker']) || !empty($ev['speaker_en'])) {
        $meta .= eg_r_meta_item('who', eg_r_bi($ev['speaker'] ?? '', $ev['speaker_en'] ?? ''));
    }
    $more = '';
    if (!empty($ev['body_html']) || !empty($ev['body_html_en'])) {
        $lab = $status === 'past' ? eg_r_bi('Rückblick', 'Review') : eg_r_bi('Beschreibung und Veranstaltungsdetails', 'Description and event details');
        $more = '<details class="ev-more"><summary class="ev-more__summary"><span class="ev-more__summary-lab">' . $lab
            . '</span><span class="ev-more__chev" aria-hidden="true"></span></summary>'
            . '<div class="ev-more__body"><div class="ev-more__content">'
            . eg_r_bi_html($ev['body_html'] ?? '', $ev['body_html_en'] ?? '') . '</div></div></details>';
    }
    $links = '';
    if ($status === 'past') {
        $rep = eg_r_safe_url($ev['report_url'] ?? '');
        $rec = eg_r_safe_url($ev['recording_url'] ?? '');
        if ($rep !== '') {
            $links .= '<a class="link-more ext small" href="' . eg_r_esc($rep) . '" target="_blank" rel="noopener">' . eg_r_bi('Zum Bericht', 'Read the report') . '</a>';
        }
        if ($rec !== '') {
            $links .= '<a class="link-more ext small" href="' . eg_r_esc($rec) . '" target="_blank" rel="noopener">' . eg_r_bi('Aufzeichnung ansehen', 'Watch the recording') . '</a>';
        }
        if ($links !== '') {
            $links = '<div class="ev__links" style="display:flex;flex-wrap:wrap;gap:6px 18px;margin-top:8px">' . $links . '</div>';
        }
    }
    return '<div ' . $attrs . '>' . eg_r_date_box($p)
        . '<div><h3 class="ev__t">' . eg_r_bi($ev['title'] ?? '', $ev['title_en'] ?? '') . '</h3><div class="ev__meta">' . $meta . '</div>' . $links . '</div>'
        . '<div class="ev__side"><span class="' . $badge_class . '">' . eg_r_bi($ev['badge'] ?? '', $ev['badge_en'] ?? '') . '</span></div>'
        . $more . '</div>';
}

function eg_r_event_list($events)
{
    $groups = array();
    foreach (eg_r_ordered($events) as $ev) {
        $y = substr((string) ($ev['date'] ?? ''), 0, 4);
        $key = 'y' . ($y !== '' ? $y : '0000');
        $groups[$key][] = $ev;
    }
    $out = '';
    foreach ($groups as $key => $list) {
        $out .= '<div class="ev-group"><h2 class="ev-group__y">' . eg_r_esc(substr($key, 1)) . '</h2>';
        foreach ($list as $ev) {
            $out .= eg_r_event_card($ev);
        }
        $out .= '</div>';
    }
    return $out;
}

function eg_r_event_home($events)
{
    $out = '';
    foreach (array_slice(eg_r_ordered($events), 0, 3) as $ev) {
        $p = eg_r_ymd($ev['date'] ?? '');
        $status = ($ev['status'] ?? '') === 'upcoming' ? 'upcoming' : 'past';
        $badge_class = $status === 'upcoming' ? 'badge badge--up' : 'badge badge--past';
        $loc = (string) ($ev['location'] ?? '');
        $loc_en = (string) ($ev['location_en'] ?? '');
        $out .= '<a class="ev" href="veranstaltungen.html#' . eg_r_esc($ev['id'] ?? '') . '">' . eg_r_date_box($p)
            . '<div><h3 class="ev__t">' . eg_r_bi($ev['title'] ?? '', $ev['title_en'] ?? '') . '</h3><div class="ev__meta">'
            . (($loc !== '' || $loc_en !== '') ? eg_r_meta_item('loc', eg_r_bi($loc, $loc_en)) : '')
            . ((!empty($ev['speaker']) || !empty($ev['speaker_en'])) ? eg_r_meta_item('who', eg_r_bi($ev['speaker'] ?? '', $ev['speaker_en'] ?? '')) : '')
            . '</div></div>'
            . '<div class="ev__side"><span class="' . $badge_class . '">' . eg_r_bi($ev['badge'] ?? '', $ev['badge_en'] ?? '') . '</span></div></a>';
    }
    return $out;
}

function eg_r_city($loc)
{
    $parts = explode(',', (string) $loc);
    return preg_replace('/^\d{4,5}\s+/', '', trim(end($parts)));
}

function eg_r_join_dot($parts)
{
    return implode(' · ', array_values(array_filter($parts, static function ($x) {
        return (string) $x !== '';
    })));
}

/** Homepage "Aktuelles" lead card: next upcoming event, otherwise the latest past one. */
function eg_r_event_news($events)
{
    $ordered = eg_r_ordered($events);
    $ev = $ordered ? $ordered[0] : null;
    $p = $ev ? eg_r_ymd($ev['date'] ?? '') : null;
    if (!$ev || !$p) {
        return '';
    }
    $is_up = ($ev['status'] ?? '') === 'upcoming';
    $md = eg_r_months('de');
    $me = eg_r_months('en');
    $date_de = sprintf('%d. %s %d', $p[2], $md[$p[1] - 1], $p[0]);
    $date_en = sprintf('%d %s %d', $p[2], $me[$p[1] - 1], $p[0]);
    $city_de = eg_r_city($ev['location'] ?? '');
    $city_en = eg_r_city(!empty($ev['location_en']) ? $ev['location_en'] : ($ev['location'] ?? ''));
    $k_de = $is_up ? eg_r_join_dot(array($ev['badge'] ?? '', $city_de)) : eg_r_join_dot(array('Rückblick', $city_de));
    $k_en = $is_up ? eg_r_join_dot(array(!empty($ev['badge_en']) ? $ev['badge_en'] : ($ev['badge'] ?? ''), $city_en)) : eg_r_join_dot(array('Review', $city_en));
    $t_de = eg_r_plain($ev['body_html'] ?? '', 150);
    if ($t_de === '') {
        $t_de = (string) ($ev['speaker'] ?? '');
    }
    $t_en = eg_r_plain($ev['body_html_en'] ?? '', 150);
    if ($t_en === '') {
        $t_en = !empty($ev['speaker_en']) ? (string) $ev['speaker_en'] : $t_de;
    }
    $link = $is_up ? eg_r_bi('Zur Veranstaltung', 'View event') : eg_r_bi('Zum Rückblick', 'Read the review');
    $p_de = mb_strpos(mb_substr($t_de, 0, 60), $date_de) !== false ? $t_de : eg_r_join_dot(array($date_de, $t_de));
    $p_en = mb_strpos(mb_substr($t_en, 0, 60), $date_en) !== false ? $t_en : eg_r_join_dot(array($date_en, $t_en));
    return '<a class="card card--feat card--link" id="home-news-event" href="veranstaltungen.html#' . eg_r_esc($ev['id'] ?? '') . '">'
        . '<span class="card__k">' . eg_r_bi($k_de, $k_en) . '</span>'
        . '<h3 class="card__t">' . eg_r_bi($ev['title'] ?? '', $ev['title_en'] ?? '') . '</h3>'
        . '<p>' . eg_r_bi($p_de, $p_en) . '</p>'
        . '<span class="link-more">' . $link . '</span></a>';
}

function eg_r_iso($ev, $key)
{
    $p = eg_r_ymd($ev['date'] ?? '');
    if (!$p) {
        return '';
    }
    $t = substr((string) ($ev[$key] ?? ''), 0, 5);
    if (!preg_match('/^\d\d:\d\d$/', $t)) {
        return sprintf('%04d-%02d-%02d', $p[0], $p[1], $p[2]);
    }
    $dt = new DateTime(sprintf('%04d-%02d-%02d %s:00', $p[0], $p[1], $p[2], $t), new DateTimeZone('Europe/Berlin'));
    return $dt->format('Y-m-d\TH:i:sP');
}

function eg_r_event_schema_block($events)
{
    $site = 'https://eurasiengesellschaft.de';
    $board = array(
        '/\bRahr\b/u'                 => 'alexander-rahr',
        '/\bNeu\b/u'                  => 'alexander-neu',
        '/\bPolajner\b/u'             => 'christoph-polajner',
        '/\bWipperf(?:ü|ue)rth\b/u'   => 'christian-wipperfuerth',
        '/\bSchraps\b/u'              => 'uwe-schraps',
    );
    $graph = array();
    foreach ($events as $ev) {
        if (empty($ev['id']) || !eg_r_ymd($ev['date'] ?? '')) {
            continue;
        }
        $url = $site . '/veranstaltungen.html#' . $ev['id'];
        $up = ($ev['status'] ?? '') === 'upcoming';
        $loc = !empty($ev['location']) ? (string) $ev['location'] : 'Berlin';
        $desc = eg_r_plain($ev['body_html'] ?? '', 500);
        if ($desc === '') {
            $desc = eg_r_join_dot(array($ev['badge'] ?? '', $ev['speaker'] ?? '', $loc));
        }
        $item = array(
            '@type'               => 'Event',
            '@id'                 => $url,
            'name'                => (string) ($ev['title'] ?? ''),
            'url'                 => $url,
            'description'         => $desc,
            'inLanguage'          => 'de',
            'startDate'           => ($up && empty($ev['time_tba'])) ? eg_r_iso($ev, 'time_start') : eg_r_iso(array('date' => $ev['date']), 'x'),
            'eventStatus'         => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'location'            => array('@type' => 'Place', 'name' => $loc, 'address' => $loc),
            'image'               => $site . '/assets/images/social-share.jpg?v=3',
        );
        if (!empty($ev['title_en']) && $ev['title_en'] !== ($ev['title'] ?? '')) {
            $item['alternateName'] = $ev['title_en'];
        }
        if ($up && !empty($ev['time_end']) && empty($ev['time_tba'])) {
            $item['endDate'] = eg_r_iso($ev, 'time_end');
        }
        if (preg_match('/Berlin|Köln|Hamburg|München|Deutschland|Logenhaus/iu', $loc)) {
            $item['organizer'] = array('@id' => $site . '/#organization');
        } else {
            $item['contributor'] = array('@id' => $site . '/#organization');
        }
        $people = array();
        foreach ($board as $rx => $slug) {
            if (preg_match($rx, (string) ($ev['speaker'] ?? ''))) {
                $people[] = array('@id' => $site . '/personen/' . $slug . '.html#person');
            }
        }
        if ($people) {
            $item['performer'] = count($people) > 1 ? $people : $people[0];
        }
        if ($up) {
            $price = isset($ev['price']) ? $ev['price'] : 0;
            if (array_key_exists('price', $ev) && $ev['price'] !== null) {
                $item['isAccessibleForFree'] = !$price;
            }
            if (!empty($ev['bookable']) || $price) {
                $item['offers'] = array('@type' => 'Offer', 'price' => eg_r_num($price), 'priceCurrency' => 'EUR', 'url' => $url, 'availability' => 'https://schema.org/InStock');
            }
        }
        $rec = eg_r_safe_url($ev['recording_url'] ?? '');
        if ($rec !== '') {
            $item['recordedIn'] = array('@type' => 'CreativeWork', 'url' => $rec);
        }
        $graph[] = $item;
    }
    $js = wp_json_encode(array('@context' => 'https://schema.org', '@graph' => $graph), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    return '<script type="application/ld+json" id="ev-schema">' . "\n" . str_replace('</', '<\/', (string) $js) . "\n</script>";
}

function eg_r_event_llms($events)
{
    $site = 'https://eurasiengesellschaft.de';
    $up = array();
    $past = array();
    foreach ($events as $e) {
        if (!eg_r_ymd($e['date'] ?? '')) {
            continue;
        }
        if (($e['status'] ?? '') === 'upcoming') {
            $up[] = $e;
        } else {
            $past[] = $e;
        }
    }
    usort($up, static function ($a, $b) {
        return strcmp((string) $a['date'], (string) $b['date']);
    });
    $line = static function ($e, $with_time) use ($site) {
        $p = eg_r_ymd($e['date']);
        $when = sprintf('%04d-%02d-%02d', $p[0], $p[1], $p[2]);
        if ($with_time && !empty($e['time_tba'])) {
            $when .= ', time to be announced';
        } elseif ($with_time && !empty($e['time_start'])) {
            $when .= ', ' . substr((string) $e['time_start'], 0, 5);
        }
        $title = (string) ($e['title'] ?? '');
        if (!empty($e['title_en']) && $e['title_en'] !== $title) {
            $title .= ' (EN: ' . $e['title_en'] . ')';
        }
        $bits = array_filter(array($when, $title, $e['speaker'] ?? '', $e['location'] ?? '', $e['badge'] ?? ''), static function ($b) {
            return (string) $b !== '';
        });
        return '- ' . implode(' – ', $bits) . ' – ' . $site . '/veranstaltungen.html#' . $e['id'];
    };
    $out = array('## Events (Veranstaltungen)', '', 'Full calendar and archive with descriptions: ' . $site . '/veranstaltungen.html', '',
        'Format of each line: date – title – speakers – place – format – link.', '', '### Upcoming', '');
    if ($up) {
        foreach ($up as $e) {
            $out[] = $line($e, true);
        }
    } else {
        $out[] = '- No upcoming events announced at the moment.';
    }
    $out[] = '';
    $out[] = '### Past events';
    $out[] = '';
    foreach ($past as $e) {
        $out[] = $line($e, false);
    }
    return implode("\n", $out);
}

/* ---------- page assembly ---------- */

/** Replace the first marked region; leave the page unchanged if the marker is missing. */
function eg_r_replace($html, $pattern, $replacement)
{
    $nl = strpos($html, "\r\n") !== false ? "\r\n" : "\n";
    $replacement = str_replace("\n", $nl, $replacement);
    $new = preg_replace_callback($pattern, static function () use ($replacement) {
        return $replacement;
    }, $html, 1);
    return is_string($new) ? $new : $html;
}

/**
 * Fill one page template. $page: index | veranstaltungen | llms | analysen | regionen.
 *
 * @throws RuntimeException When the data is missing (the caller then serves its fallback).
 */
function eg_render_apply($page, $html, $slug = '')
{
    if (in_array($page, array('vorstand', 'person', 'sitemap'), true)) {
        if (!function_exists('eg_ps_apply')) {
            throw new RuntimeException('personen plugin missing');
        }
        return eg_ps_apply($page, $html, $slug);
    }
    if ($page === 'analysen' || $page === 'regionen' || $page === 'mediathek') {
        if (!function_exists('eg_an_apply') || !function_exists('eg_rec_apply')) {
            throw new RuntimeException('analysen/mediathek plugin missing');
        }
        if ($page === 'mediathek') {
            return eg_rec_apply('mediathek', $html);
        }
        $html = eg_an_apply($page, $html);
        return $page === 'regionen' ? eg_rec_apply('regionen', $html) : $html;
    }
    $events = eg_render_events();
    if (!$events) {
        throw new RuntimeException('no events');
    }
    if ($page === 'veranstaltungen') {
        $html = eg_r_replace($html, '/<!--ev-prerender-->.*?<!--\/ev-prerender-->/s', '<!--ev-prerender-->' . eg_r_event_list($events) . '<!--/ev-prerender-->');
        $html = eg_r_replace($html, '/<script type="application\/ld\+json" id="ev-schema">.*?<\/script>/s', eg_r_event_schema_block($events));
    } elseif ($page === 'index') {
        $html = eg_r_replace($html, '/<!--ev-prerender-->.*?<!--\/ev-prerender-->/s', '<!--ev-prerender-->' . eg_r_event_home($events) . '<!--/ev-prerender-->');
        $news = eg_r_event_news($events);
        if ($news !== '') {
            $html = eg_r_replace($html, '/<!--ev-news-->.*?<!--\/ev-news-->/s', '<!--ev-news-->' . $news . '<!--/ev-news-->');
        }
    } elseif ($page === 'llms') {
        $html = eg_r_replace($html, '/<!-- events:start -->.*?<!-- events:end -->/s', "<!-- events:start -->\n" . eg_r_event_llms($events) . "\n<!-- events:end -->");
    }
    if (($page === 'llms' || $page === 'index') && function_exists('eg_ps_apply')) {
        try {
            $html = eg_ps_apply($page, $html); // board data is an extra: the events part must not fail because of it
        } catch (Throwable $e) {
            unset($e);
        }
    }
    return $html;
}
