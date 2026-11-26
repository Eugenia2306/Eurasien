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
