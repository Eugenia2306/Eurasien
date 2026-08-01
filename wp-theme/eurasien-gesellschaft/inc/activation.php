<?php
/**
 * Theme activation: flush rewrites and seed migrated pages.
 *
 * @package Eurasien_Gesellschaft
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * On theme switch: create pages/persons and flush permalinks.
 */
add_action(
	'after_switch_theme',
	static function (): void {
		if ( function_exists( 'eg_run_seed' ) ) {
			eg_run_seed( false );
		}
		flush_rewrite_rules();
		update_option( 'eg_needs_permalink_flush', 0 );
	}
);

/**
 * Catch cases where CPTs registered after last flush (upload without reactivation).
 */
add_action(
	'init',
	static function (): void {
		if ( get_option( 'eg_needs_permalink_flush' ) ) {
			flush_rewrite_rules( false );
			update_option( 'eg_needs_permalink_flush', 0 );
		}
	},
	99
);

/**
 * Mark flush needed when theme version changes; ensure Regionen uses interactive template.
 */
add_action(
	'after_setup_theme',
	static function (): void {
		$stored = (string) get_option( 'eg_theme_version', '' );
		if ( $stored === EG_THEME_VERSION ) {
			return;
		}
		update_option( 'eg_theme_version', EG_THEME_VERSION );
		update_option( 'eg_needs_permalink_flush', 1 );

		$page = get_page_by_path( 'regionen' );
		if ( $page instanceof WP_Post ) {
			update_post_meta( (int) $page->ID, '_wp_page_template', 'page-templates/template-regionen.php' );
		}

		$vorteile = get_page_by_path( 'mitgliedschaft-vorteile' );
		if ( $vorteile instanceof WP_Post ) {
			update_post_meta( (int) $vorteile->ID, '_wp_page_template', 'page-templates/template-vorteile.php' );
		}
	}
);

/**
 * Replace tokens and rewrite prototype / broken root-relative links in content.
 */
add_filter(
	'the_content',
	static function ( string $content ): string {
		if ( str_contains( $content, '{{EG_THEME_URI}}' ) ) {
			$content = str_replace( '{{EG_THEME_URI}}', esc_url( EG_THEME_URI ), $content );
		}

		$portraits = array(
			'Rahr'     => 'portrait-rahr.jpg',
			'Neu'      => 'portrait-neu.jpg',
			'Polajner' => 'portrait-polajner.jpg',
			'Wipper'   => 'portrait-wipperfuerth.jpg',
			'Schraps'  => 'portrait-schraps.jpg',
		);

		$content = preg_replace_callback(
			'/<img([^>]*?)src=""([^>]*?)alt="([^"]+)"([^>]*)>/i',
			static function ( array $m ) use ( $portraits ): string {
				$alt  = $m[3];
				$file = null;
				foreach ( $portraits as $needle => $name ) {
					if ( false !== stripos( $alt, $needle ) ) {
						$file = $name;
						break;
					}
				}
				if ( ! $file ) {
					return $m[0];
				}
				$src = esc_url( EG_THEME_URI . '/assets/images/' . $file );
				return '<img' . $m[1] . 'src="' . $src . '"' . $m[2] . 'alt="' . esc_attr( $alt ) . '"' . $m[4] . '>';
			},
			$content
		);

		// Rewrite leftover #p-* and root-relative prototype paths.
		if ( function_exists( 'eg_page_route_map' ) ) {
			foreach ( eg_page_route_map() as $id => $url ) {
				$content = str_replace( 'href="#' . $id . '"', 'href="' . esc_url( $url ) . '"', $content );
			}
		}

		$content = preg_replace_callback(
			'/href="\/([a-z0-9\-\/]*)"/i',
			static function ( array $m ): string {
				$slug = trim( $m[1], '/' );
				if ( '' === $slug ) {
					return 'href="' . esc_url( home_url( '/' ) ) . '"';
				}
				// Keep wp-admin absolute to site.
				if ( str_starts_with( $slug, 'wp-admin' ) ) {
					return 'href="' . esc_url( admin_url() ) . '"';
				}
				return 'href="' . esc_url( home_url( '/' . $slug . '/' ) ) . '"';
			},
			is_string( $content ) ? $content : ''
		);

		return is_string( $content ) ? $content : '';
	},
	8
);

/**
 * Admin notice until setup has been run / permalinks saved.
 */
add_action(
	'admin_notices',
	static function (): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$seeded = (int) get_option( 'eg_seeded', 0 );
		if ( $seeded ) {
			return;
		}
		$url = admin_url( 'themes.php?page=eg-setup' );
		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Eurasien Gesellschaft: run setup to create pages and fix 404s.', 'eurasien-gesellschaft' );
		echo ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Open Eurasien Setup', 'eurasien-gesellschaft' ) . '</a>';
		echo '</p></div>';
	}
);
