<?php
/**
 * Theme supports, menus, image sizes.
 *
 * @package Eurasien_Gesellschaft
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'after_setup_theme',
	static function (): void {
		load_theme_textdomain( 'eurasien-gesellschaft', EG_THEME_DIR . '/languages' );

		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support(
			'html5',
			array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' )
		);
		add_theme_support(
			'custom-logo',
			array(
				'height'      => 78,
				'width'       => 303,
				'flex-height' => true,
				'flex-width'  => true,
			)
		);
		add_theme_support( 'align-wide' );
		add_theme_support( 'responsive-embeds' );

		register_nav_menus(
			array(
				'primary' => __( 'Hauptnavigation', 'eurasien-gesellschaft' ),
				'footer_about' => __( 'Footer: Über uns', 'eurasien-gesellschaft' ),
				'footer_topics' => __( 'Footer: Themen & Analysen', 'eurasien-gesellschaft' ),
				'footer_service' => __( 'Footer: Service', 'eurasien-gesellschaft' ),
			)
		);

		add_image_size( 'eg-card', 640, 400, true );
		add_image_size( 'eg-person', 480, 480, true );
	}
);

add_filter(
	'body_class',
	static function ( array $classes ): array {
		$classes[] = 'eg-theme';
		if ( function_exists( 'eg_is_app_utility_page' ) && eg_is_app_utility_page() ) {
			$classes[] = 'eg-app-utility';
		}
		if ( function_exists( 'eg_is_app_auth_page' ) && eg_is_app_auth_page() ) {
			$classes[] = 'eg-app-auth';
		}
		return $classes;
	}
);
