<?php
/**
 * Seed Analysen, Veranstaltungen, Aufzeichnungen from migrated HTML fragments.
 *
 * @package Eurasien_Gesellschaft
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return array{analyses:int,events:int,recordings:int}
 */
function eg_seed_cpt_from_content( bool $overwrite = false ): array {
	$counts = array(
		'analyses'   => 0,
		'events'     => 0,
		'recordings' => 0,
	);

	$counts['events']     = eg_seed_events_from_html( $overwrite );
	$counts['recordings'] = eg_seed_recordings_from_html( $overwrite );
	$counts['analyses']   = eg_seed_analyses_from_html( $overwrite );

	return $counts;
}

/**
 * Upsert a CPT by slug.
 *
 * @param array<string, mixed> $postarr Post fields.
 */
function eg_upsert_cpt_by_slug( string $post_type, string $slug, array $postarr, bool $overwrite ): int {
	$existing = get_page_by_path( $slug, OBJECT, $post_type );
	$postarr['post_type']   = $post_type;
	$postarr['post_name']   = $slug;
	$postarr['post_status'] = 'publish';

	if ( $existing instanceof WP_Post ) {
		if ( ! $overwrite ) {
			return (int) $existing->ID;
		}
		$postarr['ID'] = $existing->ID;
		$id            = wp_update_post( $postarr, true );
		return is_wp_error( $id ) ? 0 : (int) $id;
	}

	$id = wp_insert_post( $postarr, true );
	return is_wp_error( $id ) ? 0 : (int) $id;
}

function eg_seed_events_from_html( bool $overwrite ): int {
	$html = eg_load_content_file( 'p-veranstaltungen.html' );
	if ( '' === $html ) {
		return 0;
	}

	$count = 0;
	if ( ! preg_match_all(
		'/<div class="ev"[^>]*data-date="([^"]+)"[^>]*data-status="([^"]+)"[^>]*>(.*?)<\/div>\s*<\/div>\s*<\/div>/s',
		$html,
		$matches,
		PREG_SET_ORDER
	) ) {
		// Fallback looser match per event opening tag.
		preg_match_all( '/<div class="ev"[^>]*data-date="([^"]+)"[^>]*data-status="([^"]+)"[^>]*data-type="([^"]*)"[^>]*>/s', $html, $opens, PREG_SET_ORDER );
		foreach ( $opens as $i => $open ) {
			$start = (int) strpos( $html, $open[0] );
			$next  = isset( $opens[ $i + 1 ] ) ? (int) strpos( $html, $opens[ $i + 1 ][0], $start + 1 ) : strlen( $html );
			$block = substr( $html, $start, $next - $start );
			$count += eg_import_event_block( $block, $open[1], $overwrite ) ? 1 : 0;
		}
		return $count;
	}

	foreach ( $matches as $m ) {
		$count += eg_import_event_block( $m[0], $m[1], $overwrite ) ? 1 : 0;
	}
	return $count;
}

function eg_import_event_block( string $block, string $date, bool $overwrite ): bool {
	$title_de = '';
	$title_en = '';
	if ( preg_match( '/class="ev__t"[^>]*>\s*<span class="de">([^<]+)<\/span>\s*<span class="en"[^>]*>([^<]+)<\/span>/s', $block, $tm ) ) {
		$title_de = html_entity_decode( wp_strip_all_tags( $tm[1] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$title_en = html_entity_decode( wp_strip_all_tags( $tm[2] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	} elseif ( preg_match( '/class="ev__t"[^>]*>(.*?)<\/h3>/s', $block, $tm ) ) {
		$raw = wp_strip_all_tags( $tm[1] );
		$title_de = trim( html_entity_decode( $raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}
	if ( '' === $title_de ) {
		return false;
	}

	$location = '';
	if ( preg_match( '/class="ev__meta"[^>]*>(.*?)<\/div>/s', $block, $mm ) ) {
		$location = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $mm[1] ) ) ?? '' );
	}

	$slug = sanitize_title( $date . '-' . $title_de );
	$id   = eg_upsert_cpt_by_slug(
		'eg_event',
		$slug,
		array(
			'post_title'   => $title_de,
			'post_content' => $title_en ? '<p class="de"></p><p class="en" hidden>' . esc_html( $title_en ) . '</p>' : '',
			'post_excerpt' => $location,
		),
		$overwrite
	);
	if ( ! $id ) {
		return false;
	}
	update_post_meta( $id, 'eg_event_start', $date );
	if ( $location ) {
		update_post_meta( $id, 'eg_event_location', $location );
	}
	return true;
}

function eg_seed_recordings_from_html( bool $overwrite ): int {
	$html = eg_load_content_file( 'p-mediathek.html' );
	if ( '' === $html ) {
		return 0;
	}
	$count = 0;
	if ( ! preg_match_all( '/<div class="mcard">(.*?)<\/div>\s*(?=<div class="mcard">|<\/div>\s*<\/div>\s*<\/section>)/s', $html, $cards, PREG_SET_ORDER ) ) {
		preg_match_all( '/<div class="mcard">([\s\S]*?)<\/div>/', $html, $cards, PREG_SET_ORDER );
	}

	foreach ( $cards as $card ) {
		$block = $card[1];
		if ( ! preg_match( '/class="mcard__t"[^>]*>\s*<span class="de">([^<]+)<\/span>/', $block, $tm ) ) {
			continue;
		}
		$title = html_entity_decode( $tm[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$yt    = '';
		if ( preg_match( '/href="(https:\/\/www\.youtube\.com\/[^"]+)"/', $block, $ym ) ) {
			$yt = $ym[1];
		}
		$by = '';
		if ( preg_match( '/class="mcard__by"[^>]*>(.*?)<\/p>/s', $block, $bm ) ) {
			$by = trim( wp_strip_all_tags( $bm[1] ) );
		}
		$slug = sanitize_title( $title );
		$id   = eg_upsert_cpt_by_slug(
			'eg_recording',
			$slug,
			array(
				'post_title'   => $title,
				'post_excerpt' => $by,
				'post_content' => $by ? '<p>' . esc_html( $by ) . '</p>' : '',
			),
			$overwrite
		);
		if ( ! $id ) {
			continue;
		}
		if ( $yt ) {
			update_post_meta( $id, 'eg_youtube_url', esc_url_raw( $yt ) );
		}
		++$count;
	}
	return $count;
}

function eg_seed_analyses_from_html( bool $overwrite ): int {
	$html = eg_load_content_file( 'p-analysen.html' );
	if ( '' === $html ) {
		return 0;
	}
	$count = 0;
	if ( ! preg_match_all( '/<div class="srcrow"[^>]*>(.*?)<\/div>\s*<\/div>/s', $html, $rows, PREG_SET_ORDER ) ) {
		return 0;
	}

	foreach ( $rows as $row ) {
		$block = $row[1];
		if ( ! preg_match( '/class="srcrow__t"[^>]*>\s*<span class="de">([^<]+)<\/span>\s*<span class="en"[^>]*>([^<]*)<\/span>/s', $block, $tm ) ) {
			continue;
		}
		$title_de = html_entity_decode( $tm[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$title_en = html_entity_decode( $tm[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$sum      = '';
		if ( preg_match( '/class="srcrow__sum"[^>]*>\s*<span class="de">([^<]+)<\/span>/s', $block, $sm ) ) {
			$sum = html_entity_decode( $sm[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}
		$format = 'Aktuelles';
		if ( preg_match( '/badge--src"[^>]*>\s*<span class="de">([^<]+)<\/span>/', $block, $fm ) ) {
			$label = $fm[1];
			if ( false !== stripos( $label, 'Stellung' ) ) {
				$format = 'Stellungnahmen';
			} elseif ( false !== stripos( $label, 'Position' ) ) {
				$format = 'Positionen';
			} elseif ( false !== stripos( $label, 'Dossier' ) ) {
				$format = 'Dossiers';
			} elseif ( false !== stripos( $label, 'Studie' ) ) {
				$format = 'Studien';
			}
		}

		$slug = sanitize_title( $title_de );
		$id   = eg_upsert_cpt_by_slug(
			'eg_analyse',
			$slug,
			array(
				'post_title'   => $title_de,
				'post_excerpt' => $sum,
				'post_content' => '<p class="de">' . esc_html( $sum ) . '</p>' .
					( $title_en ? '<p class="en" hidden>' . esc_html( $title_en ) . '</p>' : '' ),
			),
			$overwrite
		);
		if ( ! $id ) {
			continue;
		}
		wp_set_object_terms( $id, array( $format ), 'eg_format', false );
		++$count;
	}
	return $count;
}
