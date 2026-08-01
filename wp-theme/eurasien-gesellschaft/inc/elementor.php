<?php
/**
 * Elementor compatibility.
 *
 * This theme was built from a fixed HTML prototype. Many pages use custom
 * templates and/or theme content files, so Elementor cannot own the layout
 * until a page is edited with Elementor (or uses the Elementor Full Width template).
 *
 * @package Eurasien_Gesellschaft
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the current (or given) post is built with Elementor.
 */
function eg_is_elementor_page( ?int $post_id = null ): bool {
	$post_id = $post_id ?? (int) get_the_ID();
	if ( $post_id <= 0 ) {
		return false;
	}
	return (string) get_post_meta( $post_id, '_elementor_edit_mode', true ) === 'builder';
}

/**
 * Register Theme Builder locations (header, footer, single, archive).
 */
add_action(
	'elementor/theme/register_locations',
	static function ( $manager ): void {
		if ( is_object( $manager ) && method_exists( $manager, 'register_all_core_locations' ) ) {
			$manager->register_all_core_locations();
		}
	}
);

/**
 * Once: enable Elementor on Pages, Posts, and theme CPTs.
 */
add_action(
	'elementor/loaded',
	static function (): void {
		if ( get_option( 'eg_elementor_types_set' ) ) {
			return;
		}
		$need      = array( 'page', 'post', 'eg_analyse', 'eg_event', 'eg_person', 'eg_recording' );
		$supported = get_option( 'elementor_cpt_support', array( 'page', 'post' ) );
		if ( ! is_array( $supported ) ) {
			$supported = array();
		}
		$merged = array_values( array_unique( array_merge( $supported, $need ) ) );
		update_option( 'elementor_cpt_support', $merged );
		update_option( 'eg_elementor_types_set', 1 );
	}
);

/**
 * Widen layout markers when Elementor owns the page.
 *
 * @param list<string> $classes Body classes.
 * @return list<string>
 */
add_filter(
	'body_class',
	static function ( array $classes ): array {
		if ( eg_is_elementor_page() ) {
			$classes[] = 'eg-elementor-page';
		}
		return $classes;
	}
);
