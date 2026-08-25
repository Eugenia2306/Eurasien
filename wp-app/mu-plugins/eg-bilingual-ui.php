<?php
/**
 * Plugin Name: EG Bilingual UI
 * Description: Dual DE/EN markup for WordPress/PMPro/Woo pages so the brochure language switcher can toggle body copy, not only chrome.
 * Author: d4rl1ngt0n
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bilingual span pair (same contract as theme eg_bi).
 *
 * @param string $de German text.
 * @param string $en English text.
 * @return string
 */
function eg_ui_bi( $de, $en ) {
	$de = (string) $de;
	$en = (string) $en;
	return '<span class="de">' . esc_html( $de ) . '</span><span class="en" hidden>' . esc_html( $en ) . '</span>';
}

/**
 * Replace a string only in text nodes, never inside HTML tags/attributes.
 * WooCommerce add-to-cart links put "Add to cart" in aria-label; injecting
 * <span> there splits the attribute and dumps the rest of the tag as text.
 *
 * @param string $html        Markup.
 * @param string $needle      Plain text to replace.
 * @param string $replacement Replacement (may contain HTML).
 * @return string
 */
function eg_ui_replace_outside_tags( $html, $needle, $replacement ) {
	if ( $needle === '' || ! is_string( $html ) || strpos( $html, $needle ) === false ) {
		return $html;
	}
	return preg_replace_callback(
		'/(<[^>]*>)|([^<]+)/',
		static function ( $m ) use ( $needle, $replacement ) {
			if ( ! empty( $m[1] ) ) {
				return $m[1];
			}
			return str_replace( $needle, $replacement, $m[2] );
		},
		$html
	);
}

/**
 * Known page titles by slug.
 *
 * @return array<string, array{0:string,1:string}>
 */
function eg_ui_title_map() {
	return array(
		'login'                   => array( 'Anmelden', 'Log In' ),
		'log-in'                  => array( 'Anmelden', 'Log In' ),
		'mitglieder'              => array( 'Mitglieder', 'Members' ),
		'positionen'              => array( 'Positionen', 'Positions' ),
		'dossiers'                => array( 'Dossiers', 'Dossiers' ),
		'studien'                 => array( 'Studien', 'Studies' ),
		'shop'                    => array( 'Shop', 'Shop' ),
		'cart'                    => array( 'Warenkorb', 'Cart' ),
		'checkout'                => array( 'Kasse', 'Checkout' ),
		'my-account'              => array( 'Mein Konto', 'My account' ),
		'membership-account'      => array( 'Mitgliedskonto', 'Membership Account' ),
		'membership-levels'       => array( 'Mitgliedschaft', 'Membership Levels' ),
		'membership-checkout'     => array( 'Mitgliedschaft Checkout', 'Membership Checkout' ),
		'membership-confirmation' => array( 'Bestätigung', 'Membership Confirmation' ),
		'membership-billing'      => array( 'Abrechnung', 'Membership Billing' ),
		'membership-cancel'       => array( 'Kündigen', 'Membership Cancel' ),
		'membership-orders'       => array( 'Bestellungen', 'Membership Orders' ),
		'your-profile'            => array( 'Ihr Profil', 'Your Profile' ),
	);
}

/**
 * Replace main singular page title with bilingual markup.
 */
add_filter(
	'the_title',
	function ( $title, $post_id ) {
		if ( is_admin() || ! did_action( 'wp' ) || ! is_singular() || ! in_the_loop() ) {
			return $title;
		}
		if ( (int) $post_id !== (int) get_queried_object_id() ) {
			return $title;
		}
		$slug = get_post_field( 'post_name', (int) $post_id );
		$map  = eg_ui_title_map();
		if ( isset( $map[ $slug ] ) ) {
			return eg_ui_bi( $map[ $slug ][0], $map[ $slug ][1] );
		}
		return $title;
	},
	20,
	2
);

/**
 * Mitglieder hub body: bilingual + correct /app/ links (no /app/app/).
 */
add_filter(
	'the_content',
	function ( $content ) {
		if ( is_admin() || ! did_action( 'wp' ) || ! is_page() ) {
			return $content;
		}
		$qid = get_queried_object_id();
		if ( ! $qid ) {
			return $content;
		}
		$slug = get_post_field( 'post_name', $qid );
		if ( 'mitglieder' !== $slug ) {
			return $content;
		}
		if ( wp_get_post_parent_id( $qid ) ) {
			return $content;
		}

		$pos = esc_url( home_url( '/mitglieder/positionen/' ) );
		$dos = esc_url( home_url( '/mitglieder/dossiers/' ) );
		$stu = esc_url( home_url( '/mitglieder/studien/' ) );

		return '<p>' . eg_ui_bi(
			'Mitgliederbereich der Eurasien Gesellschaft e. V.',
			'Members area of Eurasien Gesellschaft e. V.'
		) . '</p><p>'
			. '<a href="' . $pos . '">' . eg_ui_bi( 'Positionen', 'Positions' ) . '</a> · '
			. '<a href="' . $dos . '">' . eg_ui_bi( 'Dossiers', 'Dossiers' ) . '</a> · '
			. '<a href="' . $stu . '">' . eg_ui_bi( 'Studien', 'Studies' ) . '</a>'
			. '</p>';
	},
	5
);

/**
 * Rewrite common PMPro gate / login / Woo strings inside content to bilingual spans.
 */
add_filter(
	'the_content',
	function ( $content ) {
		if ( is_admin() || ! did_action( 'wp' ) || ! is_string( $content ) || $content === '' ) {
			return $content;
		}

		$pairs = array(
			'Membership Required'       => array( 'Mitgliedschaft erforderlich', 'Membership Required' ),
			'You must be a member to access this content.' => array( 'Sie müssen Mitglied sein, um diesen Inhalt zu sehen.', 'You must be a member to access this content.' ),
			'View Membership Levels'    => array( 'Mitgliedschaft ansehen', 'View Membership Levels' ),
			'Already a member?'         => array( 'Bereits Mitglied?', 'Already a member?' ),
			'Log in here'               => array( 'Hier anmelden', 'Log in here' ),
			'Username or Email Address' => array( 'Benutzername oder E-Mail-Adresse', 'Username or Email Address' ),
			'Remember Me'               => array( 'Angemeldet bleiben', 'Remember Me' ),
			'Lost Password?'            => array( 'Passwort vergessen?', 'Lost Password?' ),
			'Show Password'             => array( 'Passwort anzeigen', 'Show Password' ),
			'Hide Password'             => array( 'Passwort verbergen', 'Hide Password' ),
			'Add to cart'               => array( 'In den Warenkorb', 'Add to cart' ),
			'View cart'                 => array( 'Warenkorb ansehen', 'View cart' ),
		);

		foreach ( $pairs as $needle => $bi ) {
			if ( strpos( $content, $needle ) === false ) {
				continue;
			}
			// Avoid double-wrapping.
			if ( strpos( $content, 'class="de">' . $bi[0] ) !== false ) {
				continue;
			}
			$content = eg_ui_replace_outside_tags( $content, $needle, eg_ui_bi( $bi[0], $bi[1] ) );
		}

		// Standalone "Log In" as visible text (not input values): replace label-like occurrences.
		$content = preg_replace(
			'/(<(?:label|a|h1|h2|button)[^>]*>\\s*)Log In(\\s*<\\/)/i',
			'$1' . eg_ui_bi( 'Anmelden', 'Log In' ) . '$2',
			$content
		);

		// Password label text nodes (not attributes).
		$content = preg_replace(
			'/(<label[^>]*for=["\']user_pass["\'][^>]*>\\s*)Password(\\s*<\\/label>)/i',
			'$1' . eg_ui_bi( 'Passwort', 'Password' ) . '$2',
			$content
		);

		return $content;
	},
	99
);

/**
 * Client-side: bilingual values for inputs/buttons that cannot contain HTML.
 */
add_action(
	'wp_footer',
	function () {
		if ( is_admin() ) {
			return;
		}
		?>
<script id="eg-bilingual-ui">
(function () {
  var pairs = [
    { sel: '#wp-submit, input[name="wp-submit"]', de: 'Anmelden', en: 'Log In' },
    { sel: 'button[name="add-to-cart"], .single_add_to_cart_button, a.add_to_cart_button, .ajax_add_to_cart', de: 'In den Warenkorb', en: 'Add to cart' },
    { sel: 'a.added_to_cart', de: 'Warenkorb ansehen', en: 'View cart' },
    { sel: '.checkout-button, a.checkout-button', de: 'Zur Kasse', en: 'Checkout' }
  ];
  function lang() {
    return document.documentElement.getAttribute('data-eg-lang') === 'en' ? 'en' : 'de';
  }
  function apply() {
    var L = lang();
    pairs.forEach(function (p) {
      document.querySelectorAll(p.sel).forEach(function (el) {
        if (el.querySelector && el.querySelector('span.de')) return;
        var t = L === 'en' ? p.en : p.de;
        if (el.tagName === 'INPUT') el.value = t;
        else el.textContent = t;
      });
    });
  }
  document.addEventListener('eg:lang', apply);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', apply);
  else apply();
})();
</script>
		<?php
	},
	40
);
