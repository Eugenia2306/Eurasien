<?php
/**
 * Plugin Name: EG Event Admin
 * Description: Veranstaltungen CPT editor fields, list columns, brochure seed import.
 * Author: d4rl1ngt0n
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Event type choices (filter chips on the brochure).
 *
 * @return array<string, string>
 */
function eg_event_admin_types()
{
    return array(
        'lecture'     => 'Vortrag / Lecture',
        'panel'       => 'Podiumsdiskussion / Panel',
        'expert'      => 'Fachgespräch / Expert Circle',
        'conference'  => 'Konferenz / Conference',
        'cultural'    => 'Kultur / Cultural',
    );
}

/**
 * Register event meta for REST and forms.
 */
add_action('init', function () {
    $string_metas = array(
        'eg_event_start',
        'eg_event_time_start',
        'eg_event_time_end',
        'eg_event_location',
        'eg_event_location_en',
        'eg_event_title_en',
        'eg_event_type',
        'eg_event_badge_de',
        'eg_event_badge_en',
        'eg_event_speaker',
        'eg_event_speaker_en',
    );
    foreach ($string_metas as $key) {
        register_post_meta(
            'eg_event',
            $key,
            array(
                'type'              => 'string',
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback'     => static function () {
                    return current_user_can('edit_posts');
                },
            )
        );
    }

    register_post_meta(
        'eg_event',
        'eg_event_price',
        array(
            'type'              => 'number',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => static function ($v) {
                return round((float) $v, 2);
            },
            'auth_callback'     => static function () {
                return current_user_can('edit_posts');
            },
        )
    );

    register_post_meta(
        'eg_event',
        'eg_event_bookable',
        array(
            'type'              => 'boolean',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => static function ($v) {
                return (bool) $v;
            },
            'auth_callback'     => static function () {
                return current_user_can('edit_posts');
            },
        )
    );

    /* Longer EN body: allow basic HTML via textarea sanitize. */
    register_post_meta(
        'eg_event',
        'eg_event_body_en',
        array(
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'wp_kses_post',
            'auth_callback'     => static function () {
                return current_user_can('edit_posts');
            },
        )
    );
}, 20);

/**
 * Classic editor for events so date / price fields are obvious (Gutenberg hides meta boxes).
 */
add_filter('use_block_editor_for_post_type', function ($use, $post_type) {
    if ($post_type === 'eg_event') {
        return false;
    }
    return $use;
}, 100, 2);

add_filter('gutenberg_can_edit_post_type', function ($can, $post_type) {
    if ($post_type === 'eg_event') {
        return false;
    }
    return $can;
}, 100, 2);

/**
 * Prefer classic screen; discourage Elementor as the default canvas for events.
 */
add_action('admin_init', function () {
    if (!isset($_GET['post_type']) && empty($_GET['post'])) {
        return;
    }
    $post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
    $type = isset($_GET['post_type']) ? sanitize_key(wp_unslash($_GET['post_type'])) : '';
    if ($post_id) {
        $type = get_post_type($post_id) ?: $type;
    }
    if ($type !== 'eg_event') {
        return;
    }
    /* Soft-disable Elementor editor button takeover for this CPT when possible. */
    add_filter('elementor/editor/show_edit_button', '__return_false', 100);
});

/**
 * Show event fields directly under the title (colleagues cannot miss them).
 */
add_action('edit_form_after_title', function ($post) {
    if (!$post instanceof WP_Post || $post->post_type !== 'eg_event') {
        return;
    }
    echo '<div id="eg-event-details-panel" class="postbox" style="margin-top:12px">';
    echo '<div class="postbox-header"><h2 class="hndle">Event details (date, price, tickets)</h2></div>';
    echo '<div class="inside">';
    eg_event_admin_render_metabox($post);
    echo '</div></div>';
});

/**
 * Also keep a side meta box as a short reminder / duplicate entry point.
 */
add_action('add_meta_boxes', function () {
    remove_meta_box('eg_event_details', 'eg_event', 'normal');
    add_meta_box(
        'eg_event_details_side',
        __('Quick: date & price', 'eurasien-gesellschaft'),
        function ($post) {
            $start = (string) get_post_meta($post->ID, 'eg_event_start', true);
            $price = get_post_meta($post->ID, 'eg_event_price', true);
            $bookable = (bool) get_post_meta($post->ID, 'eg_event_bookable', true);
            echo '<p><strong>Date:</strong> ' . esc_html($start !== '' ? $start : '—') . '</p>';
            echo '<p><strong>Price:</strong> ' . esc_html($price !== '' && $price !== null ? $price . ' €' : '—') . '</p>';
            echo '<p><strong>Bookable:</strong> ' . ($bookable ? 'Yes' : 'No') . '</p>';
            echo '<p class="description">Edit full fields in the panel under the title.</p>';
        },
        'eg_event',
        'side',
        'high'
    );
});

/**
 * @param WP_Post $post Post.
 */
function eg_event_admin_render_metabox($post)
{
    static $rendered = false;
    if ($rendered) {
        return;
    }
    $rendered = true;

    wp_nonce_field('eg_event_admin_save', 'eg_event_admin_nonce');

    $get = static function ($key, $default = '') use ($post) {
        $v = get_post_meta($post->ID, $key, true);
        return $v !== '' && $v !== null ? $v : $default;
    };

    $start = $get('eg_event_start', '');
    $t_start = $get('eg_event_time_start', '19:00');
    $t_end = $get('eg_event_time_end', '21:00');
    $loc = $get('eg_event_location', '');
    $loc_en = $get('eg_event_location_en', '');
    $title_en = $get('eg_event_title_en', '');
    $type = $get('eg_event_type', 'lecture');
    $badge_de = $get('eg_event_badge_de', '');
    $badge_en = $get('eg_event_badge_en', '');
    $price = $get('eg_event_price', '10');
    $bookable = (bool) get_post_meta($post->ID, 'eg_event_bookable', true);
    $speaker = $get('eg_event_speaker', '');
    $speaker_en = $get('eg_event_speaker_en', '');
    $body_en = (string) get_post_meta($post->ID, 'eg_event_body_en', true);

    echo '<style>.eg-ev-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px 16px;max-width:920px}.eg-ev-grid .full{grid-column:1/-1}.eg-ev-grid label{display:block;font-weight:600;margin:0 0 4px}.eg-ev-grid input[type=text],.eg-ev-grid input[type=date],.eg-ev-grid input[type=time],.eg-ev-grid input[type=number],.eg-ev-grid select,.eg-ev-grid textarea{width:100%;max-width:100%}@media(max-width:782px){.eg-ev-grid{grid-template-columns:1fr}}#eg-event-details-panel .inside{padding:12px 16px 16px}</style>';
    echo '<p style="margin-top:0;color:#646970"><strong>Required for the public calendar:</strong> date, location, type. Check <em>Bookable</em> and set a price to show Register + Stripe.</p>';
    echo '<div class="eg-ev-grid">';

    echo '<p><label for="eg_event_start">Date</label>';
    echo '<input type="date" id="eg_event_start" name="eg_event_start" value="' . esc_attr($start) . '" required></p>';

    echo '<p><label for="eg_event_type">Type (filter chip)</label><select id="eg_event_type" name="eg_event_type">';
    foreach (eg_event_admin_types() as $val => $label) {
        echo '<option value="' . esc_attr($val) . '"' . selected($type, $val, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select></p>';

    echo '<p><label for="eg_event_time_start">Start time</label>';
    echo '<input type="time" id="eg_event_time_start" name="eg_event_time_start" value="' . esc_attr($t_start) . '"></p>';

    echo '<p><label for="eg_event_time_end">End time</label>';
    echo '<input type="time" id="eg_event_time_end" name="eg_event_time_end" value="' . esc_attr($t_end) . '"></p>';

    echo '<p class="full"><label for="eg_event_title_en">Title (English, optional)</label>';
    echo '<input type="text" id="eg_event_title_en" name="eg_event_title_en" value="' . esc_attr($title_en) . '" placeholder="Leave empty to use the German title"></p>';

    echo '<p class="full"><label for="eg_event_location">Location (German)</label>';
    echo '<input type="text" id="eg_event_location" name="eg_event_location" value="' . esc_attr($loc) . '"></p>';

    echo '<p class="full"><label for="eg_event_location_en">Location (English, optional)</label>';
    echo '<input type="text" id="eg_event_location_en" name="eg_event_location_en" value="' . esc_attr($loc_en) . '"></p>';

    echo '<p><label for="eg_event_badge_de">Badge (German)</label>';
    echo '<input type="text" id="eg_event_badge_de" name="eg_event_badge_de" value="' . esc_attr($badge_de) . '" placeholder="Vortrag"></p>';

    echo '<p><label for="eg_event_badge_en">Badge (English)</label>';
    echo '<input type="text" id="eg_event_badge_en" name="eg_event_badge_en" value="' . esc_attr($badge_en) . '" placeholder="Lecture"></p>';

    echo '<p class="full"><label for="eg_event_speaker">Speaker / line (German)</label>';
    echo '<input type="text" id="eg_event_speaker" name="eg_event_speaker" value="' . esc_attr($speaker) . '"></p>';

    echo '<p class="full"><label for="eg_event_speaker_en">Speaker / line (English, optional)</label>';
    echo '<input type="text" id="eg_event_speaker_en" name="eg_event_speaker_en" value="' . esc_attr($speaker_en) . '"></p>';

    echo '<p><label for="eg_event_price">Price per ticket (EUR)</label>';
    echo '<input type="number" step="0.01" min="0" id="eg_event_price" name="eg_event_price" value="' . esc_attr((string) $price) . '"></p>';

    echo '<p><label for="eg_event_bookable" style="display:flex;align-items:center;gap:8px;font-weight:600">';
    echo '<input type="checkbox" id="eg_event_bookable" name="eg_event_bookable" value="1"' . checked($bookable, true, false) . '> ';
    echo 'Bookable (show Register + Stripe when date is upcoming)</label></p>';

    echo '<p class="full"><label for="eg_event_body_en">Description (English, optional)</label>';
    echo '<textarea id="eg_event_body_en" name="eg_event_body_en" rows="5">' . esc_textarea($body_en) . '</textarea>';
    echo '<span class="description">German description = main editor content below.</span></p>';

    echo '</div>';
}

/**
 * Save meta box.
 *
 * @param int $post_id Post ID.
 */
add_action('save_post_eg_event', function ($post_id) {
    if (!isset($_POST['eg_event_admin_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['eg_event_admin_nonce'])), 'eg_event_admin_save')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $map = array(
        'eg_event_start'       => 'sanitize_text_field',
        'eg_event_time_start'  => 'sanitize_text_field',
        'eg_event_time_end'    => 'sanitize_text_field',
        'eg_event_location'    => 'sanitize_text_field',
        'eg_event_location_en' => 'sanitize_text_field',
        'eg_event_title_en'    => 'sanitize_text_field',
        'eg_event_type'        => 'sanitize_key',
        'eg_event_badge_de'    => 'sanitize_text_field',
        'eg_event_badge_en'    => 'sanitize_text_field',
        'eg_event_speaker'     => 'sanitize_text_field',
        'eg_event_speaker_en'  => 'sanitize_text_field',
    );
    foreach ($map as $key => $cb) {
        if (!isset($_POST[$key])) {
            continue;
        }
        $val = call_user_func($cb, wp_unslash($_POST[$key]));
        if ($key === 'eg_event_type' && !isset(eg_event_admin_types()[$val])) {
            $val = 'lecture';
        }
        update_post_meta($post_id, $key, $val);
    }

    $price = isset($_POST['eg_event_price']) ? round((float) wp_unslash($_POST['eg_event_price']), 2) : 10;
    update_post_meta($post_id, 'eg_event_price', $price);
    update_post_meta($post_id, 'eg_event_bookable', !empty($_POST['eg_event_bookable']));

    if (isset($_POST['eg_event_body_en'])) {
        update_post_meta($post_id, 'eg_event_body_en', wp_kses_post(wp_unslash($_POST['eg_event_body_en'])));
    }
});

/**
 * Admin list columns.
 */
add_filter('manage_eg_event_posts_columns', function ($cols) {
    $new = array();
    foreach ($cols as $k => $v) {
        $new[$k] = $v;
        if ($k === 'title') {
            $new['eg_date'] = 'Date';
            $new['eg_type'] = 'Type';
            $new['eg_price'] = 'Price';
            $new['eg_bookable'] = 'Bookable';
        }
    }
    return $new;
});

add_action('manage_eg_event_posts_custom_column', function ($col, $post_id) {
    if ($col === 'eg_date') {
        echo esc_html((string) get_post_meta($post_id, 'eg_event_start', true));
    } elseif ($col === 'eg_type') {
        echo esc_html((string) get_post_meta($post_id, 'eg_event_type', true));
    } elseif ($col === 'eg_price') {
        $p = get_post_meta($post_id, 'eg_event_price', true);
        echo $p !== '' ? esc_html($p . ' €') : '—';
    } elseif ($col === 'eg_bookable') {
        echo get_post_meta($post_id, 'eg_event_bookable', true) ? 'Yes' : 'No';
    }
}, 10, 2);

add_filter('manage_edit-eg_event_sortable_columns', function ($cols) {
    $cols['eg_date'] = 'eg_event_start';
    return $cols;
});

add_action('pre_get_posts', function ($q) {
    if (!is_admin() || !$q->is_main_query()) {
        return;
    }
    if ($q->get('orderby') === 'eg_event_start') {
        $q->set('meta_key', 'eg_event_start');
        $q->set('orderby', 'meta_value');
    }
});

/**
 * Seed catalog (brochure cutover).
 *
 * @return array<int, array<string, mixed>>
 */
function eg_event_admin_seed_catalog()
{
    return array(
        array(
            'slug' => 'ev-verschluesselte-chronik-2026',
            'title' => 'Buchgespräch: „Die verschlüsselte Chronik der Menschheitsgeschichte“',
            'title_en' => 'Book discussion: “The Encrypted Chronicle of Human History”',
            'date' => '2026-08-26',
            'type' => 'cultural',
            'badge_de' => 'Buchgespräch',
            'badge_en' => 'Book discussion',
            'location' => 'Preußensaal des Logenhauses, Peter-Lenné-Straße 1, 14195 Berlin',
            'location_en' => 'Preußensaal at the Logenhaus, Peter-Lenné-Straße 1, 14195 Berlin',
            'time_start' => '19:00',
            'time_end' => '21:00',
            'speaker' => 'Alexander Rahr, Autor und Historiker',
            'speaker_en' => 'Alexander Rahr, Author and historian',
            'price' => 10,
            'bookable' => true,
            'content' => '<p>Alexander Rahr beschäftigte sich bereits während seines Geschichtsstudiums in München mit einer Epistel Nostradamus’ an den französischen König Heinrich II. Seine Deutung dieses nahezu 500 Jahre alten Textes hat er nun in Form eines historischen Traktats veröffentlicht.</p><p>Das Buchgespräch richtet sich an Interessierte aus den Bereichen Geschichte, Theologie, Eschatologie und Futurologie.</p>',
            'body_en' => '<p>During his history studies in Munich, Alexander Rahr examined an epistle by Nostradamus addressed to King Henry II of France. He has now published his interpretation as a historical treatise.</p><p>The book discussion is intended for those interested in history, theology, eschatology and futurology.</p>',
        ),
        array(
            'slug' => 'ev-sommerfest-2026',
            'title' => 'Sommerfest 2026',
            'title_en' => 'Summer festival 2026',
            'date' => '2026-06-25',
            'type' => 'panel',
            'badge_de' => 'Vergangen',
            'badge_en' => 'Past',
            'location' => 'Logenhaus, Berlin',
            'location_en' => 'Logenhaus, Berlin',
            'speaker' => 'Podiumsdiskussion & Begegnung',
            'speaker_en' => 'Panel & gathering',
            'price' => 0,
            'bookable' => false,
            'content' => '',
        ),
        array(
            'slug' => 'ev-energie-krise-koennen-wir-2026',
            'title' => 'Energie-Krise: Können wir ohne russisches Öl und Gas auskommen?',
            'title_en' => 'Energy crisis: can we manage without Russian oil and gas?',
            'date' => '2026-05-05',
            'type' => 'lecture',
            'badge_de' => 'Vortrag',
            'badge_en' => 'Lecture',
            'location' => 'Berlin',
            'speaker' => 'Wolfgang J. Hummel',
            'bookable' => false,
        ),
        array(
            'slug' => 'ev-teilnahme-petersburger-dialog-2026',
            'title' => 'Teilnahme am Petersburger Dialog',
            'title_en' => 'Participation in the Petersburg Dialogue',
            'date' => '2026-04-29',
            'type' => 'expert',
            'badge_de' => 'Fachgespräch',
            'badge_en' => 'Expert Circle',
            'location' => 'Moskau',
            'speaker' => 'Nahost-Konflikt & Ukraine',
            'speaker_en' => 'Middle East & Ukraine',
            'bookable' => false,
        ),
        array(
            'slug' => 'ev-ruestungskontrolle-vizeaussenminister-ryabkov-2026',
            'title' => 'Rüstungskontrolle, mit Vizeaußenminister Ryabkov',
            'title_en' => 'Arms control, with Deputy Foreign Minister Ryabkov',
            'date' => '2026-04-16',
            'type' => 'expert',
            'badge_de' => 'Fachgespräch',
            'badge_en' => 'Expert Circle',
            'location' => 'Moskau',
            'speaker' => 'Christoph Polajner',
            'bookable' => false,
        ),
        array(
            'slug' => 'ev-krieg-oder-frieden-2026',
            'title' => '2026: Krieg oder Frieden?',
            'title_en' => '2026: War or peace?',
            'date' => '2026-03-26',
            'type' => 'panel',
            'badge_de' => 'Podiumsdiskussion',
            'badge_en' => 'Panel',
            'location' => 'Berlin',
            'speaker' => 'Rahr, Neu, Polajner, Wipperfürth',
            'bookable' => false,
        ),
        array(
            'slug' => 'ev-veraenderungen-internationalen-ordnung-2026',
            'title' => 'Veränderungen in der internationalen Ordnung',
            'title_en' => 'Changes in the international order',
            'date' => '2026-01-12',
            'type' => 'lecture',
            'badge_de' => 'Vortrag',
            'badge_en' => 'Lecture',
            'location' => 'Berlin',
            'speaker' => 'Christoph Polajner',
            'bookable' => false,
        ),
        array(
            'slug' => 'ev-10th-china-global-think-2025',
            'title' => '10th China Global Think Tank Innovation Forum',
            'date' => '2025-11-20',
            'type' => 'conference',
            'badge_de' => 'Konferenz',
            'badge_en' => 'Conference',
            'location' => 'Peking',
            'location_en' => 'Beijing',
            'speaker' => 'Christoph Polajner',
            'bookable' => false,
        ),
        array(
            'slug' => 'ev-gespraech-prof-theodore-postol-2025',
            'title' => 'Gespräch mit Prof. Theodore Postol',
            'title_en' => 'Conversation with Prof. Theodore Postol',
            'date' => '2025-10-09',
            'type' => 'lecture',
            'badge_de' => 'Vortrag',
            'badge_en' => 'Lecture',
            'location' => 'Berlin',
            'speaker' => 'Nukleare Abschreckung',
            'speaker_en' => 'Nuclear deterrence',
            'bookable' => false,
        ),
        array(
            'slug' => 'ev-passiven-aktiven-abschreckung-russlands-2025',
            'title' => 'Von der passiven zur aktiven Abschreckung: Russlands neue Sicherheits- und Geopolitik',
            'title_en' => 'From passive to active deterrence: Russia’s new security and geopolitics',
            'date' => '2025-09-11',
            'type' => 'lecture',
            'badge_de' => 'Vortrag',
            'badge_en' => 'Lecture',
            'location' => 'Berlin',
            'speaker' => 'S. Fischer, A. Rahr, Dr. A. Neu',
            'bookable' => false,
        ),
        array(
            'slug' => 'ev-fachgespraech-amerikanischen-experten-2025',
            'title' => 'Fachgespräch mit amerikanischen Experten',
            'title_en' => 'Expert circle with US experts',
            'date' => '2025-07-11',
            'type' => 'expert',
            'badge_de' => 'Fachgespräch',
            'badge_en' => 'Expert Circle',
            'location' => 'Berlin',
            'speaker' => 'Ray McGovern, Elizabeth Murray',
            'bookable' => false,
        ),
        array(
            'slug' => 'ev-deutsche-aussenpolitik-eurasien-2025',
            'title' => 'Die deutsche Außenpolitik in Eurasien',
            'title_en' => 'German foreign policy in Eurasia',
            'date' => '2025-07-04',
            'type' => 'lecture',
            'badge_de' => 'Vortrag',
            'badge_en' => 'Lecture',
            'location' => 'Berlin',
            'speaker' => 'Dr. Thomas Falk',
            'bookable' => false,
        ),
        array(
            'slug' => 'ev-erbe-vermaechtnis-bewahren-wofuer-2025',
            'title' => 'Erbe und Vermächtnis bewahren: Wofür Vollmer und Gorbatschow wirklich kämpften',
            'title_en' => 'Preserving heritage and legacy: what Vollmer and Gorbachev really fought for',
            'date' => '2025-03-11',
            'type' => 'panel',
            'badge_de' => 'Podiumsdiskussion',
            'badge_en' => 'Panel',
            'location' => 'Berlin',
            'speaker' => 'P. Erler, A. Rahr u. a.',
            'bookable' => false,
        ),
        array(
            'slug' => 'ev-chancen-frieden-ukraine-trump-2025',
            'title' => 'Chancen für Frieden in der Ukraine: der Trump-Putin-Gipfel',
            'title_en' => 'Opportunities for peace in Ukraine: the Trump-Putin summit',
            'date' => '2025-02-27',
            'type' => 'lecture',
            'badge_de' => 'Vortrag',
            'badge_en' => 'Lecture',
            'location' => 'Berlin',
            'speaker' => 'H. Kujat, Gy. Varga',
            'bookable' => false,
        ),
        array(
            'slug' => 'ev-sieben-gruende-warum-kein-2025',
            'title' => 'Sieben Gründe, warum 2025 kein gutes Jahr für die EU sein könnte',
            'title_en' => 'Seven reasons why 2025 might not be a good year for the EU',
            'date' => '2025-01-14',
            'type' => 'lecture',
            'badge_de' => 'Vortrag',
            'badge_en' => 'Lecture',
            'location' => 'Berlin',
            'speaker' => 'Michael von der Schulenburg',
            'bookable' => false,
        ),
        array(
            'slug' => 'ev-vom-niedergang-des-westens-2024',
            'title' => 'Vom Niedergang des Westens zur Neuerfindung Europas',
            'title_en' => 'From the decline of the West to the reinvention of Europe',
            'date' => '2024-12-06',
            'type' => 'cultural',
            'badge_de' => 'Lesung',
            'badge_en' => 'Reading',
            'location' => 'Köln',
            'location_en' => 'Cologne',
            'speaker' => 'Dr. H. Ritz, Prof. Dr. W. Streeck',
            'bookable' => false,
        ),
        array(
            'slug' => 'ev-european-silk-road-summit-2024',
            'title' => 'European Silk Road Summit',
            'date' => '2024-11-27',
            'type' => 'conference',
            'badge_de' => 'Konferenz',
            'badge_en' => 'Conference',
            'location' => 'Wien',
            'location_en' => 'Vienna',
            'speaker' => 'Konnektivität Europa-Asien',
            'speaker_en' => 'Europe-Asia connectivity',
            'bookable' => false,
        ),
        array(
            'slug' => 'ev-teilnahme-verona-eurasian-economic-2024',
            'title' => 'Teilnahme am Verona Eurasian Economic Forum',
            'title_en' => 'Participation in the Verona Eurasian Economic Forum',
            'date' => '2024-12-05',
            'type' => 'conference',
            'badge_de' => 'Konferenz',
            'badge_en' => 'Conference',
            'location' => 'Ras Al Khaimah, VAE',
            'location_en' => 'Ras Al Khaimah, UAE',
            'speaker' => 'Teilnahme der Eurasien Gesellschaft',
            'speaker_en' => 'Participation by Eurasien Gesellschaft',
            'bookable' => false,
        ),
        array(
            'slug' => 'ev-zeitenwende-eurasien-interessen-deutschlands-2024',
            'title' => 'Zeitenwende in Eurasien: Die Interessen Deutschlands',
            'title_en' => 'A turning point in Eurasia: the interests of Germany',
            'date' => '2024-07-03',
            'type' => 'lecture',
            'badge_de' => 'Vortrag',
            'badge_en' => 'Lecture',
            'location' => 'Berlin',
            'speaker' => 'Vizeadmiral a. D. K.-A. Schönbach',
            'bookable' => false,
        ),
        array(
            'slug' => 'ev-je-taime-moi-non-2024',
            'title' => 'Je t’aime moi non plus: Was ist los mit Frankreich?',
            'title_en' => 'Je t’aime moi non plus: What’s going on with France?',
            'date' => '2024-06-13',
            'type' => 'lecture',
            'badge_de' => 'Vortrag',
            'badge_en' => 'Lecture',
            'location' => 'Berlin',
            'speaker' => 'Prof. Dr. Ulrike Guérot',
            'bookable' => false,
        ),
        array(
            'slug' => 'ev-eurasische-handels-transportkorridore-2024',
            'title' => 'Eurasische Handels- und Transportkorridore',
            'title_en' => 'Eurasian trade and transport corridors',
            'date' => '2024-05-29',
            'type' => 'lecture',
            'badge_de' => 'Vortrag',
            'badge_en' => 'Lecture',
            'location' => 'Hamburg',
            'speaker' => 'Uwe Leuschner',
            'bookable' => false,
        ),
        array(
            'slug' => 'ev-friedensplaene-ukraine-2024',
            'title' => 'Friedenspläne für die Ukraine',
            'title_en' => 'Peace plans for Ukraine',
            'date' => '2024-05-14',
            'type' => 'panel',
            'badge_de' => 'Podiumsdiskussion',
            'badge_en' => 'Panel',
            'location' => 'Berlin',
            'speaker' => 'Dr. A. Neu, C. Polajner, A. Rahr',
            'bookable' => false,
        ),
        array(
            'slug' => 'ev-tanz-dem-vulkan-2023',
            'title' => 'Tanz auf dem Vulkan',
            'title_en' => 'Dancing on the volcano',
            'date' => '2023-10-23',
            'type' => 'lecture',
            'badge_de' => 'Vortrag',
            'badge_en' => 'Lecture',
            'location' => 'Berlin',
            'speaker' => 'Patrik Baab',
            'bookable' => false,
        ),
        array(
            'slug' => 'ev-selbsternannten-republiken-postsowjetischen-raum-2023',
            'title' => 'Die selbsternannten Republiken im postsowjetischen Raum',
            'title_en' => 'The self-proclaimed republics in the post-Soviet space',
            'date' => '2023-10-16',
            'type' => 'panel',
            'badge_de' => 'Podiumsdiskussion',
            'badge_en' => 'Panel',
            'location' => 'Berlin',
            'speaker' => 'Dr. C. Wipperfürth, W. Matzke',
            'bookable' => false,
        ),
        array(
            'slug' => 'ev-search-peaceful-coexistence-eurasia-2023',
            'title' => 'The Search for Peaceful Coexistence in Eurasia',
            'date' => '2023-06-26',
            'type' => 'lecture',
            'badge_de' => 'Vortrag',
            'badge_en' => 'Lecture',
            'location' => 'Berlin',
            'speaker' => 'Prof. Nicolai N. Petro',
            'bookable' => false,
        ),
        array(
            'slug' => 'ev-china-logistik-eurasiens-2023',
            'title' => 'China in der Logistik Eurasiens',
            'title_en' => 'China in the logistics of Eurasia',
            'date' => '2023-05-23',
            'type' => 'lecture',
            'badge_de' => 'Vortrag',
            'badge_en' => 'Lecture',
            'location' => 'Berlin',
            'speaker' => 'Uwe Leuschner',
            'bookable' => false,
        ),
        array(
            'slug' => 'ev-endspiel-europa-2022',
            'title' => 'Hauke Ritz: Endspiel Europa',
            'title_en' => 'Hauke Ritz: Endgame Europe',
            'date' => '2022-11-11',
            'type' => 'cultural',
            'badge_de' => 'Lesung',
            'badge_en' => 'Reading',
            'location' => 'Berlin',
            'speaker' => 'Dr. Hauke Ritz',
            'bookable' => false,
        ),
        array(
            'slug' => 'ev-choices-ukraine-russia-eu-2022',
            'title' => 'Prof. Anatol Lieven: Handlungsoptionen für die Ukraine, Russland und die EU',
            'title_en' => 'Prof. Anatol Lieven: Choices for Ukraine, Russia and the EU',
            'date' => '2022-10-17',
            'type' => 'lecture',
            'badge_de' => 'Vortrag',
            'badge_en' => 'Lecture',
            'location' => 'Berlin',
            'speaker' => 'Prof. Dr. Anatol Lieven',
            'bookable' => false,
        ),
        array(
            'slug' => 'ev-globale-bedeutung-cica-konferenz-2022',
            'title' => 'Globale Bedeutung der CICA-Konferenz',
            'title_en' => 'The global significance of the CICA conference',
            'date' => '2022-10-14',
            'type' => 'conference',
            'badge_de' => 'Konferenz',
            'badge_en' => 'Conference',
            'location' => 'Kasachische Botschaft, Berlin',
            'location_en' => 'Kazakh Embassy, Berlin',
            'speaker' => 'Dr. Dr. Arne Seifert',
            'bookable' => false,
        ),
        array(
            'slug' => 'ev-wladimir-putin-politische-biographie-2022',
            'title' => 'Wladimir Putin: Eine politische Biographie',
            'title_en' => 'Vladimir Putin: A political biography',
            'date' => '2022-02-19',
            'type' => 'cultural',
            'badge_de' => 'Buchvorstellung',
            'badge_en' => 'Book launch',
            'location' => 'Berlin',
            'speaker' => 'Thomas Fasbender',
            'bookable' => false,
        ),
    );
}

/**
 * Insert or update one seeded event by slug.
 *
 * @param array<string,mixed> $row Catalog row.
 * @return int Post ID.
 */
function eg_event_admin_upsert_seed_row(array $row)
{
    $slug = sanitize_title((string) ($row['slug'] ?? ''));
    if ($slug === '') {
        return 0;
    }

    $existing = get_page_by_path($slug, OBJECT, 'eg_event');
    if (!$existing instanceof WP_Post) {
        $trashed = get_posts(
            array(
                'name'           => $slug,
                'post_type'      => 'eg_event',
                'post_status'    => 'trash',
                'posts_per_page' => 1,
            )
        );
        if ($trashed && $trashed[0] instanceof WP_Post) {
            wp_untrash_post((int) $trashed[0]->ID);
            $existing = get_post((int) $trashed[0]->ID);
        }
    }
    $postarr = array(
        'post_type'    => 'eg_event',
        'post_status'  => 'publish',
        'post_title'   => (string) ($row['title'] ?? $slug),
        'post_name'    => $slug,
        'post_content' => (string) ($row['content'] ?? ''),
    );
    if ($existing instanceof WP_Post) {
        $postarr['ID'] = (int) $existing->ID;
        $id = wp_update_post($postarr, true);
    } else {
        $id = wp_insert_post($postarr, true);
    }
    if (is_wp_error($id) || !$id) {
        return 0;
    }
    $id = (int) $id;

    update_post_meta($id, 'eg_event_start', (string) ($row['date'] ?? ''));
    update_post_meta($id, 'eg_event_time_start', (string) ($row['time_start'] ?? '19:00'));
    update_post_meta($id, 'eg_event_time_end', (string) ($row['time_end'] ?? '21:00'));
    update_post_meta($id, 'eg_event_location', (string) ($row['location'] ?? ''));
    update_post_meta($id, 'eg_event_location_en', (string) ($row['location_en'] ?? ''));
    update_post_meta($id, 'eg_event_title_en', (string) ($row['title_en'] ?? ''));
    update_post_meta($id, 'eg_event_type', (string) ($row['type'] ?? 'lecture'));
    update_post_meta($id, 'eg_event_badge_de', (string) ($row['badge_de'] ?? ''));
    update_post_meta($id, 'eg_event_badge_en', (string) ($row['badge_en'] ?? ''));
    update_post_meta($id, 'eg_event_speaker', (string) ($row['speaker'] ?? ''));
    update_post_meta($id, 'eg_event_speaker_en', (string) ($row['speaker_en'] ?? ''));
    update_post_meta($id, 'eg_event_price', isset($row['price']) ? (float) $row['price'] : 0);
    update_post_meta($id, 'eg_event_bookable', !empty($row['bookable']));
    if (!empty($row['body_en'])) {
        update_post_meta($id, 'eg_event_body_en', wp_kses_post((string) $row['body_en']));
    }
    return $id;
}

/**
 * Run brochure seed import.
 *
 * @return array{created:int,updated:int,ids:int[]}
 */
function eg_event_admin_run_seed()
{
    $created = 0;
    $updated = 0;
    $ids = array();
    $keep_slugs = array();
    foreach (eg_event_admin_seed_catalog() as $row) {
        $slug = sanitize_title((string) ($row['slug'] ?? ''));
        $keep_slugs[$slug] = true;
        $existed = (bool) get_page_by_path($slug, OBJECT, 'eg_event');
        $id = eg_event_admin_upsert_seed_row($row);
        if (!$id) {
            continue;
        }
        $ids[] = $id;
        if ($existed) {
            $updated++;
        } else {
            $created++;
        }
    }

    /* Remove older theme-seed posts that are not in the brochure catalog. */
    $trashed = 0;
    $all = get_posts(
        array(
            'post_type'      => 'eg_event',
            'post_status'    => array('publish', 'draft', 'pending'),
            'posts_per_page' => 300,
            'fields'         => 'all',
        )
    );
    foreach ($all as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }
        if (isset($keep_slugs[$post->post_name])) {
            continue;
        }
        wp_trash_post((int) $post->ID);
        $trashed++;
    }

    update_option('eg_events_seeded', time(), false);
    return array(
        'created' => $created,
        'updated' => $updated,
        'trashed' => $trashed,
        'ids'     => $ids,
    );
}

/**
 * Tools submenu: import brochure events.
 */
add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=eg_event',
        'Import brochure events',
        'Import brochure',
        'manage_options',
        'eg-event-seed',
        'eg_event_admin_seed_page'
    );
});

function eg_event_admin_seed_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }
    $result = null;
    if (!empty($_POST['eg_event_seed']) && check_admin_referer('eg_event_seed')) {
        $result = eg_event_admin_run_seed();
    }
    echo '<div class="wrap"><h1>Import brochure events</h1>';
    echo '<p>Copies the existing static Veranstaltungen list into WordPress so colleagues can edit them here. Safe to run again (updates by slug).</p>';
    if (is_array($result)) {
        echo '<div class="notice notice-success"><p>Done. Created: '
            . (int) $result['created'] . ', updated: ' . (int) $result['updated']
            . ', duplicates trashed: ' . (int) ($result['trashed'] ?? 0) . '.</p></div>';
    }
    $seeded = (int) get_option('eg_events_seeded', 0);
    if ($seeded) {
        echo '<p>Last import: ' . esc_html(gmdate('Y-m-d H:i', $seeded)) . ' UTC</p>';
    }
    echo '<form method="post">';
    wp_nonce_field('eg_event_seed');
    echo '<p><button type="submit" class="button button-primary" name="eg_event_seed" value="1">Import / refresh brochure events</button></p>';
    echo '</form></div>';
}

/**
 * Serialize one event for the public JSON feed.
 *
 * @param WP_Post $post Event post.
 * @return array<string,mixed>
 */
function eg_event_admin_to_feed_item(WP_Post $post)
{
    $id = (int) $post->ID;
    $date = (string) get_post_meta($id, 'eg_event_start', true);
    if ($date === '') {
        $date = get_the_date('Y-m-d', $post);
    }
    $today = wp_date('Y-m-d');
    $bookable = (bool) get_post_meta($id, 'eg_event_bookable', true);
    $status = ($date >= $today) ? 'upcoming' : 'past';
    if ($bookable && $status === 'upcoming') {
        /* keep upcoming */
    } elseif ($status === 'upcoming' && !$bookable) {
        /* still upcoming for filters, but no register button */
    }

    $price = get_post_meta($id, 'eg_event_price', true);
    $price = $price === '' || $price === null ? 0 : (float) $price;

    $title_de = get_the_title($post);
    $title_en = (string) get_post_meta($id, 'eg_event_title_en', true);
    if ($title_en === '') {
        $title_en = $title_de;
    }

    $loc = (string) get_post_meta($id, 'eg_event_location', true);
    $loc_en = (string) get_post_meta($id, 'eg_event_location_en', true);
    if ($loc_en === '') {
        $loc_en = $loc;
    }

    $speaker = (string) get_post_meta($id, 'eg_event_speaker', true);
    $speaker_en = (string) get_post_meta($id, 'eg_event_speaker_en', true);
    if ($speaker_en === '') {
        $speaker_en = $speaker;
    }

    $badge_de = (string) get_post_meta($id, 'eg_event_badge_de', true);
    $badge_en = (string) get_post_meta($id, 'eg_event_badge_en', true);
    $type = (string) get_post_meta($id, 'eg_event_type', true);
    if ($type === '') {
        $type = 'lecture';
    }
    if ($badge_de === '') {
        $defaults = array(
            'lecture' => 'Vortrag',
            'panel' => 'Podiumsdiskussion',
            'expert' => 'Fachgespräch',
            'conference' => 'Konferenz',
            'cultural' => 'Kultur',
        );
        $badge_de = $defaults[$type] ?? 'Veranstaltung';
    }
    if ($badge_en === '') {
        $defaults_en = array(
            'lecture' => 'Lecture',
            'panel' => 'Panel',
            'expert' => 'Expert Circle',
            'conference' => 'Conference',
            'cultural' => 'Cultural',
        );
        $badge_en = $defaults_en[$type] ?? 'Event';
    }

    $body_de = apply_filters('the_content', $post->post_content);
    $body_en = (string) get_post_meta($id, 'eg_event_body_en', true);
    if ($body_en !== '') {
        $body_en = wpautop($body_en);
    }

    $slug = $post->post_name ?: ('ev-' . $id);

    return array(
        'id'           => $slug,
        'date'         => $date,
        'status'       => $status,
        'type'         => $type,
        'price'        => $price,
        'bookable'     => $bookable && $status === 'upcoming',
        'time_start'   => (string) get_post_meta($id, 'eg_event_time_start', true) ?: '19:00',
        'time_end'     => (string) get_post_meta($id, 'eg_event_time_end', true) ?: '21:00',
        'location'     => $loc,
        'location_en'  => $loc_en,
        'title'        => $title_de,
        'title_en'     => $title_en,
        'badge'        => $badge_de,
        'badge_en'     => $badge_en,
        'speaker'      => $speaker,
        'speaker_en'   => $speaker_en,
        'body_html'    => $body_de,
        'body_html_en' => $body_en,
    );
}

/**
 * Build full public feed payload.
 *
 * @return array{events: array<int, array<string,mixed>>, generated: string}
 */
function eg_event_admin_feed_payload()
{
    $q = new WP_Query(
        array(
            'post_type'      => 'eg_event',
            'post_status'    => 'publish',
            'posts_per_page' => 200,
            'meta_key'       => 'eg_event_start',
            'orderby'        => 'meta_value',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        )
    );
    $events = array();
    foreach ($q->posts as $post) {
        if ($post instanceof WP_Post) {
            $events[] = eg_event_admin_to_feed_item($post);
        }
    }
    return array(
        'events'    => $events,
        'generated' => gmdate('c'),
    );
}
