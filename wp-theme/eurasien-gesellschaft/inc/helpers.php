<?php
/**
 * Template helpers and route map (prototype #p-* -> WP URLs).
 *
 * @package Eurasien_Gesellschaft
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Slug map from the HTML prototype section IDs.
 *
 * @return array<string, string> prototype id without # => preferred page/post slug
 */
function eg_prototype_slugs(): array {
	return array(
		'p-home'                 => '',
		'p-mission'              => 'mission',
		'p-vorstand'             => 'vorstand',
		'p-partner'              => 'partner',
		'p-news'                 => 'gesellschaftsnachrichten',
		'p-themen'               => 'themen',
		'p-topic-geopolitik'     => 'geopolitik',
		'p-topic-energie'        => 'energie',
		'p-topic-wirtschaft'     => 'wirtschaft',
		'p-kultur'               => 'kultur',
		'p-topic-wissenschaft'   => 'wissenschaft',
		'p-laender'              => 'laender-gesellschaften',
		'p-analysen'             => 'analysen',
		'p-regionen'             => 'regionen',
		'p-veranstaltungen'      => 'veranstaltungen',
		'p-mediathek'            => 'mediathek',
		'p-recordings-archive'   => 'aufzeichnungen',
		'p-mitgliedschaft'       => 'mitgliedschaft',
		'p-mitgliedschaft-vorteile' => 'mitgliedschaft-vorteile',
		'p-members-positionen'   => 'positionen',
		'p-members-dossiers'     => 'dossiers',
		'p-members-studien'      => 'studien',
		'p-login'                => 'anmelden',
		'p-impressum'            => 'impressum',
		'p-person-rahr'          => 'alexander-rahr',
		'p-person-wipperfurth'   => 'christian-wipperfuerth',
		'p-person-neu'           => 'alexander-neu',
		'p-person-polajner'      => 'christoph-polajner',
		'p-person-schraps'       => 'andreas-schraps',
	);
}

/**
 * Resolve a prototype route id to a live URL.
 */
function eg_route( string $prototype_id ): string {
	$id = ltrim( $prototype_id, '#' );
	$map = eg_prototype_slugs();

	if ( ! isset( $map[ $id ] ) ) {
		return home_url( '/' );
	}

	$slug = $map[ $id ];
	if ( '' === $slug ) {
		return home_url( '/' );
	}

	// CPT archives.
	if ( 'analysen' === $slug ) {
		return get_post_type_archive_link( 'eg_analyse' ) ?: home_url( '/analysen/' );
	}
	if ( 'veranstaltungen' === $slug ) {
		return get_post_type_archive_link( 'eg_event' ) ?: home_url( '/veranstaltungen/' );
	}
	if ( 'mediathek' === $slug ) {
		return get_post_type_archive_link( 'eg_recording' ) ?: home_url( '/mediathek/' );
	}

	$page = get_page_by_path( $slug );
	if ( $page instanceof WP_Post ) {
		return get_permalink( $page ) ?: home_url( '/' . $slug . '/' );
	}

	$person = get_page_by_path( $slug, OBJECT, 'eg_person' );
	if ( $person instanceof WP_Post ) {
		return get_permalink( $person ) ?: home_url( '/personen/' . $slug . '/' );
	}

	return home_url( '/' . $slug . '/' );
}

/**
 * Route map for JS localization.
 *
 * @return array<string, string>
 */
function eg_page_route_map(): array {
	$map = array();
	foreach ( array_keys( eg_prototype_slugs() ) as $id ) {
		$map[ $id ] = eg_route( $id );
	}
	return $map;
}

/**
 * Bilingual span helper (DE visible by default, EN hidden until lang toggle).
 */
function eg_bi( string $de, string $en ): string {
	return '<span class="de">' . esc_html( $de ) . '</span><span class="en" hidden>' . esc_html( $en ) . '</span>';
}

/**
 * Echo bilingual markup.
 */
function eg_bi_e( string $de, string $en ): void {
	echo eg_bi( $de, $en ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Theme logo URL (custom logo or bundled asset).
 */
function eg_logo_url(): string {
	$custom = get_theme_mod( 'custom_logo' );
	if ( $custom ) {
		$url = wp_get_attachment_image_url( (int) $custom, 'full' );
		if ( $url ) {
			return $url;
		}
	}
	return EG_THEME_URI . '/assets/images/logo.png';
}

/**
 * Format event start meta for list cards.
 *
 * @return array{d:string,m:string,y:string}|null
 */
function eg_event_date_parts( int $post_id ): ?array {
	$raw = (string) get_post_meta( $post_id, 'eg_event_start', true );
	if ( '' === $raw ) {
		$raw = get_the_date( 'Y-m-d', $post_id );
	}
	$ts = strtotime( $raw );
	if ( false === $ts ) {
		return null;
	}
	$months = array(
		1 => 'Jan', 2 => 'Feb', 3 => 'Mär', 4 => 'Apr', 5 => 'Mai', 6 => 'Jun',
		7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Dez',
	);
	$m = (int) gmdate( 'n', $ts );
	return array(
		'd' => gmdate( 'd', $ts ),
		'm' => $months[ $m ] ?? gmdate( 'M', $ts ),
		'y' => gmdate( 'Y', $ts ),
	);
}

/**
 * Whether an event is in the past (by eg_event_start or post date).
 */
function eg_event_is_past( int $post_id ): bool {
	$raw = (string) get_post_meta( $post_id, 'eg_event_start', true );
	if ( '' === $raw ) {
		$raw = get_the_date( 'Y-m-d', $post_id );
	}
	$ts = strtotime( $raw );
	return false !== $ts && $ts < time();
}
