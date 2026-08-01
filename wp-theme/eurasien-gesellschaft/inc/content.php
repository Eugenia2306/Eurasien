<?php
/**
 * Load migrated HTML fragments from /content.
 *
 * @package Eurasien_Gesellschaft
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Absolute path to a content fragment.
 */
function eg_content_path( string $filename ): string {
	return EG_THEME_DIR . '/content/' . ltrim( $filename, '/' );
}

/**
 * Read and prepare a migrated HTML fragment for use as post_content.
 */
function eg_load_content_file( string $filename ): string {
	$path = eg_content_path( $filename );
	if ( ! is_readable( $path ) ) {
		return '';
	}

	$html = (string) file_get_contents( $path );
	$html = str_replace( '{{EG_THEME_URI}}', esc_url( EG_THEME_URI ), $html );

	// Turn root-relative prototype paths into absolute site URLs.
	$html = preg_replace_callback(
		'/href="\/([a-z0-9\-\/]*)"/i',
		static function ( array $m ): string {
			$slug = trim( $m[1], '/' );
			if ( '' === $slug ) {
				return 'href="' . esc_url( home_url( '/' ) ) . '"';
			}
			return 'href="' . esc_url( home_url( '/' . $slug . '/' ) ) . '"';
		},
		$html
	);

	return is_string( $html ) ? $html : '';
}

/**
 * Manifest: page slug => content filename.
 *
 * @return array<string, string>
 */
function eg_content_manifest(): array {
	$path = eg_content_path( 'manifest.json' );
	if ( ! is_readable( $path ) ) {
		return array();
	}
	$data = json_decode( (string) file_get_contents( $path ), true );
	return is_array( $data ) ? $data : array();
}

/**
 * Person seed definitions.
 *
 * @return list<array{title:string,slug:string,file:string,role:string,roles:list<string>}>
 */
function eg_person_seed_defs(): array {
	return array(
		array(
			'title' => 'Alexander Rahr',
			'slug'  => 'alexander-rahr',
			'file'  => 'p-person-rahr.html',
			'role'  => 'Vorsitzender',
			'roles' => array( 'Vorstand', 'Experte', 'Referent' ),
		),
		array(
			'title' => 'Dr. Christian Wipperfürth',
			'slug'  => 'christian-wipperfuerth',
			'file'  => 'p-person-wipperfurth.html',
			'role'  => 'Vorstand',
			'roles' => array( 'Vorstand', 'Experte', 'Autor' ),
		),
		array(
			'title' => 'Dr. Alexander Neu',
			'slug'  => 'alexander-neu',
			'file'  => 'p-person-neu.html',
			'role'  => 'Vorstand',
			'roles' => array( 'Vorstand', 'Experte' ),
		),
		array(
			'title' => 'Christoph Polajner',
			'slug'  => 'christoph-polajner',
			'file'  => 'p-person-polajner.html',
			'role'  => 'Vorstand',
			'roles' => array( 'Vorstand', 'Experte', 'Referent' ),
		),
		array(
			'title' => 'Andreas Schraps',
			'slug'  => 'andreas-schraps',
			'file'  => 'p-person-schraps.html',
			'role'  => 'Vorstand',
			'roles' => array( 'Vorstand' ),
		),
	);
}
