<?php
/**
 * Plugin Name: EG Mediathek
 * Description: Videos (eg_recording) maintained in WordPress: German edit screen, server-side rendering into mediathek.html (video cards) and regionen.html (video cards per region). Uses helpers from eg-analysen.php and eg-render-engine.php.
 * Author: d4rl1ngt0n
 */

if (!defined('ABSPATH')) {
    exit;
}

function eg_rec_meta_keys()
{
    return array(
        'eg_title_en', 'eg_rec_by', 'eg_rec_by_en', 'eg_rec_tags', 'eg_rec_tags_en', 'eg_rec_in_mediathek', 'eg_undated',
        'eg_kicker', 'eg_excerpt_en', 'eg_more_de', 'eg_more_en', 'eg_byline', 'eg_byline_en', 'eg_card_title', 'eg_card_title_en',
    );
}

add_action('init', static function () {
    if (!post_type_exists('eg_recording')) {
        return;
    }
    $auth = static function () {
        return current_user_can('edit_posts');
    };
    foreach (eg_rec_meta_keys() as $key) {
        $multi = in_array($key, array('eg_rec_tags', 'eg_rec_tags_en', 'eg_more_de', 'eg_more_en', 'eg_excerpt_en'), true);
        register_post_meta('eg_recording', $key, array(
            'type' => 'string', 'single' => true, 'show_in_rest' => true,
            'sanitize_callback' => $multi ? 'sanitize_textarea_field' : 'sanitize_text_field', 'auth_callback' => $auth,
        ));
    }
    add_post_type_support('eg_recording', 'excerpt');
    remove_post_type_support('eg_recording', 'editor');
    remove_post_type_support('eg_recording', 'thumbnail');
}, 30);

add_filter('use_block_editor_for_post_type', static function ($use, $type) {
    return $type === 'eg_recording' ? false : $use;
}, 110, 2);

/* ---------- edit screen ---------- */

add_action('add_meta_boxes_eg_recording', static function () {
    foreach (array('postexcerpt', 'eg_topicdiv', 'tagsdiv-eg_topic', 'authordiv', 'postcustom') as $box) {
        remove_meta_box($box, 'eg_recording', 'normal');
        remove_meta_box($box, 'eg_recording', 'side');
    }
    add_meta_box('eg_rec_fields', 'Video auf der Website', 'eg_rec_render_box', 'eg_recording', 'normal', 'high');
}, 99);

function eg_rec_render_box($post)
{
    wp_nonce_field('eg_rec_save', 'eg_rec_nonce');
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
    $is_new = $post->post_status === 'auto-draft';
    $in_med = $is_new || $m('eg_rec_in_mediathek') === '1';
    $kicker = $m('eg_kicker') ?: 'analyse';

    echo '<p style="margin:4px 0 12px">Deutsch ist Pflicht, Englisch freiwillig (leer = deutscher Text). Der deutsche Titel steht oben im Titelfeld.</p>';
    echo '<table class="form-table" role="presentation" style="margin-top:0">';
    $row('Titel', '<div style="padding-top:6px;color:#646970">Deutsch: oben im Titelfeld</div>', $in('eg_title_en', $m('eg_title_en'), 'Englischer Titel (optional)'));
    $row('YouTube-Link', $in('eg_youtube_url', $m('eg_youtube_url'), 'https://www.youtube.com/watch?v=…', 'url'), null, 'Ohne Link steht auf der Regionen-Seite „Quelle in Prüfung“.');
    $row('Datum', '<p style="margin:0 0 6px">Die Reihenfolge (neueste zuerst) richtet sich nach dem Datum im Kasten „Veröffentlichen“ rechts.</p><label><input type="checkbox" name="eg_undated" value="1"' . checked($m('eg_undated'), '1', false) . '> Ohne Datum (steht am Ende)</label>');

    echo '<tr><td colspan="2" style="padding:14px 0 2px"><h3 style="margin:0">Mediathek</h3></td></tr>';
    $row('In der Mediathek zeigen', '<label><input type="checkbox" name="eg_rec_in_mediathek" value="1"' . checked($in_med, true, false) . '> Ja</label>');
    $row('Mitwirkende (kurz)', $in('eg_rec_by', $m('eg_rec_by'), 'z. B. Dr. A. Neu, A. Rahr · 2025'), $in('eg_rec_by_en', $m('eg_rec_by_en'), 'optional'));
    $row('Schlagworte', $ta('eg_rec_tags', $m('eg_rec_tags'), "Eines pro Zeile, z. B.\nOsteuropa\nSicherheit"), $ta('eg_rec_tags_en', $m('eg_rec_tags_en'), "Same order, one per line (optional)\nOsteuropa\nSecurity"),
        'Englisch in derselben Reihenfolge, ein Wort pro Zeile. Gilt ein Wort in beiden Sprachen, einfach wiederholen. Ganz leer lassen = alles wie Deutsch.');

    echo '<tr><td colspan="2" style="padding:14px 0 2px"><h3 style="margin:0">Regionen-Seite (optional)</h3><p class="description" style="margin:4px 0 0">Erscheint nur, wenn rechts mindestens eine Region angekreuzt und ein Anriss eingetragen ist.</p></td></tr>';
    $kopts = '';
    foreach (eg_an_kickers() as $k => $l) {
        $kopts .= '<option value="' . esc_attr($k) . '"' . selected($kicker, $k, false) . '>' . esc_html($l[0]) . '</option>';
    }
    $row('Rubrik', '<select name="eg_kicker">' . $kopts . '</select>');
    $row('Titel auf der Karte', $in('eg_card_title', $m('eg_card_title'), 'leer = wie oben'), $in('eg_card_title_en', $m('eg_card_title_en'), 'optional'));
    $row('Anriss', $ta('excerpt', $post->post_excerpt, 'Ein bis zwei Sätze'), $ta('eg_excerpt_en', $m('eg_excerpt_en'), 'optional'));
    $row('„Weiterlesen“-Text', $ta('eg_more_de', $m('eg_more_de'), 'optional', 4), $ta('eg_more_en', $m('eg_more_en'), 'optional', 4));
    $row('Zeile unter der Karte', $in('eg_byline', $m('eg_byline'), 'z. B. Vortrag von Harald Kujat, 20. Februar 2024'), $in('eg_byline_en', $m('eg_byline_en'), 'e.g. Lecture by Harald Kujat, 20 February 2024'));
    echo '</table>';
}

add_action('save_post_eg_recording', static function ($post_id) {
    if (!isset($_POST['eg_rec_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['eg_rec_nonce'])), 'eg_rec_save')) {
        return;
    }
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_post', $post_id)) {
        return;
    }
    foreach (array('eg_title_en', 'eg_rec_by', 'eg_rec_by_en', 'eg_byline', 'eg_byline_en', 'eg_card_title', 'eg_card_title_en') as $k) {
        update_post_meta($post_id, $k, isset($_POST[$k]) ? sanitize_text_field(wp_unslash($_POST[$k])) : '');
    }
    foreach (array('eg_rec_tags', 'eg_rec_tags_en', 'eg_excerpt_en', 'eg_more_de', 'eg_more_en') as $k) {
        update_post_meta($post_id, $k, isset($_POST[$k]) ? sanitize_textarea_field(wp_unslash($_POST[$k])) : '');
    }
    update_post_meta($post_id, 'eg_youtube_url', isset($_POST['eg_youtube_url']) ? esc_url_raw(wp_unslash($_POST['eg_youtube_url'])) : '');
    update_post_meta($post_id, 'eg_rec_in_mediathek', empty($_POST['eg_rec_in_mediathek']) ? '' : '1');
    update_post_meta($post_id, 'eg_undated', empty($_POST['eg_undated']) ? '' : '1');
    $kicker = isset($_POST['eg_kicker']) ? sanitize_key(wp_unslash($_POST['eg_kicker'])) : 'analyse';
    update_post_meta($post_id, 'eg_kicker', isset(eg_an_kickers()[$kicker]) ? $kicker : 'analyse');
}, 30);

/* ---------- data ---------- */

function eg_rec_items()
{
    $q = new WP_Query(array(
        'post_type' => 'eg_recording', 'post_status' => 'publish', 'posts_per_page' => 500,
        'orderby' => 'date', 'order' => 'DESC', 'no_found_rows' => true,
    ));
    $items = array();
    foreach ($q->posts as $i => $p) {
        $m = static function ($k) use ($p) {
            return (string) get_post_meta($p->ID, $k, true);
        };
        $lines = static function ($s) {
            return array_map('trim', preg_split('/\r\n|\r|\n/', (string) $s));
        };
        $tags_de = array_values(array_filter($lines($m('eg_rec_tags')), 'strlen'));
        $tags_en = $lines($m('eg_rec_tags_en'));
        $tags = array();
        foreach ($tags_de as $n => $de) {
            $tags[] = array($de, isset($tags_en[$n]) ? $tags_en[$n] : '');
        }
        $title = (string) $p->post_title; // as typed (get_the_title would turn " - " into a dash)
        $places = eg_an_places($p->ID);
        $items[] = array(
            'pos' => $i, 'undated' => $m('eg_undated') === '1',
            'title' => $title, 'title_en' => $m('eg_title_en'),
            'card_title' => $m('eg_card_title') !== '' ? $m('eg_card_title') : $title,
            'card_title_en' => $m('eg_card_title') !== '' ? $m('eg_card_title_en') : $m('eg_title_en'),
            'url' => eg_r_safe_url($m('eg_youtube_url')),
            'by' => $m('eg_rec_by'), 'by_en' => $m('eg_rec_by_en'), 'tags' => $tags,
            'in_mediathek' => $m('eg_rec_in_mediathek') === '1',
            'kicker' => isset(eg_an_kickers()[$m('eg_kicker')]) ? $m('eg_kicker') : 'analyse',
            'teaser' => trim((string) $p->post_excerpt), 'teaser_en' => $m('eg_excerpt_en'),
            'more' => $m('eg_more_de'), 'more_en' => $m('eg_more_en'),
            'byline' => $m('eg_byline'), 'byline_en' => $m('eg_byline_en'),
            'regions' => $places['regions'], 'countries' => $places['countries'],
        );
    }
    usort($items, static function ($a, $b) {
        if ($a['undated'] !== $b['undated']) {
            return $a['undated'] ? 1 : -1;
        }
        return $a['pos'] - $b['pos'];
    });
    return $items;
}

/* ---------- markup ---------- */

/** German/English pair, or plain text when there is no separate English. */
function eg_rec_text($de, $en)
{
    return ((string) $en !== '' && (string) $en !== (string) $de) ? eg_an_sp($de, $en) : eg_r_esc($de);
}

function eg_rec_watch($url)
{
    return '<a class="btn btn--ghost btn--sm ext" href="' . eg_r_esc($url) . '" target="_blank" rel="noopener">' . eg_an_sp('Auf YouTube ansehen', 'Watch on YouTube') . '</a>';
}

function eg_rec_mcard($it)
{
    $play = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M23 12s0-3.9-.5-5.6a2.9 2.9 0 0 0-2-2C18.7 4 12 4 12 4s-6.7 0-8.5.4a2.9 2.9 0 0 0-2 2C1 8.1 1 12 1 12s0 3.9.5 5.6a2.9 2.9 0 0 0 2 2C5.3 20 12 20 12 20s6.7 0 8.5-.4a2.9 2.9 0 0 0 2-2C23 15.9 23 12 23 12zM9.8 15.3V8.7l5.7 3.3z"/></svg>';
    $tags = '';
    foreach ($it['tags'] as $t) {
        $tags .= '<span>' . eg_rec_text($t[0], $t[1]) . '</span>';
    }
    return '<div class="mcard"><span class="mcard__k">' . $play . eg_an_sp('Aufzeichnung', 'Recording') . '</span>'
        . '<h3 class="mcard__t">' . eg_rec_text($it['title'], $it['title_en']) . '</h3>'
        . ($it['by'] !== '' ? '<p class="mcard__by">' . eg_rec_text($it['by'], $it['by_en']) . '</p>' : '')
        . ($tags !== '' ? '<div class="mcard__tags">' . $tags . '</div>' : '')
        . ($it['url'] !== '' ? eg_rec_watch($it['url']) : '') . '</div>';
}

function eg_rec_card($it, $region)
{
    $map = eg_an_country_regions();
    $countries = array();
    foreach ($it['countries'] as $c) {
        if (($map[$c] ?? '') === $region) {
            $countries[] = $c;
        }
    }
    $short = array(
        'europa' => array('Europa', 'Europe'), 'osteuropa' => array('Osteuropa', 'Eastern Europe'),
        'zentralasien' => array('Zentralasien', 'Central Asia'), 'kaukasus' => array('Kaukasus', 'Caucasus'),
        'ostasien' => array('Ostasien', 'East Asia'), 'suedasien' => array('Südasien', 'South Asia'),
        'naherosten' => array('Naher Osten', 'Middle East'),
    );
    $o_de = array();
    $o_en = array();
    foreach ($short as $key => $n) {
        if ($key !== $region && in_array($key, $it['regions'], true)) {
            $o_de[] = $n[0];
            $o_en[] = $n[1];
        }
    }
    $also = $o_de ? '<p class="small muted" style="margin:2px 0 10px">' . eg_an_sp('Auch in: ' . implode(', ', $o_de), 'Also in: ' . implode(', ', $o_en)) . '</p>' : '';
    $more = ($it['more'] !== '' || $it['more_en'] !== '')
        ? '<details class="card-more"><summary>' . eg_an_sp('Weiterlesen', 'Read more') . '</summary><p>' . eg_an_sp($it['more'], $it['more_en']) . '</p>' . $also . '</details>'
        : $also;
    $k = eg_an_kickers()[$it['kicker']];
    $src = $it['url'] !== '' ? eg_rec_watch($it['url']) : '<span class="src-note">' . eg_an_sp('Quelle in Prüfung', 'Source under review') . '</span>';
    return '<article class="card" data-region="' . eg_r_esc($region) . '" data-countries="' . eg_r_esc($countries ? implode(',', $countries) : 'regional') . '">'
        . '<span class="card__k">' . eg_an_sp($k[0], $k[1]) . '</span>'
        . '<h3 class="card__t">' . eg_an_sp($it['card_title'], $it['card_title_en']) . '</h3>'
        . '<p class="card__excerpt">' . eg_an_sp($it['teaser'], $it['teaser_en']) . '</p>'
        . $more
        . ($it['byline'] !== '' ? '<p class="card__meta">' . eg_an_sp($it['byline'], $it['byline_en']) . '</p>' : '')
        . '<div class="card__src">' . $src . '</div></article>';
}

/** Fill mediathek.html (video grid) or the video cards of regionen.html. */
function eg_rec_apply($page, $html)
{
    $items = eg_rec_items();
    if ($page === 'mediathek') {
        $cards = '';
        foreach ($items as $it) {
            if ($it['in_mediathek']) {
                $cards .= "\n      " . eg_rec_mcard($it);
            }
        }
        if ($cards === '') {
            throw new RuntimeException('no recordings');
        }
        return eg_r_replace($html, '/<!--wp:media-list-->.*?<!--\/wp:media-list-->/s', '<!--wp:media-list-->' . $cards . "\n      <!--/wp:media-list-->");
    }
    if ($page === 'regionen') {
        $by_region = array();
        foreach ($items as $it) {
            if ($it['teaser'] === '') {
                continue;
            }
            foreach ($it['regions'] as $r) {
                $by_region[$r][] = $it;
            }
        }
        $out = preg_replace_callback('/<!--wp:reg-rec:([a-z]+)-->.*?<!--\/wp:reg-rec:\1-->/s', static function ($m) use ($by_region) {
            $cards = '';
            foreach ($by_region[$m[1]] ?? array() as $it) {
                $cards .= "\n          " . eg_rec_card($it, $m[1]);
            }
            return '<!--wp:reg-rec:' . $m[1] . '-->' . $cards . "\n          <!--/wp:reg-rec:" . $m[1] . '-->';
        }, $html);
        if (!is_string($out)) {
            throw new RuntimeException('regionen video replace failed');
        }
        return $out;
    }
    return $html;
}
