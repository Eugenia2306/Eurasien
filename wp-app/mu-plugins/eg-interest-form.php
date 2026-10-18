<?php
/**
 * Plugin Name: EG Interest and contact forms
 * Description: "Interesse an einer Mitgliedschaft" form (membership page) and the contact form in the floating "Kontakt" button. Both send the message by e-mail to the Society. No account, no payment, nothing stored on the website.
 *
 * Forms: POST to /app/wp-admin/admin-post.php with action=eg_interest (redirects back) or action=eg_contact (JSON answer for the button).
 * Spam protection: hidden trap field, minimum fill time (set by JS), max. 5 messages per IP per hour and form.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Where to send the visitor back to: the page the form was on (main site or WordPress copy).
 */
function eg_interest_back_url($status)
{
    $base = 'https://eurasiengesellschaft.de/mitgliedschaft.html';
    $ref  = isset($_SERVER['HTTP_REFERER']) ? (string) $_SERVER['HTTP_REFERER'] : '';
    $p    = $ref !== '' ? wp_parse_url($ref) : false;
    if (is_array($p) && isset($p['host'], $p['path']) && $p['host'] === 'eurasiengesellschaft.de'
        && preg_match('#^/(app/)?mitgliedschaft(\.html|/)?$#', $p['path'])) {
        $base = 'https://eurasiengesellschaft.de' . $p['path'];
    }
    return $base . '?plan=expert&interest=' . rawurlencode($status) . '#membership-registration';
}

/**
 * One-line text field, trimmed and cut to $max characters.
 */
function eg_interest_field($key, $max)
{
    $v = isset($_POST[$key]) ? wp_unslash($_POST[$key]) : '';
    if (!is_string($v)) {
        return '';
    }
    $v = trim(preg_replace('/[\r\n\t]+/', ' ', $v));
    return function_exists('mb_substr') ? mb_substr($v, 0, $max) : substr($v, 0, $max);
}

function eg_interest_handle()
{
    $back = function ($status) {
        wp_redirect(eg_interest_back_url($status), 303);
        exit;
    };

    /* Trap field that people never see: bots fill it. Pretend success. */
    if (!empty($_POST['website'])) {
        $back('sent');
    }

    /* Filled in too fast, or sent without JavaScript (which sets the time). */
    $elapsed = isset($_POST['elapsed']) ? (int) $_POST['elapsed'] : 0;
    if ($elapsed < 4) {
        $back('error');
    }

    /* At most 5 messages per hour from one IP address (stored only as a hash). */
    $ip    = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    $key   = 'eg_interest_' . md5($ip);
    $count = (int) get_transient($key);
    if ($count >= 5) {
        $back('limit');
    }

    $first   = sanitize_text_field(eg_interest_field('first_name', 80));
    $last    = sanitize_text_field(eg_interest_field('last_name', 80));
    $email   = sanitize_email(eg_interest_field('email', 190));
    $phone   = preg_replace('/[^0-9+()\/\-. ]/', '', eg_interest_field('phone', 40));
    $message = (isset($_POST['message']) && is_string($_POST['message'])) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
    $message = function_exists('mb_substr') ? mb_substr($message, 0, 3000) : substr($message, 0, 3000);
    $consent = !empty($_POST['consent']);

    $wanted = (isset($_POST['interest']) && is_array($_POST['interest'])) ? array_map('strval', wp_unslash($_POST['interest'])) : array();
    $labels = array(
        'info'       => 'Informationen über die Gesellschaft',
        'membership' => 'eine Mitgliedschaft',
    );
    $chosen = array();
    foreach ($labels as $k => $label) {
        if (in_array($k, $wanted, true)) {
            $chosen[] = $label;
        }
    }

    if ($first === '' || $last === '' || !is_email($email) || !$consent) {
        $back('error');
    }

    $lines = array(
        'Neue Nachricht über das Formular "Interesse an einer Mitgliedschaft" auf eurasiengesellschaft.de',
        '',
        'Name: ' . $first . ' ' . $last,
        'E-Mail: ' . $email,
        'Telefon: ' . ($phone !== '' ? $phone : '-'),
        'Interesse an: ' . ($chosen ? implode('; ', $chosen) : '-'),
        '',
        'Nachricht:',
        ($message !== '' ? $message : '-'),
        '',
        'Datenschutzhinweise zur Kenntnis genommen: ja',
        'Gesendet: ' . wp_date('d.m.Y, H:i') . ' Uhr',
        '',
        'Zum Antworten einfach auf diese E-Mail antworten.',
    );

    $to = function_exists('eg_admin_notification_recipients')
        ? eg_admin_notification_recipients()
        : array('kontakt@eurasien-gesellschaft.org');
    $reply_name = trim(preg_replace('/["<>,;:\\\\]/', '', $first . ' ' . $last));
    $headers = array(
        'Content-Type: text/plain; charset=UTF-8',
        'Reply-To: ' . $reply_name . ' <' . $email . '>',
    );
    $subject = 'Interesse an der Eurasien Gesellschaft: ' . $first . ' ' . $last;

    $ok = wp_mail($to, $subject, implode("\n", $lines), $headers);
    set_transient($key, $count + 1, HOUR_IN_SECONDS);
    $back($ok ? 'sent' : 'error');
}

add_action('admin_post_nopriv_eg_interest', 'eg_interest_handle');
add_action('admin_post_eg_interest', 'eg_interest_handle');

/**
 * Contact form in the floating "Kontakt" button. Answers JSON when ajax=1 (the button), otherwise redirects.
 */
function eg_contact_respond($ok, $code)
{
    if (!empty($_POST['ajax'])) {
        wp_send_json(array('ok' => (bool) $ok, 'code' => (string) $code), $ok ? 200 : 400);
    }
    wp_redirect('https://eurasiengesellschaft.de/?contact=' . rawurlencode($ok ? 'sent' : (string) $code), 303);
    exit;
}

function eg_contact_handle()
{
    /* Trap field that people never see: bots fill it. Pretend success. */
    if (!empty($_POST['website'])) {
        eg_contact_respond(true, 'sent');
    }

    $elapsed = isset($_POST['elapsed']) ? (int) $_POST['elapsed'] : 0;
    if ($elapsed < 4) {
        eg_contact_respond(false, 'too_fast');
    }

    $ip    = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    $key   = 'eg_contact_' . md5($ip);
    $count = (int) get_transient($key);
    if ($count >= 5) {
        eg_contact_respond(false, 'limit');
    }

    $name    = sanitize_text_field(eg_interest_field('name', 120));
    $email   = sanitize_email(eg_interest_field('email', 190));
    $message = (isset($_POST['message']) && is_string($_POST['message'])) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
    $message = function_exists('mb_substr') ? mb_substr($message, 0, 3000) : substr($message, 0, 3000);
    $page    = eg_interest_field('page', 200);
    $page    = preg_match('#^/[A-Za-z0-9/._-]*$#', $page) ? $page : '';
    $consent = !empty($_POST['consent']);

    if ($name === '' || !is_email($email) || trim($message) === '' || !$consent) {
        eg_contact_respond(false, 'invalid');
    }

    $lines = array(
        'Neue Nachricht über den Kontakt-Button auf eurasiengesellschaft.de',
        '',
        'Name: ' . $name,
        'E-Mail: ' . $email,
        'Geschrieben auf der Seite: ' . ($page !== '' ? 'https://eurasiengesellschaft.de' . $page : '-'),
        '',
        'Nachricht:',
        $message,
        '',
        'Datenschutzhinweise zur Kenntnis genommen: ja',
        'Gesendet: ' . wp_date('d.m.Y, H:i') . ' Uhr',
        '',
        'Zum Antworten einfach auf diese E-Mail antworten.',
    );

    $to = function_exists('eg_admin_notification_recipients')
        ? eg_admin_notification_recipients()
        : array('kontakt@eurasien-gesellschaft.org');
    $reply_name = trim(preg_replace('/["<>,;:\\\\]/', '', $name));
    $headers = array(
        'Content-Type: text/plain; charset=UTF-8',
        'Reply-To: ' . $reply_name . ' <' . $email . '>',
    );

    $ok = wp_mail($to, 'Kontaktanfrage über die Website: ' . $name, implode("\n", $lines), $headers);
    set_transient($key, $count + 1, HOUR_IN_SECONDS);
    eg_contact_respond($ok, $ok ? 'sent' : 'mail');
}

add_action('admin_post_nopriv_eg_contact', 'eg_contact_handle');
add_action('admin_post_eg_contact', 'eg_contact_handle');
