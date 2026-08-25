<?php
/**
 * SEO + GEO for WordPress /app/ (main domain cutover).
 *
 * Public brochure SEO lives in static-site/; this module covers WP chrome:
 * title, description, canonical, robots, Open Graph, Twitter, JSON-LD.
 * Auth, checkout, and gated members pages are noindex.
 *
 * @package Eurasien_Gesellschaft
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public brochure origin (apex site, not /app/).
 */
function eg_seo_brochure_base(): string {
	if ( function_exists( 'eg_brochure_public_url' ) ) {
		return untrailingslashit( eg_brochure_public_url() );
	}
	$home = untrailingslashit( home_url() );
	if ( substr( $home, -4 ) === '/app' ) {
		return substr( $home, 0, -4 );
	}
	return $home;
}

/**
 * Absolute logo / default OG image on the brochure host.
 */
function eg_seo_default_image(): string {
	return eg_seo_brochure_base() . '/assets/images/embed-62c28610d17731c2.png';
}

/**
 * Per-slug SEO map for /app/ pages.
 *
 * @return array<string, array{title_de:string,title_en:string,description:string,robots?:string,og_type?:string}>
 */
function eg_seo_page_map(): array {
	return array(
		'login'                   => array(
			'title_de'    => 'Anmelden | Eurasien Gesellschaft',
			'title_en'    => 'Log In | Eurasien Gesellschaft',
			'description' => 'Anmeldung zum Mitgliederbereich der Eurasien Gesellschaft e. V.',
			'robots'      => 'noindex,nofollow',
		),
		'log-in'                  => array(
			'title_de'    => 'Anmelden | Eurasien Gesellschaft',
			'title_en'    => 'Log In | Eurasien Gesellschaft',
			'description' => 'Anmeldung zum Mitgliederbereich der Eurasien Gesellschaft e. V.',
			'robots'      => 'noindex,nofollow',
		),
		'mitglieder'              => array(
			'title_de'    => 'Mitgliederbereich | Eurasien Gesellschaft',
			'title_en'    => "Members' Area | Eurasien Gesellschaft",
			'description' => 'Mitgliederbereich der Eurasien Gesellschaft e. V. (Zugang erforderlich).',
			'robots'      => 'noindex,follow',
		),
		'positionen'              => array(
			'title_de'    => 'Positionen | Eurasien Gesellschaft',
			'title_en'    => 'Positions | Eurasien Gesellschaft',
			'description' => 'Mitgliederbereich: Positionspapiere der Eurasien Gesellschaft (Zugang erforderlich).',
			'robots'      => 'noindex,follow',
		),
		'dossiers'                => array(
			'title_de'    => 'Dossiers | Eurasien Gesellschaft',
			'title_en'    => 'Dossiers | Eurasien Gesellschaft',
			'description' => 'Mitgliederbereich: Dossiers der Eurasien Gesellschaft (Zugang erforderlich).',
			'robots'      => 'noindex,follow',
		),
		'studien'                 => array(
			'title_de'    => 'Studien | Eurasien Gesellschaft',
			'title_en'    => 'Studies | Eurasien Gesellschaft',
			'description' => 'Mitgliederbereich: Studien der Eurasien Gesellschaft (Zugang erforderlich).',
			'robots'      => 'noindex,follow',
		),
		'membership-account'      => array(
			'title_de'    => 'Mitgliedskonto | Eurasien Gesellschaft',
			'title_en'    => 'Membership Account | Eurasien Gesellschaft',
			'description' => 'Mitgliedskonto und Planverwaltung der Eurasien Gesellschaft e. V.',
			'robots'      => 'noindex,nofollow',
		),
		'membership-levels'       => array(
			'title_de'    => 'Mitgliedschaft | Eurasien Gesellschaft',
			'title_en'    => 'Membership | Eurasien Gesellschaft',
			'description' => 'Mitgliedschaftsstufen der Eurasien Gesellschaft e. V.',
			'robots'      => 'noindex,follow',
		),
		'membership-checkout'     => array(
			'title_de'    => 'Checkout Mitgliedschaft | Eurasien Gesellschaft',
			'title_en'    => 'Membership Checkout | Eurasien Gesellschaft',
			'description' => 'Abschluss der Mitgliedschaft bei der Eurasien Gesellschaft e. V.',
			'robots'      => 'noindex,nofollow',
		),
		'membership-confirmation' => array(
			'title_de'    => 'Bestätigung | Eurasien Gesellschaft',
			'title_en'    => 'Confirmation | Eurasien Gesellschaft',
			'description' => 'Bestätigung Ihrer Mitgliedschaft bei der Eurasien Gesellschaft e. V.',
			'robots'      => 'noindex,nofollow',
		),
		'membership-billing'      => array(
			'title_de'    => 'Abrechnung | Eurasien Gesellschaft',
			'title_en'    => 'Billing | Eurasien Gesellschaft',
			'description' => 'Abrechnung und Zahlungsdaten für Ihre Mitgliedschaft.',
			'robots'      => 'noindex,nofollow',
		),
		'membership-cancel'       => array(
			'title_de'    => 'Kündigen | Eurasien Gesellschaft',
			'title_en'    => 'Cancel Membership | Eurasien Gesellschaft',
			'description' => 'Mitgliedschaft kündigen – Eurasien Gesellschaft e. V.',
			'robots'      => 'noindex,nofollow',
		),
		'membership-orders'       => array(
			'title_de'    => 'Bestellungen | Eurasien Gesellschaft',
			'title_en'    => 'Orders | Eurasien Gesellschaft',
			'description' => 'Bestellübersicht Ihrer Mitgliedschaft.',
			'robots'      => 'noindex,nofollow',
		),
		'your-profile'            => array(
			'title_de'    => 'Ihr Profil | Eurasien Gesellschaft',
			'title_en'    => 'Your Profile | Eurasien Gesellschaft',
			'description' => 'Profil im Mitgliederbereich der Eurasien Gesellschaft e. V.',
			'robots'      => 'noindex,nofollow',
		),
		'shop'                    => array(
			'title_de'    => 'Shop | Eurasien Gesellschaft',
			'title_en'    => 'Shop | Eurasien Gesellschaft',
			'description' => 'Shop der Eurasien Gesellschaft e. V.',
			'robots'      => 'noindex,follow',
		),
		'cart'                    => array(
			'title_de'    => 'Warenkorb | Eurasien Gesellschaft',
			'title_en'    => 'Cart | Eurasien Gesellschaft',
			'description' => 'Warenkorb – Eurasien Gesellschaft e. V.',
			'robots'      => 'noindex,nofollow',
		),
		'checkout'                => array(
			'title_de'    => 'Kasse | Eurasien Gesellschaft',
			'title_en'    => 'Checkout | Eurasien Gesellschaft',
			'description' => 'Kasse – Eurasien Gesellschaft e. V.',
			'robots'      => 'noindex,nofollow',
		),
		'my-account'              => array(
			'title_de'    => 'Mein Konto | Eurasien Gesellschaft',
			'title_en'    => 'My Account | Eurasien Gesellschaft',
			'description' => 'Kontobereich der Eurasien Gesellschaft e. V.',
			'robots'      => 'noindex,nofollow',
		),
	);
}

/**
 * Resolve SEO meta for the current front-end request.
 *
 * @return array{title:string,description:string,robots:string,canonical:string,og_type:string}
 */
function eg_seo_current_meta(): array {
	$brochure = eg_seo_brochure_base();
	$default  = array(
		'title'       => 'Eurasien Gesellschaft e. V.',
		'description' => 'Unabhängige, gemeinnützige Berliner Think-Tank-Plattform für Dialog, Analyse und Verständigung im eurasischen Raum.',
		'robots'      => 'noindex,follow',
		'canonical'   => home_url( add_query_arg( array(), $GLOBALS['wp']->request ?? '' ) ),
		'og_type'     => 'website',
	);

	if ( is_singular() ) {
		$permalink = get_permalink();
		if ( is_string( $permalink ) && $permalink !== '' ) {
			$default['canonical'] = $permalink;
		}
	} else {
		$default['canonical'] = home_url( add_query_arg( array() ) );
	}

	$map  = eg_seo_page_map();
	$slug = '';
	if ( is_singular() ) {
		$qid = get_queried_object_id();
		if ( $qid ) {
			$slug = (string) get_post_field( 'post_name', $qid );
		}
	}

	/* Document titles stay DE by default; JS language toggle does not split URLs. */
	if ( $slug && isset( $map[ $slug ] ) ) {
		$row                    = $map[ $slug ];
		$default['title']       = $row['title_de'];
		$default['description'] = $row['description'];
		$default['robots']      = $row['robots'] ?? 'noindex,follow';
		$default['og_type']     = $row['og_type'] ?? 'website';
	} elseif ( is_search() || is_404() ) {
		$default['robots'] = 'noindex,nofollow';
		$default['title']  = is_404()
			? 'Seite nicht gefunden | Eurasien Gesellschaft'
			: 'Suche | Eurasien Gesellschaft';
	} else {
		/* Unknown /app/ surface: stay out of the index (brochure is the public site). */
		$default['robots'] = 'noindex,follow';
		if ( is_singular() ) {
			$t = wp_strip_all_tags( get_the_title() );
			if ( $t !== '' ) {
				$default['title'] = $t . ' | Eurasien Gesellschaft';
			}
		}
	}

	/**
	 * Filter resolved SEO meta for the current request.
	 *
	 * @param array{title:string,description:string,robots:string,canonical:string,og_type:string} $default Meta.
	 * @param string                                                                               $slug    Page slug or empty.
	 * @param string                                                                               $brochure Brochure base URL.
	 */
	return apply_filters( 'eg_seo_meta', $default, $slug, $brochure );
}

/**
 * Document title via title-tag support.
 *
 * @param array<string, string> $parts Title parts.
 * @return array<string, string>
 */
function eg_seo_document_title_parts( array $parts ): array {
	if ( is_admin() ) {
		return $parts;
	}
	$meta           = eg_seo_current_meta();
	$parts['title'] = $meta['title'];
	unset( $parts['site'] ); /* Full title already includes brand. */
	return $parts;
}
add_filter( 'document_title_parts', 'eg_seo_document_title_parts', 20 );

/**
 * Emit SEO + GEO tags in wp_head.
 */
function eg_seo_wp_head(): void {
	if ( is_admin() ) {
		return;
	}

	$meta     = eg_seo_current_meta();
	$brochure = eg_seo_brochure_base();
	$image    = eg_seo_default_image();
	$org_id   = $brochure . '/#organization';
	$site_id  = $brochure . '/#website';

	echo "\n<!-- EG SEO + GEO (/app/) -->\n";
	echo '<meta name="description" content="' . esc_attr( $meta['description'] ) . '">' . "\n";
	echo '<meta name="robots" content="' . esc_attr( $meta['robots'] ) . '">' . "\n";
	echo '<link rel="canonical" href="' . esc_url( $meta['canonical'] ) . '">' . "\n";
	echo '<link rel="alternate" hreflang="de" href="' . esc_url( $meta['canonical'] ) . '">' . "\n";
	echo '<link rel="alternate" hreflang="en" href="' . esc_url( $meta['canonical'] ) . '">' . "\n";
	echo '<link rel="alternate" hreflang="x-default" href="' . esc_url( $meta['canonical'] ) . '">' . "\n";

	echo '<meta property="og:type" content="' . esc_attr( $meta['og_type'] ) . '">' . "\n";
	echo '<meta property="og:site_name" content="Eurasien Gesellschaft e. V.">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $meta['title'] ) . '">' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $meta['description'] ) . '">' . "\n";
	echo '<meta property="og:url" content="' . esc_url( $meta['canonical'] ) . '">' . "\n";
	echo '<meta property="og:locale" content="de_DE">' . "\n";
	echo '<meta property="og:locale:alternate" content="en_US">' . "\n";
	echo '<meta property="og:image" content="' . esc_url( $image ) . '">' . "\n";
	echo '<meta property="og:image:alt" content="Eurasien Gesellschaft e. V.">' . "\n";

	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	echo '<meta name="twitter:title" content="' . esc_attr( $meta['title'] ) . '">' . "\n";
	echo '<meta name="twitter:description" content="' . esc_attr( $meta['description'] ) . '">' . "\n";
	echo '<meta name="twitter:image" content="' . esc_url( $image ) . '">' . "\n";

	echo '<meta name="theme-color" content="#0032A0">' . "\n";
	echo '<link rel="icon" href="' . esc_url( $image ) . '" type="image/png">' . "\n";
	echo '<link rel="apple-touch-icon" href="' . esc_url( $image ) . '">' . "\n";

	$graph = array(
		array(
			'@type'         => 'Organization',
			'@id'           => $org_id,
			'name'          => 'Eurasien Gesellschaft e. V.',
			'alternateName' => 'Eurasien Gesellschaft',
			'url'           => $brochure . '/',
			'logo'          => array(
				'@type' => 'ImageObject',
				'url'   => $image,
			),
			'email'         => 'kontakt@eurasien-gesellschaft.org',
			'foundingDate'  => '2021',
			'description'   => 'Unabhängige, gemeinnützige Berliner Think-Tank-Plattform für Dialog, Analyse und Verständigung im eurasischen Raum: Kultur, Wissenschaft, Wirtschaft und Geopolitik.',
			'address'       => array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => 'Kunz-Buntschuh-Straße 11',
				'postalCode'      => '14193',
				'addressLocality' => 'Berlin',
				'addressCountry'  => 'DE',
			),
			'sameAs'        => array(
				'https://www.linkedin.com/company/eurasien-gesellschaft/',
				'https://www.youtube.com/@EurasienGesellschaft',
				'https://t.me/EurasienGesellschaft',
			),
		),
		array(
			'@type'       => 'WebSite',
			'@id'         => $site_id,
			'url'         => $brochure . '/',
			'name'        => 'Eurasien Gesellschaft e. V.',
			'publisher'   => array( '@id' => $org_id ),
			'inLanguage'  => array( 'de', 'en' ),
		),
		array(
			'@type'       => 'WebPage',
			'@id'         => $meta['canonical'] . '#webpage',
			'url'         => $meta['canonical'],
			'name'        => $meta['title'],
			'description' => $meta['description'],
			'isPartOf'    => array( '@id' => $site_id ),
			'about'       => array( '@id' => $org_id ),
			'inLanguage'  => 'de',
		),
	);

	$payload = array(
		'@context' => 'https://schema.org',
		'@graph'   => $graph,
	);

	echo '<script type="application/ld+json">' . "\n";
	echo wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
	echo "\n</script>\n";
	echo "<!-- /EG SEO + GEO -->\n";
}
add_action( 'wp_head', 'eg_seo_wp_head', 2 );

/**
 * Align core wp_robots() with our meta (gated /app/ stays noindex).
 *
 * @param array<string, bool|string> $robots Robots directives.
 * @return array<string, bool|string>
 */
add_filter(
	'wp_robots',
	static function ( array $robots ): array {
		$meta  = eg_seo_current_meta();
		$parts = array_map( 'trim', explode( ',', $meta['robots'] ) );
		foreach ( $parts as $p ) {
			if ( 'noindex' === $p ) {
				$robots['noindex'] = true;
				unset( $robots['index'] );
			} elseif ( 'index' === $p ) {
				$robots['index'] = true;
				unset( $robots['noindex'] );
			} elseif ( 'nofollow' === $p ) {
				$robots['nofollow'] = true;
				unset( $robots['follow'] );
			} elseif ( 'follow' === $p ) {
				$robots['follow'] = true;
				unset( $robots['nofollow'] );
			}
		}
		return $robots;
	},
	99
);
