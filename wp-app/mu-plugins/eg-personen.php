<?php
/**
 * Plugin Name: EG Personen
 * Description: Board members and profile pages maintained in WordPress (eg_person) with their publications (eg_publication): German edit screens, server-side rendering of the board cards (vorstand.html), the profile pages (personen/<slug>.html, also for new people), the board in llms.txt, the profile URLs in sitemap.xml and the member list in the homepage data. Uses helpers from eg-render-engine.php and eg-analysen.php.
 * Author: d4rl1ngt0n
 */

if (!defined('ABSPATH')) {
    exit;
}

function eg_ps_site()
{
    return 'https://eurasiengesellschaft.de';
}

function eg_ps_person_keys()
{
    return array(
        'eg_role_de', 'eg_role_en', 'eg_short_de', 'eg_short_en', 'eg_bio_de', 'eg_bio_en', 'eg_photo', 'eg_photo_card',
        'eg_desc', 'eg_same_as', 'eg_on_board', 'eg_pubs_eyebrow_de', 'eg_pubs_eyebrow_en', 'eg_pubs_title_de', 'eg_pubs_title_en',
        'eg_pubs_note_de', 'eg_pubs_note_en', 'eg_further_label_de', 'eg_further_label_en', 'eg_further_links', 'eg_llms',
    );
}

function eg_ps_pub_keys()
{
    return array('eg_person_id', 'eg_year', 'eg_type_de', 'eg_type_en', 'eg_src', 'eg_abs_de', 'eg_abs_en', 'eg_links');
}

/** Link fields keep %-encoded characters (sanitize_text_field would delete them and break the URL). */
function eg_ps_is_link_field($key)
{
    return in_array($key, array('eg_links', 'eg_further_links', 'eg_same_as'), true);
}

/** "Label | URL" or plain URL lines: label as text, URL with esc_url_raw. */
function eg_ps_sanitize_links($text)
{
    $out = array();
    foreach (preg_split('/\r\n|\r|\n/', (string) $text) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $parts = array_map('trim', explode('|', $line, 2));
        if (count($parts) === 2) {
            $out[] = sanitize_text_field($parts[0]) . ' | ' . esc_url_raw($parts[1]);
        } else {
            $out[] = esc_url_raw($parts[0]);
        }
    }
    return implode("\n", $out);
}

function eg_ps_clean($key, $raw)
{
    if (eg_ps_is_link_field($key)) {
        return eg_ps_sanitize_links($raw);
    }
    return eg_ps_multiline($key) ? sanitize_textarea_field($raw) : sanitize_text_field($raw);
}

function eg_ps_multiline($key)
{
    return in_array($key, array('eg_bio_de', 'eg_bio_en', 'eg_same_as', 'eg_further_links', 'eg_abs_de', 'eg_abs_en', 'eg_links', 'eg_short_de', 'eg_short_en', 'eg_desc'), true);
}

/* ---------- types and fields ---------- */

add_action('init', static function () {
    register_post_type('eg_publication', array(
        'labels' => array(
            'name' => 'Publikationen', 'singular_name' => 'Publikation', 'add_new' => 'Neue Publikation',
            'add_new_item' => 'Neue Publikation', 'edit_item' => 'Publikation bearbeiten', 'all_items' => 'Publikationen',
        ),
        'public' => false, 'show_ui' => true, 'show_in_menu' => 'edit.php?post_type=eg_person',
        'show_in_rest' => true, 'supports' => array('title', 'page-attributes', 'custom-fields'),
    ));
    $auth = static function () {
        return current_user_can('edit_posts');
    };
    foreach (array('eg_person' => eg_ps_person_keys(), 'eg_publication' => eg_ps_pub_keys()) as $type => $keys) {
        foreach ($keys as $k) {
            register_post_meta($type, $k, array('type' => 'string', 'single' => true, 'show_in_rest' => true,
                'sanitize_callback' => static function ($v) use ($k) {
                    return eg_ps_clean($k, (string) $v);
                }, 'auth_callback' => $auth));
        }
    }
    if (post_type_exists('eg_person')) {
        add_post_type_support('eg_person', 'page-attributes');
        add_post_type_support('eg_person', 'custom-fields');
        remove_post_type_support('eg_person', 'editor');
        remove_post_type_support('eg_person', 'thumbnail');
    }
}, 30);

add_filter('use_block_editor_for_post_type', static function ($use, $type) {
    return in_array($type, array('eg_person', 'eg_publication'), true) ? false : $use;
}, 110, 2);

/* ---------- edit screens ---------- */

function eg_ps_field_helpers($post)
{
    $m = static function ($k) use ($post) {
        return (string) get_post_meta($post->ID, $k, true);
    };
    $in = static function ($name, $val, $ph, $type = 'text') {
        return '<input type="' . $type . '" class="widefat" name="' . esc_attr($name) . '" value="' . esc_attr($val) . '" placeholder="' . esc_attr($ph) . '">';
    };
    $ta = static function ($name, $val, $ph, $rows = 3) {
        return '<textarea class="widefat" rows="' . (int) $rows . '" name="' . esc_attr($name) . '" placeholder="' . esc_attr($ph) . '">' . esc_textarea($val) . '</textarea>';
    };
    $row = static function ($label, $de, $en = null, $help = '') {
        echo '<tr><th style="width:190px;vertical-align:top;padding-top:10px">' . $label . '</th><td style="padding:6px 0">';
        echo $en === null ? $de : '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">' . $de . $en . '</div>';
        if ($help) {
            echo '<p class="description" style="margin-top:4px">' . $help . '</p>';
        }
        echo '</td></tr>';
    };
    return array($m, $in, $ta, $row);
}

add_action('add_meta_boxes_eg_person', static function () {
    foreach (array('postcustom', 'authordiv', 'eg_rolediv', 'tagsdiv-eg_role', 'slugdiv') as $box) {
        remove_meta_box($box, 'eg_person', 'normal');
        remove_meta_box($box, 'eg_person', 'side');
    }
    add_meta_box('eg_ps_fields', 'Person auf der Website', 'eg_ps_render_person_box', 'eg_person', 'normal', 'high');
    add_meta_box('eg_ps_pubs', 'Publikationen dieser Person', 'eg_ps_render_person_pubs', 'eg_person', 'normal', 'default');
}, 99);

function eg_ps_render_person_box($post)
{
    wp_nonce_field('eg_ps_save', 'eg_ps_nonce');
    wp_enqueue_media();
    list($m, $in, $ta, $row) = eg_ps_field_helpers($post);
    $is_new = $post->post_status === 'auto-draft';
    $slug = $post->post_name !== '' ? $post->post_name : '';
    $photo_field = static function ($name, $val) {
        return '<div style="display:flex;gap:8px;align-items:center"><input type="url" class="widefat eg-ps-photo" name="' . esc_attr($name) . '" value="' . esc_attr($val) . '" placeholder="/assets/images/… oder Bild wählen">'
            . '<button type="button" class="button eg-ps-pick" data-target="' . esc_attr($name) . '">Bild wählen</button></div>'
            . ($val !== '' ? '<img src="' . esc_url(strpos($val, '/') === 0 ? eg_ps_site() . $val : $val) . '" alt="" style="max-height:90px;margin-top:6px">' : '');
    };
    echo '<p style="margin:4px 0 12px">Der Name steht oben im Titelfeld (so wie er auf der Website erscheinen soll, z. B. „Dr. Alexander S. Neu“). Deutsch ist Pflicht, Englisch freiwillig.</p>';
    echo '<table class="form-table" role="presentation" style="margin-top:0">';
    $row('Adresse der Profilseite', '<input type="text" class="widefat" name="eg_ps_slug" value="' . esc_attr($slug) . '" placeholder="z. B. max-mustermann">', null,
        'Ergibt eurasiengesellschaft.de/personen/<strong>' . esc_html($slug !== '' ? $slug : 'max-mustermann') . '</strong>.html. Nur Kleinbuchstaben, Ziffern und Bindestriche; bei bestehenden Personen bitte nicht ändern.');
    $row('Funktion', $in('eg_role_de', $m('eg_role_de'), 'z. B. Stellvertretender Vorsitzender'), $in('eg_role_en', $m('eg_role_en'), 'e.g. Deputy Chairman'));
    $row('Auf der Vorstandsseite zeigen', '<label><input type="checkbox" name="eg_on_board" value="1"' . checked($is_new || $m('eg_on_board') === '1', true, false) . '> Ja</label>', null,
        'Die Reihenfolge bestimmt das Feld „Reihenfolge“ im Kasten „Attribute“ rechts (1 = zuerst).');
    $row('Kurztext für die Übersicht', $ta('eg_short_de', $m('eg_short_de'), 'Ein Satz (optional)', 2), $ta('eg_short_en', $m('eg_short_en'), 'optional', 2));
    $row('Profilfoto', $photo_field('eg_photo', $m('eg_photo')), null, 'Quadratisch, mindestens 400 × 400 Pixel.');
    $row('Foto für die Übersicht', $photo_field('eg_photo_card', $m('eg_photo_card')), null, 'Optional, Querformat 4:3. Leer = Profilfoto.');
    $row('Biografie', $ta('eg_bio_de', $m('eg_bio_de'), 'Absätze durch eine Leerzeile trennen', 12), $ta('eg_bio_en', $m('eg_bio_en'), 'Same paragraphs, separated by a blank line (optional)', 12));
    $row('Beschreibung für Suchmaschinen', $ta('eg_desc', $m('eg_desc'), 'z. B. Profil von …: Funktion der Eurasien Gesellschaft, Beruf.', 2), null, 'Ein Satz, höchstens etwa 160 Zeichen. Leer = wird aus Name und Funktion gebildet.');
    $row('Kurzinfo für KI-Assistenten (englisch)', $ta('eg_llms', $m('eg_llms'), 'e.g. Chairman (Vorsitzender). Historian, Eastern Europe expert and author.', 2), null, 'Eine Zeile auf Englisch für ChatGPT & Co. (Datei llms.txt). Leer = aus Funktion und Kurztext gebildet.');
    $row('Weitere Profile im Netz', $ta('eg_same_as', $m('eg_same_as'), "z. B. https://de.wikipedia.org/wiki/…\nEine Adresse pro Zeile", 2), null, 'Für Suchmaschinen (nicht sichtbar).');
    echo '<tr><td colspan="2" style="padding:14px 0 2px"><h3 style="margin:0">Publikationsbereich der Profilseite</h3><p class="description" style="margin:4px 0 0">Die einzelnen Publikationen stehen unten bzw. unter Personen → Publikationen.</p></td></tr>';
    $row('Überschrift klein', $in('eg_pubs_eyebrow_de', $m('eg_pubs_eyebrow_de'), 'Publikationen'), $in('eg_pubs_eyebrow_en', $m('eg_pubs_eyebrow_en'), 'Publications'));
    $row('Überschrift', $in('eg_pubs_title_de', $m('eg_pubs_title_de'), 'Veröffentlichungen & Analysen'), $in('eg_pubs_title_en', $m('eg_pubs_title_en'), 'Publications & analyses'));
    $row('Hinweis (optional)', $ta('eg_pubs_note_de', $m('eg_pubs_note_de'), '', 2), $ta('eg_pubs_note_en', $m('eg_pubs_note_en'), '', 2));
    $row('„Weitere“-Zeile: Beschriftung', $in('eg_further_label_de', $m('eg_further_label_de'), 'Weitere Analysen'), $in('eg_further_label_en', $m('eg_further_label_en'), 'Further analyses'));
    $row('„Weitere“-Zeile: Links', $ta('eg_further_links', $m('eg_further_links'), "Linktext | https://…\nEin Link pro Zeile", 2));
    echo '</table>';
    echo "<script>jQuery(function($){ $(document).on('click','.eg-ps-pick',function(e){ e.preventDefault(); var t=$(this).data('target'); var f=wp.media({title:'Bild wählen',button:{text:'Übernehmen'},library:{type:'image'},multiple:false}); f.on('select',function(){ var a=f.state().get('selection').first().toJSON(); $('input[name=\"'+t+'\"]').val(a.url); }); f.open(); }); });</script>";
}

function eg_ps_render_person_pubs($post)
{
    if ($post->post_status === 'auto-draft') {
        echo '<p>Bitte die Person zuerst speichern; danach können hier Publikationen hinzugefügt werden.</p>';
        return;
    }
    $pubs = eg_ps_pub_posts($post->ID, array('publish', 'draft', 'pending', 'private'));
    echo '<p>Die ersten vier stehen sichtbar auf der Profilseite, weitere unter „Weitere … Titel anzeigen“. Reihenfolge: Feld „Reihenfolge“ in der Publikation (kleinste Zahl zuerst).</p>';
    if ($pubs) {
        echo '<ol style="margin-left:18px">';
        foreach ($pubs as $p) {
            echo '<li><a href="' . esc_url(get_edit_post_link($p->ID)) . '">' . esc_html($p->post_title) . '</a> <span style="color:#646970">(' . esc_html((string) get_post_meta($p->ID, 'eg_year', true)) . ', Reihenfolge ' . (int) $p->menu_order . ($p->post_status !== 'publish' ? ', ' . esc_html($p->post_status) : '') . ')</span></li>';
        }
        echo '</ol>';
    }
    echo '<p><a class="button" href="' . esc_url(admin_url('post-new.php?post_type=eg_publication&eg_person_id=' . $post->ID)) . '">Neue Publikation für diese Person</a></p>';
}

add_action('save_post_eg_person', static function ($post_id) {
    if (!isset($_POST['eg_ps_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['eg_ps_nonce'])), 'eg_ps_save')) {
        return;
    }
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_post', $post_id)) {
        return;
    }
    foreach (eg_ps_person_keys() as $k) {
        if ($k === 'eg_on_board') {
            continue;
        }
        $raw = isset($_POST[$k]) ? wp_unslash($_POST[$k]) : '';
        update_post_meta($post_id, $k, eg_ps_clean($k, (string) $raw));
    }
    update_post_meta($post_id, 'eg_on_board', empty($_POST['eg_on_board']) ? '' : '1');
    $slug = isset($_POST['eg_ps_slug']) ? sanitize_title(wp_unslash($_POST['eg_ps_slug'])) : '';
    if ($slug === '') {
        $slug = sanitize_title((string) get_post_field('post_title', $post_id));
    }
    if ($slug !== '' && $slug !== get_post_field('post_name', $post_id)) {
        remove_all_actions('save_post_eg_person');
        wp_update_post(array('ID' => $post_id, 'post_name' => $slug));
    }
}, 30);

add_action('add_meta_boxes_eg_publication', static function () {
    remove_meta_box('postcustom', 'eg_publication', 'normal');
    add_meta_box('eg_pub_fields', 'Publikation', 'eg_ps_render_pub_box', 'eg_publication', 'normal', 'high');
}, 99);

function eg_ps_render_pub_box($post)
{
    wp_nonce_field('eg_pub_save', 'eg_pub_nonce');
    list($m, $in, $ta, $row) = eg_ps_field_helpers($post);
    $person = (int) ($m('eg_person_id') !== '' ? $m('eg_person_id') : (isset($_GET['eg_person_id']) ? absint($_GET['eg_person_id']) : 0));
    $people = get_posts(array('post_type' => 'eg_person', 'post_status' => array('publish', 'draft'), 'numberposts' => 200, 'orderby' => 'menu_order title', 'order' => 'ASC'));
    $opts = '<option value="">– bitte wählen –</option>';
    foreach ($people as $p) {
        $opts .= '<option value="' . (int) $p->ID . '"' . selected($person, $p->ID, false) . '>' . esc_html($p->post_title) . '</option>';
    }
    echo '<p style="margin:4px 0 12px">Der Titel der Publikation steht oben im Titelfeld (in der Originalsprache).</p><table class="form-table" role="presentation" style="margin-top:0">';
    $row('Person', '<select name="eg_person_id">' . $opts . '</select>');
    $row('Jahr', $in('eg_year', $m('eg_year'), 'z. B. 2024 (optional)'));
    $row('Art', $in('eg_type_de', $m('eg_type_de'), 'z. B. Buch, Analyse, Buchbeitrag'), $in('eg_type_en', $m('eg_type_en'), 'e.g. Book, Analysis, Book chapter'));
    $row('Erschienen in', $in('eg_src', $m('eg_src'), 'z. B. WeltTrends 162 oder Verlag · Ort · ISBN'));
    $row('Kurzbeschreibung', $ta('eg_abs_de', $m('eg_abs_de'), '', 4), $ta('eg_abs_en', $m('eg_abs_en'), 'optional', 4));
    $row('Links', $ta('eg_links', $m('eg_links'), "Linktext | https://…\nEin Link pro Zeile", 2), null, 'z. B. „DNB | https://d-nb.info/…“');
    echo '</table><p class="description">Reihenfolge auf der Profilseite: Feld „Reihenfolge“ rechts im Kasten „Attribute“ (kleinste Zahl zuerst).</p>';
}

add_action('save_post_eg_publication', static function ($post_id) {
    if (!isset($_POST['eg_pub_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['eg_pub_nonce'])), 'eg_pub_save')) {
        return;
    }
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_post', $post_id)) {
        return;
    }
    foreach (eg_ps_pub_keys() as $k) {
        $raw = isset($_POST[$k]) ? wp_unslash($_POST[$k]) : '';
        update_post_meta($post_id, $k, $k === 'eg_person_id' ? (string) absint($raw) : eg_ps_clean($k, (string) $raw));
    }
}, 30);

/* ---------- data ---------- */

function eg_ps_pub_posts($person_id, $status = array('publish'))
{
    return get_posts(array(
        'post_type' => 'eg_publication', 'post_status' => $status, 'numberposts' => 300,
        'meta_key' => 'eg_person_id', 'meta_value' => (string) (int) $person_id,
        'orderby' => array('menu_order' => 'ASC', 'date' => 'DESC'),
    ));
}

/** "Label | URL" lines -> [[label, url], ...] (only http/https URLs). */
function eg_ps_links($text)
{
    $out = array();
    foreach (preg_split('/\r\n|\r|\n/', (string) $text) as $line) {
        $parts = array_map('trim', explode('|', $line, 2));
        if (count($parts) === 2 && eg_r_safe_url($parts[1]) !== '') {
            $out[] = array($parts[0] !== '' ? $parts[0] : $parts[1], $parts[1]);
        }
    }
    return $out;
}

function eg_ps_paragraphs($text)
{
    $text = trim(str_replace(array("\r\n", "\r"), "\n", (string) $text));
    if ($text === '') {
        return array();
    }
    return array_values(array_filter(array_map('trim', preg_split('/\n\s*\n/', $text)), 'strlen'));
}

function eg_ps_person($p)
{
    $m = static function ($k) use ($p) {
        return (string) get_post_meta($p->ID, $k, true);
    };
    $pubs = array();
    foreach (eg_ps_pub_posts($p->ID) as $q) {
        $pm = static function ($k) use ($q) {
            return (string) get_post_meta($q->ID, $k, true);
        };
        $pubs[] = array(
            'title' => (string) $q->post_title, 'year' => $pm('eg_year'), 'type' => $pm('eg_type_de'), 'type_en' => $pm('eg_type_en'),
            'src' => $pm('eg_src'), 'abs' => $pm('eg_abs_de'), 'abs_en' => $pm('eg_abs_en'), 'links' => eg_ps_links($pm('eg_links')),
        );
    }
    $name = (string) $p->post_title;
    $role = $m('eg_role_de');
    $desc = $m('eg_desc') !== '' ? $m('eg_desc') : 'Profil von ' . $name . ($role !== '' ? ': ' . $role . ' der Eurasien Gesellschaft.' : '.');
    $photo = $m('eg_photo');
    return array(
        'id' => $p->ID, 'slug' => (string) $p->post_name, 'name' => $name, 'role' => $role, 'role_en' => $m('eg_role_en'),
        'short' => $m('eg_short_de'), 'short_en' => $m('eg_short_en'), 'photo' => $photo,
        'photo_card' => $m('eg_photo_card') !== '' ? $m('eg_photo_card') : $photo, 'desc' => $desc,
        'same_as' => array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $m('eg_same_as'))), 'eg_r_safe_url')),
        'on_board' => $m('eg_on_board') === '1', 'bio' => eg_ps_paragraphs($m('eg_bio_de')), 'bio_en' => eg_ps_paragraphs($m('eg_bio_en')),
        'pubs_eyebrow' => $m('eg_pubs_eyebrow_de') !== '' ? $m('eg_pubs_eyebrow_de') : 'Publikationen',
        'pubs_eyebrow_en' => $m('eg_pubs_eyebrow_en') !== '' ? $m('eg_pubs_eyebrow_en') : 'Publications',
        'pubs_title' => $m('eg_pubs_title_de') !== '' ? $m('eg_pubs_title_de') : 'Veröffentlichungen & Analysen',
        'pubs_title_en' => $m('eg_pubs_title_en') !== '' ? $m('eg_pubs_title_en') : 'Publications & analyses',
        'pubs_note' => $m('eg_pubs_note_de'), 'pubs_note_en' => $m('eg_pubs_note_en'),
        'further_label' => $m('eg_further_label_de') !== '' ? $m('eg_further_label_de') : 'Weitere Analysen',
        'further_label_en' => $m('eg_further_label_en') !== '' ? $m('eg_further_label_en') : 'Further analyses',
        'further' => eg_ps_links($m('eg_further_links')), 'pubs' => $pubs, 'llms' => $m('eg_llms'),
    );
}

/** Published people, in board order. */
function eg_ps_people()
{
    $out = array();
    foreach (get_posts(array('post_type' => 'eg_person', 'post_status' => 'publish', 'numberposts' => 200, 'orderby' => array('menu_order' => 'ASC', 'title' => 'ASC'))) as $p) {
        if ($p->post_name !== '') {
            $out[] = eg_ps_person($p);
        }
    }
    return $out;
}

function eg_ps_find($slug)
{
    $p = get_page_by_path($slug, OBJECT, 'eg_person');
    return ($p && $p->post_status === 'publish') ? eg_ps_person($p) : null;
}

function eg_ps_abs($url)
{
    return strpos($url, '/') === 0 ? eg_ps_site() . $url : $url;
}

/* ---------- markup ---------- */

function eg_ps_card($ps)
{
    return '<article class="person"><div class="person__ph"><img class="person__img" src="' . eg_r_esc($ps['photo_card']) . '" alt="' . eg_r_esc($ps['name']) . '"></div>'
        . '<div class="person__b"><h3 class="person__n">' . eg_r_esc($ps['name']) . '</h3>'
        . ($ps['role'] !== '' ? '<p class="person__r">' . eg_an_sp($ps['role'], $ps['role_en']) . '</p>' : '')
        . ($ps['short'] !== '' ? '<p class="person__x">' . eg_an_sp($ps['short'], $ps['short_en']) . '</p>' : '')
        . '<p class="person__more"><a class="link-more" href="personen/' . eg_r_esc($ps['slug']) . '.html" data-analytics="profile_view">' . eg_an_sp('Zum Profil', 'View profile') . '</a></p></div></article>';
}

function eg_ps_pub_html($p)
{
    $meta = $p['year'] !== '' ? '<span class="pub__year">' . eg_r_esc($p['year']) . '</span>' : '';
    if ($p['type'] !== '') {
        $meta .= '<span class="pub__type de">' . eg_r_esc($p['type']) . '</span><span class="pub__type en" hidden>' . eg_r_esc($p['type_en'] !== '' ? $p['type_en'] : $p['type']) . '</span>';
    }
    $links = array();
    foreach ($p['links'] as $l) {
        $links[] = '<a href="' . eg_r_esc($l[1]) . '" target="_blank" rel="noopener">' . eg_r_esc($l[0]) . '</a>';
    }
    return "\n        " . '<article class="pub">' . "\n          " . '<div class="pub__meta">' . $meta . '</div>'
        . "\n          " . '<h3 class="pub__title">' . eg_r_esc($p['title']) . '</h3>'
        . ($p['src'] !== '' ? "\n          " . '<p class="pub__src">' . eg_r_esc($p['src']) . '</p>' : '')
        . ($p['abs'] !== '' ? "\n          " . '<p class="pub__abs de">' . eg_r_esc($p['abs']) . '</p>' . "\n          " . '<p class="pub__abs en" hidden>' . eg_r_esc($p['abs_en'] !== '' ? $p['abs_en'] : $p['abs']) . '</p>' : '')
        . ($links ? "\n          " . '<p class="pub__links">' . implode(' · ', $links) . '</p>' : '')
        . "\n        </article>";
}

function eg_ps_profile_body($ps)
{
    $out = '';
    $n = max(count($ps['bio']), count($ps['bio_en']));
    for ($i = 0; $i < $n; $i++) {
        $de = $ps['bio'][$i] ?? '';
        $en = $ps['bio_en'][$i] ?? $de;
        $out .= "\n        " . '<p class="de">' . eg_r_esc($de) . '</p>' . "\n        " . '<p class="en" hidden>' . eg_r_esc($en) . '</p>';
    }
    if ($ps['pubs']) {
        $out .= "\n        " . '<div class="pubs">' . "\n          " . '<div class="pubs__head">'
            . "\n            " . '<p class="eyebrow">' . eg_an_sp($ps['pubs_eyebrow'], $ps['pubs_eyebrow_en']) . '</p>'
            . "\n            " . '<h2>' . eg_an_sp($ps['pubs_title'], $ps['pubs_title_en']) . '</h2>' . "\n          </div>";
        if ($ps['pubs_note'] !== '') {
            $out .= "\n          " . '<p class="pubs__note de">' . eg_r_esc($ps['pubs_note']) . '</p>'
                . "\n          " . '<p class="pubs__note en" hidden>' . eg_r_esc($ps['pubs_note_en'] !== '' ? $ps['pubs_note_en'] : $ps['pubs_note']) . '</p>';
        }
        $out .= "\n          " . '<div class="pubs__list">';
        $shown = 4;
        foreach (array_slice($ps['pubs'], 0, $shown) as $p) {
            $out .= eg_ps_pub_html($p);
        }
        $rest = array_slice($ps['pubs'], $shown);
        if ($rest) {
            $c = count($rest);
            $out .= "\n            " . '<details class="pubs__more"><summary><span class="pm-show">' . eg_an_sp('Weitere ' . $c . ' Titel anzeigen', 'Show ' . $c . ' more')
                . '</span><span class="pm-hide">' . eg_an_sp('Weniger anzeigen', 'Show fewer') . '</span></summary>';
            foreach ($rest as $p) {
                $out .= eg_ps_pub_html($p);
            }
            $out .= "\n            </details>";
        }
        if ($ps['further']) {
            $links = array();
            foreach ($ps['further'] as $l) {
                $links[] = '<a href="' . eg_r_esc($l[1]) . '" target="_blank" rel="noopener">' . eg_r_esc($l[0]) . '</a>';
            }
            $out .= "\n            " . '<p class="pubs__further"><span class="pubs__further-lbl">' . eg_an_sp($ps['further_label'], $ps['further_label_en']) . '</span> ' . implode(' · ', $links) . '</p>';
        }
        $out .= "\n          </div>\n        </div>";
    }
    return $out . "\n        " . '<p style="margin-top:22px"><a class="link-more" href="../vorstand.html">' . eg_an_sp('← Zurück zur Übersicht', '← Back to overview') . '</a></p>';
}

function eg_ps_profile_page($ps)
{
    return "\n  " . '<div class="phead"><div class="wrap"><p class="crumb"><a href="/">Start</a> › <a href="../vorstand.html">' . eg_an_sp('Vorstand & Experten', 'Board & Experts') . '</a> › ' . eg_r_esc($ps['name']) . '</p>'
        . '<p class="eyebrow">' . eg_an_sp('Profil', 'Profile') . '</p><h1>' . eg_r_esc($ps['name']) . '</h1>'
        . ($ps['role'] !== '' ? '<p>' . eg_an_sp($ps['role'], $ps['role_en']) . '</p>' : '') . '</div></div>'
        . "\n  " . '<section class="sec"><div class="wrap">' . "\n    " . '<div class="pf">' . "\n      " . '<div class="pf__side">'
        . "\n        " . '<div class="pf__ph"><img class="pf__img" src="' . eg_r_esc($ps['photo']) . '" alt="' . eg_r_esc($ps['name']) . '"></div>'
        . "\n      </div>\n      " . '<div class="pf__body prose">' . eg_ps_profile_body($ps) . "\n      </div>\n    </div>\n  </div></section>\n";
}

function eg_ps_head($ps)
{
    $url = eg_ps_site() . '/personen/' . $ps['slug'] . '.html';
    $title = eg_r_esc($ps['name'] . ' | Eurasien Gesellschaft');
    $desc = eg_r_esc($ps['desc']);
    $img = eg_r_esc(eg_ps_abs($ps['photo']));
    return '<title>' . $title . '</title>' . "\n\n\n"
        . '<meta name="description" content="' . $desc . '">' . "\n" . '<meta name="robots" content="index,follow">' . "\n"
        . '<link rel="canonical" href="' . eg_r_esc($url) . '">' . "\n" . '<!-- Open Graph -->' . "\n"
        . '<meta property="og:type" content="profile">' . "\n" . '<meta property="og:site_name" content="Eurasien Gesellschaft e. V.">' . "\n"
        . '<meta property="og:title" content="' . $title . '">' . "\n" . '<meta property="og:description" content="' . $desc . '">' . "\n"
        . '<meta property="og:url" content="' . eg_r_esc($url) . '">' . "\n" . '<meta property="og:locale" content="de_DE">' . "\n"
        . '<meta property="og:locale:alternate" content="en_US">' . "\n" . '<meta property="og:image" content="' . $img . '">' . "\n"
        . '<meta property="og:image:alt" content="Eurasien Gesellschaft e. V.">' . "\n" . '<!-- Twitter Card -->' . "\n"
        . '<meta name="twitter:card" content="summary_large_image">' . "\n" . '<meta name="twitter:title" content="' . $title . '">' . "\n"
        . '<meta name="twitter:description" content="' . $desc . '">' . "\n" . '<meta name="twitter:image" content="' . $img . '">' . "\n";
}

/** Replace the person nodes of the page's JSON-LD; the organisation node stays as it is in the template. */
function eg_ps_ld($json, $ps)
{
    $d = json_decode($json, true);
    if (!is_array($d) || !isset($d['@graph'])) {
        throw new RuntimeException('profile JSON-LD unreadable');
    }
    $site = eg_ps_site();
    $url = $site . '/personen/' . $ps['slug'] . '.html';
    $org = array('@id' => $site . '/#organization');
    $graph = array();
    foreach ($d['@graph'] as $n) {
        if (!in_array($n['@type'] ?? '', array('ProfilePage', 'Person', 'BreadcrumbList'), true)) {
            $graph[] = $n;
        }
    }
    $graph[] = array('@type' => 'ProfilePage', '@id' => $url . '#webpage', 'url' => $url, 'name' => $ps['name'] . ' | Eurasien Gesellschaft',
        'description' => $ps['desc'], 'isPartOf' => array('@id' => $site . '/#website'), 'about' => $org, 'inLanguage' => 'de',
        'mainEntity' => array('@id' => $url . '#person'));
    $person = array('@type' => 'Person', '@id' => $url . '#person', 'name' => $ps['name']);
    if ($ps['role'] !== '') {
        $person['jobTitle'] = $ps['role'];
    }
    $person += array('image' => eg_ps_abs($ps['photo']), 'url' => $url, 'worksFor' => $org, 'description' => $ps['desc'], 'memberOf' => $org);
    if ($ps['same_as']) {
        $person['sameAs'] = $ps['same_as'];
    }
    $graph[] = $person;
    $graph[] = array('@type' => 'BreadcrumbList', 'itemListElement' => array(
        array('@type' => 'ListItem', 'position' => 1, 'name' => 'Start', 'item' => $site . '/'),
        array('@type' => 'ListItem', 'position' => 2, 'name' => 'Vorstand & Experten', 'item' => $site . '/vorstand.html'),
        array('@type' => 'ListItem', 'position' => 3, 'name' => $ps['name'], 'item' => $url),
    ));
    $d['@graph'] = $graph;
    return wp_json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

/* ---------- page assembly ---------- */

/** @throws RuntimeException; EgRenderNotFound when the person does not exist. */
function eg_ps_apply($page, $html, $slug = '')
{
    if ($page === 'person') {
        $ps = eg_ps_find($slug);
        if (!$ps) {
            throw new EgRenderNotFound('no such person');
        }
        $html = eg_r_replace($html, '/<!--wp:person-meta-->.*?<!--\/wp:person-meta-->/s', '<!--wp:person-meta-->' . eg_ps_head($ps) . '<!--/wp:person-meta-->');
        $html = preg_replace_callback('/(<!--wp:person-ld--><script type="application\/ld\+json">)(.*?)(<\/script><!--\/wp:person-ld-->)/s', static function ($m) use ($ps) {
            return $m[1] . "\n" . str_replace('</', '<\/', eg_ps_ld($m[2], $ps)) . "\n" . $m[3];
        }, $html);
        if (!is_string($html)) {
            throw new RuntimeException('profile JSON-LD replace failed');
        }
        // existing profile files keep their section id; the template for new people gets one
        $html = str_replace('<section class="page" id="p-person-profil">', '<section class="page" id="p-person-' . eg_r_esc($ps['slug']) . '">', $html);
        return eg_r_replace($html, '/<!--wp:person-page-->.*?<!--\/wp:person-page-->/s', '<!--wp:person-page-->' . eg_ps_profile_page($ps) . '<!--/wp:person-page-->');
    }
    $people = eg_ps_people();
    if (!$people) {
        throw new RuntimeException('no people');
    }
    $board = array_values(array_filter($people, static function ($p) {
        return $p['on_board'];
    }));
    if ($page === 'vorstand') {
        $cards = '';
        foreach ($board as $ps) {
            $cards .= "\n      " . eg_ps_card($ps);
        }
        return eg_r_replace($html, '/<!--wp:board-->.*?<!--\/wp:board-->/s', '<!--wp:board-->' . $cards . "\n      <!--/wp:board-->");
    }
    if ($page === 'sitemap') {
        $urls = '';
        foreach ($people as $ps) {
            $urls .= "\n  <url>\n    <loc>" . eg_r_esc(eg_ps_site() . '/personen/' . $ps['slug'] . '.html') . "</loc>\n    <lastmod>"
                . esc_html(get_post_modified_time('Y-m-d', true, $ps['id'])) . "</lastmod>\n    <changefreq>monthly</changefreq>\n    <priority>0.7</priority>\n  </url>";
        }
        return eg_r_replace($html, '/<!--wp:people-->.*?<!--\/wp:people-->/s', '<!--wp:people-->' . $urls . "\n  <!--/wp:people-->");
    }
    if (($page === 'llms' || $page === 'index') && !$board) {
        return $html; // nothing usable in WordPress: keep the page as it is
    }
    if ($page === 'llms') {
        $lines = array();
        foreach ($board as $ps) {
            $role = $ps['role_en'] !== '' ? $ps['role_en'] . ($ps['role'] !== '' && $ps['role'] !== $ps['role_en'] ? ' (' . $ps['role'] . ')' : '') : $ps['role'];
            $about = $ps['short_en'] !== '' ? $ps['short_en'] : $ps['short'];
            $text = $ps['llms'] !== '' ? $ps['llms'] : ($role !== '' ? $role . '.' : '') . ($about !== '' ? ' ' . rtrim($about, '.') . '.' : '');
            $lines[] = '- ' . $ps['name'] . ($text !== '' ? ' – ' . trim($text) : '') . ' ' . eg_ps_site() . '/personen/' . $ps['slug'] . '.html';
        }
        return eg_r_replace($html, '/<!-- board:start -->.*?<!-- board:end -->/s', "<!-- board:start -->\n" . implode("\n", $lines) . "\n<!-- board:end -->");
    }
    if ($page === 'index') {
        $members = array();
        foreach ($board as $ps) {
            $members[] = array('@id' => eg_ps_site() . '/personen/' . $ps['slug'] . '.html#person');
        }
        $out = preg_replace_callback('/(<script type="application\/ld\+json"[^>]*>)(.*?)(<\/script>)/s', static function ($m) use ($members) {
            $d = json_decode($m[2], true);
            if (!is_array($d) || empty($d['@graph'][0]) || !array_key_exists('member', $d['@graph'][0])) {
                return $m[0];
            }
            $d['@graph'][0]['member'] = $members;
            return $m[1] . "\n" . str_replace('</', '<\/', wp_json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) . "\n" . $m[3];
        }, $html, 1);
        return is_string($out) ? $out : $html;
    }
    return $html;
}
