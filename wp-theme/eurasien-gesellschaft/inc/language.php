<?php
/**
 * Language visibility + allow SVG / bilingual attributes in post HTML.
 *
 * @package Eurasien_Gesellschaft
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param array<string, array<string, bool>> $tags Tags.
 * @param string                             $context Context.
 * @return array<string, array<string, bool>>
 */
add_filter(
	'wp_kses_allowed_html',
	static function ( array $tags, string $context ): array {
		if ( 'post' !== $context ) {
			return $tags;
		}

		foreach ( array( 'span', 'p', 'div', 'li', 'a', 'h1', 'h2', 'h3', 'h4', 'small', 'strong', 'em', 'ul', 'ol', 'button', 'label', 'input', 'section', 'nav', 'aside' ) as $tag ) {
			if ( ! isset( $tags[ $tag ] ) ) {
				$tags[ $tag ] = array();
			}
			$tags[ $tag ]['class']          = true;
			$tags[ $tag ]['id']             = true;
			$tags[ $tag ]['hidden']         = true;
			$tags[ $tag ]['lang']           = true;
			$tags[ $tag ]['role']           = true;
			$tags[ $tag ]['aria-label']     = true;
			$tags[ $tag ]['aria-pressed']   = true;
			$tags[ $tag ]['aria-selected']  = true;
			$tags[ $tag ]['aria-hidden']    = true;
			$tags[ $tag ]['aria-expanded']  = true;
			$tags[ $tag ]['data-region']    = true;
			$tags[ $tag ]['data-country']   = true;
			$tags[ $tag ]['data-tab']       = true;
			$tags[ $tag ]['data-reg-reset'] = true;
			$tags[ $tag ]['tabindex']       = true;
			$tags[ $tag ]['type']           = true;
			$tags[ $tag ]['placeholder']    = true;
		}

		$tags['img']['data-eg-inline'] = true;
		$tags['img']['class']          = true;
		$tags['img']['alt']            = true;
		$tags['img']['width']          = true;
		$tags['img']['height']         = true;
		$tags['img']['loading']        = true;
		$tags['img']['decoding']       = true;

		// Interactive regions map (and other prototype SVGs).
		$svg_attrs = array(
			'class'               => true,
			'id'                  => true,
			'viewbox'             => true,
			'xmlns'               => true,
			'width'               => true,
			'height'              => true,
			'fill'                => true,
			'stroke'              => true,
			'stroke-width'        => true,
			'stroke-linejoin'     => true,
			'stroke-linecap'      => true,
			'd'                   => true,
			'cx'                  => true,
			'cy'                  => true,
			'r'                   => true,
			'x'                   => true,
			'y'                   => true,
			'rx'                  => true,
			'ry'                  => true,
			'transform'           => true,
			'opacity'             => true,
			'role'                => true,
			'tabindex'            => true,
			'aria-label'          => true,
			'aria-pressed'        => true,
			'aria-hidden'         => true,
			'data-region'         => true,
			'data-country'        => true,
			'preserveaspectratio' => true,
			'vector-effect'       => true,
			'focusable'           => true,
		);

		foreach ( array( 'svg', 'g', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'defs', 'clippath', 'title', 'desc', 'use', 'symbol', 'text', 'tspan' ) as $svg_tag ) {
			$tags[ $svg_tag ] = $svg_attrs;
		}

		return $tags;
	},
	10,
	2
);

/**
 * Front-end html lang follows eg_lang cookie (brochure + /app/), not WP install locale.
 *
 * @param string $output language_attributes() output.
 */
add_filter(
	'language_attributes',
	static function ( string $output ): string {
		if ( is_admin() ) {
			return $output;
		}
		$lang = 'de';
		if ( isset( $_COOKIE['eg_lang'] ) && $_COOKIE['eg_lang'] === 'en' ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$lang = 'en';
		}
		$output = preg_replace( '/\slang=(["\'])[^"\']*\1/', '', $output );
		return trim( $output . ' lang="' . esc_attr( $lang ) . '" data-eg-lang="' . esc_attr( $lang ) . '"' );
	},
	20
);
