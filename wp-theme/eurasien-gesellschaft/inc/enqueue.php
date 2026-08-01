<?php
/**
 * Styles and scripts.
 *
 * @package Eurasien_Gesellschaft
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		wp_enqueue_style(
			'eg-fonts',
			'https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@400;500;600;700;800&family=Source+Serif+4:ital,opsz,wght@0,8..60,400;0,8..60,500;0,8..60,600;0,8..60,700;1,8..60,400&display=swap',
			array(),
			null
		);

		wp_enqueue_style(
			'eg-main',
			EG_THEME_URI . '/assets/css/main.css',
			array( 'eg-fonts' ),
			EG_THEME_VERSION
		);

		wp_enqueue_style(
			'eg-wp',
			EG_THEME_URI . '/assets/css/wp-overrides.css',
			array( 'eg-main' ),
			EG_THEME_VERSION
		);

		// Language switcher: small, isolated, always loads.
		wp_enqueue_script(
			'eg-lang',
			EG_THEME_URI . '/assets/js/lang-switch.js',
			array(),
			EG_THEME_VERSION,
			true
		);

		wp_enqueue_script(
			'eg-theme',
			EG_THEME_URI . '/assets/js/theme.js',
			array( 'eg-lang' ),
			EG_THEME_VERSION,
			true
		);

		wp_localize_script(
			'eg-theme',
			'egTheme',
			array(
				'homeUrl'  => home_url( '/' ),
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'restUrl'  => esc_url_raw( rest_url() ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'routes'   => eg_page_route_map(),
				'themeUri' => EG_THEME_URI,
			)
		);

		$tax_path = EG_THEME_DIR . '/assets/js/eg-taxonomy.json';
		if ( is_readable( $tax_path ) ) {
			wp_add_inline_script(
				'eg-theme',
				'window.egTaxonomy = ' . (string) file_get_contents( $tax_path ) . ';',
				'before'
			);
		}
	}
);

add_action(
	'wp_head',
	static function (): void {
		echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
		echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
		// Prevent FOUC: hide .en before CSS/JS if German is default.
		echo '<style id="eg-lang-boot">html:not([data-eg-lang="en"]) .en{display:none!important}</style>' . "\n";
		echo '<script>try{var L=localStorage.getItem("eg-lang");if(L==="en"){document.documentElement.setAttribute("data-eg-lang","en");document.documentElement.setAttribute("lang","en");}}catch(e){}</script>' . "\n";
	},
	1
);
