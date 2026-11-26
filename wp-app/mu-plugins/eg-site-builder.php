<?php
/**
 * Plugin Name: EG Site Builder
 * Description: For administrators: the main menu of the website (Website → Menü). The menu is stored in WordPress and written into every page by the render engine (eg_render_global); without a saved menu the pages keep the menu from their files. The phone menu is built from it in the browser.
 * Author: d4rl1ngt0n
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Only administrators change the structure of the website. */
function eg_sb_cap()
{
    return 'manage_options';
}

/* =====================================================================
 * Menu
 * Stored as a flat list in order: level 0 = main item, level 1 = item in the dropdown of the main item above it.
 * Each item: level, de, en, href (site-relative like "mission.html" or https://…), sub_de, sub_en (small second line), attrs (kept data-* attributes).
 * ===================================================================== */

/** Parse the menu of a page file (for the first edit and as reference). */
function eg_sb_menu_from_file()
{
    $file = function_exists('eg_render_site_root') ? eg_render_site_root() . '/mission.html' : '';
    $s = ($file && is_readable($file)) ? (string) file_get_contents($file) : '';
    if (!preg_match('~<nav class="nav" aria-label="Hauptnavigation">(.*?)</nav>~s', $s, $m)) {
        return array();
    }
    $items = array();
    $link = static function ($a, $level) {
        preg_match('~href="([^"]*)"~', $a, $h);
        preg_match_all('~\s(data-[a-z-]+="[^"]*")~', $a, $d);
        preg_match('~<span class="de">(.*?)</span><span class="en" hidden>(.*?)</span>~s', $a, $t);
        $split = static function ($html) {
            $sub = '';
            if (preg_match('~<small>(.*?)</small>~s', $html, $sm)) {
                $sub = html_entity_decode($sm[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $html = str_replace($sm[0], '', $html);
            }
            return array(html_entity_decode(trim($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'), $sub);
        };
        list($de, $sub_de) = $split($t[1] ?? '');
        list($en, $sub_en) = $split($t[2] ?? '');
        return array('level' => $level, 'de' => $de, 'en' => $en, 'href' => html_entity_decode($h[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'sub_de' => $sub_de, 'sub_en' => $sub_en, 'attrs' => implode(' ', $d[1]));
    };
    preg_match_all('~<div class="nav-item">(.*?)</div>\s*</div>|<div class="nav-item">(\s*<a class="nav-link".*?</a>\s*)</div>~s', $m[1], $blocks, PREG_SET_ORDER);
    foreach ($blocks as $bm) {
        $blk = $bm[1] !== '' ? $bm[1] : $bm[2];
        if (!preg_match('~<a class="nav-link"[^>]*>.*?</a>~s', $blk, $top)) {
            continue;
        }
        $items[] = $link($top[0], 0);
        if (preg_match('~<div class="mega">(.*)$~s', $blk, $mega)) {
            preg_match_all('~<a [^>]*>.*?</a>~s', $mega[1], $subs);
            foreach ($subs[0] as $a) {
                $items[] = $link($a, 1);
            }
        }
    }
    return $items;
}

function eg_sb_menu_saved()
{
    $v = get_option('eg_site_menu', null);
    return is_array($v) && !empty($v['items']) ? $v['items'] : null;
}

/** Clean one item coming from the admin screen or REST. */
function eg_sb_clean_item($it)
{
    $href = trim((string) ($it['href'] ?? ''));
    if (!preg_match('~^(https?://|mailto:)~i', $href)) {
        $href = ltrim(preg_replace('~[^A-Za-z0-9/._#?=&%-]~', '', $href), '/');
    } else {
        $href = esc_url_raw($href);
    }
    $attrs = array();
    if (preg_match_all('~(data-[a-z-]+)="([^"<>]*)"~', (string) ($it['attrs'] ?? ''), $am, PREG_SET_ORDER)) {
        foreach ($am as $a) {
            $attrs[] = $a[1] . '="' . esc_attr($a[2]) . '"';
        }
    }
    return array(
        'level' => ((int) ($it['level'] ?? 0)) === 1 ? 1 : 0,
        'de' => sanitize_text_field((string) ($it['de'] ?? '')), 'en' => sanitize_text_field((string) ($it['en'] ?? '')),
        'href' => $href, 'sub_de' => sanitize_text_field((string) ($it['sub_de'] ?? '')), 'sub_en' => sanitize_text_field((string) ($it['sub_en'] ?? '')),
        'attrs' => implode(' ', $attrs),
    );
}

function eg_sb_menu_save($items)
{
    $clean = array();
    foreach ((array) $items as $it) {
        $c = eg_sb_clean_item((array) $it);
        if ($c['de'] === '' || $c['href'] === '') {
            continue;
        }
        if ($c['level'] === 1 && !$clean) {
            $c['level'] = 0; // a dropdown item needs a main item above it
        }
        $clean[] = $c;
    }
    if (!$clean) {
        return new WP_Error('eg_sb_empty', 'Das Menü braucht mindestens einen Eintrag.', array('status' => 400));
    }
    update_option('eg_site_menu', array('items' => $clean, 'by' => get_current_user_id(), 'at' => time()), false);
    if (function_exists('eg_render_touch_stamp')) {
        eg_render_touch_stamp();
    }
    return true;
}

/** The <nav> for a page; $prefix is '' for pages in the main folder, '../' for pages in sub-folders. */
function eg_sb_menu_html($items, $prefix)
{
    $url = static function ($href) use ($prefix) {
        if (preg_match('~^(https?://|mailto:|/|#)~i', $href)) {
            return $href;
        }
        return $prefix . $href;
    };
    $label = static function ($it) {
        $de = eg_r_esc($it['de']) . ($it['sub_de'] !== '' ? '<small>' . eg_r_esc($it['sub_de']) . '</small>' : '');
        $en_text = $it['en'] !== '' ? $it['en'] : $it['de'];
        $en_sub = $it['sub_en'] !== '' ? $it['sub_en'] : $it['sub_de'];
        $en = eg_r_esc($en_text) . ($en_sub !== '' ? '<small>' . eg_r_esc($en_sub) . '</small>' : '');
        return '<span class="de">' . $de . '</span><span class="en" hidden>' . $en . '</span>';
    };
    $attrs = static function ($it) {
        return $it['attrs'] !== '' ? ' ' . $it['attrs'] : '';
    };
    $out = '<nav class="nav" aria-label="Hauptnavigation">';
    $n = count($items);
    for ($i = 0; $i < $n; $i++) {
        $it = $items[$i];
        if ($it['level'] !== 0) {
            continue;
        }
        $out .= "\n      <div class=\"nav-item\">\n        <a class=\"nav-link\" href=\"" . eg_r_esc($url($it['href'])) . '"' . $attrs($it) . '>' . $label($it) . '</a>';
        $subs = '';
        for ($j = $i + 1; $j < $n && $items[$j]['level'] === 1; $j++) {
            $subs .= "\n          <a href=\"" . eg_r_esc($url($items[$j]['href'])) . '"' . $attrs($items[$j]) . '>' . $label($items[$j]) . '</a>';
        }
        if ($subs !== '') {
            $out .= "\n        <div class=\"mega\">" . $subs . "\n        </div>";
        }
        $out .= "\n      </div>";
    }
    return $out . "\n    </nav>";
}

/** Replace the menu of a page (called for every page by eg_render_global). */
function eg_sb_apply_menu($html)
{
    $items = eg_sb_menu_saved();
    if (!$items) {
        return $html;
    }
    $out = preg_replace_callback('~<nav class="nav" aria-label="Hauptnavigation">.*?</nav>~s', static function ($m) use ($items) {
        // the page's own first link tells whether it sits in a sub-folder ("../mission.html")
        $prefix = preg_match('~<a class="nav-link" href="(\.\./)~', $m[0]) ? '../' : '';
        return eg_sb_menu_html($items, $prefix);
    }, $html, 1);
    return is_string($out) ? $out : $html;
}

/** Pages a menu item can point to: the website's pages plus pages made in WordPress (step 2). */
function eg_sb_link_choices()
{
    $choices = array(
        'index.html' => 'Startseite', 'mission.html' => 'Über uns (Mission)', 'vorstand.html' => 'Vorstand & Experten', 'partner.html' => 'Kooperationen',
        'gesellschaftsnachrichten.html' => 'Gesellschaftsnachrichten', 'themen.html' => 'Themen', 'themen/geopolitik.html' => 'Geopolitik',
        'themen/energie.html' => 'Energie', 'themen/wirtschaft.html' => 'Wirtschaft', 'themen/wissenschaft.html' => 'Wissenschaft', 'kultur.html' => 'Kultur',
        'laender-gesellschaften.html' => 'Länder & Gesellschaften', 'analysen.html' => 'Analysen', 'regionen.html' => 'Regionen',
        'veranstaltungen.html' => 'Veranstaltungen', 'mediathek.html' => 'Mediathek', 'mitgliedschaft.html' => 'Mitgliedschaft',
        'mitgliedschaft-vorteile.html' => 'Mitgliedschaft: Vorteile', 'impressum.html' => 'Impressum', 'datenschutz.html' => 'Datenschutz',
    );
    if (function_exists('eg_sb_pages')) {
        foreach (eg_sb_pages() as $p) {
            $choices[$p['slug'] . '.html'] = 'Neue Seite: ' . $p['title'];
        }
    }
    return $choices;
}

add_action('admin_menu', static function () {
    add_submenu_page('eg-website', 'Menü', 'Menü', eg_sb_cap(), 'eg-menu', 'eg_sb_menu_page');
}, 30);

function eg_sb_menu_page()
{
    if (!current_user_can(eg_sb_cap())) {
        wp_die('Keine Berechtigung.');
    }
    $items = eg_sb_menu_saved();
    $from_file = !$items;
    if (!$items) {
        $items = eg_sb_menu_from_file();
    }
    $choices = eg_sb_link_choices();
    echo '<div class="wrap"><h1>Menü der Website</h1>';
    if (isset($_GET['eg_saved'])) {
        echo '<div class="notice notice-success is-dismissible"><p>Gespeichert. Das Menü ist auf allen Seiten sofort geändert (auch im Handy-Menü).</p></div>';
    }
    if (isset($_GET['eg_error'])) {
        echo '<div class="notice notice-error"><p>' . esc_html(sanitize_text_field(wp_unslash($_GET['eg_error']))) . '</p></div>';
    }
    echo '<p>Jede Zeile ist ein Menüpunkt. <strong>Hauptpunkt</strong> = oben im Menü; <strong>Unterpunkt</strong> = erscheint im aufklappenden Menü des Hauptpunkts darüber. Mit ↑ ↓ ändern Sie die Reihenfolge. Englisch leer = deutscher Text. '
        . ($from_file ? '<em>Noch nicht gespeichert: angezeigt wird das aktuelle Menü der Website.</em>' : '') . '</p>';
    echo '<datalist id="eg-sb-links">';
    foreach ($choices as $href => $label) {
        echo '<option value="' . esc_attr($href) . '">' . esc_html($label) . '</option>';
    }
    echo '</datalist>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" id="eg-sb-menu-form">';
    wp_nonce_field('eg_sb_menu', 'eg_sb_nonce');
    echo '<input type="hidden" name="action" value="eg_sb_menu">';
    echo '<table class="widefat" style="max-width:1300px"><thead><tr><th style="width:120px">Ebene</th><th>Deutsch</th><th>English</th><th>Link</th><th>Untertitel (klein, optional) DE / EN</th><th style="width:110px"></th></tr></thead><tbody id="eg-sb-rows">';
    $row = static function ($i, $it) {
        $lvl = (int) ($it['level'] ?? 0);
        return '<tr class="eg-sb-row"' . ($lvl ? ' style="background:#f6f7f7"' : '') . '>'
            . '<td><select name="m[' . $i . '][level]"><option value="0"' . selected($lvl, 0, false) . '>Hauptpunkt</option><option value="1"' . selected($lvl, 1, false) . '>↳ Unterpunkt</option></select></td>'
            . '<td><input type="text" class="widefat" name="m[' . $i . '][de]" value="' . esc_attr($it['de'] ?? '') . '"></td>'
            . '<td><input type="text" class="widefat" name="m[' . $i . '][en]" value="' . esc_attr($it['en'] ?? '') . '"></td>'
            . '<td><input type="text" class="widefat" list="eg-sb-links" name="m[' . $i . '][href]" value="' . esc_attr($it['href'] ?? '') . '" placeholder="Seite wählen oder https://…"></td>'
            . '<td><input type="text" style="width:48%" name="m[' . $i . '][sub_de]" value="' . esc_attr($it['sub_de'] ?? '') . '"> <input type="text" style="width:48%" name="m[' . $i . '][sub_en]" value="' . esc_attr($it['sub_en'] ?? '') . '">'
            . '<input type="hidden" name="m[' . $i . '][attrs]" value="' . esc_attr($it['attrs'] ?? '') . '"></td>'
            . '<td><button type="button" class="button eg-up" title="nach oben">↑</button> <button type="button" class="button eg-down" title="nach unten">↓</button> <button type="button" class="button-link eg-del" title="entfernen" style="color:#b32d2e">✕</button></td></tr>';
    };
    foreach (array_values($items) as $i => $it) {
        echo $row($i, $it);
    }
    echo '</tbody></table>';
    echo '<p><button type="button" class="button" id="eg-sb-add">+ Menüpunkt hinzufügen</button></p>';
    echo '<p><button type="submit" class="button button-primary">Speichern</button></p></form>';
    if (!$from_file) {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:10px">';
        wp_nonce_field('eg_sb_menu', 'eg_sb_nonce');
        echo '<input type="hidden" name="action" value="eg_sb_menu"><input type="hidden" name="reset" value="1">'
            . '<button type="submit" class="button" onclick="return confirm(&quot;Wieder das ursprüngliche Menü verwenden?&quot;)">Ursprüngliches Menü wiederherstellen</button></form>';
    }
    echo '</div>';
    $tpl = $row('__I__', array());
    echo '<script>(function(){var tb=document.getElementById("eg-sb-rows"),tpl=' . wp_json_encode($tpl) . ',n=tb.rows.length+1000;'
        . 'document.getElementById("eg-sb-add").addEventListener("click",function(){tb.insertAdjacentHTML("beforeend",tpl.replace(/__I__/g,String(n++)));});'
        . 'tb.addEventListener("click",function(e){var tr=e.target.closest("tr");if(!tr)return;'
        . 'if(e.target.classList.contains("eg-up")&&tr.previousElementSibling){tb.insertBefore(tr,tr.previousElementSibling);}'
        . 'if(e.target.classList.contains("eg-down")&&tr.nextElementSibling){tb.insertBefore(tr.nextElementSibling,tr);}'
        . 'if(e.target.classList.contains("eg-del")&&confirm("Diesen Menüpunkt entfernen?")){tr.remove();}});'
        . 'tb.addEventListener("change",function(e){if(e.target.tagName==="SELECT"){e.target.closest("tr").style.background=e.target.value==="1"?"#f6f7f7":"";}});'
        . '})();</script>';
}

add_action('admin_post_eg_sb_menu', static function () {
    if (!current_user_can(eg_sb_cap()) || !isset($_POST['eg_sb_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['eg_sb_nonce'])), 'eg_sb_menu')) {
        wp_die('Keine Berechtigung.');
    }
    $back = admin_url('admin.php?page=eg-menu');
    if (!empty($_POST['reset'])) {
        delete_option('eg_site_menu');
        if (function_exists('eg_render_touch_stamp')) {
            eg_render_touch_stamp();
        }
        wp_safe_redirect(add_query_arg('eg_saved', '1', $back));
        exit;
    }
    // rows arrive in screen order (the browser keeps DOM order for the inputs)
    $rows = isset($_POST['m']) && is_array($_POST['m']) ? array_values(wp_unslash($_POST['m'])) : array();
    $res = eg_sb_menu_save($rows);
    wp_safe_redirect(is_wp_error($res) ? add_query_arg('eg_error', rawurlencode($res->get_error_message()), $back) : add_query_arg('eg_saved', '1', $back));
    exit;
});

/* ---------- REST (administrators): read / save / reset the menu; used for checks ---------- */

add_action('rest_api_init', static function () {
    $can = static function () {
        return current_user_can(eg_sb_cap());
    };
    register_rest_route('eg/v1', '/menu', array(
        array('methods' => 'GET', 'permission_callback' => $can, 'callback' => static function () {
            $saved = eg_sb_menu_saved();
            return array('saved' => (bool) $saved, 'items' => $saved ? $saved : eg_sb_menu_from_file());
        }),
        array('methods' => 'POST', 'permission_callback' => $can, 'callback' => static function (WP_REST_Request $r) {
            $res = eg_sb_menu_save((array) ($r->get_json_params()['items'] ?? array()));
            return is_wp_error($res) ? $res : array('saved' => true);
        }),
        array('methods' => 'DELETE', 'permission_callback' => $can, 'callback' => static function () {
            delete_option('eg_site_menu');
            if (function_exists('eg_render_touch_stamp')) {
                eg_render_touch_stamp();
            }
            return array('reset' => true);
        }),
    ));
});

/* =====================================================================
 * Pages (Website → Seiten), administrators only.
 * A page = a German entry (address, description, intro) and optionally an English entry ("English version of …").
 * Both are written with the block editor. Rendered into static-site/_seite.html at /<address>.html.
 * ===================================================================== */

function eg_sb_page_caps()
{
    $c = eg_sb_cap();
    return array(
        'edit_post' => $c, 'read_post' => 'read', 'delete_post' => $c, 'edit_posts' => $c, 'edit_others_posts' => $c,
        'publish_posts' => $c, 'read_private_posts' => $c, 'delete_posts' => $c, 'delete_others_posts' => $c,
        'delete_published_posts' => $c, 'edit_published_posts' => $c, 'create_posts' => $c,
    );
}

add_action('init', static function () {
    register_post_type('eg_sitepage', array(
        'labels' => array(
            'name' => 'Seiten', 'singular_name' => 'Seite', 'add_new' => 'Neue Seite', 'add_new_item' => 'Neue Seite',
            'edit_item' => 'Seite bearbeiten', 'all_items' => 'Seiten', 'menu_name' => 'Seiten',
        ),
        'public' => false, 'show_ui' => true, 'show_in_menu' => 'eg-website', 'show_in_rest' => true,
        'supports' => array('title', 'editor', 'revisions', 'custom-fields'),
        'capabilities' => eg_sb_page_caps(), 'map_meta_cap' => false,
    ));
    foreach (array('eg_page_lang', 'eg_page_slug', 'eg_page_desc', 'eg_page_lead', 'eg_page_of') as $k) {
        register_post_meta('eg_sitepage', $k, array(
            'type' => 'string', 'single' => true, 'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => static function () {
                return current_user_can(eg_sb_cap());
            },
        ));
    }
}, 30);

/* blocks the pages may use (all styled in assets/css/blocks.css) */
add_filter('allowed_block_types_all', static function ($allowed, $ctx) {
    if (isset($ctx->post) && $ctx->post && $ctx->post->post_type === 'eg_sitepage') {
        return array('core/paragraph', 'core/heading', 'core/list', 'core/list-item', 'core/quote', 'core/image', 'core/gallery',
            'core/columns', 'core/column', 'core/buttons', 'core/button', 'core/embed', 'core/separator', 'core/spacer', 'core/group');
    }
    return $allowed;
}, 10, 2);

/** Addresses that belong to the website already (files in the site folder, WordPress, technical names). */
function eg_sb_reserved_slug($slug)
{
    if ($slug === '' || in_array($slug, array('app', 'assets', 'personen', 'themen', 'mitglieder', 'index', 'eg-render', 'sitemap', 'llms', '404'), true)) {
        return true;
    }
    $root = function_exists('eg_render_site_root') ? eg_render_site_root() : '';
    return $root !== '' && (file_exists($root . '/' . $slug . '.html') || is_dir($root . '/' . $slug));
}

/** Published German pages: [id, slug, title]. */
function eg_sb_pages()
{
    $out = array();
    foreach (get_posts(array('post_type' => 'eg_sitepage', 'post_status' => 'publish', 'numberposts' => 200, 'orderby' => 'title', 'order' => 'ASC')) as $p) {
        if ((string) get_post_meta($p->ID, 'eg_page_lang', true) === 'en') {
            continue;
        }
        $slug = (string) get_post_meta($p->ID, 'eg_page_slug', true);
        if ($slug !== '') {
            $out[] = array('id' => $p->ID, 'slug' => $slug, 'title' => (string) $p->post_title);
        }
    }
    return $out;
}

add_action('add_meta_boxes_eg_sitepage', static function () {
    remove_meta_box('postcustom', 'eg_sitepage', 'normal');
    add_meta_box('eg_sb_page', 'Seite auf der Website', 'eg_sb_page_box', 'eg_sitepage', 'side', 'high');
});

function eg_sb_page_box($post)
{
    wp_nonce_field('eg_sb_page', 'eg_sb_page_nonce');
    $m = static function ($k) use ($post) {
        return (string) get_post_meta($post->ID, $k, true);
    };
    $lang = $m('eg_page_lang') === 'en' ? 'en' : 'de';
    $slug = $m('eg_page_slug');
    echo '<p><strong>Sprache dieser Fassung</strong><br><label><input type="radio" name="eg_page_lang" value="de"' . checked($lang, 'de', false) . '> Deutsch (Hauptseite)</label><br>'
        . '<label><input type="radio" name="eg_page_lang" value="en"' . checked($lang, 'en', false) . '> English (Übersetzung)</label></p>';
    echo '<div class="eg-de"' . ($lang === 'en' ? ' style="display:none"' : '') . '>';
    echo '<p><label for="eg_page_slug"><strong>Adresse</strong></label><br><input type="text" class="widefat" id="eg_page_slug" name="eg_page_slug" value="' . esc_attr($slug) . '" placeholder="z. B. projekte"><br>'
        . '<span class="description">eurasiengesellschaft.de/<strong>' . esc_html($slug !== '' ? $slug : 'projekte') . '</strong>.html · Kleinbuchstaben, Ziffern, Bindestriche</span>'
        . ($slug !== '' && $post->post_status === 'publish' ? '<br><a href="' . esc_url('https://eurasiengesellschaft.de/' . $slug . '.html') . '" target="_blank" rel="noopener">Seite ansehen ↗</a>' : '') . '</p>';
    echo '<p><label for="eg_page_lead"><strong>Einleitung unter der Überschrift</strong> (optional)</label><textarea class="widefat" rows="3" id="eg_page_lead" name="eg_page_lead">' . esc_textarea($m('eg_page_lead')) . '</textarea></p>';
    echo '<p><label for="eg_page_desc"><strong>Beschreibung für Suchmaschinen</strong></label><textarea class="widefat" rows="3" id="eg_page_desc" name="eg_page_desc" placeholder="Ein Satz, höchstens etwa 160 Zeichen">' . esc_textarea($m('eg_page_desc')) . '</textarea></p>';
    echo '</div><div class="eg-en"' . ($lang === 'de' ? ' style="display:none"' : '') . '>';
    $opts = '<option value="">– deutsche Seite wählen –</option>';
    foreach (eg_sb_pages() as $p) {
        if ((int) $p['id'] !== (int) $post->ID) {
            $opts .= '<option value="' . (int) $p['id'] . '"' . selected((int) $m('eg_page_of'), (int) $p['id'], false) . '>' . esc_html($p['title']) . '</option>';
        }
    }
    echo '<p><label for="eg_page_of"><strong>English version of</strong></label><br><select class="widefat" id="eg_page_of" name="eg_page_of">' . $opts . '</select></p>';
    echo '<p><label for="eg_page_lead_en"><strong>Intro below the heading</strong> (optional)</label><textarea class="widefat" rows="3" id="eg_page_lead_en" name="eg_page_lead_en">' . esc_textarea($lang === 'en' ? $m('eg_page_lead') : '') . '</textarea></p>';
    echo '<p class="description">Titel und Inhalt dieser Fassung erscheinen auf der englischen Seite. Ohne englische Fassung zeigt die englische Seite den deutschen Text.</p></div>';
    echo "<script>document.addEventListener('change',function(e){if(e.target.name!=='eg_page_lang')return;var en=e.target.value==='en';document.querySelector('#eg_sb_page .eg-de').style.display=en?'none':'';document.querySelector('#eg_sb_page .eg-en').style.display=en?'':'none';});</script>";
}

add_action('save_post_eg_sitepage', static function ($post_id) {
    if (!isset($_POST['eg_sb_page_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['eg_sb_page_nonce'])), 'eg_sb_page')) {
        return;
    }
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can(eg_sb_cap())) {
        return;
    }
    $lang = (isset($_POST['eg_page_lang']) && wp_unslash($_POST['eg_page_lang']) === 'en') ? 'en' : 'de';
    update_post_meta($post_id, 'eg_page_lang', $lang);
    if ($lang === 'en') {
        update_post_meta($post_id, 'eg_page_of', isset($_POST['eg_page_of']) ? (string) absint($_POST['eg_page_of']) : '');
        update_post_meta($post_id, 'eg_page_lead', isset($_POST['eg_page_lead_en']) ? sanitize_textarea_field(wp_unslash($_POST['eg_page_lead_en'])) : '');
        update_post_meta($post_id, 'eg_page_slug', '');
    } else {
        $slug = isset($_POST['eg_page_slug']) ? sanitize_title(wp_unslash($_POST['eg_page_slug'])) : '';
        if ($slug === '') {
            $slug = sanitize_title((string) get_post_field('post_title', $post_id));
        }
        $base = $slug;
        $n = 2;
        while (eg_sb_reserved_slug($slug) || eg_sb_slug_taken($slug, $post_id)) {
            $slug = $base . '-' . $n++;
        }
        update_post_meta($post_id, 'eg_page_slug', $slug);
        update_post_meta($post_id, 'eg_page_lead', isset($_POST['eg_page_lead']) ? sanitize_textarea_field(wp_unslash($_POST['eg_page_lead'])) : '');
        update_post_meta($post_id, 'eg_page_desc', isset($_POST['eg_page_desc']) ? sanitize_textarea_field(wp_unslash($_POST['eg_page_desc'])) : '');
    }
}, 30);

function eg_sb_slug_taken($slug, $except)
{
    $q = get_posts(array('post_type' => 'eg_sitepage', 'post_status' => array('publish', 'draft', 'pending', 'private', 'future'), 'numberposts' => 1,
        'meta_key' => 'eg_page_slug', 'meta_value' => $slug, 'exclude' => array((int) $except), 'fields' => 'ids'));
    return !empty($q);
}

/** Rendered content of one entry (blocks → HTML; YouTube without tracking cookies). */
function eg_sb_page_content($post)
{
    $html = apply_filters('the_content', (string) $post->post_content);
    return str_replace(array('https://www.youtube.com/embed/', 'https://youtube.com/embed/'), 'https://www.youtube-nocookie.com/embed/', (string) $html);
}

/** The German page with this address and its English version (if any). */
function eg_sb_page_find($slug)
{
    $ids = get_posts(array('post_type' => 'eg_sitepage', 'post_status' => 'publish', 'numberposts' => 1,
        'meta_key' => 'eg_page_slug', 'meta_value' => $slug, 'fields' => 'ids'));
    if (!$ids) {
        return null;
    }
    $de = get_post($ids[0]);
    $en_ids = get_posts(array('post_type' => 'eg_sitepage', 'post_status' => 'publish', 'numberposts' => 1,
        'meta_key' => 'eg_page_of', 'meta_value' => (string) $de->ID, 'fields' => 'ids'));
    return array('de' => $de, 'en' => $en_ids ? get_post($en_ids[0]) : null);
}

function eg_sb_page_render($page, $html, $slug)
{
    $found = eg_sb_page_find($slug);
    if (!$found) {
        throw new EgRenderNotFound('no such page');
    }
    $de = $found['de'];
    $en = $found['en'];
    $site = 'https://eurasiengesellschaft.de';
    $url = $site . '/' . $slug . '.html';
    $title = (string) $de->post_title;
    $title_en = $en ? (string) $en->post_title : $title;
    $lead = (string) get_post_meta($de->ID, 'eg_page_lead', true);
    $lead_en = $en ? (string) get_post_meta($en->ID, 'eg_page_lead', true) : '';
    $desc = (string) get_post_meta($de->ID, 'eg_page_desc', true);
    if ($desc === '') {
        $desc = function_exists('eg_r_plain') ? eg_r_plain(eg_sb_page_content($de), 160) : $title;
    }
    $body_de = eg_sb_page_content($de);
    $body_en = $en ? eg_sb_page_content($en) : $body_de;
    $t = eg_r_esc($title . ' | Eurasien Gesellschaft');
    $d = eg_r_esc($desc);
    $img = $site . '/assets/images/og-share.png?v=3';
    $head = '<title>' . $t . '</title>' . "\n\n\n" . '<meta name="description" content="' . $d . '">' . "\n" . '<meta name="robots" content="index,follow">' . "\n"
        . '<link rel="canonical" href="' . eg_r_esc($url) . '">' . "\n" . '<!-- Open Graph -->' . "\n" . '<meta property="og:type" content="website">' . "\n"
        . '<meta property="og:site_name" content="Eurasien Gesellschaft e. V.">' . "\n" . '<meta property="og:title" content="' . $t . '">' . "\n"
        . '<meta property="og:description" content="' . $d . '">' . "\n" . '<meta property="og:url" content="' . eg_r_esc($url) . '">' . "\n"
        . '<meta property="og:locale" content="de_DE">' . "\n" . '<meta property="og:locale:alternate" content="en_US">' . "\n"
        . '<meta property="og:image" content="' . $img . '">' . "\n" . '<meta property="og:image:alt" content="Eurasien Gesellschaft e. V.">' . "\n"
        . '<!-- Twitter Card -->' . "\n" . '<meta name="twitter:card" content="summary">' . "\n" . '<meta name="twitter:title" content="' . $t . '">' . "\n"
        . '<meta name="twitter:description" content="' . $d . '">' . "\n" . '<meta name="twitter:image" content="' . $img . '">' . "\n";
    $html = eg_r_replace($html, '/<!--wp:page-meta-->.*?<!--\/wp:page-meta-->/s', '<!--wp:page-meta-->' . $head . '<!--/wp:page-meta-->');
    $html = preg_replace_callback('/(<!--wp:page-ld--><script type="application\/ld\+json">)(.*?)(<\/script><!--\/wp:page-ld-->)/s', static function ($m) use ($site, $url, $title, $desc) {
        $data = json_decode($m[2], true);
        $graph = array();
        foreach ((array) ($data['@graph'] ?? array()) as $n) {
            if (!in_array($n['@type'] ?? '', array('WebPage', 'BreadcrumbList'), true)) {
                $graph[] = $n;
            }
        }
        $graph[] = array('@type' => 'WebPage', '@id' => $url . '#webpage', 'url' => $url, 'name' => $title . ' | Eurasien Gesellschaft', 'description' => $desc,
            'isPartOf' => array('@id' => $site . '/#website'), 'about' => array('@id' => $site . '/#organization'), 'inLanguage' => 'de');
        $graph[] = array('@type' => 'BreadcrumbList', 'itemListElement' => array(
            array('@type' => 'ListItem', 'position' => 1, 'name' => 'Start', 'item' => $site . '/'),
            array('@type' => 'ListItem', 'position' => 2, 'name' => $title, 'item' => $url),
        ));
        $data['@graph'] = $graph;
        return $m[1] . "\n" . str_replace('</', '<\/', (string) wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) . "\n" . $m[3];
    }, $html);
    $bi = static function ($a, $b) {
        return '<span class="de">' . eg_r_esc($a) . '</span><span class="en" hidden>' . eg_r_esc($b !== '' ? $b : $a) . '</span>';
    };
    $content = "\n  " . '<div class="phead"><div class="wrap"><p class="crumb"><a href="/">Start</a> › ' . $bi($title, $title_en) . '</p>'
        . '<h1>' . $bi($title, $title_en) . '</h1>'
        . ($lead !== '' ? '<p>' . $bi($lead, $lead_en) . '</p>' : '') . '</div></div>'
        . "\n  " . '<section class="sec"><div class="wrap">'
        . '<div class="prose eg-blocks de">' . $body_de . '</div><div class="prose eg-blocks en" hidden>' . $body_en . '</div>'
        . "</div></section>\n";
    $html = eg_r_replace($html, '/<!--wp:page-content-->.*?<!--\/wp:page-content-->/s', '<!--wp:page-content-->' . $content . '<!--/wp:page-content-->');
    return str_replace('<section class="page" id="p-seite">', '<section class="page" id="p-seite-' . eg_r_esc($slug) . '">', $html);
}

/** Page addresses in sitemap.xml. */
function eg_sb_apply_sitemap($html)
{
    $urls = '';
    foreach (eg_sb_pages() as $p) {
        $urls .= "\n  <url>\n    <loc>https://eurasiengesellschaft.de/" . eg_r_esc($p['slug']) . ".html</loc>\n    <lastmod>"
            . esc_html(get_post_modified_time('Y-m-d', true, $p['id'])) . "</lastmod>\n    <changefreq>monthly</changefreq>\n    <priority>0.6</priority>\n  </url>";
    }
    return eg_r_replace($html, '/<!--wp:sitepages-->.*?<!--\/wp:sitepages-->/s', '<!--wp:sitepages-->' . $urls . ($urls !== '' ? "\n  " : '') . '<!--/wp:sitepages-->');
}

/* the list of pages: show address and language */
add_filter('manage_eg_sitepage_posts_columns', static function ($cols) {
    $cols['eg_addr'] = 'Adresse / Sprache';
    return $cols;
});
add_action('manage_eg_sitepage_posts_custom_column', static function ($col, $id) {
    if ($col !== 'eg_addr') {
        return;
    }
    if ((string) get_post_meta($id, 'eg_page_lang', true) === 'en') {
        $of = (int) get_post_meta($id, 'eg_page_of', true);
        echo 'English' . ($of ? ' → ' . esc_html(get_the_title($of)) : ' (noch keiner deutschen Seite zugeordnet)');
        return;
    }
    $slug = (string) get_post_meta($id, 'eg_page_slug', true);
    echo $slug !== '' ? '<a href="' . esc_url('https://eurasiengesellschaft.de/' . $slug . '.html') . '" target="_blank" rel="noopener">/' . esc_html($slug) . '.html</a>' : '–';
}, 10, 2);
