<?php
/**
 * Plugin Name: EG Website
 * Description: "Website" menu for the team (Redakteure and Administratoren): logo of the website (SVG or PNG) and the fixed page texts (Website → Texte). SVG uploads are checked and served with a script-blocking header.
 * Author: d4rl1ngt0n
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Capability for the Website menu: editors and administrators. */
function eg_ws_cap()
{
    return 'edit_others_posts';
}

/** Current logo: url, width, height (defaults = the bundled logo). @return array{url:string,w:int,h:int,email:string} */
function eg_ws_logo()
{
    $url = (string) get_option('eg_site_logo', '');
    return array(
        'url'   => $url !== '' ? $url : '/assets/images/logo.png?v=3',
        'w'     => (int) get_option('eg_site_logo_w', 267) ?: 267,
        'h'     => (int) get_option('eg_site_logo_h', 78) ?: 78,
        'email' => (string) get_option('eg_site_logo_email', ''),
    );
}

/* ---------- menu ---------- */

add_action('admin_menu', static function () {
    add_menu_page('Website', 'Website', eg_ws_cap(), 'eg-website', 'eg_ws_logo_page', 'dashicons-admin-site-alt3', 3);
    add_submenu_page('eg-website', 'Logo', 'Logo', eg_ws_cap(), 'eg-website', 'eg_ws_logo_page');
});

function eg_ws_logo_page()
{
    if (!current_user_can(eg_ws_cap())) {
        wp_die('Keine Berechtigung.');
    }
    wp_enqueue_media();
    $logo = eg_ws_logo();
    $custom = (string) get_option('eg_site_logo', '') !== '';
    $preview = strpos($logo['url'], '/') === 0 ? 'https://eurasiengesellschaft.de' . $logo['url'] : $logo['url'];
    echo '<div class="wrap"><h1>Logo der Website</h1>';
    if (isset($_GET['eg_saved'])) {
        echo '<div class="notice notice-success is-dismissible"><p>Gespeichert. Die Website zeigt das neue Logo ab sofort.</p></div>';
    }
    if (isset($_GET['eg_error'])) {
        echo '<div class="notice notice-error"><p>' . esc_html(sanitize_text_field(wp_unslash($_GET['eg_error']))) . '</p></div>';
    }
    echo '<p>Das Logo erscheint oben auf allen Seiten der Website und im Mitgliederbereich. Am schärfsten ist eine <strong>SVG-Datei</strong>; sonst ein PNG mit transparentem Hintergrund, mindestens 1068 × 312 Pixel.</p>';
    echo '<div style="background:#fff;border:1px solid #dcdcde;padding:18px;display:inline-block;margin:8px 0 18px"><img src="' . esc_url($preview) . '" alt="" style="height:44px;width:auto;display:block"></div>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('eg_ws_logo', 'eg_ws_nonce');
    echo '<input type="hidden" name="action" value="eg_ws_logo"><input type="hidden" name="eg_logo_id" id="eg_logo_id" value="">';
    echo '<table class="form-table" role="presentation">';
    echo '<tr><th>Logo</th><td><button type="button" class="button button-primary" id="eg-pick-logo">Logo aus der Mediathek wählen oder hochladen</button> <span id="eg-logo-name" style="margin-left:8px"></span></td></tr>';
    echo '<tr><th>Logo für E-Mails (PNG)</th><td><input type="hidden" name="eg_logo_email_id" id="eg_logo_email_id" value=""><button type="button" class="button" id="eg-pick-email">PNG wählen (optional)</button> <span id="eg-email-name" style="margin-left:8px">'
        . ($logo['email'] !== '' ? esc_html(basename($logo['email'])) : 'bisher: das bisherige Logo') . '</span>'
        . '<p class="description">E-Mail-Programme zeigen keine SVG-Dateien. Ist das Logo oben ein PNG, wird es automatisch auch für E-Mails verwendet.</p></td></tr>';
    echo '</table><p><button type="submit" class="button button-primary">Speichern</button></p></form>';
    if ($custom) {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:24px">';
        wp_nonce_field('eg_ws_logo', 'eg_ws_nonce');
        echo '<input type="hidden" name="action" value="eg_ws_logo"><input type="hidden" name="eg_logo_reset" value="1">'
            . '<button type="submit" class="button" onclick="return confirm(\'Wieder das bisherige Logo verwenden?\')">Zurück zum bisherigen Logo</button></form>';
    }
    echo '</div>';
    echo "<script>jQuery(function($){ function pick(btn, field, label, types){ $(btn).on('click', function(e){ e.preventDefault(); var f = wp.media({title:'Logo wählen', button:{text:'Übernehmen'}, library:{type: types}, multiple:false}); f.on('select', function(){ var a = f.state().get('selection').first().toJSON(); $(field).val(a.id); $(label).text(a.filename || a.title); }); f.open(); }); } pick('#eg-pick-logo', '#eg_logo_id', '#eg-logo-name', ['image/svg+xml','image/png','image/jpeg','image/webp']); pick('#eg-pick-email', '#eg_logo_email_id', '#eg-email-name', ['image/png','image/jpeg']); });</script>";
}

/** Width/height of an attachment; for SVG from viewBox or width/height attributes. */
function eg_ws_dims($id)
{
    $file = get_attached_file($id);
    $meta = wp_get_attachment_metadata($id);
    if (!empty($meta['width']) && !empty($meta['height'])) {
        return array((int) $meta['width'], (int) $meta['height']);
    }
    if ($file && is_readable($file) && preg_match('/\.svg$/i', $file)) {
        $svg = (string) file_get_contents($file, false, null, 0, 4096);
        if (preg_match('/viewBox="\s*[-\d.]+[\s,]+[-\d.]+[\s,]+([\d.]+)[\s,]+([\d.]+)\s*"/i', $svg, $m) && (float) $m[1] > 0 && (float) $m[2] > 0) {
            return array((int) round((float) $m[1]), (int) round((float) $m[2]));
        }
        if (preg_match('/<svg[^>]*\swidth="([\d.]+)(?:px)?"[^>]*\sheight="([\d.]+)(?:px)?"/i', $svg, $m)) {
            return array((int) round((float) $m[1]), (int) round((float) $m[2]));
        }
    }
    return array(267, 78);
}

add_action('admin_post_eg_ws_logo', static function () {
    if (!current_user_can(eg_ws_cap()) || !isset($_POST['eg_ws_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['eg_ws_nonce'])), 'eg_ws_logo')) {
        wp_die('Keine Berechtigung.');
    }
    $back = admin_url('admin.php?page=eg-website');
    if (!empty($_POST['eg_logo_reset'])) {
        foreach (array('eg_site_logo', 'eg_site_logo_w', 'eg_site_logo_h', 'eg_site_logo_email') as $k) {
            delete_option($k);
        }
        eg_ws_changed();
        wp_safe_redirect(add_query_arg('eg_saved', '1', $back));
        exit;
    }
    $id = isset($_POST['eg_logo_id']) ? absint($_POST['eg_logo_id']) : 0;
    $email_id = isset($_POST['eg_logo_email_id']) ? absint($_POST['eg_logo_email_id']) : 0;
    if ($id) {
        $mime = (string) get_post_mime_type($id);
        if (!in_array($mime, array('image/svg+xml', 'image/png', 'image/jpeg', 'image/webp'), true)) {
            wp_safe_redirect(add_query_arg('eg_error', rawurlencode('Bitte eine SVG-, PNG-, JPG- oder WebP-Datei wählen.'), $back));
            exit;
        }
        list($w, $h) = eg_ws_dims($id);
        if ($mime !== 'image/svg+xml' && $w < 400) {
            wp_safe_redirect(add_query_arg('eg_error', rawurlencode('Das Bild ist nur ' . $w . ' Pixel breit und würde unscharf. Bitte SVG oder mindestens 1068 Pixel Breite.'), $back));
            exit;
        }
        update_option('eg_site_logo', esc_url_raw(wp_get_attachment_url($id)), false);
        update_option('eg_site_logo_w', $w, false);
        update_option('eg_site_logo_h', $h, false);
        if ($mime !== 'image/svg+xml' && !$email_id) {
            update_option('eg_site_logo_email', esc_url_raw(wp_get_attachment_url($id)), false);
        }
    }
    if ($email_id && in_array((string) get_post_mime_type($email_id), array('image/png', 'image/jpeg'), true)) {
        update_option('eg_site_logo_email', esc_url_raw(wp_get_attachment_url($email_id)), false);
    }
    eg_ws_changed();
    wp_safe_redirect(add_query_arg('eg_saved', '1', $back));
    exit;
});

/** Website pages pick up the change at once. */
function eg_ws_changed()
{
    if (function_exists('eg_render_touch_stamp')) {
        eg_render_touch_stamp();
    }
}

/* ---------- SVG uploads (team only, checked, served without scripts) ---------- */

add_filter('upload_mimes', static function ($mimes) {
    if (current_user_can(eg_ws_cap())) {
        $mimes['svg'] = 'image/svg+xml';
    }
    return $mimes;
});

add_filter('wp_check_filetype_and_ext', static function ($data, $file, $filename) {
    if (current_user_can(eg_ws_cap()) && preg_match('/\.svg$/i', (string) $filename)) {
        $data['ext'] = 'svg';
        $data['type'] = 'image/svg+xml';
        $data['proper_filename'] = $data['proper_filename'] ?? false;
    }
    return $data;
}, 10, 3);

add_filter('wp_handle_upload_prefilter', static function ($file) {
    if (!preg_match('/\.svg$/i', (string) ($file['name'] ?? ''))) {
        return $file;
    }
    $svg = is_readable($file['tmp_name']) ? (string) file_get_contents($file['tmp_name']) : '';
    if ($svg === '' || stripos($svg, '<svg') === false
        || preg_match('/<script|<foreignObject|<iframe|<embed|<object|javascript:|\son[a-z]+\s*=|<!ENTITY|<\?php/i', $svg)) {
        $file['error'] = 'Diese SVG-Datei enthält Skripte oder unerlaubte Teile und wurde aus Sicherheitsgründen abgelehnt. Bitte das Logo als „einfaches SVG“ exportieren.';
    }
    return $file;
});

/* SVGs in uploads are delivered with a Content-Security-Policy that blocks any script inside them. */
add_action('admin_init', static function () {
    $dir = wp_get_upload_dir();
    $ht = trailingslashit($dir['basedir']) . '.htaccess';
    $marker = '# EG svg safety';
    $cur = is_readable($ht) ? (string) file_get_contents($ht) : '';
    if (strpos($cur, $marker) !== false || !is_dir($dir['basedir'])) {
        return;
    }
    $rule = "\n" . $marker . "\n<FilesMatch \"\\.svg$\">\n  <IfModule mod_headers.c>\n    Header set Content-Security-Policy \"default-src 'none'; img-src data:; style-src 'unsafe-inline'\"\n  </IfModule>\n</FilesMatch>\n";
    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
    @file_put_contents($ht, $cur . $rule, LOCK_EX);
});

/* ---------- logo in the static pages (called by the render engine for every page) ---------- */

function eg_ws_apply_logo($html)
{
    if ((string) get_option('eg_site_logo', '') === '') {
        return $html;
    }
    $logo = eg_ws_logo();
    $out = preg_replace_callback('/<img class="brand__logo"[^>]*>/', static function () use ($logo) {
        return '<img class="brand__logo" src="' . esc_url($logo['url']) . '" alt="Eurasien Gesellschaft e. V." width="' . (int) $logo['w'] . '" height="' . (int) $logo['h'] . '" decoding="async">';
    }, $html);
    return is_string($out) ? $out : $html;
}

/* =====================================================================
 * Texts of the website (Website → Texte)
 * Two kinds of sections, marked in the page files with <!--wp:text:KEY-->…<!--/wp:text:KEY-->:
 * - "slots": the wording of each German/English text piece can be changed; the layout stays as it is.
 * - "rich":  full text with headings, paragraphs and lists (Impressum, Datenschutz, …).
 * Nothing is stored until someone saves a section; until then the page shows the text from the file.
 * ===================================================================== */

function eg_tx_blocks()
{
    return array(
        'home-lead'              => array('index.html', 'Startseite', 'Einleitung unter der großen Überschrift', 'slots'),
        'home-board'             => array('index.html', 'Startseite', 'Text im Bereich „Vorstand & Expertennetzwerk“', 'slots'),
        'home-member'            => array('index.html', 'Startseite', 'Text im Bereich „Werden Sie Mitglied“', 'slots'),
        'mission-lead'           => array('mission.html', 'Über uns (Mission)', 'Einleitung', 'slots'),
        'mission-pillars'        => array('mission.html', 'Über uns (Mission)', 'Drei Säulen (Überschrift und Text)', 'slots'),
        'mission-approach'       => array('mission.html', 'Über uns (Mission)', '„Unser Ansatz“ (Fließtext)', 'rich'),
        'vorstand-intro'         => array('vorstand.html', 'Vorstand & Experten', 'Leitsatz und Einleitung', 'slots'),
        'vorstand-network'       => array('vorstand.html', 'Vorstand & Experten', 'Expertennetzwerk (Text, Disziplinen, Hinweis)', 'slots'),
        'mitgliedschaft-info'    => array('mitgliedschaft.html', 'Mitgliedschaft', 'Beitrag und Hinweis neben dem Formular', 'slots'),
        'mitgliedschaft-faq'     => array('mitgliedschaft.html', 'Mitgliedschaft', 'Häufige Fragen', 'slots'),
        'mitgliedschaft-support' => array('mitgliedschaft.html', 'Mitgliedschaft', '„Die Arbeit der Gesellschaft unterstützen“ (inkl. Bankverbindung)', 'slots'),
        'impressum'              => array('impressum.html', 'Impressum', 'Impressum (Fließtext)', 'rich'),
        'datenschutz'            => array('datenschutz.html', 'Datenschutz', 'Datenschutzerklärung (Fließtext)', 'rich'),
    );
}

/** Same pattern as internal-docs/tools/texte_markers.py: span pairs and element pairs (de/en). */
function eg_tx_slot_regex()
{
    return '~<span class="de">((?:(?!<span).)*?)</span><span class="en" hidden>((?:(?!<span).)*?)</span>'
        . '|<(p|div|strong|li|ul) class="(?:[^"]*\s)?de">(.*?)</\3>\s*<\3 class="(?:[^"]*\s)?en" hidden>(.*?)</\3>~s';
}

/** The section as it is in the page file (the original). */
function eg_tx_original($key)
{
    $blocks = eg_tx_blocks();
    if (!isset($blocks[$key]) || !function_exists('eg_render_site_root')) {
        return null;
    }
    $file = eg_render_site_root() . '/' . $blocks[$key][0];
    $s = is_readable($file) ? (string) file_get_contents($file) : '';
    if (!preg_match('~<!--wp:text:' . preg_quote($key, '~') . '-->(.*?)<!--/wp:text:' . preg_quote($key, '~') . '-->~s', $s, $m)) {
        return null;
    }
    return $m[1];
}

/** Text pieces of a section: list of [de, en] (inner HTML). */
function eg_tx_slots($html)
{
    preg_match_all(eg_tx_slot_regex(), (string) $html, $all, PREG_SET_ORDER);
    $out = array();
    foreach ($all as $m) {
        $out[] = (isset($m[3]) && $m[3] !== '') ? array($m[4], $m[5]) : array($m[1], $m[2]);
    }
    return $out;
}

/** Put new wording into the section, piece by piece; the markup around it stays. */
function eg_tx_fill_slots($html, $values)
{
    $i = 0;
    $out = preg_replace_callback(eg_tx_slot_regex(), static function ($m) use (&$i, $values) {
        $v = $values[$i++] ?? null;
        if (!is_array($v) || (string) $v[0] === '') {
            return $m[0];
        }
        $de = (string) $v[0];
        $en = (string) $v[1] !== '' ? (string) $v[1] : $de;
        if (isset($m[3]) && $m[3] !== '') { // element pair: keep both opening tags as they were
            $tag = $m[3];
            preg_match('~^<' . $tag . '[^>]*>~', $m[0], $o1);
            $close = '</' . $tag . '>';
            $second = substr($m[0], strpos($m[0], $close) + strlen($close));
            preg_match('~^(\s*)(<' . $tag . '[^>]*>)~', $second, $o2);
            return $o1[0] . $de . $close . $o2[1] . $o2[2] . $en . $close;
        }
        return '<span class="de">' . $de . '</span><span class="en" hidden>' . $en . '</span>';
    }, (string) $html);
    return is_string($out) ? $out : $html;
}

/** German or English version of a mixed section (for the full-text editor). */
function eg_tx_split($html, $lang)
{
    $other = $lang === 'de' ? 'en' : 'de';
    libxml_use_internal_errors(true);
    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->loadHTML('<?xml encoding="UTF-8"?><div id="eg-tx-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $xp = new DOMXPath($doc);
    $has = static function ($cls) {
        return "contains(concat(' ', normalize-space(@class), ' '), ' " . $cls . " ')";
    };
    foreach (iterator_to_array($xp->query('//*[' . $has($other) . ']')) as $n) {
        if ($n->parentNode) {
            $n->parentNode->removeChild($n);
        }
    }
    foreach (iterator_to_array($xp->query('//*[' . $has($lang) . ']')) as $n) {
        $n->removeAttribute('hidden');
        if ($n->nodeName === 'span' && trim((string) $n->getAttribute('class')) === $lang) {
            while ($n->firstChild) {
                $n->parentNode->insertBefore($n->firstChild, $n);
            }
            $n->parentNode->removeChild($n);
            continue;
        }
        $cls = trim(preg_replace('/(^|\s)' . $lang . '(\s|$)/', ' ', (string) $n->getAttribute('class')));
        if ($cls === '') {
            $n->removeAttribute('class');
        } else {
            $n->setAttribute('class', $cls);
        }
    }
    $root = $doc->getElementById('eg-tx-root');
    $out = '';
    foreach ($root ? iterator_to_array($root->childNodes) : array() as $c) {
        $out .= $doc->saveHTML($c);
    }
    return trim($out);
}

/** Allowed markup in saved texts (wp_kses_post plus the icons and details used in some sections). */
function eg_tx_kses($html)
{
    $allowed = wp_kses_allowed_html('post');
    $allowed['svg'] = array('class' => true, 'viewbox' => true, 'aria-hidden' => true, 'width' => true, 'height' => true, 'fill' => true);
    $allowed['path'] = array('d' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true);
    $allowed['circle'] = array('cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true);
    $allowed['details'] = array('class' => true, 'open' => true);
    $allowed['summary'] = array('class' => true);
    return wp_kses((string) $html, $allowed);
}

/** Stored value of a section, or null. */
function eg_tx_saved($key)
{
    $v = get_option('eg_text_' . $key, null);
    return is_array($v) ? $v : null;
}

/** Fill all marked sections of a page with the saved texts. */
function eg_tx_apply($html)
{
    $blocks = eg_tx_blocks();
    $out = preg_replace_callback('~<!--wp:text:([a-z0-9-]+)-->(.*?)<!--/wp:text:\1-->~s', static function ($m) use ($blocks) {
        $key = $m[1];
        $saved = eg_tx_saved($key);
        if (!$saved || !isset($blocks[$key])) {
            return $m[0];
        }
        if ($blocks[$key][3] === 'slots') {
            if (count(eg_tx_slots($m[2])) !== (int) ($saved['n'] ?? -1)) {
                return $m[0]; // the page layout changed since saving: keep the original until saved again
            }
            return '<!--wp:text:' . $key . '-->' . eg_tx_fill_slots($m[2], $saved['slots'] ?? array()) . '<!--/wp:text:' . $key . '-->';
        }
        if (trim((string) ($saved['de'] ?? '')) === '') {
            return $m[0];
        }
        $en = trim((string) ($saved['en'] ?? '')) !== '' ? $saved['en'] : $saved['de'];
        return '<!--wp:text:' . $key . '--><div class="de">' . $saved['de'] . '</div><div class="en" hidden>' . $en . '</div><!--/wp:text:' . $key . '-->';
    }, (string) $html);
    return is_string($out) ? $out : $html;
}

/** Save a section (admin screen and REST). */
function eg_tx_save($key, $data)
{
    $blocks = eg_tx_blocks();
    $orig = eg_tx_original($key);
    if (!isset($blocks[$key]) || $orig === null) {
        return new WP_Error('eg_tx_unknown', 'Unbekannter Abschnitt', array('status' => 404));
    }
    if ($blocks[$key][3] === 'slots') {
        $n = count(eg_tx_slots($orig));
        $slots = array();
        for ($i = 0; $i < $n; $i++) {
            // keep spaces at the ends (e.g. " / Jahr"); a field with only spaces counts as empty
            $de = str_replace("\r", '', (string) ($data['slots'][$i][0] ?? ''));
            $en = str_replace("\r", '', (string) ($data['slots'][$i][1] ?? ''));
            $slots[] = array(trim($de) === '' ? '' : eg_tx_kses($de), trim($en) === '' ? '' : eg_tx_kses($en));
        }
        update_option('eg_text_' . $key, array('kind' => 'slots', 'n' => $n, 'slots' => $slots, 'by' => get_current_user_id(), 'at' => time()), false);
    } else {
        update_option('eg_text_' . $key, array('kind' => 'rich', 'de' => eg_tx_kses((string) ($data['de'] ?? '')), 'en' => eg_tx_kses((string) ($data['en'] ?? '')),
            'by' => get_current_user_id(), 'at' => time()), false);
    }
    eg_ws_changed();
    return true;
}

function eg_tx_reset($key)
{
    delete_option('eg_text_' . $key);
    eg_ws_changed();
}

/** Current values for the edit screen (saved, otherwise taken from the page). */
function eg_tx_current($key)
{
    $blocks = eg_tx_blocks();
    $orig = (string) eg_tx_original($key);
    $saved = eg_tx_saved($key);
    if ($blocks[$key][3] === 'slots') {
        $slots = eg_tx_slots($orig);
        if ($saved && (int) ($saved['n'] ?? -1) === count($slots)) {
            foreach ($slots as $i => $pair) {
                if (isset($saved['slots'][$i]) && (string) $saved['slots'][$i][0] !== '') {
                    $slots[$i] = $saved['slots'][$i];
                }
            }
        }
        return array('kind' => 'slots', 'slots' => $slots);
    }
    if ($saved && trim((string) ($saved['de'] ?? '')) !== '') {
        return array('kind' => 'rich', 'de' => $saved['de'], 'en' => $saved['en']);
    }
    return array('kind' => 'rich', 'de' => eg_tx_split($orig, 'de'), 'en' => eg_tx_split($orig, 'en'));
}

/* ---------- admin screens ---------- */

add_action('admin_menu', static function () {
    add_submenu_page('eg-website', 'Texte', 'Texte', eg_ws_cap(), 'eg-texte', 'eg_tx_page');
}, 20);

function eg_tx_page()
{
    if (!current_user_can(eg_ws_cap())) {
        wp_die('Keine Berechtigung.');
    }
    $blocks = eg_tx_blocks();
    $key = isset($_GET['block']) ? sanitize_key(wp_unslash($_GET['block'])) : '';
    echo '<div class="wrap">';
    if ($key === '' || !isset($blocks[$key])) {
        echo '<h1>Texte der Website</h1><p>Hier lassen sich die festen Texte der Website ändern. Veranstaltungen, Analysen, Videos und Personen haben eigene Menüpunkte.</p>';
        if (isset($_GET['eg_saved'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Gespeichert. Die Seite zeigt den neuen Text ab sofort.</p></div>';
        }
        $page = '';
        echo '<table class="widefat striped" style="max-width:900px"><tbody>';
        foreach ($blocks as $k => $b) {
            if ($b[1] !== $page) {
                $page = $b[1];
                $url = 'https://eurasiengesellschaft.de/' . ($b[0] === 'index.html' ? '' : $b[0]);
                echo '<tr><th colspan="3" style="background:#f0f0f1"><strong>' . esc_html($page) . '</strong> <a href="' . esc_url($url) . '" target="_blank" rel="noopener">ansehen ↗</a></th></tr>';
            }
            $saved = eg_tx_saved($k);
            $state = $saved ? 'geändert am ' . esc_html(wp_date('j.n.Y H:i', (int) ($saved['at'] ?? 0))) : 'Originaltext';
            echo '<tr><td>' . esc_html($b[2]) . '</td><td style="color:#646970">' . $state . '</td><td style="text-align:right"><a class="button" href="' . esc_url(admin_url('admin.php?page=eg-texte&block=' . $k)) . '">Bearbeiten</a></td></tr>';
        }
        echo '</tbody></table></div>';
        return;
    }
    $b = $blocks[$key];
    $cur = eg_tx_current($key);
    echo '<h1>' . esc_html($b[1] . ': ' . $b[2]) . '</h1><p><a href="' . esc_url(admin_url('admin.php?page=eg-texte')) . '">← Alle Texte</a></p>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('eg_tx_save', 'eg_tx_nonce');
    echo '<input type="hidden" name="action" value="eg_tx_save"><input type="hidden" name="block" value="' . esc_attr($key) . '">';
    if ($cur['kind'] === 'slots') {
        echo '<p>Jedes Feld ist ein Textstück auf der Seite. Die Gestaltung bleibt gleich; nur der Wortlaut ändert sich. Erlaubt sind einfache Auszeichnungen wie &lt;strong&gt;fett&lt;/strong&gt; und Links. Englisch leer = deutscher Text.</p>';
        echo '<table class="widefat" style="max-width:1100px"><thead><tr><th style="width:40px">#</th><th>Deutsch</th><th>English</th></tr></thead><tbody>';
        foreach ($cur['slots'] as $i => $pair) {
            $rows = max(2, min(8, (int) ceil(strlen($pair[0]) / 90)));
            echo '<tr><td>' . ($i + 1) . '</td><td><textarea class="widefat" rows="' . $rows . '" name="slot_de[' . $i . ']">' . esc_textarea($pair[0]) . '</textarea></td>'
                . '<td><textarea class="widefat" rows="' . $rows . '" name="slot_en[' . $i . ']">' . esc_textarea($pair[1]) . '</textarea></td></tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>Überschriften, Absätze, Listen, Fettdruck und Links sind möglich. Englisch leer = deutscher Text.</p>';
        echo '<h2>Deutsch</h2>';
        wp_editor($cur['de'], 'eg_tx_de', array('textarea_name' => 'rich_de', 'textarea_rows' => 18, 'media_buttons' => false));
        echo '<h2 style="margin-top:28px">English</h2>';
        wp_editor($cur['en'], 'eg_tx_en', array('textarea_name' => 'rich_en', 'textarea_rows' => 18, 'media_buttons' => false));
    }
    echo '<p style="margin-top:18px"><button type="submit" class="button button-primary">Speichern</button></p></form>';
    if (eg_tx_saved($key)) {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:10px">';
        wp_nonce_field('eg_tx_save', 'eg_tx_nonce');
        echo '<input type="hidden" name="action" value="eg_tx_save"><input type="hidden" name="block" value="' . esc_attr($key) . '"><input type="hidden" name="reset" value="1">'
            . '<button type="submit" class="button" onclick="return confirm(&quot;Den Originaltext wiederherstellen?&quot;)">Originaltext wiederherstellen</button></form>';
    }
    echo '</div>';
}

add_action('admin_post_eg_tx_save', static function () {
    if (!current_user_can(eg_ws_cap()) || !isset($_POST['eg_tx_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['eg_tx_nonce'])), 'eg_tx_save')) {
        wp_die('Keine Berechtigung.');
    }
    $key = isset($_POST['block']) ? sanitize_key(wp_unslash($_POST['block'])) : '';
    if (!empty($_POST['reset'])) {
        eg_tx_reset($key);
    } else {
        $slots = array();
        $de = isset($_POST['slot_de']) && is_array($_POST['slot_de']) ? wp_unslash($_POST['slot_de']) : array();
        $en = isset($_POST['slot_en']) && is_array($_POST['slot_en']) ? wp_unslash($_POST['slot_en']) : array();
        foreach ($de as $i => $v) {
            $slots[(int) $i] = array((string) $v, (string) ($en[$i] ?? ''));
        }
        eg_tx_save($key, array(
            'slots' => $slots,
            'de'    => isset($_POST['rich_de']) ? wp_unslash($_POST['rich_de']) : '',
            'en'    => isset($_POST['rich_en']) ? wp_unslash($_POST['rich_en']) : '',
        ));
    }
    wp_safe_redirect(admin_url('admin.php?page=eg-texte&eg_saved=1'));
    exit;
});

/* ---------- REST (team only): read, save and reset a section; used for checks ---------- */

add_action('rest_api_init', static function () {
    $can = static function () {
        return current_user_can(eg_ws_cap());
    };
    register_rest_route('eg/v1', '/text/(?P<key>[a-z0-9-]+)', array(
        array('methods' => 'GET', 'permission_callback' => $can, 'callback' => static function (WP_REST_Request $r) {
            $key = (string) $r['key'];
            if (!isset(eg_tx_blocks()[$key])) {
                return new WP_Error('eg_tx_unknown', 'unknown', array('status' => 404));
            }
            return array('key' => $key, 'saved' => (bool) eg_tx_saved($key), 'current' => eg_tx_current($key));
        }),
        array('methods' => 'POST', 'permission_callback' => $can, 'callback' => static function (WP_REST_Request $r) {
            $res = eg_tx_save((string) $r['key'], (array) $r->get_json_params());
            return is_wp_error($res) ? $res : array('saved' => true);
        }),
        array('methods' => 'DELETE', 'permission_callback' => $can, 'callback' => static function (WP_REST_Request $r) {
            eg_tx_reset((string) $r['key']);
            return array('reset' => true);
        }),
    ));
});
