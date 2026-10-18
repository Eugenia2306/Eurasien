<?php
/**
 * Plugin Name: EG Editorial Pipeline
 * Description: Material records, human approval, dumb publisher, Analysen public feed. Agents cannot approve or publish.
 * Author: d4rl1ngt0n
 */

if (!defined('ABSPATH')) {
    exit;
}

define('EG_EDITORIAL_STAGES', 'ingest,analyst,reviewer,translator,bilingual_review,preview,blocked,approved,published');
define('EG_EDITORIAL_MAX_LOOPS', 2);
define('EG_EDITORIAL_AGENTS', 'analyst,reviewer,translator,bilingual_review');
define('EG_EDITORIAL_PUBLIC_FORMATS', 'aktuelles,stellungnahmen');
define('EG_EDITORIAL_GATED_FORMATS', 'positionen,dossiers,studien');

/**
 * Register private material CPT and analyse bilingual meta.
 */
add_action('init', function () {
    register_post_type(
        'eg_material',
        array(
            'labels' => array(
                'name'               => 'Materialien',
                'singular_name'      => 'Material',
                'add_new'            => 'LinkedIn-Beitrag einreichen',
                'add_new_item'       => 'LinkedIn-Beitrag einreichen',
                'edit_item'          => 'Material bearbeiten',
                'search_items'       => 'Materialien suchen',
                'not_found'          => 'Kein Material gefunden.',
            ),
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'menu_icon'           => 'dashicons-media-document',
            'menu_position'       => 21,
            'supports'            => array('title'),
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
            'has_archive'         => false,
            'rewrite'             => false,
            'query_var'           => false,
            'show_in_rest'        => false,
        )
    );

    $analyse_strings = array(
        'eg_title_en',
        'eg_excerpt_en',
        'eg_byline',
        'eg_byline_en',
        'eg_badge_de',
        'eg_badge_en',
        'eg_feed_cat',
        'eg_feed_id',
        'eg_published_hash',
        'eg_material_id',
    );
    foreach ($analyse_strings as $key) {
        register_post_meta(
            'eg_analyse',
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
        'eg_analyse',
        'eg_source_url',
        array(
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'esc_url_raw',
            'auth_callback'     => static function () {
                return current_user_can('edit_posts');
            },
        )
    );
    register_post_meta(
        'eg_analyse',
        'eg_body_en',
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

add_action('init', function () {
    if (!taxonomy_exists('eg_format')) {
        return;
    }
    $terms = array(
        'aktuelles'       => 'Aktuelles',
        'stellungnahmen'  => 'Stellungnahmen',
        'positionen'      => 'Positionen',
        'dossiers'        => 'Dossiers',
        'studien'         => 'Studien',
    );
    foreach ($terms as $slug => $name) {
        if (!term_exists($slug, 'eg_format')) {
            wp_insert_term($name, 'eg_format', array('slug' => $slug));
        }
    }
}, 30);

function eg_editorial_empty_record()
{
    return array(
        'original' => array(
            'text'       => '',
            'url'        => '',
            'urn'        => '',
            'author'     => '',
            'date'       => '',
            'is_video'   => false,
            'transcript' => '',
        ),
        'editorial_basis' => array(
            'mission_version' => '',
            'format'          => 'stellungnahmen',
            'language'        => 'de',
            'theses'          => array(),
        ),
        'material' => array(
            'title_de'    => '',
            'title_en'    => '',
            'excerpt_de'  => '',
            'excerpt_en'  => '',
            'body_de'     => '',
            'body_en'     => '',
            'version_de'  => 0,
            'version_en'  => 0,
            'badge_de'    => 'Stellungnahme',
            'badge_en'    => 'Statement',
            'byline_de'   => '',
            'byline_en'   => '',
            'feed_cat'    => 'stellungnahmen',
            'sources'     => array(),
        ),
        'review' => array(
            'claims'   => array(),
            'result'   => '',
            'comments' => array(),
            'loops'    => array(
                'analyst'          => 0,
                'reviewer'         => 0,
                'translator'       => 0,
                'bilingual_review' => 0,
            ),
        ),
        'approval' => null,
        'stage'    => 'ingest',
        'human_question' => '',
        'analyse_id'     => 0,
        'history'        => array(),
    );
}

function eg_editorial_get_record($post_id)
{
    $raw = get_post_meta((int) $post_id, '_eg_editorial_record', true);
    $base = eg_editorial_empty_record();
    if (!is_array($raw) || !$raw) {
        return $base;
    }
    return eg_editorial_merge_record($base, $raw);
}

function eg_editorial_merge_record($base, $raw)
{
    foreach ($raw as $k => $v) {
        if (is_array($v) && isset($base[$k]) && is_array($base[$k]) && eg_editorial_is_assoc($base[$k])) {
            $base[$k] = eg_editorial_merge_record($base[$k], $v);
        } else {
            $base[$k] = $v;
        }
    }
    return $base;
}

function eg_editorial_is_assoc($arr)
{
    if (!is_array($arr) || $arr === array()) {
        return false;
    }
    return array_keys($arr) !== range(0, count($arr) - 1);
}

function eg_editorial_save_record($post_id, array $record)
{
    $post_id = (int) $post_id;
    $hash = eg_editorial_version_hash($record);
    update_post_meta($post_id, '_eg_editorial_record', $record);
    update_post_meta($post_id, '_eg_editorial_stage', (string) $record['stage']);
    update_post_meta($post_id, '_eg_editorial_hash', $hash);
    $urn = (string) ($record['original']['urn'] ?? '');
    if ($urn !== '') {
        update_post_meta($post_id, '_eg_li_urn', $urn);
    }
    $url = (string) ($record['original']['url'] ?? '');
    if ($url !== '') {
        update_post_meta($post_id, '_eg_li_url', esc_url_raw($url));
    }
    return $hash;
}

function eg_editorial_public_fields(array $record)
{
    $sources = array();
    foreach ((array) ($record['material']['sources'] ?? array()) as $src) {
        if (!is_array($src)) {
            continue;
        }
        $sources[] = array(
            'label_de' => (string) ($src['label_de'] ?? ''),
            'label_en' => (string) ($src['label_en'] ?? ''),
            'url'      => (string) ($src['url'] ?? ''),
        );
    }
    return array(
        'title_de'   => (string) ($record['material']['title_de'] ?? ''),
        'title_en'   => (string) ($record['material']['title_en'] ?? ''),
        'excerpt_de' => (string) ($record['material']['excerpt_de'] ?? ''),
        'excerpt_en' => (string) ($record['material']['excerpt_en'] ?? ''),
        'body_de'    => (string) ($record['material']['body_de'] ?? ''),
        'body_en'    => (string) ($record['material']['body_en'] ?? ''),
        'format'     => (string) ($record['editorial_basis']['format'] ?? ''),
        'feed_cat'   => (string) ($record['material']['feed_cat'] ?? ''),
        'badge_de'   => (string) ($record['material']['badge_de'] ?? ''),
        'badge_en'   => (string) ($record['material']['badge_en'] ?? ''),
        'byline_de'  => (string) ($record['material']['byline_de'] ?? ''),
        'byline_en'  => (string) ($record['material']['byline_en'] ?? ''),
        'sources'    => $sources,
        'source_url' => (string) ($record['original']['url'] ?? ''),
    );
}

function eg_editorial_version_hash(array $record)
{
    $fields = eg_editorial_public_fields($record);
    $json = wp_json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return hash('sha256', (string) $json);
}

function eg_editorial_extract_urn($url)
{
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }
    if (preg_match('/urn:li:(?:activity|ugcPost|share):([0-9]+)/i', $url, $m)) {
        return 'urn:li:activity:' . $m[1];
    }
    if (preg_match('/activity-([0-9]{8,})/i', $url, $m)) {
        return 'urn:li:activity:' . $m[1];
    }
    if (preg_match('/\/feed\/update\/([^\/\?\#]+)/i', $url, $m)) {
        $part = rawurldecode($m[1]);
        if (preg_match('/([0-9]{8,})/', $part, $n)) {
            return 'urn:li:activity:' . $n[1];
        }
    }
    return 'url:' . md5(strtolower(untrailingslashit($url)));
}

function eg_editorial_find_by_urn($urn)
{
    $urn = (string) $urn;
    if ($urn === '') {
        return 0;
    }
    $q = new WP_Query(
        array(
            'post_type'      => 'eg_material',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_key'       => '_eg_li_urn',
            'meta_value'     => $urn,
            'no_found_rows'  => true,
        )
    );
    return $q->posts ? (int) $q->posts[0] : 0;
}

function eg_editorial_user_is_agent($user = null)
{
    if (!$user) {
        $user = wp_get_current_user();
    }
    if (!$user || empty($user->ID)) {
        return false;
    }
    if (user_can($user, 'eg_pipeline_agent')) {
        return true;
    }
    return (bool) get_user_meta((int) $user->ID, 'eg_pipeline_agent', true);
}

function eg_editorial_user_is_coordinator($user = null)
{
    if (!$user) {
        $user = wp_get_current_user();
    }
    if (!$user || empty($user->ID)) {
        return false;
    }
    if (eg_editorial_user_is_agent($user)) {
        return false;
    }
    if (user_can($user, 'eg_pipeline_coordinator')) {
        return true;
    }
    return (bool) get_user_meta((int) $user->ID, 'eg_pipeline_coordinator', true);
}

function eg_editorial_can_approve($user = null)
{
    if (!$user) {
        $user = wp_get_current_user();
    }
    if (!$user || empty($user->ID)) {
        return false;
    }
    if (eg_editorial_user_is_agent($user) || eg_editorial_user_is_coordinator($user)) {
        return false;
    }
    return user_can($user, 'publish_posts');
}

function eg_editorial_task_payload($post_id)
{
    $post = get_post((int) $post_id);
    if (!$post || $post->post_type !== 'eg_material') {
        return null;
    }
    $record = eg_editorial_get_record($post->ID);
    $open_comments = array();
    foreach ((array) ($record['review']['comments'] ?? array()) as $c) {
        if (is_array($c) && empty($c['resolved'])) {
            $open_comments[] = $c;
        }
    }
    return array(
        'id'             => (int) $post->ID,
        'title'          => $post->post_title,
        'stage'          => (string) $record['stage'],
        'version_hash'   => eg_editorial_version_hash($record),
        'version_de'     => (int) ($record['material']['version_de'] ?? 0),
        'version_en'     => (int) ($record['material']['version_en'] ?? 0),
        'human_question' => (string) ($record['human_question'] ?? ''),
        'open_comments'  => $open_comments,
        'record'         => $record,
        'analyse_id'     => (int) ($record['analyse_id'] ?? 0),
        'updated'        => $post->post_modified_gmt,
    );
}

function eg_editorial_append_history(array &$record, $actor, $event, $detail = '')
{
    if (!isset($record['history']) || !is_array($record['history'])) {
        $record['history'] = array();
    }
    $record['history'][] = array(
        'at'     => gmdate('c'),
        'actor'  => (string) $actor,
        'event'  => (string) $event,
        'detail' => (string) $detail,
    );
    if (count($record['history']) > 80) {
        $record['history'] = array_slice($record['history'], -80);
    }
}

function eg_editorial_sanitize_sources($sources)
{
    $out = array();
    foreach ((array) $sources as $src) {
        if (!is_array($src)) {
            continue;
        }
        $url = isset($src['url']) ? esc_url_raw((string) $src['url']) : '';
        if ($url === '') {
            continue;
        }
        $out[] = array(
            'label_de' => sanitize_text_field((string) ($src['label_de'] ?? '')),
            'label_en' => sanitize_text_field((string) ($src['label_en'] ?? '')),
            'url'      => $url,
        );
    }
    return $out;
}

function eg_editorial_apply_material_patch(array $record, array $patch, $agent)
{
    $mat = isset($patch['material']) && is_array($patch['material']) ? $patch['material'] : $patch;
    $text_keys = array(
        'title_de', 'title_en', 'excerpt_de', 'excerpt_en',
        'badge_de', 'badge_en', 'byline_de', 'byline_en', 'feed_cat',
    );
    $changed_de = false;
    $changed_en = false;
    foreach ($text_keys as $key) {
        if (!array_key_exists($key, $mat)) {
            continue;
        }
        $val = sanitize_text_field((string) $mat[$key]);
        $old = (string) ($record['material'][$key] ?? '');
        if ($val !== $old) {
            $record['material'][$key] = $val;
            if (substr($key, -3) === '_de' || $key === 'feed_cat') {
                $changed_de = true;
            }
            if (substr($key, -3) === '_en') {
                $changed_en = true;
            }
        }
    }
    foreach (array('body_de', 'body_en') as $key) {
        if (!array_key_exists($key, $mat)) {
            continue;
        }
        $val = wp_kses_post((string) $mat[$key]);
        $old = (string) ($record['material'][$key] ?? '');
        if ($val !== $old) {
            $record['material'][$key] = $val;
            if ($key === 'body_de') {
                $changed_de = true;
            } else {
                $changed_en = true;
            }
        }
    }
    if (isset($mat['sources'])) {
        $urls_before = array();
        foreach ((array) $record['material']['sources'] as $s) {
            $urls_before[] = (string) ($s['url'] ?? '');
        }
        $record['material']['sources'] = eg_editorial_sanitize_sources($mat['sources']);
        $urls_after = array();
        foreach ($record['material']['sources'] as $s) {
            $urls_after[] = (string) ($s['url'] ?? '');
        }
        if ($agent === 'translator' && $urls_before !== $urls_after) {
            $record['material']['sources'] = eg_editorial_sanitize_sources(
                array_map(
                    static function ($s, $i) use ($urls_before) {
                        if (isset($urls_before[$i])) {
                            $s['url'] = $urls_before[$i];
                        }
                        return $s;
                    },
                    $mat['sources'],
                    array_keys((array) $mat['sources'])
                )
            );
        }
    }
    if (isset($patch['editorial_basis']) && is_array($patch['editorial_basis'])) {
        if (isset($patch['editorial_basis']['format'])) {
            $record['editorial_basis']['format'] = sanitize_key((string) $patch['editorial_basis']['format']);
        }
        if (isset($patch['editorial_basis']['theses']) && is_array($patch['editorial_basis']['theses'])) {
            $record['editorial_basis']['theses'] = array_values(
                array_map('sanitize_text_field', $patch['editorial_basis']['theses'])
            );
        }
        if (isset($patch['editorial_basis']['mission_version'])) {
            $record['editorial_basis']['mission_version'] = sanitize_text_field(
                (string) $patch['editorial_basis']['mission_version']
            );
        }
    }
    if ($changed_de) {
        $record['material']['version_de'] = (int) $record['material']['version_de'] + 1;
    }
    if ($changed_en) {
        $record['material']['version_en'] = (int) $record['material']['version_en'] + 1;
    }
    if ($changed_de || $changed_en) {
        $record['approval'] = null;
        if ($record['stage'] === 'approved') {
            $record['stage'] = 'preview';
        }
    }
    return $record;
}

function eg_editorial_advance_after_result(array $record, $agent, $result, $comments, $question)
{
    $pass = in_array($result, array('pass', 'ok', 'accept', 'done'), true);
    $fail = in_array($result, array('fail', 'reject', 'revision'), true);
    $stage = (string) $record['stage'];
    $loops = &$record['review']['loops'];

    if ($comments) {
        foreach ($comments as $c) {
            $record['review']['comments'][] = array(
                'at'       => gmdate('c'),
                'agent'    => $agent,
                'text'     => sanitize_textarea_field((string) (is_array($c) ? ($c['text'] ?? $c) : $c)),
                'resolved' => false,
            );
        }
    }
    $record['review']['result'] = sanitize_key((string) $result);
    $record['human_question'] = '';

    if ($agent === 'analyst') {
        $record['stage'] = 'reviewer';
        return $record;
    }
    if ($agent === 'reviewer') {
        if ($pass) {
            $record['stage'] = 'translator';
            return $record;
        }
        $loops['reviewer'] = (int) $loops['reviewer'] + 1;
        if ($loops['reviewer'] > EG_EDITORIAL_MAX_LOOPS) {
            $record['stage'] = 'blocked';
            $record['human_question'] = $question !== ''
                ? $question
                : 'Reviewer rejected the draft after two correction loops. What should change?';
            return $record;
        }
        $record['stage'] = 'analyst';
        return $record;
    }
    if ($agent === 'translator') {
        $record['stage'] = 'bilingual_review';
        return $record;
    }
    if ($agent === 'bilingual_review') {
        if ($pass) {
            $record['stage'] = 'preview';
            return $record;
        }
        $loops['bilingual_review'] = (int) $loops['bilingual_review'] + 1;
        if ($loops['bilingual_review'] > EG_EDITORIAL_MAX_LOOPS) {
            $record['stage'] = 'blocked';
            $record['human_question'] = $question !== ''
                ? $question
                : 'Bilingual review failed after two loops. Which language or section should be corrected?';
            return $record;
        }
        $record['stage'] = 'translator';
        return $record;
    }

    if ($stage === 'ingest' && $agent === 'analyst') {
        $record['stage'] = 'reviewer';
    }
    return $record;
}

function eg_editorial_ingest($args)
{
    $url = isset($args['url']) ? esc_url_raw((string) $args['url']) : '';
    $text = isset($args['text']) ? sanitize_textarea_field((string) $args['text']) : '';
    $transcript = isset($args['transcript']) ? sanitize_textarea_field((string) $args['transcript']) : '';
    $is_video = !empty($args['is_video']);
    $author = isset($args['author']) ? sanitize_text_field((string) $args['author']) : '';
    $date = isset($args['date']) ? sanitize_text_field((string) $args['date']) : '';
    $format = isset($args['format']) ? sanitize_key((string) $args['format']) : 'stellungnahmen';
    if (!in_array($format, array('aktuelles', 'stellungnahmen', 'veranstaltungen'), true)) {
        $format = 'stellungnahmen';
    }
    if ($url === '') {
        return new WP_Error('eg_editorial_url', 'A LinkedIn post URL is required.', array('status' => 400));
    }
    $urn = eg_editorial_extract_urn($url);
    $existing = eg_editorial_find_by_urn($urn);
    if ($existing) {
        return array(
            'duplicate' => true,
            'id'        => $existing,
            'task'      => eg_editorial_task_payload($existing),
        );
    }
    if ($is_video && $transcript === '') {
        $stage = 'blocked';
        $question = 'This is a video post. Add a transcript before the analyst can start.';
    } elseif ($text === '' && $transcript === '') {
        return new WP_Error(
            'eg_editorial_text',
            'Full post text (or a video transcript) is required. Web search is not a source.',
            array('status' => 400)
        );
    } else {
        $stage = 'analyst';
        $question = '';
    }

    $title = $author !== '' ? $author : 'LinkedIn-Beitrag';
    if ($text !== '') {
        $title = wp_trim_words($text, 8, '…');
    }

    $id = wp_insert_post(
        array(
            'post_type'   => 'eg_material',
            'post_status' => 'publish',
            'post_title'  => $title,
        ),
        true
    );
    if (is_wp_error($id)) {
        return $id;
    }

    $record = eg_editorial_empty_record();
    $record['original'] = array(
        'text'       => $is_video && $transcript !== '' ? $transcript : $text,
        'url'        => $url,
        'urn'        => $urn,
        'author'     => $author,
        'date'       => $date,
        'is_video'   => (bool) $is_video,
        'transcript' => $transcript,
    );
    $record['editorial_basis']['format'] = $format;
    $record['material']['feed_cat'] = $format === 'veranstaltungen' ? 'veranstaltungen' : 'stellungnahmen';
    if ($format === 'aktuelles') {
        $record['material']['badge_de'] = 'Aktuelles';
        $record['material']['badge_en'] = 'Current Affairs';
        $record['material']['feed_cat'] = 'stellungnahmen';
    }
    $record['material']['sources'] = array(
        array(
            'label_de' => 'Originalbeitrag',
            'label_en' => 'Original post',
            'url'      => $url,
        ),
    );
    $record['stage'] = $stage;
    $record['human_question'] = $question;
    eg_editorial_append_history($record, wp_get_current_user()->user_login, 'ingest', $urn);
    eg_editorial_save_record($id, $record);

    return array(
        'duplicate' => false,
        'id'        => (int) $id,
        'task'      => eg_editorial_task_payload($id),
    );
}

function eg_editorial_save_result($args)
{
    $id = isset($args['id']) ? (int) $args['id'] : 0;
    $agent = isset($args['agent']) ? sanitize_key((string) $args['agent']) : '';
    $allowed = explode(',', EG_EDITORIAL_AGENTS);
    if (!in_array($agent, $allowed, true)) {
        return new WP_Error('eg_editorial_agent', 'Unknown agent.', array('status' => 400));
    }
    $post = get_post($id);
    if (!$post || $post->post_type !== 'eg_material') {
        return new WP_Error('eg_editorial_missing', 'Material not found.', array('status' => 404));
    }
    $record = eg_editorial_get_record($id);
    $record = eg_editorial_apply_material_patch($record, isset($args['payload']) && is_array($args['payload']) ? $args['payload'] : array(), $agent);
    $result = isset($args['result']) ? sanitize_key((string) $args['result']) : 'done';
    $comments = isset($args['comments']) ? (array) $args['comments'] : array();
    $question = isset($args['human_question']) ? sanitize_textarea_field((string) $args['human_question']) : '';
    $record = eg_editorial_advance_after_result($record, $agent, $result, $comments, $question);
    eg_editorial_append_history($record, $agent, 'save_result', $result . ' -> ' . $record['stage']);
    $hash = eg_editorial_save_record($id, $record);

    $title_de = (string) ($record['material']['title_de'] ?? '');
    if ($title_de !== '') {
        wp_update_post(
            array(
                'ID'         => $id,
                'post_title' => wp_trim_words($title_de, 12, ''),
            )
        );
    }

    return array(
        'id'           => $id,
        'stage'        => $record['stage'],
        'version_hash' => $hash,
        'task'         => eg_editorial_task_payload($id),
    );
}

function eg_editorial_approve($id, $section = 'analysen')
{
    if (!eg_editorial_can_approve()) {
        return new WP_Error('eg_editorial_forbidden', 'Only a human editor can approve.', array('status' => 403));
    }
    $post = get_post((int) $id);
    if (!$post || $post->post_type !== 'eg_material') {
        return new WP_Error('eg_editorial_missing', 'Material not found.', array('status' => 404));
    }
    $record = eg_editorial_get_record($id);
    if (!in_array($record['stage'], array('preview', 'approved'), true)) {
        return new WP_Error(
            'eg_editorial_stage',
            'Approval is only possible in preview (current stage: ' . $record['stage'] . ').',
            array('status' => 409)
        );
    }
    $title_de = trim((string) $record['material']['title_de']);
    $title_en = trim((string) $record['material']['title_en']);
    $excerpt_de = trim((string) $record['material']['excerpt_de']);
    $excerpt_en = trim((string) $record['material']['excerpt_en']);
    if ($title_de === '' || $title_en === '' || $excerpt_de === '' || $excerpt_en === '') {
        return new WP_Error(
            'eg_editorial_incomplete',
            'DE and EN title and description are required before approval.',
            array('status' => 409)
        );
    }
    $hash = eg_editorial_version_hash($record);
    $user = wp_get_current_user();
    $record['approval'] = array(
        'user_id'     => (int) $user->ID,
        'user_login'  => (string) $user->user_login,
        'timestamp'   => gmdate('c'),
        'hash'        => $hash,
        'section'     => sanitize_key($section),
        'version_de'  => (int) $record['material']['version_de'],
        'version_en'  => (int) $record['material']['version_en'],
    );
    $record['stage'] = 'approved';
    $record['human_question'] = '';
    eg_editorial_append_history($record, $user->user_login, 'approve', $hash);
    eg_editorial_save_record($id, $record);
    return array(
        'id'           => (int) $id,
        'approved'     => true,
        'version_hash' => $hash,
        'task'         => eg_editorial_task_payload($id),
    );
}

function eg_editorial_publish($id)
{
    if (!eg_editorial_can_approve()) {
        return new WP_Error('eg_editorial_forbidden', 'Only a human editor can publish.', array('status' => 403));
    }
    $id = (int) $id;
    $post = get_post($id);
    if (!$post || $post->post_type !== 'eg_material') {
        return new WP_Error('eg_editorial_missing', 'Material not found.', array('status' => 404));
    }
    $record = eg_editorial_get_record($id);
    $hash = eg_editorial_version_hash($record);
    $approval = $record['approval'];
    if (!is_array($approval) || empty($approval['hash'])) {
        return new WP_Error('eg_editorial_no_approval', 'No human approval on this version.', array('status' => 409));
    }
    if (!hash_equals((string) $approval['hash'], $hash)) {
        return new WP_Error(
            'eg_editorial_stale',
            'The text changed after approval. A new approval is required.',
            array('status' => 409)
        );
    }
    $review_result = (string) ($record['review']['result'] ?? '');
    if ($review_result === 'fail') {
        return new WP_Error('eg_editorial_review', 'A failed review cannot be published.', array('status' => 409));
    }

    $fields = eg_editorial_public_fields($record);
    $format = $fields['format'] !== '' ? $fields['format'] : 'stellungnahmen';
    $existing_id = (int) ($record['analyse_id'] ?? 0);
    $analyse = $existing_id ? get_post($existing_id) : null;
    if ($analyse && $analyse->post_type !== 'eg_analyse') {
        $analyse = null;
        $existing_id = 0;
    }

    $postarr = array(
        'post_type'    => 'eg_analyse',
        'post_status'  => 'publish',
        'post_title'   => $fields['title_de'],
        'post_excerpt' => $fields['excerpt_de'],
        'post_content' => $fields['body_de'] !== '' ? $fields['body_de'] : wpautop($fields['excerpt_de']),
    );
    if ($existing_id) {
        $postarr['ID'] = $existing_id;
        $analyse_id = wp_update_post($postarr, true);
    } else {
        $slug = sanitize_title($fields['title_de']);
        if (strpos($slug, 'an-') !== 0) {
            $slug = 'an-' . $slug;
        }
        $postarr['post_name'] = $slug;
        if ($record['original']['date']) {
            $postarr['post_date'] = $record['original']['date'] . ' 12:00:00';
        }
        $analyse_id = wp_insert_post($postarr, true);
    }
    if (is_wp_error($analyse_id)) {
        return $analyse_id;
    }

    update_post_meta($analyse_id, 'eg_title_en', $fields['title_en']);
    update_post_meta($analyse_id, 'eg_excerpt_en', $fields['excerpt_en']);
    update_post_meta($analyse_id, 'eg_body_en', $fields['body_en']);
    update_post_meta($analyse_id, 'eg_source_url', $fields['source_url']);
    update_post_meta($analyse_id, 'eg_byline', $fields['byline_de']);
    update_post_meta($analyse_id, 'eg_byline_en', $fields['byline_en']);
    update_post_meta($analyse_id, 'eg_badge_de', $fields['badge_de']);
    update_post_meta($analyse_id, 'eg_badge_en', $fields['badge_en']);
    update_post_meta($analyse_id, 'eg_feed_cat', $fields['feed_cat'] !== '' ? $fields['feed_cat'] : 'stellungnahmen');
    update_post_meta($analyse_id, 'eg_published_hash', $hash);
    update_post_meta($analyse_id, 'eg_material_id', (string) $id);
    $feed_id = 'an-' . get_post_field('post_name', $analyse_id);
    $feed_id = preg_replace('/^an-an-/', 'an-', $feed_id);
    update_post_meta($analyse_id, 'eg_feed_id', $feed_id);

    if (taxonomy_exists('eg_format')) {
        wp_set_object_terms($analyse_id, array($format), 'eg_format', false);
    }

    $record['analyse_id'] = (int) $analyse_id;
    $record['stage'] = 'published';
    eg_editorial_append_history($record, wp_get_current_user()->user_login, 'publish', (string) $analyse_id);
    eg_editorial_save_record($id, $record);

    $item = eg_editorial_analyse_to_feed_item(get_post($analyse_id));
    $brochure = function_exists('eg_brochure_public_url')
        ? eg_brochure_public_url('analysen.html') . '#' . $feed_id
        : '/analysen.html#' . $feed_id;

    return array(
        'id'            => $id,
        'analyse_id'    => (int) $analyse_id,
        'feed_id'       => $feed_id,
        'version_hash'  => $hash,
        'permalink'     => $brochure,
        'feed_item'     => $item,
        'task'          => eg_editorial_task_payload($id),
    );
}

function eg_editorial_format_date_pair($ymd)
{
    $ymd = substr((string) $ymd, 0, 10);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m)) {
        return array('de' => '', 'en' => '');
    }
    $months_de = array(
        1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni',
        7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
    );
    $months_en = array(
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
        7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    );
    $d = (int) $m[3];
    $mo = (int) $m[2];
    $y = $m[1];
    return array(
        'de' => $d . '. ' . $months_de[$mo] . ' ' . $y,
        'en' => $d . ' ' . $months_en[$mo] . ' ' . $y,
    );
}

function eg_editorial_analyse_format_slug($post_id)
{
    $terms = get_the_terms((int) $post_id, 'eg_format');
    if ($terms && !is_wp_error($terms)) {
        return sanitize_title($terms[0]->slug);
    }
    return '';
}

function eg_editorial_analyse_is_gated($post_id)
{
    $slug = eg_editorial_analyse_format_slug($post_id);
    return in_array($slug, explode(',', EG_EDITORIAL_GATED_FORMATS), true);
}

function eg_editorial_analyse_to_feed_item($post)
{
    if (!$post instanceof WP_Post) {
        return null;
    }
    $id = (int) $post->ID;
    $format = eg_editorial_analyse_format_slug($id);
    if (in_array($format, explode(',', EG_EDITORIAL_GATED_FORMATS), true)) {
        return null;
    }
    $cat = (string) get_post_meta($id, 'eg_feed_cat', true);
    if ($cat === '') {
        $cat = $format === 'veranstaltungen' ? 'veranstaltungen' : 'stellungnahmen';
    }
    $title_de = $post->post_title;
    $title_en = (string) get_post_meta($id, 'eg_title_en', true);
    $excerpt_de = $post->post_excerpt !== '' ? $post->post_excerpt : wp_trim_words(wp_strip_all_tags($post->post_content), 55, '');
    $excerpt_en = (string) get_post_meta($id, 'eg_excerpt_en', true);
    $badge_de = (string) get_post_meta($id, 'eg_badge_de', true);
    $badge_en = (string) get_post_meta($id, 'eg_badge_en', true);
    if ($badge_de === '') {
        $badge_de = $format === 'aktuelles' ? 'Aktuelles' : 'Stellungnahme';
        $badge_en = $format === 'aktuelles' ? 'Current Affairs' : 'Statement';
    }
    $dates = eg_editorial_format_date_pair(get_the_date('Y-m-d', $post));
    $by_de = (string) get_post_meta($id, 'eg_byline', true);
    $by_en = (string) get_post_meta($id, 'eg_byline_en', true);
    if ($by_de === '') {
        $by_de = $badge_de . ' der Eurasien Gesellschaft' . ($dates['de'] !== '' ? ', ' . $dates['de'] : '');
        $by_en = $badge_en . ' by Eurasien Gesellschaft' . ($dates['en'] !== '' ? ', ' . $dates['en'] : '');
    }
    $feed_id = (string) get_post_meta($id, 'eg_feed_id', true);
    if ($feed_id === '') {
        $feed_id = 'an-' . $post->post_name;
        $feed_id = preg_replace('/^an-an-/', 'an-', $feed_id);
    }
    $source = (string) get_post_meta($id, 'eg_source_url', true);

    return array(
        'id'         => $feed_id,
        'wp_id'      => $id,
        'cat'        => $cat,
        'date'       => get_the_date('Y-m-d', $post),
        'badge'      => $badge_de,
        'badge_en'   => $badge_en !== '' ? $badge_en : $badge_de,
        'title'      => $title_de,
        'title_en'   => $title_en !== '' ? $title_en : $title_de,
        'summary'    => $excerpt_de,
        'summary_en' => $excerpt_en !== '' ? $excerpt_en : $excerpt_de,
        'byline'     => $by_de,
        'byline_en'  => $by_en !== '' ? $by_en : $by_de,
        'source_url' => $source,
        'format'     => $format !== '' ? $format : 'stellungnahmen',
    );
}

function eg_editorial_feed_payload()
{
    $q = new WP_Query(
        array(
            'post_type'      => 'eg_analyse',
            'post_status'    => 'publish',
            'posts_per_page' => 200,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        )
    );
    $items = array();
    foreach ($q->posts as $post) {
        $item = eg_editorial_analyse_to_feed_item($post);
        if ($item) {
            $items[] = $item;
        }
    }
    return array(
        'generated' => gmdate('c'),
        'items'     => $items,
    );
}

add_action('rest_api_init', function () {
    $ns = 'eg-editorial/v1';
    register_rest_route($ns, '/ingest', array(
        'methods'             => 'POST',
        'permission_callback' => static function () {
            return current_user_can('edit_posts') && !eg_editorial_user_is_agent();
        },
        'callback'            => static function (WP_REST_Request $req) {
            $out = eg_editorial_ingest($req->get_json_params() ?: $req->get_params());
            if (is_wp_error($out)) {
                return $out;
            }
            return rest_ensure_response($out);
        },
    ));
    register_rest_route($ns, '/task/(?P<id>\d+)', array(
        'methods'             => 'GET',
        'permission_callback' => static function () {
            return current_user_can('edit_posts');
        },
        'callback'            => static function (WP_REST_Request $req) {
            $task = eg_editorial_task_payload((int) $req['id']);
            if (!$task) {
                return new WP_Error('eg_editorial_missing', 'Material not found.', array('status' => 404));
            }
            return rest_ensure_response($task);
        },
    ));
    register_rest_route($ns, '/result', array(
        'methods'             => 'POST',
        'permission_callback' => static function () {
            return current_user_can('edit_posts');
        },
        'callback'            => static function (WP_REST_Request $req) {
            $out = eg_editorial_save_result($req->get_json_params() ?: $req->get_params());
            if (is_wp_error($out)) {
                return $out;
            }
            return rest_ensure_response($out);
        },
    ));
    register_rest_route($ns, '/approve', array(
        'methods'             => 'POST',
        'permission_callback' => static function () {
            return eg_editorial_can_approve();
        },
        'callback'            => static function (WP_REST_Request $req) {
            $params = $req->get_json_params() ?: $req->get_params();
            $out = eg_editorial_approve(isset($params['id']) ? $params['id'] : 0, isset($params['section']) ? $params['section'] : 'analysen');
            if (is_wp_error($out)) {
                return $out;
            }
            if (!empty($params['publish'])) {
                $pub = eg_editorial_publish((int) $params['id']);
                if (is_wp_error($pub)) {
                    return $pub;
                }
                $out['published'] = $pub;
            }
            return rest_ensure_response($out);
        },
    ));
    register_rest_route($ns, '/publish', array(
        'methods'             => 'POST',
        'permission_callback' => static function () {
            return eg_editorial_can_approve();
        },
        'callback'            => static function (WP_REST_Request $req) {
            $params = $req->get_json_params() ?: $req->get_params();
            $out = eg_editorial_publish(isset($params['id']) ? $params['id'] : 0);
            if (is_wp_error($out)) {
                return $out;
            }
            return rest_ensure_response($out);
        },
    ));
    register_rest_route($ns, '/queue', array(
        'methods'             => 'GET',
        'permission_callback' => static function () {
            return current_user_can('edit_posts');
        },
        'callback'            => static function (WP_REST_Request $req) {
            $stage = sanitize_key((string) $req->get_param('stage'));
            $meta = array();
            if ($stage !== '') {
                $meta = array(
                    array(
                        'key'   => '_eg_editorial_stage',
                        'value' => $stage,
                    ),
                );
            }
            $q = new WP_Query(
                array(
                    'post_type'      => 'eg_material',
                    'post_status'    => 'publish',
                    'posts_per_page' => 50,
                    'orderby'        => 'modified',
                    'order'          => 'DESC',
                    'meta_query'     => $meta,
                    'fields'         => 'ids',
                )
            );
            $items = array();
            foreach ($q->posts as $mid) {
                $items[] = eg_editorial_task_payload((int) $mid);
            }
            return rest_ensure_response(array('items' => $items));
        },
    ));
});

add_action('add_meta_boxes', function () {
    add_meta_box(
        'eg_editorial_ingest',
        'LinkedIn source',
        'eg_editorial_render_ingest_box',
        'eg_material',
        'normal',
        'high'
    );
    add_meta_box(
        'eg_editorial_public',
        'Public card (DE / EN)',
        'eg_editorial_render_public_box',
        'eg_material',
        'normal',
        'high'
    );
    add_meta_box(
        'eg_editorial_status',
        'Pipeline status',
        'eg_editorial_render_status_box',
        'eg_material',
        'side',
        'high'
    );
    add_meta_box(
        'eg_analyse_brochure',
        'Brochure card (DE / EN)',
        'eg_editorial_render_analyse_box',
        'eg_analyse',
        'normal',
        'high'
    );
});

function eg_editorial_render_ingest_box($post)
{
    wp_nonce_field('eg_editorial_save', 'eg_editorial_nonce');
    $r = eg_editorial_get_record($post->ID);
    $o = $r['original'];
    echo '<p><label for="eg_li_url"><strong>LinkedIn post URL</strong></label><br>';
    echo '<input type="url" class="widefat" id="eg_li_url" name="eg_li_url" value="' . esc_attr($o['url']) . '" placeholder="https://www.linkedin.com/feed/update/urn:li:activity:…" required></p>';
    echo '<p><label for="eg_li_author">Author line</label><br>';
    echo '<input type="text" class="widefat" id="eg_li_author" name="eg_li_author" value="' . esc_attr($o['author']) . '" placeholder="Eurasien Gesellschaft / Alexander Rahr"></p>';
    echo '<p><label for="eg_li_date">Post date (YYYY-MM-DD)</label><br>';
    echo '<input type="date" id="eg_li_date" name="eg_li_date" value="' . esc_attr($o['date']) . '"></p>';
    echo '<p><label for="eg_li_format">Format</label><br>';
    echo '<select id="eg_li_format" name="eg_li_format">';
    foreach (array('stellungnahmen' => 'Stellungnahmen', 'aktuelles' => 'Aktuelles', 'veranstaltungen' => 'Aus Veranstaltung') as $k => $lab) {
        echo '<option value="' . esc_attr($k) . '"' . selected($r['editorial_basis']['format'], $k, false) . '>' . esc_html($lab) . '</option>';
    }
    echo '</select></p>';
    echo '<p><label><input type="checkbox" name="eg_li_is_video" value="1"' . checked(!empty($o['is_video']), true, false) . '> This post is a video</label></p>';
    echo '<p><label for="eg_li_text"><strong>Full post text</strong> (paste from LinkedIn; do not summarise from search)</label><br>';
    echo '<textarea class="widefat" rows="10" id="eg_li_text" name="eg_li_text">' . esc_textarea($o['text']) . '</textarea></p>';
    echo '<p><label for="eg_li_transcript">Video transcript (required for video posts)</label><br>';
    echo '<textarea class="widefat" rows="6" id="eg_li_transcript" name="eg_li_transcript">' . esc_textarea($o['transcript']) . '</textarea></p>';
}

function eg_editorial_render_public_box($post)
{
    $r = eg_editorial_get_record($post->ID);
    $m = $r['material'];
    echo '<p class="description">Fill these when a human (or the agents) have a finished bilingual card. Editors can then mark the item ready and approve it without n8n.</p>';
    echo '<p><label>Title DE<br><input type="text" class="widefat" name="eg_mat_title_de" value="' . esc_attr($m['title_de']) . '"></label></p>';
    echo '<p><label>Title EN<br><input type="text" class="widefat" name="eg_mat_title_en" value="' . esc_attr($m['title_en']) . '"></label></p>';
    echo '<p><label>Short description DE<br><textarea class="widefat" rows="4" name="eg_mat_excerpt_de">' . esc_textarea($m['excerpt_de']) . '</textarea></label></p>';
    echo '<p><label>Short description EN<br><textarea class="widefat" rows="4" name="eg_mat_excerpt_en">' . esc_textarea($m['excerpt_en']) . '</textarea></label></p>';
    echo '<p><label>Byline DE<br><input type="text" class="widefat" name="eg_mat_byline_de" value="' . esc_attr($m['byline_de']) . '"></label></p>';
    echo '<p><label>Byline EN<br><input type="text" class="widefat" name="eg_mat_byline_en" value="' . esc_attr($m['byline_en']) . '"></label></p>';
    if (eg_editorial_can_approve()) {
        echo '<p><label><input type="checkbox" name="eg_editorial_to_preview" value="1"> Mark as ready for approval (skip waiting for agents)</label></p>';
    }
}

function eg_editorial_render_status_box($post)
{
    $r = eg_editorial_get_record($post->ID);
    $hash = eg_editorial_version_hash($r);
    echo '<p><strong>Stage:</strong> ' . esc_html($r['stage']) . '</p>';
    echo '<p><strong>URN:</strong><br><code style="word-break:break-all">' . esc_html((string) $r['original']['urn']) . '</code></p>';
    echo '<p><strong>Version hash:</strong><br><code style="word-break:break-all">' . esc_html(substr($hash, 0, 16)) . '…</code></p>';
    echo '<p>DE v' . (int) $r['material']['version_de'] . ' / EN v' . (int) $r['material']['version_en'] . '</p>';
    if (!empty($r['human_question'])) {
        echo '<p style="color:#8a1f11"><strong>Needs a human:</strong> ' . esc_html($r['human_question']) . '</p>';
    }
    if (is_array($r['approval'])) {
        echo '<p>Approved by ' . esc_html($r['approval']['user_login']) . '<br>' . esc_html($r['approval']['timestamp']) . '</p>';
    }
    if (!empty($r['analyse_id'])) {
        echo '<p><a href="' . esc_url(get_edit_post_link((int) $r['analyse_id'])) . '">Open published Analyse</a></p>';
    }
    if (eg_editorial_can_approve() && in_array($r['stage'], array('preview', 'approved'), true)) {
        echo '<p><label><input type="checkbox" name="eg_editorial_do_approve" value="1"> Approve this DE+EN version</label></p>';
        echo '<p><label><input type="checkbox" name="eg_editorial_do_publish" value="1"> Publish to Analysen after approval</label></p>';
    } elseif (eg_editorial_user_is_agent()) {
        echo '<p>This account is a pipeline agent. It cannot approve or publish.</p>';
    }
}

function eg_editorial_render_analyse_box($post)
{
    wp_nonce_field('eg_analyse_brochure_save', 'eg_analyse_brochure_nonce');
    $get = static function ($key) use ($post) {
        return (string) get_post_meta($post->ID, $key, true);
    };
    echo '<p><label>English title<br><input type="text" class="widefat" name="eg_title_en" value="' . esc_attr($get('eg_title_en')) . '"></label></p>';
    echo '<p><label>English short description<br><textarea class="widefat" rows="4" name="eg_excerpt_en">' . esc_textarea($get('eg_excerpt_en')) . '</textarea></label></p>';
    echo '<p><label>English body (optional)<br><textarea class="widefat" rows="6" name="eg_body_en">' . esc_textarea($get('eg_body_en')) . '</textarea></label></p>';
    echo '<p><label>Source URL (LinkedIn)<br><input type="url" class="widefat" name="eg_source_url" value="' . esc_attr($get('eg_source_url')) . '"></label></p>';
    echo '<p><label>Byline DE<br><input type="text" class="widefat" name="eg_byline" value="' . esc_attr($get('eg_byline')) . '"></label></p>';
    echo '<p><label>Byline EN<br><input type="text" class="widefat" name="eg_byline_en" value="' . esc_attr($get('eg_byline_en')) . '"></label></p>';
    echo '<p><label>Badge DE<br><input type="text" name="eg_badge_de" value="' . esc_attr($get('eg_badge_de')) . '"></label> ';
    echo '<label>Badge EN<br><input type="text" name="eg_badge_en" value="' . esc_attr($get('eg_badge_en')) . '"></label></p>';
    echo '<p><label>Feed category </label>';
    $cat = $get('eg_feed_cat');
    echo '<select name="eg_feed_cat">';
    foreach (array('stellungnahmen' => 'Stellungnahmen', 'veranstaltungen' => 'Veranstaltungen', 'gesellschaftsnachrichten' => 'Gesellschaftsnachrichten', 'mediathek' => 'Mediathek') as $k => $lab) {
        echo '<option value="' . esc_attr($k) . '"' . selected($cat, $k, false) . '>' . esc_html($lab) . '</option>';
    }
    echo '</select></p>';
}

add_action('save_post_eg_material', function ($post_id, $post) {
    if (!isset($_POST['eg_editorial_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['eg_editorial_nonce'])), 'eg_editorial_save')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    if (eg_editorial_user_is_agent()) {
        return;
    }

    $record = eg_editorial_get_record($post_id);
    $url = isset($_POST['eg_li_url']) ? esc_url_raw(wp_unslash($_POST['eg_li_url'])) : '';
    $text = isset($_POST['eg_li_text']) ? sanitize_textarea_field(wp_unslash($_POST['eg_li_text'])) : '';
    $transcript = isset($_POST['eg_li_transcript']) ? sanitize_textarea_field(wp_unslash($_POST['eg_li_transcript'])) : '';
    $author = isset($_POST['eg_li_author']) ? sanitize_text_field(wp_unslash($_POST['eg_li_author'])) : '';
    $date = isset($_POST['eg_li_date']) ? sanitize_text_field(wp_unslash($_POST['eg_li_date'])) : '';
    $format = isset($_POST['eg_li_format']) ? sanitize_key(wp_unslash($_POST['eg_li_format'])) : 'stellungnahmen';
    $is_video = !empty($_POST['eg_li_is_video']);

    $urn = $url !== '' ? eg_editorial_extract_urn($url) : '';
    if ($urn !== '') {
        $dup = eg_editorial_find_by_urn($urn);
        if ($dup && $dup !== (int) $post_id) {
            add_filter('redirect_post_location', static function ($loc) use ($dup) {
                return add_query_arg('eg_editorial_dup', $dup, $loc);
            });
            return;
        }
    }

    $record['original']['url'] = $url;
    $record['original']['urn'] = $urn;
    $record['original']['text'] = $text;
    $record['original']['transcript'] = $transcript;
    $record['original']['author'] = $author;
    $record['original']['date'] = $date;
    $record['original']['is_video'] = $is_video;
    $record['editorial_basis']['format'] = $format;
    $record['material']['feed_cat'] = $format === 'veranstaltungen' ? 'veranstaltungen' : 'stellungnahmen';
    if ($url !== '' && empty($record['material']['sources'])) {
        $record['material']['sources'] = array(
            array('label_de' => 'Originalbeitrag', 'label_en' => 'Original post', 'url' => $url),
        );
    }

    $text_map = array(
        'eg_mat_title_de'    => 'title_de',
        'eg_mat_title_en'    => 'title_en',
        'eg_mat_excerpt_de'  => 'excerpt_de',
        'eg_mat_excerpt_en'  => 'excerpt_en',
        'eg_mat_byline_de'   => 'byline_de',
        'eg_mat_byline_en'   => 'byline_en',
    );
    foreach ($text_map as $post_key => $mat_key) {
        if (isset($_POST[$post_key])) {
            $record['material'][$mat_key] = sanitize_textarea_field(wp_unslash($_POST[$post_key]));
        }
    }

    if ($is_video && $transcript === '') {
        $record['stage'] = 'blocked';
        $record['human_question'] = 'This is a video post. Add a transcript before the analyst can start.';
    } elseif ($record['stage'] === 'ingest' && ($text !== '' || $transcript !== '')) {
        $record['stage'] = 'analyst';
        $record['human_question'] = '';
    } elseif ($record['stage'] === 'blocked' && $is_video && $transcript !== '') {
        $record['stage'] = 'analyst';
        $record['human_question'] = '';
    }

    if (!empty($_POST['eg_editorial_to_preview']) && eg_editorial_can_approve()) {
        $record['stage'] = 'preview';
        $record['human_question'] = '';
        eg_editorial_append_history($record, wp_get_current_user()->user_login, 'to_preview', 'editor skip');
    }

    if (!empty($_POST['eg_editorial_do_approve'])) {
        $tmp_hash = eg_editorial_save_record($post_id, $record);
        $ap = eg_editorial_approve($post_id, 'analysen');
        if (!is_wp_error($ap)) {
            $record = eg_editorial_get_record($post_id);
        }
        unset($tmp_hash);
    }
    eg_editorial_save_record($post_id, $record);

    if (!empty($_POST['eg_editorial_do_publish'])) {
        $pub = eg_editorial_publish($post_id);
        if (is_wp_error($pub)) {
            add_filter('redirect_post_location', static function ($loc) use ($pub) {
                return add_query_arg('eg_editorial_err', rawurlencode($pub->get_error_message()), $loc);
            });
        }
    }
}, 20, 2);

add_action('save_post_eg_analyse', function ($post_id) {
    if (!isset($_POST['eg_analyse_brochure_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['eg_analyse_brochure_nonce'])), 'eg_analyse_brochure_save')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    $map = array(
        'eg_title_en'    => 'sanitize_text_field',
        'eg_excerpt_en'  => 'sanitize_textarea_field',
        'eg_body_en'     => 'wp_kses_post',
        'eg_source_url'  => 'esc_url_raw',
        'eg_byline'      => 'sanitize_text_field',
        'eg_byline_en'   => 'sanitize_text_field',
        'eg_badge_de'    => 'sanitize_text_field',
        'eg_badge_en'    => 'sanitize_text_field',
        'eg_feed_cat'    => 'sanitize_key',
    );
    foreach ($map as $key => $cb) {
        if (!isset($_POST[$key])) {
            continue;
        }
        $val = call_user_func($cb, wp_unslash($_POST[$key]));
        update_post_meta($post_id, $key, $val);
    }
    $feed_id = (string) get_post_meta($post_id, 'eg_feed_id', true);
    if ($feed_id === '') {
        $slug = get_post_field('post_name', $post_id);
        $feed_id = (strpos($slug, 'an-') === 0) ? $slug : 'an-' . $slug;
        update_post_meta($post_id, 'eg_feed_id', $feed_id);
    }
}, 20);

add_filter('manage_eg_material_posts_columns', function ($cols) {
    $cols['eg_stage'] = 'Stage';
    $cols['eg_urn'] = 'LinkedIn id';
    return $cols;
});
add_action('manage_eg_material_posts_custom_column', function ($col, $post_id) {
    if ($col === 'eg_stage') {
        echo esc_html((string) get_post_meta($post_id, '_eg_editorial_stage', true));
    }
    if ($col === 'eg_urn') {
        echo esc_html((string) get_post_meta($post_id, '_eg_li_urn', true));
    }
}, 10, 2);

add_action('admin_notices', function () {
    if (!empty($_GET['eg_editorial_dup'])) {
        $dup = (int) $_GET['eg_editorial_dup'];
        echo '<div class="notice notice-warning"><p>This LinkedIn id already exists as material #' . esc_html((string) $dup) . '. Same id does not create a second article.</p></div>';
    }
    if (!empty($_GET['eg_editorial_err'])) {
        echo '<div class="notice notice-error"><p>' . esc_html(sanitize_text_field(wp_unslash($_GET['eg_editorial_err']))) . '</p></div>';
    }
});

add_action('show_user_profile', 'eg_editorial_agent_user_field');
add_action('edit_user_profile', 'eg_editorial_agent_user_field');
function eg_editorial_agent_user_field($user)
{
    if (!current_user_can('promote_users')) {
        return;
    }
    $agent = (bool) get_user_meta($user->ID, 'eg_pipeline_agent', true);
    $coord = (bool) get_user_meta($user->ID, 'eg_pipeline_coordinator', true);
    echo '<h2>Editorial pipeline</h2><table class="form-table">';
    echo '<tr><th>Pipeline agent</th><td><label><input type="checkbox" name="eg_pipeline_agent" value="1"' . checked($agent, true, false) . '> ChatGPT MCP account. Can get_task and save_result. Cannot ingest, approve, or publish.</label></td></tr>';
    echo '<tr><th>n8n coordinator</th><td><label><input type="checkbox" name="eg_pipeline_coordinator" value="1"' . checked($coord, true, false) . '> n8n account. Can ingest and read the queue. Cannot approve or publish.</label></td></tr>';
    echo '</table>';
}
add_action('personal_options_update', 'eg_editorial_agent_user_save');
add_action('edit_user_profile_update', 'eg_editorial_agent_user_save');
function eg_editorial_agent_user_save($user_id)
{
    if (!current_user_can('promote_users')) {
        return;
    }
    $agent = !empty($_POST['eg_pipeline_agent']);
    $coord = !empty($_POST['eg_pipeline_coordinator']) && !$agent;
    update_user_meta($user_id, 'eg_pipeline_agent', $agent ? 1 : 0);
    update_user_meta($user_id, 'eg_pipeline_coordinator', $coord ? 1 : 0);
    $user = new WP_User($user_id);
    if ($agent) {
        $user->add_cap('eg_pipeline_agent');
    } else {
        $user->remove_cap('eg_pipeline_agent');
    }
    if ($coord) {
        $user->add_cap('eg_pipeline_coordinator');
    } else {
        $user->remove_cap('eg_pipeline_coordinator');
    }
}

add_filter('use_block_editor_for_post_type', function ($use, $post_type) {
    if ($post_type === 'eg_material') {
        return false;
    }
    return $use;
}, 100, 2);

function eg_editorial_sandbox_url($id = 0)
{
    $url = home_url('/eg-editorial-sandbox.php');
    if ((int) $id > 0) {
        $url = add_query_arg('id', (int) $id, $url);
    }
    return $url;
}

add_action('admin_bar_menu', function ($bar) {
    if (!current_user_can('edit_posts') || !is_admin_bar_showing()) {
        return;
    }
    $bar->add_node(
        array(
            'id'    => 'eg-editorial-sandbox',
            'title' => 'Editorial sandbox',
            'href'  => eg_editorial_sandbox_url(),
            'meta'  => array('title' => 'Agent workflow preview. Not the public Analysen page.'),
        )
    );
}, 80);

add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=eg_material',
        'Editorial sandbox',
        'Sandbox preview',
        'edit_posts',
        'eg-editorial-sandbox',
        static function () {
            wp_safe_redirect(eg_editorial_sandbox_url());
            exit;
        }
    );
});

function eg_editorial_sandbox_apply_fields($id, array $record)
{
    $map = array(
        'title_de'   => 'eg_sb_title_de',
        'title_en'   => 'eg_sb_title_en',
        'excerpt_de' => 'eg_sb_excerpt_de',
        'excerpt_en' => 'eg_sb_excerpt_en',
        'byline_de'  => 'eg_sb_byline_de',
        'byline_en'  => 'eg_sb_byline_en',
    );
    $changed = false;
    foreach ($map as $key => $post_key) {
        if (!isset($_POST[$post_key])) {
            continue;
        }
        $val = sanitize_textarea_field(wp_unslash($_POST[$post_key]));
        if ((string) ($record['material'][$key] ?? '') !== $val) {
            $record['material'][$key] = $val;
            $changed = true;
        }
    }
    if ($changed) {
        $record['approval'] = null;
        if ($record['stage'] === 'approved') {
            $record['stage'] = 'preview';
        }
        $record['material']['version_de'] = (int) $record['material']['version_de'] + 1;
        $record['material']['version_en'] = (int) $record['material']['version_en'] + 1;
    }
    return $record;
}

function eg_editorial_sandbox_card_complete(array $record)
{
    $m = $record['material'];
    return trim((string) $m['title_de']) !== ''
        && trim((string) $m['title_en']) !== ''
        && trim((string) $m['excerpt_de']) !== ''
        && trim((string) $m['excerpt_en']) !== '';
}

function eg_editorial_sandbox_handle_post()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return '';
    }
    if (!isset($_POST['eg_sandbox_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['eg_sandbox_nonce'])), 'eg_sandbox')) {
        return 'Invalid request.';
    }
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $action = isset($_POST['eg_sandbox_action']) ? sanitize_key(wp_unslash($_POST['eg_sandbox_action'])) : '';
    if (!$id || get_post_type($id) !== 'eg_material') {
        return 'Material not found.';
    }
    if (!eg_editorial_can_approve() && in_array($action, array('save', 'preview', 'approve', 'changes'), true)) {
        return 'Only an editor can change this record.';
    }

    $record = eg_editorial_sandbox_apply_fields($id, eg_editorial_get_record($id));

    if ($action === 'save') {
        eg_editorial_append_history($record, wp_get_current_user()->user_login, 'save', 'sandbox');
        eg_editorial_save_record($id, $record);
        return 'Draft saved on this sandbox page.';
    }
    if ($action === 'preview') {
        if (!eg_editorial_sandbox_card_complete($record)) {
            eg_editorial_save_record($id, $record);
            return 'Fill DE and EN title and short description first.';
        }
        $record['stage'] = 'preview';
        $record['human_question'] = '';
        eg_editorial_append_history($record, wp_get_current_user()->user_login, 'to_preview', 'sandbox');
        eg_editorial_save_record($id, $record);
        return 'This version is ready for approval.';
    }
    if ($action === 'approve') {
        if (!eg_editorial_sandbox_card_complete($record)) {
            eg_editorial_save_record($id, $record);
            return 'Fill DE and EN title and short description first.';
        }
        if ($record['stage'] !== 'preview' && $record['stage'] !== 'approved') {
            $record['stage'] = 'preview';
            $record['human_question'] = '';
            eg_editorial_append_history($record, wp_get_current_user()->user_login, 'to_preview', 'sandbox before approve');
        }
        eg_editorial_save_record($id, $record);
        $out = eg_editorial_approve($id, 'sandbox');
        return is_wp_error($out) ? $out->get_error_message() : 'Approved. This stays on the sandbox. The public Analysen page is unchanged.';
    }
    if ($action === 'changes') {
        $note = isset($_POST['editor_note']) ? sanitize_textarea_field(wp_unslash($_POST['editor_note'])) : '';
        if ($note === '') {
            eg_editorial_save_record($id, $record);
            return 'Write the specific change you need.';
        }
        $record['stage'] = 'blocked';
        $record['human_question'] = $note;
        $record['approval'] = null;
        $record['review']['comments'][] = array(
            'at'       => gmdate('c'),
            'agent'    => 'editor',
            'text'     => $note,
            'resolved' => false,
        );
        eg_editorial_append_history($record, wp_get_current_user()->user_login, 'request_changes', $note);
        eg_editorial_save_record($id, $record);
        return '';
    }
    return 'Unknown action.';
}

function eg_editorial_sandbox_esc($s)
{
    return esc_html((string) $s);
}

function eg_editorial_sandbox_render()
{
    nocache_headers();
    header('X-Robots-Tag: noindex, nofollow, noarchive', true);
    header('Content-Type: text/html; charset=utf-8');

    if (!is_user_logged_in()) {
        wp_safe_redirect(wp_login_url(eg_editorial_sandbox_url(isset($_GET['id']) ? (int) $_GET['id'] : 0)));
        exit;
    }
    if (!current_user_can('edit_posts')) {
        status_header(403);
        echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><title>Forbidden</title></head><body><p>This sandbox is for editors only.</p></body></html>';
        exit;
    }

    $notice = eg_editorial_sandbox_handle_post();
    $id = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;
    $task = $id ? eg_editorial_task_payload($id) : null;
    if ($id && !$task) {
        $notice = 'Material not found.';
        $id = 0;
    }

    $q = new WP_Query(
        array(
            'post_type'      => 'eg_material',
            'post_status'    => 'publish',
            'posts_per_page' => 80,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'fields'         => 'ids',
            'no_found_rows'  => true,
        )
    );

    $self = eg_editorial_sandbox_url();
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="robots" content="noindex,nofollow,noarchive">';
    echo '<title>Editorial sandbox | Eurasien Gesellschaft</title>';
    echo '<style>
:root{--ink:#0b2338;--muted:#4a5d73;--line:rgba(11,35,56,.14);--blue:#0032A0;--bg:#f4f6f8;--paper:#fff}
*{box-sizing:border-box}
body{margin:0;font:17px/1.5 system-ui,Segoe UI,sans-serif;color:var(--ink);background:var(--bg)}
.banner{background:#8a1f11;color:#fff;padding:10px 20px;font-size:14px;font-weight:600;letter-spacing:.02em}
.banner strong{font-weight:800}
.wrap{max-width:1120px;margin:0 auto;padding:24px 20px 96px}
input[type=text]{width:100%;padding:8px 10px;border:1px solid var(--line);border-radius:6px;font:inherit}
label.fld{display:block;margin:0 0 12px;font-size:14px;font-weight:600}
label.fld span{display:block;margin:0 0 4px;color:var(--muted);font-weight:600}
.ok{background:#e8f5ee;border:1px solid #9dccb0}
h1{font-size:28px;line-height:1.2;margin:16px 0 8px}
.lead{color:var(--muted);max-width:62ch;margin:0 0 24px}
.layout{display:grid;grid-template-columns:minmax(220px,280px) 1fr;gap:24px}
@media(max-width:860px){.layout{grid-template-columns:1fr}}
.panel{background:var(--paper);border:1px solid var(--line);border-radius:10px;padding:16px}
.queue{list-style:none;margin:0;padding:0}
.queue a{display:block;padding:10px 8px;border-bottom:1px solid var(--line);color:inherit;text-decoration:none}
.queue a:hover,.queue a.is-on{background:#eef2f8}
.queue .st{display:block;font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.04em}
.kicker{font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--blue);margin:0 0 8px}
.card{border:1px solid var(--line);border-radius:10px;padding:16px 18px;margin:0 0 16px;background:#fafbfd}
.card h2{font-size:22px;margin:0 0 8px}
.by{color:var(--muted);font-size:14px}
.src{margin-top:12px}
.src a{color:var(--blue)}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
@media(max-width:860px){.grid2{grid-template-columns:1fr}}
pre,textarea{width:100%;font:14px/1.45 ui-monospace,Consolas,monospace}
textarea{min-height:90px;padding:8px;border:1px solid var(--line);border-radius:6px}
.meta{font-size:13px;color:var(--muted);word-break:break-all}
.actions{display:flex;flex-wrap:wrap;gap:10px;margin:16px 0}
.btn{appearance:none;border:0;border-radius:6px;padding:10px 14px;font:inherit;font-weight:600;cursor:pointer}
.btn-p{background:var(--blue);color:#fff}
.btn-g{background:#e8edf4;color:var(--ink)}
.btn-d{background:#8a1f11;color:#fff}
.notice{background:#fff4e5;border:1px solid #e0c080;padding:10px 12px;border-radius:8px;margin:0 0 16px}
.orig{white-space:pre-wrap;background:#f7f8fa;padding:12px;border-radius:8px;border:1px solid var(--line)}
</style></head><body>';
    echo '<div class="banner">Editorial sandbox. This page is not public and is not the live Analysen page. Nothing here is shown on eurasiengesellschaft.de/analysen.html.</div>';
    echo '<div class="wrap">';
    echo '<p class="kicker">Internal only</p>';
    echo '<h1>Agent workflow preview</h1>';
    echo '<p class="lead">Results from the analyst, reviewer and translator land here so a human can read the exact DE and EN version. Approval on this page does not change the public website.</p>';
    if ($notice !== '') {
        $ok = (strpos($notice, 'Approved') === 0 || strpos($notice, 'ready') !== false || strpos($notice, 'saved') !== false);
        echo '<p class="notice' . ($ok ? ' ok' : '') . '">' . eg_editorial_sandbox_esc($notice) . '</p>';
    }
    echo '<div class="layout"><aside class="panel"><p class="kicker">Queue</p><ul class="queue">';
    if (!$q->posts) {
        echo '<li>No materials yet. Submit a LinkedIn URL under Materialien in WordPress.</li>';
    }
    foreach ($q->posts as $mid) {
        $row = eg_editorial_task_payload((int) $mid);
        if (!$row) {
            continue;
        }
        $on = ((int) $mid === $id) ? ' is-on' : '';
        echo '<li><a class="' . esc_attr($on) . '" href="' . esc_url(eg_editorial_sandbox_url($mid)) . '">';
        echo '<span class="st">' . eg_editorial_sandbox_esc($row['stage']) . '</span>';
        echo eg_editorial_sandbox_esc($row['title']);
        echo '</a></li>';
    }
    echo '</ul></aside><section class="panel">';

    if (!$task) {
        echo '<p>Choose an item in the queue. The bilingual card the agents produced will open here.</p>';
        echo '</section></div></div></body></html>';
        exit;
    }

    $r = $task['record'];
    $m = $r['material'];
    $o = $r['original'];
    echo '<p class="kicker">Material #' . (int) $id . ' · stage ' . eg_editorial_sandbox_esc($task['stage']) . '</p>';
    echo '<p class="meta">Version hash ' . eg_editorial_sandbox_esc($task['version_hash']) . '<br>DE v' . (int) $task['version_de'] . ' · EN v' . (int) $task['version_en'] . '</p>';
    if (!empty($task['human_question'])) {
        echo '<p class="notice"><strong>Needs a human:</strong> ' . eg_editorial_sandbox_esc($task['human_question']) . '</p>';
    }
    if (!empty($o['url'])) {
        echo '<p class="src"><a href="' . esc_url($o['url']) . '" target="_blank" rel="noopener">LinkedIn original</a>';
        if (!empty($o['urn'])) {
            echo ' · <span class="meta">' . eg_editorial_sandbox_esc($o['urn']) . '</span>';
        }
        echo '</p>';
    }

    $complete = eg_editorial_sandbox_card_complete($r);
    echo '<div class="grid2">';
    echo '<article class="card"><p class="kicker">Deutsch</p><h2>' . eg_editorial_sandbox_esc($m['title_de'] !== '' ? $m['title_de'] : 'Noch kein DE-Titel') . '</h2>';
    echo '<p>' . nl2br(eg_editorial_sandbox_esc($m['excerpt_de'])) . '</p>';
    if ($m['body_de'] !== '') {
        echo '<div>' . wp_kses_post($m['body_de']) . '</div>';
    }
    echo '<p class="by">' . eg_editorial_sandbox_esc($m['byline_de']) . '</p></article>';
    echo '<article class="card"><p class="kicker">English</p><h2>' . eg_editorial_sandbox_esc($m['title_en'] !== '' ? $m['title_en'] : 'No EN title yet') . '</h2>';
    echo '<p>' . nl2br(eg_editorial_sandbox_esc($m['excerpt_en'])) . '</p>';
    if ($m['body_en'] !== '') {
        echo '<div>' . wp_kses_post($m['body_en']) . '</div>';
    }
    echo '<p class="by">' . eg_editorial_sandbox_esc($m['byline_en']) . '</p></article>';
    echo '</div>';

    if (!empty($m['sources'])) {
        echo '<p class="kicker">Quellen</p><ul>';
        foreach ($m['sources'] as $src) {
            if (empty($src['url'])) {
                continue;
            }
            echo '<li><a href="' . esc_url($src['url']) . '" target="_blank" rel="noopener">' . eg_editorial_sandbox_esc($src['label_de'] !== '' ? $src['label_de'] : $src['url']) . '</a></li>';
        }
        echo '</ul>';
    }

    echo '<p class="kicker">Original text</p><div class="orig">' . eg_editorial_sandbox_esc($o['text'] !== '' ? $o['text'] : $o['transcript']) . '</div>';

    if (!empty($task['open_comments'])) {
        echo '<p class="kicker">Open comments</p><ul>';
        foreach ($task['open_comments'] as $c) {
            echo '<li>' . eg_editorial_sandbox_esc(($c['agent'] ?? '') . ': ' . ($c['text'] ?? '')) . '</li>';
        }
        echo '</ul>';
    }

    echo '<form method="post" action="' . esc_url(eg_editorial_sandbox_url($id)) . '">';
    wp_nonce_field('eg_sandbox', 'eg_sandbox_nonce');
    echo '<input type="hidden" name="id" value="' . (int) $id . '">';
    echo '<p class="kicker">Public card (edit here while agents are not connected)</p>';
    echo '<div class="grid2">';
    echo '<div><label class="fld"><span>Title DE</span><input type="text" name="eg_sb_title_de" value="' . esc_attr($m['title_de']) . '"></label>';
    echo '<label class="fld"><span>Short description DE</span><textarea name="eg_sb_excerpt_de">' . esc_textarea($m['excerpt_de']) . '</textarea></label>';
    echo '<label class="fld"><span>Byline DE</span><input type="text" name="eg_sb_byline_de" value="' . esc_attr($m['byline_de']) . '"></label></div>';
    echo '<div><label class="fld"><span>Title EN</span><input type="text" name="eg_sb_title_en" value="' . esc_attr($m['title_en']) . '"></label>';
    echo '<label class="fld"><span>Short description EN</span><textarea name="eg_sb_excerpt_en">' . esc_textarea($m['excerpt_en']) . '</textarea></label>';
    echo '<label class="fld"><span>Byline EN</span><input type="text" name="eg_sb_byline_en" value="' . esc_attr($m['byline_en']) . '"></label></div>';
    echo '</div>';
    if (eg_editorial_can_approve()) {
        echo '<div class="actions">';
        echo '<button class="btn btn-g" type="submit" name="eg_sandbox_action" value="save">Save draft</button>';
        echo '<button class="btn btn-g" type="submit" name="eg_sandbox_action" value="preview">Mark ready for approval</button>';
        if ($complete) {
            echo '<button class="btn btn-p" type="submit" name="eg_sandbox_action" value="approve">Approve this DE+EN version</button>';
        }
        echo '</div>';
        if (!$complete) {
            echo '<p class="meta">The analyst has not written a card yet. Fill the DE and EN title and short description above, then approve. That still does not publish to the live Analysen page.</p>';
        } else {
            echo '<p class="meta">Approve stores a version hash on this record. It does not edit analysen.html or any public page.</p>';
        }
        echo '<p><label>Send back with a specific question<br><textarea name="editor_note" placeholder="What exactly should change?"></textarea></label></p>';
        echo '<button class="btn btn-d" type="submit" name="eg_sandbox_action" value="changes">Request changes</button>';
    } else {
        echo '<p class="meta">This account can read the workflow. Approval is reserved for a human editor.</p>';
    }
    echo '</form>';
    echo '<p class="meta"><a href="' . esc_url(get_edit_post_link($id, 'raw')) . '">Open in WordPress</a> · <a href="' . esc_url($self) . '">All items</a></p>';
    echo '</section></div></div></body></html>';
    exit;
}
