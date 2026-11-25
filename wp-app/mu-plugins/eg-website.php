<?php
/**
 * Plugin Name: EG Website
 * Description: "Website" menu for the team (Redakteure and Administratoren): logo of the website (SVG or PNG). SVG uploads are checked and served with a script-blocking header.
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
