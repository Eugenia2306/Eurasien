<?php
/**
 * Plugin Name: EG Analysen
 * Description: Analysen (statements, event analyses, society news) maintained in WordPress: German edit screen, regions and countries, server-side rendering into analysen.html (list + region filter) and regionen.html (cards per region).
 * Author: d4rl1ngt0n
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Formats that belong to the members area and never appear on the public pages. */
function eg_an_gated_formats()
{
    return array('positionen', 'dossiers', 'studien');
}

function eg_an_cats()
{
    return array(
        'stellungnahmen'           => array('Stellungnahme', 'Statement'),
        'veranstaltungen'          => array('Veranstaltung', 'Event'),
        'gesellschaftsnachrichten' => array('Mitteilung', 'News'),
    );
}

function eg_an_kickers()
{
    return array(
        'analyse'   => array('Analyse', 'Analysis'),
        'korridore' => array('Konnektivität & Korridore', 'Connectivity & corridors'),
    );
}

/* ---------- fields ---------- */

add_action('init', static function () {
    if (!post_type_exists('eg_analyse')) {
        return;
    }
    $auth = static function () {
        return current_user_can('edit_posts');
    };
    foreach (array('eg_note_de', 'eg_note_en', 'eg_source_kind', 'eg_kicker', 'eg_undated') as $key) {
        register_post_meta('eg_analyse', $key, array(
            'type' => 'string', 'single' => true, 'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => $auth,
        ));
    }
    foreach (array('eg_key', 'eg_name_en') as $key) {
        register_term_meta('eg_region', $key, array(
            'type' => 'string', 'single' => true, 'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => $auth,
        ));
    }
    // No body text on the website: the edit screen shows only the fields below.
    remove_post_type_support('eg_analyse', 'editor');
    remove_post_type_support('eg_analyse', 'thumbnail');
}, 30);

add_filter('use_block_editor_for_post_type', static function ($use, $type) {
    return $type === 'eg_analyse' ? false : $use;
}, 110, 2);

add_filter('register_taxonomy_args', static function ($args, $tax) {
    if ($tax === 'eg_region') {
        $args['labels'] = array_merge((array) ($args['labels'] ?? array()), array(
            'name' => 'Regionen & Länder', 'singular_name' => 'Region / Land', 'menu_name' => 'Regionen & Länder',
        ));
    }
    return $args;
}, 10, 2);

/* ---------- edit screen ---------- */

add_action('add_meta_boxes_eg_analyse', static function () {
    remove_meta_box('eg_analyse_brochure', 'eg_analyse', 'normal');
    remove_meta_box('postexcerpt', 'eg_analyse', 'normal');
    remove_meta_box('eg_formatdiv', 'eg_analyse', 'side');
    remove_meta_box('tagsdiv-eg_format', 'eg_analyse', 'side');
    remove_meta_box('eg_topicdiv', 'eg_analyse', 'side');
    remove_meta_box('tagsdiv-eg_topic', 'eg_analyse', 'side');
    remove_meta_box('authordiv', 'eg_analyse', 'normal');
    add_meta_box('eg_an_fields', 'Inhalt auf der Website', 'eg_an_render_box', 'eg_analyse', 'normal', 'high');
}, 99);

function eg_an_render_box($post)
{
    wp_nonce_field('eg_an_save', 'eg_an_nonce');
    $m = static function ($k) use ($post) {
        return (string) get_post_meta($post->ID, $k, true);
    };
    $cat = $m('eg_feed_cat') ?: 'stellungnahmen';
    $kind = $m('eg_source_kind') ?: 'link';
    $kicker = $m('eg_kicker') ?: 'analyse';
    $row = static function ($label, $de, $en, $help = '') {
        echo '<tr><th style="width:190px;vertical-align:top;padding-top:10px">' . $label . '</th><td style="padding:6px 0">';
        echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">' . $de . $en . '</div>';
        if ($help) {
            echo '<p class="description" style="margin-top:4px">' . $help . '</p>';
        }
        echo '</td></tr>';
    };
    $input = static function ($name, $val, $ph) {
        return '<input type="text" class="widefat" name="' . esc_attr($name) . '" value="' . esc_attr($val) . '" placeholder="' . esc_attr($ph) . '">';
    };
    $area = static function ($name, $val, $ph, $rows = 4) {
        return '<textarea class="widefat" rows="' . (int) $rows . '" name="' . esc_attr($name) . '" placeholder="' . esc_attr($ph) . '">' . esc_textarea($val) . '</textarea>';
    };
    echo '<p style="margin:4px 0 12px">Deutsch ist Pflicht. Englisch ist freiwillig: Bleibt ein englisches Feld leer, zeigt die englische Seite den deutschen Text. Der deutsche Titel steht oben im Titelfeld.</p>';
    echo '<table class="form-table" role="presentation" style="margin-top:0">';
    $row('Titel', '<div style="padding-top:6px;color:#646970">Deutsch: oben im Titelfeld</div>', $input('eg_title_en', $m('eg_title_en'), 'Englischer Titel (optional)'));
    $opts = '';
    foreach (eg_an_cats() as $k => $l) {
        $opts .= '<option value="' . esc_attr($k) . '"' . selected($cat, $k, false) . '>' . esc_html($l[0]) . '</option>';
    }
    echo '<tr><th style="vertical-align:top;padding-top:10px">Art</th><td style="padding:6px 0"><select name="eg_feed_cat">' . $opts . '</select>'
        . '<p class="description">Bestimmt den Filter auf der Analysen-Seite.</p></td></tr>';
    $row('Etikett', $input('eg_badge_de', $m('eg_badge_de'), 'z. B. Stellungnahme'), $input('eg_badge_en', $m('eg_badge_en'), 'e.g. Statement'),
        'Kleines Etikett über dem Titel. Leer = passend zur Art.');
    $row('Zusammenfassung', $area('excerpt', $post->post_excerpt, 'Deutsche Zusammenfassung (2 bis 5 Sätze)', 6), $area('eg_excerpt_en', $m('eg_excerpt_en'), 'English summary (optional)', 6),
        'Auf der Regionen-Seite erscheint der erste Satz als Anriss, der Rest unter „Weiterlesen“.');
    $row('Autorenzeile', $input('eg_byline', $m('eg_byline'), 'z. B. Stellungnahme von Jens Winkelmann, 19. September 2026'), $input('eg_byline_en', $m('eg_byline_en'), 'e.g. Statement by Jens Winkelmann, 19 September 2026'));
    $row('Hinweis (optional)', $input('eg_note_de', $m('eg_note_de'), 'z. B. Persönliche Analyse von …; keine offizielle Position des Vorstands.'), $input('eg_note_en', $m('eg_note_en'), 'e.g. Personal analysis by …; not an official position of the board.'),
        'Zweite, kleinere Zeile unter der Autorenzeile.');
    echo '<tr><th style="vertical-align:top;padding-top:10px">Quelle</th><td style="padding:6px 0">'
        . '<label style="display:block;margin-bottom:6px"><input type="radio" name="eg_source_kind" value="link"' . checked($kind, 'link', false) . '> Link zum Originalbeitrag (z. B. LinkedIn):</label>'
        . '<input type="url" class="widefat" name="eg_source_url" value="' . esc_attr($m('eg_source_url')) . '" placeholder="https://www.linkedin.com/feed/update/…">'
        . '<label style="display:block;margin-top:8px"><input type="radio" name="eg_source_kind" value="archive"' . checked($kind, 'archive', false) . '> Kein Link, Hinweis „Quelle: Veranstaltungsarchiv“</label>'
        . '</td></tr>';
    $kopts = '';
    foreach (eg_an_kickers() as $k => $l) {
        $kopts .= '<option value="' . esc_attr($k) . '"' . selected($kicker, $k, false) . '>' . esc_html($l[0]) . '</option>';
    }
    echo '<tr><th style="vertical-align:top;padding-top:10px">Rubrik auf der Regionen-Seite</th><td style="padding:6px 0"><select name="eg_kicker">' . $kopts . '</select></td></tr>';
    echo '<tr><th style="vertical-align:top;padding-top:10px">Datum</th><td style="padding:6px 0">'
        . '<p style="margin:0 0 6px">Die Reihenfolge (neueste zuerst) richtet sich nach dem Datum im Kasten „Veröffentlichen“ rechts (Veröffentlicht am … Bearbeiten).</p>'
        . '<label><input type="checkbox" name="eg_undated" value="1"' . checked($m('eg_undated'), '1', false) . '> Ohne Datum (steht am Ende der Liste)</label></td></tr>';
    echo '<tr><th style="vertical-align:top;padding-top:10px">Regionen</th><td style="padding:6px 0"><p style="margin:0">Rechts im Kasten „Regionen &amp; Länder“ ankreuzen. Ein Land zählt automatisch auch für seine Region. Ohne Region erscheint der Beitrag nur unter „Alle Regionen“.</p></td></tr>';
    echo '</table>';
}

add_action('save_post_eg_analyse', static function ($post_id) {
    if (!isset($_POST['eg_an_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['eg_an_nonce'])), 'eg_an_save')) {
        return;
    }
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_post', $post_id)) {
        return;
    }
    $text = array('eg_title_en', 'eg_badge_de', 'eg_badge_en', 'eg_byline', 'eg_byline_en', 'eg_note_de', 'eg_note_en');
    foreach ($text as $k) {
        update_post_meta($post_id, $k, isset($_POST[$k]) ? sanitize_text_field(wp_unslash($_POST[$k])) : '');
    }
    update_post_meta($post_id, 'eg_excerpt_en', isset($_POST['eg_excerpt_en']) ? sanitize_textarea_field(wp_unslash($_POST['eg_excerpt_en'])) : '');
    update_post_meta($post_id, 'eg_source_url', isset($_POST['eg_source_url']) ? esc_url_raw(wp_unslash($_POST['eg_source_url'])) : '');
    $cat = isset($_POST['eg_feed_cat']) ? sanitize_key(wp_unslash($_POST['eg_feed_cat'])) : '';
    update_post_meta($post_id, 'eg_feed_cat', isset(eg_an_cats()[$cat]) ? $cat : 'stellungnahmen');
    $kind = isset($_POST['eg_source_kind']) ? sanitize_key(wp_unslash($_POST['eg_source_kind'])) : 'link';
    update_post_meta($post_id, 'eg_source_kind', $kind === 'archive' ? 'archive' : 'link');
    $kicker = isset($_POST['eg_kicker']) ? sanitize_key(wp_unslash($_POST['eg_kicker'])) : 'analyse';
    update_post_meta($post_id, 'eg_kicker', isset(eg_an_kickers()[$kicker]) ? $kicker : 'analyse');
    update_post_meta($post_id, 'eg_undated', empty($_POST['eg_undated']) ? '' : '1');
    if ((string) get_post_meta($post_id, 'eg_feed_id', true) === '') {
        $slug = (string) get_post_field('post_name', $post_id);
        if ($slug === '') {
            $slug = sanitize_title((string) get_post_field('post_title', $post_id));
        }
        update_post_meta($post_id, 'eg_feed_id', 'an-' . substr($slug, 0, 58));
    }
    if (taxonomy_exists('eg_format') && !wp_get_object_terms($post_id, 'eg_format', array('fields' => 'ids'))) {
        wp_set_object_terms($post_id, 'stellungnahmen', 'eg_format', false);
    }
}, 30);

/* ---------- data ---------- */

/** Region key and country keys of one analysis. @return array{regions:string[],countries:string[]} */
function eg_an_places($post_id)
{
    $regions = array();
    $countries = array();
    $terms = wp_get_object_terms($post_id, 'eg_region');
    if (is_wp_error($terms)) {
        return array('regions' => array(), 'countries' => array());
    }
    foreach ($terms as $t) {
        $key = (string) get_term_meta($t->term_id, 'eg_key', true);
        if ($t->parent) {
            $parent_key = (string) get_term_meta($t->parent, 'eg_key', true);
            if ($parent_key !== '' && !in_array($parent_key, $regions, true)) {
                $regions[] = $parent_key;
            }
            if ($key !== '' && !in_array($key, $countries, true)) {
                $countries[] = $key;
            }
        } elseif ($key !== '' && !in_array($key, $regions, true)) {
            $regions[] = $key;
        }
    }
    return array('regions' => $regions, 'countries' => $countries);
}

/** Country key => region key, for the Regionen cards. */
function eg_an_country_regions()
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $out = array();
    $terms = get_terms(array('taxonomy' => 'eg_region', 'hide_empty' => false));
    if (is_wp_error($terms)) {
        return $out;
    }
    $keys = array();
    foreach ($terms as $t) {
        $keys[$t->term_id] = (string) get_term_meta($t->term_id, 'eg_key', true);
    }
    foreach ($terms as $t) {
        if ($t->parent && isset($keys[$t->parent])) {
            $out[$keys[$t->term_id]] = $keys[$t->parent];
        }
    }
    $cache = $out;
    return $out;
}

/** All public analyses, newest first, undated ones last. */
function eg_an_items()
{
    $q = new WP_Query(array(
        'post_type' => 'eg_analyse', 'post_status' => 'publish', 'posts_per_page' => 500,
        'orderby' => 'date', 'order' => 'DESC', 'no_found_rows' => true,
    ));
    $gated = eg_an_gated_formats();
    $items = array();
    foreach ($q->posts as $i => $p) {
        $fmts = taxonomy_exists('eg_format') ? wp_get_object_terms($p->ID, 'eg_format', array('fields' => 'slugs')) : array();
        if (!is_wp_error($fmts) && array_intersect($gated, (array) $fmts)) {
            continue;
        }
        $m = static function ($k) use ($p) {
            return (string) get_post_meta($p->ID, $k, true);
        };
        $cat = $m('eg_feed_cat');
        if (!isset(eg_an_cats()[$cat])) {
            $cat = 'stellungnahmen';
        }
        $title = html_entity_decode(get_the_title($p), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $places = eg_an_places($p->ID);
        $items[] = array(
            'pos'         => $i,
            'undated'     => $m('eg_undated') === '1',
            'id'          => $m('eg_feed_id'),
            'cat'         => $cat,
            'badge'       => $m('eg_badge_de') !== '' ? $m('eg_badge_de') : eg_an_cats()[$cat][0],
            'badge_en'    => $m('eg_badge_en') !== '' ? $m('eg_badge_en') : ($m('eg_badge_de') !== '' ? $m('eg_badge_de') : eg_an_cats()[$cat][1]),
            'title'       => $title,
            'title_en'    => $m('eg_title_en'),
            'summary'     => (string) $p->post_excerpt,
            'summary_en'  => $m('eg_excerpt_en'),
            'byline'      => $m('eg_byline'),
            'byline_en'   => $m('eg_byline_en'),
            'note'        => $m('eg_note_de'),
            'note_en'     => $m('eg_note_en'),
            'source_kind' => $m('eg_source_kind') === 'archive' ? 'archive' : 'link',
            'source_url'  => $m('eg_source_url'),
            'kicker'      => isset(eg_an_kickers()[$m('eg_kicker')]) ? $m('eg_kicker') : 'analyse',
            'regions'     => $places['regions'],
            'countries'   => $places['countries'],
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

/** Text pair for the static markup: English falls back to German. */
function eg_an_sp($de, $en)
{
    return function_exists('eg_r_bi') ? eg_r_bi($de, $en) : esc_html($de);
}

function eg_an_row($it)
{
    $id = $it['id'] !== '' ? ' id="' . eg_r_esc($it['id']) . '"' : '';
    $regions = $it['regions'] ? ' data-regions="' . eg_r_esc(implode(' ', $it['regions'])) . '"' : '';
    $note = $it['note'] !== '' ? '<p class="srcrow__by src-note">' . eg_an_sp($it['note'], $it['note_en']) . '</p>' : '';
    if ($it['source_kind'] === 'archive' || !eg_r_safe_url($it['source_url'])) {
        $right = $it['source_kind'] === 'archive' ? '<span class="src-note">' . eg_an_sp('Quelle: Veranstaltungsarchiv', 'Source: Event archive') . '</span>' : '';
    } else {
        $right = '<a class="btn btn--ghost btn--sm ext" href="' . eg_r_esc($it['source_url']) . '" target="_blank" rel="noopener" data-analytics="linkedin_source_click">'
            . eg_an_sp('Originalbeitrag ansehen', 'View original post') . '</a>';
    }
    return '<div class="srcrow"' . $id . ' data-cat="' . eg_r_esc($it['cat']) . '"' . $regions . '><div><div class="srcrow__m"><span class="badge badge--src">' . eg_an_sp($it['badge'], $it['badge_en']) . '</span></div>'
        . '<h3 class="srcrow__t">' . eg_an_sp($it['title'], $it['title_en']) . '</h3>'
        . ($it['summary'] !== '' ? '<p class="srcrow__sum">' . eg_an_sp($it['summary'], $it['summary_en']) . '</p>' : '')
        . ($it['byline'] !== '' ? '<p class="srcrow__by">' . eg_an_sp($it['byline'], $it['byline_en']) . '</p>' : '')
        . $note . '</div><div class="srcrow__r">' . $right . '</div></div>';
}

/** First sentence and the rest (dates like "29. April" and one-letter abbreviations do not end a sentence). */
function eg_an_split($s)
{
    $s = trim((string) $s);
    if (preg_match('/(*UCP)(?<![0-9])(?<!\b[A-Za-zÄÖÜäöü])[.?!](?=\s+[A-ZÄÖÜ„"“(])/u', $s, $m, PREG_OFFSET_CAPTURE)) {
        $end = $m[0][1] + strlen($m[0][0]);
        return array(substr($s, 0, $end), trim(substr($s, $end)));
    }
    return array($s, '');
}

function eg_an_card($it, $region)
{
    $countries = array();
    $map = eg_an_country_regions();
    foreach ($it['countries'] as $c) {
        if (($map[$c] ?? '') === $region) {
            $countries[] = $c;
        }
    }
    list($ex, $more) = eg_an_split($it['summary']);
    list($ex_en, $more_en) = eg_an_split($it['summary_en'] !== '' ? $it['summary_en'] : $it['summary']);
    $k = eg_an_kickers()[$it['kicker']];
    // "Auch in: …" names the other regions this analysis also appears in (page order).
    $short = array(
        'europa' => array('Europa', 'Europe'), 'osteuropa' => array('Osteuropa', 'Eastern Europe'),
        'zentralasien' => array('Zentralasien', 'Central Asia'), 'kaukasus' => array('Kaukasus', 'Caucasus'),
        'ostasien' => array('Ostasien', 'East Asia'), 'suedasien' => array('Südasien', 'South Asia'),
        'naherosten' => array('Naher Osten', 'Middle East'),
    );
    $other_de = array();
    $other_en = array();
    foreach ($short as $key => $names) {
        if ($key !== $region && in_array($key, $it['regions'], true)) {
            $other_de[] = $names[0];
            $other_en[] = $names[1];
        }
    }
    $also = $other_de ? '<p class="small muted" style="margin:2px 0 10px">' . eg_an_sp('Auch in: ' . implode(', ', $other_de), 'Also in: ' . implode(', ', $other_en)) . '</p>' : '';
    $ref = $it['id'];
    return '<article class="card" data-region="' . eg_r_esc($region) . '"' . ($ref !== '' ? ' data-analysis-ref="' . eg_r_esc($ref) . '"' : '')
        . ' data-countries="' . eg_r_esc($countries ? implode(',', $countries) : 'regional') . '">'
        . '<span class="card__k">' . eg_an_sp($k[0], $k[1]) . '</span>'
        . '<h3 class="card__t">' . eg_an_sp($it['title'], $it['title_en']) . '</h3>'
        . '<p class="card__excerpt">' . eg_an_sp($ex, $ex_en) . '</p>'
        . (($more !== '' || $more_en !== '') ? '<details class="card-more"><summary>' . eg_an_sp('Weiterlesen', 'Read more') . '</summary><p>' . eg_an_sp($more, $more_en !== '' ? $more_en : $more) . '</p>' . $also . '</details>' : $also)
        . ($it['byline'] !== '' ? '<p class="card__meta">' . eg_an_sp($it['byline'], $it['byline_en']) . '</p>' : '')
        . '<div class="card__src"><a class="link-more reg-to-analysis" href="analysen.html' . ($ref !== '' ? '#' . eg_r_esc($ref) : '') . '">' . eg_an_sp('Zur Analyse', 'Read in Analysis') . '</a></div></article>';
}

/** Fill analysen.html / regionen.html. Returns the page unchanged when there is nothing to show. */
function eg_an_apply($page, $html)
{
    $items = eg_an_items();
    if (!$items) {
        throw new RuntimeException('no analyses');
    }
    if ($page === 'analysen') {
        $rows = '';
        foreach ($items as $it) {
            $rows .= "\n      " . eg_an_row($it);
        }
        return eg_r_replace($html, '/<!--wp:an-list-->.*?<!--\/wp:an-list-->/s', '<!--wp:an-list-->' . $rows . "\n      <!--/wp:an-list-->");
    }
    if ($page === 'regionen') {
        $by_region = array();
        foreach ($items as $it) {
            foreach ($it['regions'] as $r) {
                $by_region[$r][] = $it;
            }
        }
        $out = preg_replace_callback('/<!--wp:reg-an:([a-z]+)-->.*?<!--\/wp:reg-an:\1-->/s', static function ($m) use ($by_region) {
            $cards = '';
            foreach ($by_region[$m[1]] ?? array() as $it) {
                $cards .= "\n          " . eg_an_card($it, $m[1]);
            }
            return '<!--wp:reg-an:' . $m[1] . '-->' . $cards . "\n          <!--/wp:reg-an:" . $m[1] . '-->';
        }, $html);
        if (!is_string($out)) {
            throw new RuntimeException('regionen replace failed');
        }
        return $out;
    }
    return $html;
}
