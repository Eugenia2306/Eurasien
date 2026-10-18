<?php
/**
 * Plugin Name: EG Event Handoff
 * Description: Brochure event registration → Stripe Checkout (qty × fee), then success with add-to-calendar.
 * Author: d4rl1ngt0n
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Brochure events root URL.
 */
function eg_event_handoff_brochure_events_url($hash = '', $query = array())
{
    $base = function_exists('eg_brochure_public_url')
        ? eg_brochure_public_url('veranstaltungen.html')
        : home_url('/');
    if (!empty($query)) {
        $base = add_query_arg($query, $base);
    }
    if ($hash !== '') {
        $base .= '#' . ltrim($hash, '#');
    }
    return $base;
}

/**
 * Stripe secret key (PMPro first, then Woo Stripe).
 */
function eg_event_handoff_stripe_secret()
{
    $key = (string) get_option('pmpro_stripe_secretkey');
    if ($key !== '') {
        return $key;
    }
    $woo = get_option('woocommerce_stripe_settings', array());
    if (is_array($woo) && !empty($woo['secret_key'])) {
        return (string) $woo['secret_key'];
    }
    return '';
}

/**
 * Stripe REST helper.
 *
 * @param string              $method GET|POST
 * @param string              $path   e.g. checkout/sessions
 * @param array<string,mixed> $body   form fields
 * @return array<string,mixed>|WP_Error
 */
function eg_event_handoff_stripe_request($method, $path, $body = array())
{
    $key = eg_event_handoff_stripe_secret();
    if ($key === '') {
        return new WP_Error('eg_stripe_key', 'Stripe secret key missing');
    }
    $url = 'https://api.stripe.com/v1/' . ltrim($path, '/');
    $args = array(
        'method'  => strtoupper($method),
        'timeout' => 45,
        'headers' => array(
            'Authorization' => 'Bearer ' . $key,
        ),
    );
    if (!empty($body)) {
        $args['body'] = $body;
    }
    $res = wp_remote_request($url, $args);
    if (is_wp_error($res)) {
        return $res;
    }
    $code = (int) wp_remote_retrieve_response_code($res);
    $data = json_decode((string) wp_remote_retrieve_body($res), true);
    if (!is_array($data)) {
        return new WP_Error('eg_stripe_json', 'Invalid Stripe response');
    }
    if ($code < 200 || $code >= 300) {
        $msg = isset($data['error']['message']) ? (string) $data['error']['message'] : 'Stripe error';
        return new WP_Error('eg_stripe_http', $msg, array('status' => $code));
    }
    return $data;
}

/**
 * Redirect helper (brochure paths may sit outside /app/).
 */
function eg_event_handoff_redirect($url)
{
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
 * Sanitize incoming registration payload.
 *
 * @return array<string,mixed>|WP_Error
 */
function eg_event_handoff_parse_request($data)
{
    if (!is_array($data)) {
        $data = array();
    }

    $event_id = isset($data['event_id']) ? sanitize_title((string) $data['event_id']) : '';
    $title = isset($data['event_title']) ? sanitize_text_field((string) $data['event_title']) : '';
    $date = isset($data['event_date']) ? sanitize_text_field((string) $data['event_date']) : '';
    $time_start = isset($data['event_time_start']) ? sanitize_text_field((string) $data['event_time_start']) : '19:00';
    $time_end = isset($data['event_time_end']) ? sanitize_text_field((string) $data['event_time_end']) : '21:00';
    $location = isset($data['event_location']) ? sanitize_text_field((string) $data['event_location']) : '';
    $unit = isset($data['unit_price']) ? (float) $data['unit_price'] : 10.0;
    $qty = isset($data['qty']) ? (int) $data['qty'] : 1;
    $first = isset($data['first_name']) ? sanitize_text_field((string) $data['first_name']) : '';
    $last = isset($data['last_name']) ? sanitize_text_field((string) $data['last_name']) : '';
    $email = isset($data['email']) ? sanitize_email((string) $data['email']) : '';
    $consent = !empty($data['consent']);

    if ($event_id === '' || $title === '' || $date === '') {
        return new WP_Error('eg_event_meta', 'missing_event');
    }
    if ($first === '' || $last === '' || !is_email($email) || !$consent) {
        return new WP_Error('eg_event_fields', 'invalid_fields');
    }
    if ($qty < 1) {
        $qty = 1;
    }
    if ($qty > 20) {
        $qty = 20;
    }
    if ($unit < 0.5) {
        $unit = 10.0;
    }

    return array(
        'event_id'   => $event_id,
        'title'      => $title,
        'date'       => $date,
        'time_start' => $time_start,
        'time_end'   => $time_end,
        'location'   => $location,
        'unit_price' => $unit,
        'qty'        => $qty,
        'first_name' => $first,
        'last_name'  => $last,
        'email'      => $email,
    );
}

/**
 * Create Stripe Checkout Session and redirect to Stripe.
 *
 * @param array<string,mixed> $p Parsed payload.
 * @return WP_Error|void
 */
function eg_event_handoff_start_checkout(array $p)
{
    $unit_cents = (int) round(((float) $p['unit_price']) * 100);
    $qty = (int) $p['qty'];
    $success = home_url('/eg-event-success.php') . '?session_id={CHECKOUT_SESSION_ID}';
    $cancel = eg_event_handoff_brochure_events_url($p['event_id']);
    $product_name = sprintf('Teilnahme: %1$s (%2$d×)', $p['title'], $qty);

    $body = array(
        'mode'                                                   => 'payment',
        'success_url'                                            => $success,
        'cancel_url'                                             => $cancel,
        'customer_email'                                         => $p['email'],
        'client_reference_id'                                    => substr($p['event_id'], 0, 200),
        'line_items[0][quantity]'                                => $qty,
        'line_items[0][price_data][currency]'                    => 'eur',
        'line_items[0][price_data][unit_amount]'                 => $unit_cents,
        'line_items[0][price_data][product_data][name]'          => $product_name,
        'metadata[eg_type]'                                      => 'event_ticket',
        'metadata[event_id]'                                     => $p['event_id'],
        'metadata[event_title]'                                  => substr($p['title'], 0, 450),
        'metadata[event_date]'                                   => $p['date'],
        'metadata[event_time_start]'                             => $p['time_start'],
        'metadata[event_time_end]'                               => $p['time_end'],
        'metadata[event_location]'                               => substr($p['location'], 0, 450),
        'metadata[qty]'                                          => (string) $qty,
        'metadata[unit_price]'                                   => (string) $p['unit_price'],
        'metadata[first_name]'                                   => $p['first_name'],
        'metadata[last_name]'                                    => $p['last_name'],
        'metadata[email]'                                        => $p['email'],
        'payment_intent_data[metadata][eg_type]'                 => 'event_ticket',
        'payment_intent_data[metadata][event_id]'                => $p['event_id'],
    );

    $session = eg_event_handoff_stripe_request('POST', 'checkout/sessions', $body);
    if (is_wp_error($session)) {
        return $session;
    }
    if (empty($session['url'])) {
        return new WP_Error('eg_stripe_url', 'No Checkout URL');
    }

    eg_event_handoff_redirect((string) $session['url']);
}

/**
 * Main handler for /app/eg-event-handoff.php
 */
function eg_event_handoff_handle()
{
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) === 'OPTIONS') {
        status_header(204);
        exit;
    }

    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
        status_header(405);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'POST required';
        exit;
    }

    $data = !empty($_POST) ? wp_unslash($_POST) : array();
    $parsed = eg_event_handoff_parse_request($data);
    if (is_wp_error($parsed)) {
        $code = $parsed->get_error_code() === 'eg_event_fields' ? 'fields' : 'event';
        $hash = isset($data['event_id']) ? sanitize_title((string) $data['event_id']) : '';
        eg_event_handoff_redirect(
            eg_event_handoff_brochure_events_url($hash, array('eg_event' => $code))
        );
    }

    $result = eg_event_handoff_start_checkout($parsed);
    if (is_wp_error($result)) {
        eg_event_handoff_redirect(
            eg_event_handoff_brochure_events_url($parsed['event_id'], array('eg_event' => 'stripe'))
        );
    }
}

/**
 * ICS datetime in Europe/Berlin (floating local, no Z).
 */
function eg_event_handoff_ics_local($date, $time)
{
    $time = preg_replace('/[^0-9:]/', '', (string) $time);
    if (!preg_match('/^\d{1,2}:\d{2}/', (string) $time)) {
        $time = '19:00';
    }
    $parts = explode(':', (string) $time);
    $h = str_pad((string) ((int) $parts[0]), 2, '0', STR_PAD_LEFT);
    $m = str_pad((string) ((int) ($parts[1] ?? 0)), 2, '0', STR_PAD_LEFT);
    $ymd = preg_replace('/[^0-9]/', '', (string) $date);
    if (strlen($ymd) !== 8) {
        $ymd = gmdate('Ymd');
    }
    return $ymd . 'T' . $h . $m . '00';
}

/**
 * Build Google Calendar template URL.
 *
 * @param array<string,string> $meta
 */
function eg_event_handoff_gcal_url(array $meta)
{
    $start = eg_event_handoff_ics_local($meta['event_date'] ?? '', $meta['event_time_start'] ?? '19:00');
    $end = eg_event_handoff_ics_local($meta['event_date'] ?? '', $meta['event_time_end'] ?? '21:00');
    $params = array(
        'action'   => 'TEMPLATE',
        'text'     => (string) ($meta['event_title'] ?? 'Eurasien Gesellschaft'),
        'dates'    => $start . '/' . $end,
        'details'  => 'Eurasien Gesellschaft e. V.',
        'location' => (string) ($meta['event_location'] ?? ''),
        'ctz'      => 'Europe/Berlin',
    );
    return 'https://calendar.google.com/calendar/render?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

/**
 * Build .ics body.
 *
 * @param array<string,string> $meta
 */
function eg_event_handoff_ics_body(array $meta)
{
    $uid = 'eg-' . sanitize_title((string) ($meta['event_id'] ?? 'event')) . '-' . wp_generate_password(8, false) . '@eurasiengesellschaft.de';
    $start = eg_event_handoff_ics_local($meta['event_date'] ?? '', $meta['event_time_start'] ?? '19:00');
    $end = eg_event_handoff_ics_local($meta['event_date'] ?? '', $meta['event_time_end'] ?? '21:00');
    $summary = str_replace(array("\n", "\r", ','), array(' ', ' ', '\\,'), (string) ($meta['event_title'] ?? 'Event'));
    $loc = str_replace(array("\n", "\r", ','), array(' ', ' ', '\\,'), (string) ($meta['event_location'] ?? ''));
    $stamp = gmdate('Ymd\THis\Z');
    $lines = array(
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Eurasien Gesellschaft//Event//DE',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'BEGIN:VEVENT',
        'UID:' . $uid,
        'DTSTAMP:' . $stamp,
        'DTSTART;TZID=Europe/Berlin:' . $start,
        'DTEND;TZID=Europe/Berlin:' . $end,
        'SUMMARY:' . $summary,
        'LOCATION:' . $loc,
        'DESCRIPTION:Eurasien Gesellschaft e. V.',
        'END:VEVENT',
        'END:VCALENDAR',
    );
    return implode("\r\n", $lines) . "\r\n";
}

/**
 * Send paid booking confirmations once per Stripe Checkout Session.
 *
 * @param array<string,mixed> $session Stripe Checkout Session.
 * @param array<string,string> $meta    Sanitized session metadata.
 */
function eg_event_handoff_send_emails(array $session, array $meta, $gcal_url, $ics_url)
{
    if (!function_exists('eg_email_layout') || !function_exists('eg_email_summary_rows')) {
        return;
    }

    $session_id = sanitize_text_field((string) ($session['id'] ?? ''));
    $email = sanitize_email((string) ($meta['email'] ?? ($session['customer_details']['email'] ?? '')));
    if ($session_id === '' || !is_email($email)) {
        return;
    }

    $title = sanitize_text_field((string) ($meta['event_title'] ?? 'Veranstaltung'));
    $first = sanitize_text_field((string) ($meta['first_name'] ?? ''));
    $last = sanitize_text_field((string) ($meta['last_name'] ?? ''));
    $qty = max(1, (int) ($meta['qty'] ?? 1));
    $unit = (float) ($meta['unit_price'] ?? 0);
    $total = number_format_i18n($qty * $unit, 2) . ' €';
    $rows = array(
        'Veranstaltung' => esc_html($title),
        'Datum'         => esc_html((string) ($meta['event_date'] ?? '')),
        'Uhrzeit'       => esc_html((string) ($meta['event_time_start'] ?? '')) . '–' . esc_html((string) ($meta['event_time_end'] ?? '')),
        'Ort'           => esc_html((string) ($meta['event_location'] ?? '')),
        'Tickets'       => esc_html((string) $qty),
        'Gesamt'        => esc_html($total),
    );

    $customer_key = 'eg_event_customer_mailed_' . md5($session_id);
    if (!get_option($customer_key)) {
        $content = eg_email_summary_rows($rows)
            . '<p>Ihre Buchung ist verbindlich bestätigt. Bitte bewahren Sie diese E-Mail als Buchungsnachweis auf.</p>'
            . '<p><a href="' . esc_url($ics_url) . '" style="color:#0032a0;font-weight:700;">Kalenderdatei (.ics) herunterladen</a></p>';
        $message = eg_email_layout(
            array(
                'eyebrow'   => 'Veranstaltungsbuchung',
                'heading'   => 'Ihre Teilnahme ist bestätigt',
                'preheader' => 'Buchungsbestätigung für ' . $title,
                'intro'     => 'Hallo ' . esc_html(trim($first . ' ' . $last)) . ', vielen Dank für Ihre Anmeldung.',
                'content'   => $content,
                'cta_url'   => $gcal_url,
                'cta_text'  => 'Zum Google Kalender hinzufügen',
            )
        );
        if (wp_mail($email, 'Buchungsbestätigung: ' . $title, $message, eg_email_html_headers())) {
            update_option($customer_key, gmdate('c'), false);
        }
    }

    $admin_emails = function_exists('eg_admin_notification_recipients')
        ? eg_admin_notification_recipients()
        : array(sanitize_email((string) get_option('admin_email')));
    $admin_emails = array_values(array_filter($admin_emails, 'is_email'));
    $admin_key = 'eg_event_admin_mailed_' . md5($session_id);
    if ($admin_emails && !get_option($admin_key)) {
        $admin_rows = array(
            'Name'          => esc_html(trim($first . ' ' . $last)),
            'Kontakt'       => '<a href="mailto:' . esc_attr($email) . '" style="color:#0032a0;">' . esc_html($email) . '</a>',
            'Veranstaltung' => esc_html($title),
            'Wann'          => esc_html(trim((string) ($meta['event_date'] ?? '') . ' ' . (string) ($meta['event_time_start'] ?? ''))),
            'Wo'            => esc_html((string) ($meta['event_location'] ?? '')),
            'Plätze'        => esc_html((string) $qty),
            'Beitrag'       => esc_html($total),
        );
        $admin_message = eg_email_layout(
            array(
                'eyebrow'   => 'Veranstaltung',
                'heading'   => 'Eine neue Anmeldung ist da',
                'preheader' => trim($first . ' ' . $last) . ' nimmt an ' . $title . ' teil.',
                'intro'     => esc_html(trim($first . ' ' . $last)) . ' hat sich für „' . esc_html($title) . '“ angemeldet. Die Teilnahme ist bestätigt.',
                'content'   => eg_email_summary_rows($admin_rows, 'admin')
                    . '<p>Wenn Sie möchten, können Sie sich kurz bei der Person melden. Alles Weitere ist bereits erledigt.</p>',
                'cta_url'   => function_exists('eg_email_public_url') ? eg_email_public_url('veranstaltungen.html') : '',
                'cta_text'  => 'Veranstaltungen ansehen',
                'admin'     => true,
            )
        );
        if (wp_mail($admin_emails, 'Neue Anmeldung: ' . $title, $admin_message, eg_email_html_headers())) {
            update_option($admin_key, gmdate('c'), false);
        }
    }
}

/**
 * Render success page HTML (or serve ICS download).
 */
function eg_event_handoff_render_success()
{
    $session_id = isset($_GET['session_id']) ? sanitize_text_field(wp_unslash($_GET['session_id'])) : '';
    if ($session_id === '' || strpos($session_id, 'cs_') !== 0) {
        status_header(400);
        echo 'Missing session';
        exit;
    }

    $session = eg_event_handoff_stripe_request('GET', 'checkout/sessions/' . rawurlencode($session_id));
    if (is_wp_error($session)) {
        status_header(502);
        echo 'Could not verify payment';
        exit;
    }

    $paid = in_array((string) ($session['payment_status'] ?? ''), array('paid', 'no_payment_required'), true);
    $meta = isset($session['metadata']) && is_array($session['metadata']) ? $session['metadata'] : array();

    if (!empty($_GET['download']) && $_GET['download'] === 'ics' && $paid) {
        $ics = eg_event_handoff_ics_body($meta);
        nocache_headers();
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="eurasien-event.ics"');
        echo $ics;
        exit;
    }

    $title = (string) ($meta['event_title'] ?? 'Veranstaltung');
    $date = (string) ($meta['event_date'] ?? '');
    $loc = (string) ($meta['event_location'] ?? '');
    $qty = (string) ($meta['qty'] ?? '1');
    $unit = (string) ($meta['unit_price'] ?? '10');
    $email = (string) ($meta['email'] ?? ($session['customer_details']['email'] ?? ''));
    $gcal = eg_event_handoff_gcal_url($meta);
    $ics_url = add_query_arg(
        array(
            'session_id' => $session_id,
            'download'   => 'ics',
        ),
        home_url('/eg-event-success.php')
    );
    if ($paid) {
        eg_event_handoff_send_emails($session, $meta, $gcal, $ics_url);
    }
    $back = eg_event_handoff_brochure_events_url((string) ($meta['event_id'] ?? ''));
    $home = function_exists('eg_brochure_public_url') ? eg_brochure_public_url('') : '/';

    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html($paid ? 'Buchung bestätigt' : 'Zahlung ausstehend'); ?> | Eurasien Gesellschaft</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@400;600;700&family=Source+Serif+4:wght@600;700&display=swap" rel="stylesheet">
<style>
:root{--ink:#0b1c2c;--muted:#5b6b7c;--line:#d7dde5;--red:#b42318;--blue:#0032a0;--paper:#f6f8fb;--surface:#fff}
*{box-sizing:border-box}
body{margin:0;font-family:"Libre Franklin",system-ui,sans-serif;color:var(--ink);background:linear-gradient(180deg,#eef3f9,#fff 40%)}
.wrap{max-width:640px;margin:0 auto;padding:48px 20px 64px}
.card{background:var(--surface);border:1px solid var(--line);border-radius:10px;padding:28px 24px;box-shadow:0 10px 30px rgba(11,28,44,.08)}
.eyebrow{color:var(--red);font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;font-weight:700;margin:0 0 8px}
h1{font-family:"Source Serif 4",Georgia,serif;color:var(--blue);font-size:1.75rem;margin:0 0 12px;line-height:1.25}
.lead{color:var(--muted);margin:0 0 20px;line-height:1.5}
.meta{border:1px solid var(--line);border-radius:8px;background:var(--paper);padding:14px 14px;margin:0 0 18px}
.meta p{margin:0 0 6px;font-size:.95rem}
.meta p:last-child{margin:0}
.meta strong{color:var(--blue)}
.actions{display:flex;flex-wrap:wrap;gap:10px;margin:18px 0 8px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border-radius:6px;padding:12px 16px;font-weight:700;text-decoration:none;border:0;cursor:pointer}
.btn--primary{background:var(--blue);color:#fff}
.btn--ghost{background:#fff;color:var(--blue);border:1px solid var(--line)}
.note{font-size:.88rem;color:var(--muted);margin:14px 0 0;line-height:1.45}
a.link{color:var(--blue)}
.warn{color:var(--red);font-weight:600}
</style>
</head>
<body>
<main class="wrap">
  <div class="card">
    <?php if ($paid) : ?>
      <p class="eyebrow">Buchung bestätigt</p>
      <h1>Vielen Dank für Ihre Anmeldung</h1>
      <p class="lead">Ihre Zahlung war erfolgreich. Die digitale Eintrittskarte erhalten Sie per E-Mail<?php echo $email ? ' an <strong>' . esc_html($email) . '</strong>' : ''; ?>.</p>
      <div class="meta">
        <p><strong><?php echo esc_html($title); ?></strong></p>
        <?php if ($date) : ?><p>Datum: <?php echo esc_html($date); ?></p><?php endif; ?>
        <?php if ($loc) : ?><p>Ort: <?php echo esc_html($loc); ?></p><?php endif; ?>
        <p>Tickets: <?php echo esc_html($qty); ?> × <?php echo esc_html($unit); ?> €</p>
      </div>
      <div class="actions">
        <a class="btn btn--primary" href="<?php echo esc_url($gcal); ?>" target="_blank" rel="noopener noreferrer">Add to calendar (Google)</a>
        <a class="btn btn--ghost" href="<?php echo esc_url($ics_url); ?>">Download .ics</a>
      </div>
      <p class="note">Sie können den Termin auch in Outlook oder Apple Kalender importieren (.ics).</p>
    <?php else : ?>
      <p class="eyebrow">Zahlung</p>
      <h1>Zahlung noch nicht abgeschlossen</h1>
      <p class="lead warn">Wir konnten diese Buchung noch nicht als bezahlt bestätigen. Bitte prüfen Sie Ihre E-Mail oder versuchen Sie es erneut.</p>
    <?php endif; ?>
    <p class="note"><a class="link" href="<?php echo esc_url($back); ?>">Zurück zu den Veranstaltungen</a> · <a class="link" href="<?php echo esc_url($home); ?>">Zur Website</a></p>
  </div>
</main>
</body>
</html>
    <?php
    exit;
}
