<?php
/**
 * Custom post types and taxonomies.
 *
 * Mapping from the HTML prototype:
 * - Analysen / Stellungnahmen / Positionen / Dossiers / Studien -> eg_analyse
 * - Veranstaltungen -> eg_event
 * - Vorstand & Experten -> eg_person
 * - Gesellschaftsnachrichten -> core posts (or category "news")
 * - Mediathek / Aufzeichnungen -> eg_recording
 *
 * @package Eurasien_Gesellschaft
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	static function (): void {
		register_post_type(
			'eg_analyse',
			array(
				'labels' => array(
					'name'          => __( 'Analysen', 'eurasien-gesellschaft' ),
					'singular_name' => __( 'Analyse', 'eurasien-gesellschaft' ),
					'add_new_item'  => __( 'Analyse hinzufügen', 'eurasien-gesellschaft' ),
					'edit_item'     => __( 'Analyse bearbeiten', 'eurasien-gesellschaft' ),
				),
				'public'       => true,
				'has_archive'  => true,
				'rewrite'      => array( 'slug' => 'analysen' ),
				'menu_icon'    => 'dashicons-analytics',
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions' ),
			)
		);

		register_post_type(
			'eg_event',
			array(
				'labels' => array(
					'name'          => __( 'Veranstaltungen', 'eurasien-gesellschaft' ),
					'singular_name' => __( 'Veranstaltung', 'eurasien-gesellschaft' ),
					'add_new_item'  => __( 'Veranstaltung hinzufügen', 'eurasien-gesellschaft' ),
				),
				'public'       => true,
				'has_archive'  => true,
				'rewrite'      => array( 'slug' => 'veranstaltungen' ),
				'menu_icon'    => 'dashicons-calendar-alt',
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
			)
		);

		register_post_type(
			'eg_person',
			array(
				'labels' => array(
					'name'          => __( 'Personen', 'eurasien-gesellschaft' ),
					'singular_name' => __( 'Person', 'eurasien-gesellschaft' ),
					'add_new_item'  => __( 'Person hinzufügen', 'eurasien-gesellschaft' ),
				),
				'public'       => true,
				'has_archive'  => true,
				'rewrite'      => array( 'slug' => 'personen' ),
				'menu_icon'    => 'dashicons-groups',
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
			)
		);

		register_post_type(
			'eg_recording',
			array(
				'labels' => array(
					'name'          => __( 'Aufzeichnungen', 'eurasien-gesellschaft' ),
					'singular_name' => __( 'Aufzeichnung', 'eurasien-gesellschaft' ),
					'add_new_item'  => __( 'Aufzeichnung hinzufügen', 'eurasien-gesellschaft' ),
				),
				'public'       => true,
				'has_archive'  => true,
				'rewrite'      => array( 'slug' => 'mediathek' ),
				'menu_icon'    => 'dashicons-video-alt3',
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
			)
		);

		register_taxonomy(
			'eg_format',
			array( 'eg_analyse' ),
			array(
				'labels' => array(
					'name'          => __( 'Formate', 'eurasien-gesellschaft' ),
					'singular_name' => __( 'Format', 'eurasien-gesellschaft' ),
				),
				'public'       => true,
				'hierarchical' => true,
				'rewrite'      => array( 'slug' => 'format' ),
				'show_in_rest' => true,
			)
		);

		register_taxonomy(
			'eg_topic',
			array( 'eg_analyse', 'eg_event', 'eg_recording', 'post' ),
			array(
				'labels' => array(
					'name'          => __( 'Themen', 'eurasien-gesellschaft' ),
					'singular_name' => __( 'Thema', 'eurasien-gesellschaft' ),
				),
				'public'       => true,
				'hierarchical' => true,
				'rewrite'      => array( 'slug' => 'thema' ),
				'show_in_rest' => true,
			)
		);

		register_taxonomy(
			'eg_region',
			array( 'eg_analyse', 'eg_event', 'eg_recording', 'post' ),
			array(
				'labels' => array(
					'name'          => __( 'Regionen', 'eurasien-gesellschaft' ),
					'singular_name' => __( 'Region', 'eurasien-gesellschaft' ),
				),
				'public'       => true,
				'hierarchical' => true,
				'rewrite'      => array( 'slug' => 'region' ),
				'show_in_rest' => true,
			)
		);

		register_taxonomy(
			'eg_role',
			array( 'eg_person' ),
			array(
				'labels' => array(
					'name'          => __( 'Rollen', 'eurasien-gesellschaft' ),
					'singular_name' => __( 'Rolle', 'eurasien-gesellschaft' ),
				),
				'public'       => true,
				'hierarchical' => true,
				'rewrite'      => array( 'slug' => 'rolle' ),
				'show_in_rest' => true,
			)
		);
	}
);

/**
 * Register meta boxes for event date/location and recording URL.
 */
add_action(
	'init',
	static function (): void {
		register_post_meta(
			'eg_event',
			'eg_event_start',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => static fn() => current_user_can( 'edit_posts' ),
			)
		);
		register_post_meta(
			'eg_event',
			'eg_event_location',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => static fn() => current_user_can( 'edit_posts' ),
			)
		);
		register_post_meta(
			'eg_recording',
			'eg_youtube_url',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'esc_url_raw',
				'auth_callback'     => static fn() => current_user_can( 'edit_posts' ),
			)
		);
		register_post_meta(
			'eg_person',
			'eg_person_role_label',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => static fn() => current_user_can( 'edit_posts' ),
			)
		);
	}
);
