<?php
/**
 * Plugin Name: EG Branded Emails
 * Description: Shared Eurasien Gesellschaft presentation for transactional email.
 * Author: d4rl1ngt0n
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public URL used in email links.
 */
function eg_email_public_url( $path = '' ) {
	if ( function_exists( 'eg_brochure_public_url' ) ) {
		$url = eg_brochure_public_url( ltrim( (string) $path, '/' ) );
	} else {
		$parts = wp_parse_url( home_url( '/' ) );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			$url = home_url( '/' );
		} else {
			$origin = $parts['scheme'] . '://' . $parts['host'];
			if ( ! empty( $parts['port'] ) ) {
				$origin .= ':' . $parts['port'];
			}
			$url = trailingslashit( $origin ) . ltrim( (string) $path, '/' );
		}
	}

	return set_url_scheme( $url, 'https' );
}

/**
 * Email-safe logo. Always the public HTTPS file so clients do not load
 * http:// or /app/wp-content/ URLs that many inboxes block.
 */
function eg_email_logo_url() {
	return 'https://eurasiengesellschaft.de/assets/images/logo.png?v=3';
}

/**
 * Turn technical admin subjects into a short human heading.
 */
function eg_email_human_admin_heading( $subject ) {
	$subject = wp_strip_all_tags( (string) $subject );
	$map     = array(
		'new user registration'     => 'Jemand hat sich angemeldet',
		'neue benutzerregistrierung'=> 'Jemand hat sich angemeldet',
		'new order'                 => 'Es gibt einen neuen Erwerb',
		'neue bestellung'           => 'Es gibt einen neuen Erwerb',
		'checkout_admin'            => 'Eine neue Mitgliedschaft ist da',
		'cancelled order'           => 'Eine Bestellung wurde storniert',
		'failed order'              => 'Eine Zahlung braucht einen Blick',
	);
	$lower = strtolower( $subject );
	foreach ( $map as $needle => $heading ) {
		if ( false !== strpos( $lower, $needle ) ) {
			return $heading;
		}
	}

	$subject = preg_replace( '/^\[MUSTER\]\s*/i', '', $subject );
	$subject = preg_replace( '/\s*[–-]\s*Eurasien Gesellschaft.*$/i', '', $subject );
	$subject = preg_replace( '/\s*#\d+\s*$/', '', (string) $subject );

	return $subject !== '' ? $subject : 'Kurze Nachricht für Sie';
}

/**
 * Normalize mail headers and force HTML without losing Reply-To headers.
 */
function eg_email_html_headers( $headers = array() ) {
	if ( is_string( $headers ) ) {
		$headers = preg_split( '/\r\n|\r|\n/', $headers );
	}
	if ( ! is_array( $headers ) ) {
		$headers = array();
	}

	$headers = array_values(
		array_filter(
			$headers,
			static function ( $header ) {
				return 0 !== stripos( trim( (string) $header ), 'Content-Type:' );
			}
		)
	);
	$headers[] = 'Content-Type: text/html; charset=UTF-8';

	return $headers;
}

/**
 * Render a label/value summary table.
 *
 * @param array<string,string> $rows Rows to render.
 */
function eg_email_summary_rows( $rows, $style = 'customer' ) {
	$html     = '';
	$is_admin = 'admin' === $style;
	foreach ( (array) $rows as $label => $value ) {
		if ( '' === trim( wp_strip_all_tags( (string) $value ) ) ) {
			continue;
		}
		$label_css = $is_admin
			? 'padding:12px 0;border-bottom:1px solid #e8edf4;color:#6a7588;font:400 14px/1.5 Arial,sans-serif;vertical-align:top;width:36%;'
			: 'padding:11px 0;border-bottom:1px solid #e4e9f1;color:#647187;font:700 11px/1.4 Arial,sans-serif;letter-spacing:.05em;text-transform:uppercase;vertical-align:top;width:38%;';
		$value_css = $is_admin
			? 'padding:12px 0;border-bottom:1px solid #e8edf4;color:#102345;font:600 15px/1.5 Georgia,serif;text-align:left;vertical-align:top;'
			: 'padding:11px 0;border-bottom:1px solid #e4e9f1;color:#102345;font:600 14px/1.5 Arial,sans-serif;text-align:right;vertical-align:top;';
		$html .= '<tr>';
		$html .= '<td style="' . $label_css . '">' . esc_html( (string) $label ) . '</td>';
		$html .= '<td style="' . $value_css . '">' . wp_kses_post( (string) $value ) . '</td>';
		$html .= '</tr>';
	}

	return '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:22px 0;border-collapse:collapse;">' . $html . '</table>';
}

/**
 * Render a complete branded transactional email.
 *
 * @param array<string,mixed> $args Email presentation arguments.
 */
function eg_email_layout( $args ) {
	$args = wp_parse_args(
		(array) $args,
		array(
			'eyebrow'   => 'Eurasien Gesellschaft',
			'heading'   => 'Information',
			'preheader' => '',
			'intro'     => '',
			'content'   => '',
			'cta_url'   => '',
			'cta_text'  => '',
			'admin'     => false,
		)
	);

	$preheader = $args['preheader'] ? $args['preheader'] : $args['heading'];
	$home      = eg_email_public_url();
	$privacy   = eg_email_public_url( 'datenschutz.html' );
	$contact   = 'mailto:kontakt@eurasien-gesellschaft.org';
	$cta       = '';

	if ( $args['cta_url'] && $args['cta_text'] ) {
		$cta = '<table role="presentation" cellspacing="0" cellpadding="0" style="margin:28px 0 8px;"><tr><td style="border-radius:8px;background:#da291c;">'
			. '<a href="' . esc_url( $args['cta_url'] ) . '" style="display:inline-block;padding:14px 22px;color:#ffffff;font:800 14px/1.2 Arial,sans-serif;text-decoration:none;">'
			. esc_html( $args['cta_text'] )
			. '</a></td></tr></table>';
	}

	$heading_size = $args['admin'] ? '27px' : '31px';
	$eyebrow      = $args['eyebrow']
		? '<div style="margin:0 0 10px;color:#b27a0a;font:700 12px/1.4 Arial,sans-serif;letter-spacing:.04em;">' . esc_html( $args['eyebrow'] ) . '</div>'
		: '';
	$footnote     = $args['admin']
		? 'Eine kurze Übersicht für das Team der Eurasien Gesellschaft.'
		: 'Sie erhalten diese Nachricht, weil Sie mit der Eurasien Gesellschaft in Verbindung stehen.';

	return '<!doctype html><!-- eg-branded-email -->'
		. '<html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html( $args['heading'] ) . '</title></head>'
		. '<body style="margin:0;padding:0;background:#f3f6fb;color:#253750;">'
		. '<div style="display:none!important;max-height:0;overflow:hidden;opacity:0;color:transparent;">' . esc_html( $preheader ) . '</div>'
		. '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;background:#f3f6fb;border-collapse:collapse;"><tr><td align="center" style="padding:30px 14px;">'
		. '<table role="presentation" width="640" cellspacing="0" cellpadding="0" style="width:100%;max-width:640px;border-collapse:separate;background:#ffffff;border:1px solid #dde4ee;border-radius:14px;box-shadow:0 16px 42px rgba(7,35,78,.08);overflow:hidden;">'
		. '<tr><td style="height:5px;background:linear-gradient(90deg,#0032a0 0 72%,#e1a734 72% 88%,#da291c 88%);font-size:0;line-height:0;">&nbsp;</td></tr>'
		. '<tr><td style="padding:26px 34px 22px;border-bottom:1px solid #e4e9f1;">'
		. '<a href="' . esc_url( $home ) . '" style="text-decoration:none;"><img src="' . esc_url( eg_email_logo_url() ) . '" width="202" alt="Eurasien Gesellschaft" style="display:block;width:202px;max-width:70%;height:auto;border:0;"></a>'
		. '</td></tr>'
		. '<tr><td style="padding:36px 34px 34px;">'
		. $eyebrow
		. '<h1 style="margin:0 0 16px;color:#0032a0;font:700 ' . $heading_size . '/1.2 Georgia,serif;letter-spacing:-.02em;">' . esc_html( $args['heading'] ) . '</h1>'
		. ( $args['intro'] ? '<div style="margin:0 0 22px;color:#42536b;font:400 16px/1.65 Arial,sans-serif;">' . wp_kses_post( $args['intro'] ) . '</div>' : '' )
		. '<div style="color:#34465e;font:400 15px/1.7 Arial,sans-serif;">' . wp_kses_post( $args['content'] ) . '</div>'
		. $cta
		. '</td></tr>'
		. '<tr><td style="padding:22px 34px;background:#0032a0;color:#dce7ff;font:400 12px/1.6 Arial,sans-serif;">'
		. '<strong style="color:#ffffff;">Eurasien Gesellschaft e. V.</strong><br>Unabhängige Plattform für Dialog und Verständigung<br>'
		. '<a href="' . esc_url( $home ) . '" style="color:#ffffff;text-decoration:underline;">Website</a>'
		. '&nbsp;&nbsp;·&nbsp;&nbsp;<a href="' . esc_url( $privacy ) . '" style="color:#ffffff;text-decoration:underline;">Datenschutz</a>'
		. '&nbsp;&nbsp;·&nbsp;&nbsp;<a href="' . esc_url( $contact ) . '" style="color:#ffffff;text-decoration:underline;">Kontakt</a>'
		. '</td></tr></table>'
		. '<div style="max-width:600px;padding:16px 20px 0;color:#7b8798;font:400 12px/1.5 Arial,sans-serif;text-align:center;">' . esc_html( $footnote ) . '</div>'
		. '</td></tr></table></body></html>';
}

/**
 * Wrap plain WordPress messages (registration, reset and account notices).
 */
add_filter(
	'wp_mail',
	static function ( $args ) {
		$message = isset( $args['message'] ) ? (string) $args['message'] : '';
		if ( false !== strpos( $message, '<!-- eg-branded-email -->' ) ) {
			return $args;
		}

		// Full HTML documents belong to a plugin-specific renderer.
		if ( false !== stripos( $message, '<html' ) || false !== stripos( $message, '<!doctype' ) ) {
			return $args;
		}

		$is_html  = $message !== wp_strip_all_tags( $message );
		$content  = $is_html
			? $message
			: wpautop( make_clickable( esc_html( $message ) ) );
		$is_admin = function_exists( 'eg_mail_list_includes_admin' )
			&& eg_mail_list_includes_admin( $args['to'] ?? '' );
		$subject  = isset( $args['subject'] ) ? (string) $args['subject'] : 'Eurasien Gesellschaft';

		$args['message'] = eg_email_layout(
			array(
				'eyebrow'   => $is_admin ? 'Kurze Nachricht' : 'Konto & Service',
				'heading'   => $is_admin ? eg_email_human_admin_heading( $subject ) : $subject,
				'preheader' => wp_strip_all_tags( $message ),
				'content'   => $content,
				'admin'     => $is_admin,
			)
		);
		$args['headers'] = eg_email_html_headers( isset( $args['headers'] ) ? $args['headers'] : array() );

		return $args;
	},
	20
);

/**
 * PMPro sends HTML fragments; wrap them before the global wp_mail filter.
 */
add_filter(
	'pmpro_email_body',
	static function ( $body, $email = null ) {
		if ( false !== strpos( (string) $body, '<!-- eg-branded-email -->' ) ) {
			return $body;
		}
		$subject  = is_object( $email ) && isset( $email->subject )
			? (string) $email->subject
			: 'Ihre Mitgliedschaft';
		$template = is_object( $email ) && isset( $email->template )
			? (string) $email->template
			: '';
		$is_admin = false !== strpos( $template, 'admin' );

		return eg_email_layout(
			array(
				'eyebrow'   => $is_admin ? 'Mitgliedschaft' : 'Mitgliedschaft',
				'heading'   => $is_admin ? eg_email_human_admin_heading( $subject ) : $subject,
				'preheader' => wp_strip_all_tags( (string) $body ),
				'content'   => (string) $body,
				'cta_url'   => $is_admin ? '' : home_url( '/membership-account/' ),
				'cta_text'  => $is_admin ? '' : 'Mein Konto öffnen',
				'admin'     => $is_admin,
			)
		);
	},
	20,
	2
);

add_filter(
	'pmpro_email_headers',
	static function ( $headers ) {
		return eg_email_html_headers( $headers );
	},
	20
);

/**
 * Give PMPro team notifications clear, human headings.
 */
add_filter(
	'pmpro_email_subject',
	static function ( $subject, $email = null ) {
		$template = is_object( $email ) && isset( $email->template ) ? (string) $email->template : '';
		$headings = array(
			'checkout_admin'        => 'Eine neue Mitgliedschaft ist da',
			'billing_success_admin' => 'Eine Mitgliedschaft wurde verlängert',
			'billing_failure_admin' => 'Eine Zahlung braucht Aufmerksamkeit',
			'cancel_admin'          => 'Eine Mitgliedschaft wurde beendet',
		);

		return isset( $headings[ $template ] ) ? $headings[ $template ] : $subject;
	},
	20,
	2
);

/**
 * WooCommerce emails: German copy, branded frame, no app promo / placeholder images.
 */
function eg_woocommerce_bind_email_templates( $mailer = null ) {
	if ( ! function_exists( 'WC' ) ) {
		return;
	}
	if ( ! is_object( $mailer ) ) {
		$mailer = WC()->mailer();
	}
	if ( ! is_object( $mailer ) ) {
		return;
	}
	remove_action( 'woocommerce_email_header', array( $mailer, 'email_header' ) );
	remove_action( 'woocommerce_email_footer', array( $mailer, 'email_footer' ) );
	if ( ! has_action( 'woocommerce_email_header', 'eg_woocommerce_email_header' ) ) {
		add_action( 'woocommerce_email_header', 'eg_woocommerce_email_header', 10, 2 );
	}
	if ( ! has_action( 'woocommerce_email_footer', 'eg_woocommerce_email_footer' ) ) {
		add_action( 'woocommerce_email_footer', 'eg_woocommerce_email_footer', 20, 1 );
	}

	if ( ! empty( $mailer->emails ) && is_array( $mailer->emails ) ) {
		foreach ( $mailer->emails as $email ) {
			remove_action( 'woocommerce_email_footer', array( $email, 'mobile_messaging' ), 9 );
		}
	}
}
add_action(
	'woocommerce_init',
	static function () {
		eg_woocommerce_bind_email_templates();
	},
	30
);
add_action( 'woocommerce_email', 'eg_woocommerce_bind_email_templates', 5 );

add_filter( 'woocommerce_email_setup_locale', '__return_true' );
add_filter(
	'plugin_locale',
	static function ( $locale, $domain ) {
		if ( 'woocommerce' === $domain || 0 === strpos( (string) $domain, 'woocommerce-' ) ) {
			return 'de_DE';
		}
		return $locale;
	},
	5,
	2
);

add_action(
	'woocommerce_email_header',
	static function () {
		if ( function_exists( 'switch_to_locale' ) ) {
			switch_to_locale( 'de_DE' );
		}
	},
	0
);

add_filter(
	'woocommerce_email_heading_new_order',
	static function () {
		return 'Neue Bestellung';
	}
);
add_filter(
	'woocommerce_email_heading_cancelled_order',
	static function () {
		return 'Bestellung storniert';
	}
);
add_filter(
	'woocommerce_email_heading_failed_order',
	static function () {
		return 'Zahlung fehlgeschlagen';
	}
);
add_filter(
	'woocommerce_email_heading_customer_processing_order',
	static function () {
		return 'Wir haben Ihre Bestellung erhalten';
	}
);
add_filter(
	'woocommerce_email_heading_customer_completed_order',
	static function () {
		return 'Ihre Bestellung ist abgeschlossen';
	}
);
add_filter(
	'woocommerce_email_heading_customer_on_hold_order',
	static function () {
		return 'Ihre Bestellung wartet auf Zahlung';
	}
);

add_filter(
	'woocommerce_email_subject_new_order',
	static function ( $subject, $order ) {
		$id = is_object( $order ) && method_exists( $order, 'get_order_number' ) ? $order->get_order_number() : '';
		return $id ? 'Neue Bestellung #' . $id : 'Neue Bestellung';
	},
	20,
	2
);

add_action(
	'woocommerce_email_before_order_table',
	static function ( $order, $sent_to_admin ) {
		if ( ! $sent_to_admin || ! is_object( $order ) ) {
			return;
		}
		$name  = method_exists( $order, 'get_formatted_billing_full_name' )
			? trim( (string) $order->get_formatted_billing_full_name() )
			: '';
		$total = method_exists( $order, 'get_formatted_order_total' )
			? wp_strip_all_tags( (string) $order->get_formatted_order_total() )
			: '';
		echo '<p style="margin:0 0 18px;color:#34465e;font:400 15px/1.65 Arial,sans-serif;">';
		echo esc_html(
			( $name ? $name . ' hat soeben im Shop bestellt.' : 'Soeben ist eine neue Bestellung eingegangen.' )
			. ( $total ? ' Betrag: ' . $total . '.' : '' )
		);
		echo '</p>';
	},
	5,
	2
);

add_filter(
	'woocommerce_email_order_details_heading',
	static function ( $heading, $sent_to_admin ) {
		return $sent_to_admin ? 'Bestellübersicht' : 'Ihre Bestellung';
	},
	20,
	2
);

add_filter(
	'woocommerce_email_customer_details_header',
	static function () {
		return 'Kundendaten';
	}
);

add_filter(
	'woocommerce_email_order_items_args',
	static function ( $args ) {
		$args['show_image'] = false;
		$args['show_sku']   = false;
		return $args;
	}
);

add_filter(
	'woocommerce_mail_content',
	static function ( $content ) {
		$content = preg_replace( '#<div class="email-introduction">.*?</div>#si', '', (string) $content );
		return $content;
	},
	20
);

add_filter(
	'woocommerce_get_order_item_totals',
	static function ( $totals ) {
		if ( isset( $totals['payment_method']['value'] ) && preg_match( '/credit|debit|karte/i', (string) $totals['payment_method']['value'] ) ) {
			$totals['payment_method']['value'] = 'Kreditkarte / Debitkarte';
		}
		return $totals;
	},
	20
);

add_filter( 'woocommerce_email_footer_text', '__return_empty_string', 20 );
add_filter( 'woocommerce_email_additional_content_new_order', '__return_empty_string', 20 );
add_filter( 'woocommerce_email_additional_content_cancelled_order', '__return_empty_string', 20 );
add_filter( 'woocommerce_email_additional_content_failed_order', '__return_empty_string', 20 );
add_filter( 'woocommerce_email_additional_content_customer_processing_order', '__return_empty_string', 20 );
add_filter( 'woocommerce_email_additional_content_customer_completed_order', '__return_empty_string', 20 );
add_filter( 'woocommerce_email_additional_content_customer_on_hold_order', '__return_empty_string', 20 );

add_filter(
	'gettext',
	static function ( $translated, $text, $domain ) {
		if ( 'woocommerce' !== $domain && 0 !== strpos( (string) $domain, 'woocommerce' ) ) {
			return $translated;
		}
		$map = array(
			'You’ve received the following order from %s:' => 'Neue Bestellung von %s:',
			"You've received the following order from %s:" => 'Neue Bestellung von %s:',
			'You have received an order from %s.'          => 'Neue Bestellung von %s.',
			'Order summary'                                => 'Bestellübersicht',
			'Product'                                      => 'Produkt',
			'Quantity'                                     => 'Anzahl',
			'Price'                                        => 'Preis',
			'Subtotal'                                     => 'Zwischensumme',
			'Total:'                                       => 'Gesamt:',
			'Total'                                        => 'Gesamt',
			'Payment method'                               => 'Zahlungsart',
			'Payment method:'                              => 'Zahlungsart:',
			'Billing address'                              => 'Rechnungsadresse',
			'Shipping address'                             => 'Lieferadresse',
			'Congratulations on the sale!'                 => '',
			'Process your orders on the go. Get the app'   => '',
			'Get the app'                                  => '',
			'Credit / Debit Card'                          => 'Kreditkarte / Debitkarte',
			'Credit/Debit card'                            => 'Kreditkarte / Debitkarte',
			'Placeholder'                                  => '',
		);
		return isset( $map[ $text ] ) ? $map[ $text ] : $translated;
	},
	20,
	3
);

function eg_woocommerce_email_header( $email_heading, $email = null ) {
	$is_admin = is_object( $email ) && method_exists( $email, 'is_customer_email' ) && ! $email->is_customer_email();
	$eyebrow  = $is_admin ? 'Nachricht für das Team' : 'Ihre Bestellung';
	?>
<!doctype html><!-- eg-branded-email -->
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
body{margin:0!important;padding:0!important;background:#f3f6fb;color:#34465e;font-family:Arial,Helvetica,sans-serif}
#wrapper{width:100%;background:#f3f6fb;padding:30px 12px}
#template_container{width:100%;max-width:640px;background:#fff;border:1px solid #dde4ee;border-radius:14px;overflow:hidden}
#template_header{padding:26px 34px 22px;background:#fff;border-bottom:1px solid #e4e9f1}
#template_header h1{margin:18px 0 0;color:#0032a0;font:700 28px/1.2 Georgia,serif;letter-spacing:-.02em}
#body_content_inner{padding:28px 34px 34px;color:#34465e;font:400 14px/1.7 Arial,sans-serif}
#body_content_inner h2,#body_content_inner h3{color:#0032a0;font:700 18px/1.3 Georgia,serif}
#body_content_inner a{color:#0032a0}
#body_content_inner table.td{width:100%;border:0;border-collapse:collapse}
#body_content_inner th.td{padding:11px 6px;background:transparent;color:#647187;font-size:11px;letter-spacing:.06em;text-transform:uppercase;border-bottom:2px solid #0032a0;text-align:left}
#body_content_inner td.td{padding:14px 6px;border:0;border-bottom:1px solid #e4e9f1;vertical-align:middle}
#body_content_inner td.td img,#body_content_inner img.attachment-woocommerce_thumbnail,.eg-email-admin .email-introduction{display:none!important}
#body_content_inner address{padding:18px 0;border:0;border-top:1px solid #e4e9f1;background:#fff;font-style:normal;line-height:1.6}
#template_footer{padding:22px 34px;background:#0032a0;color:#dce7ff;font:400 12px/1.6 Arial,sans-serif}
#template_footer a{color:#fff}
#credit,#footer_wrapper,.mobile-messaging,a[href*="woocommerce.com/mobile"]{display:none!important}
@media only screen and (max-width:660px){#wrapper{padding:0!important}#template_container{border-radius:0!important}#template_header,#body_content_inner,#template_footer{padding-left:20px!important;padding-right:20px!important}}
</style>
</head>
<body class="<?php echo $is_admin ? 'eg-email-admin' : 'eg-email-customer'; ?>" style="margin:0;padding:0;background:#f3f6fb;">
<table role="presentation" id="wrapper" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center">
<table role="presentation" id="template_container" width="640" cellspacing="0" cellpadding="0">
<tr><td style="height:5px;background:linear-gradient(90deg,#0032a0 0 72%,#e1a734 72% 88%,#da291c 88%);font-size:0;line-height:0;">&nbsp;</td></tr>
<tr><td id="template_header">
	<a href="<?php echo esc_url( eg_email_public_url() ); ?>" style="text-decoration:none;">
		<img src="<?php echo esc_url( eg_email_logo_url() ); ?>" width="180" alt="Eurasien Gesellschaft" style="display:block;width:180px;max-width:70%;height:auto;border:0;">
	</a>
	<div style="margin:22px 0 0;color:#b27a0a;font:700 12px/1.4 Arial,sans-serif;letter-spacing:.04em;"><?php echo esc_html( $eyebrow ); ?></div>
	<h1><?php echo esc_html( (string) $email_heading ); ?></h1>
</td></tr>
<tr><td id="template_body"><table role="presentation" id="body_content" width="100%" cellspacing="0" cellpadding="0"><tr><td id="body_content_inner">
	<?php
}

function eg_woocommerce_email_footer() {
	?>
</td></tr></table></td></tr>
<tr><td id="template_footer">
	<strong style="color:#fff;">Eurasien Gesellschaft e. V.</strong><br>
	Unabhängige Plattform für Dialog und Verständigung<br>
	<a href="<?php echo esc_url( eg_email_public_url() ); ?>">Website</a>
	&nbsp;&nbsp;·&nbsp;&nbsp;<a href="<?php echo esc_url( eg_email_public_url( 'datenschutz.html' ) ); ?>">Datenschutz</a>
	&nbsp;&nbsp;·&nbsp;&nbsp;<a href="mailto:kontakt@eurasien-gesellschaft.org">Kontakt</a>
</td></tr>
</table>
<div style="max-width:600px;padding:16px 20px 0;color:#7b8798;font:400 12px/1.5 Arial,sans-serif;text-align:center;">Sie erhalten diese Nachricht, weil eine Bestellung über die Eurasien Gesellschaft eingegangen ist.</div>
</td></tr></table>
</body>
</html>
	<?php
}

/**
 * Send an editorial-style branded sample without creating a transaction.
 */
function eg_send_branded_email_sample( $to, $audience = 'customer' ) {
	$to = sanitize_email( (string) $to );
	if ( ! is_email( $to ) ) {
		return false;
	}

	$is_admin = 'admin' === $audience;
	if ( $is_admin ) {
		$heading = 'Jemand hat sich angemeldet';
		$intro   = 'Fred Darlington ist neu dabei. Eine kurze Übersicht für Sie:';
		$content = eg_email_summary_rows(
			array(
				'Name'    => 'Fred Darlington',
				'Kontakt' => 'freddarlington545@gmail.com',
				'Interesse' => 'Analysen, Veranstaltungen und Dialogformate',
			),
			'admin'
		) . '<p>Wenn Sie möchten, können Sie ihn persönlich begrüßen. Weitere Angaben brauchen Sie dafür nicht.</p>';
		$cta_url  = eg_email_public_url();
		$cta_text = 'Zur Website';
		$eyebrow  = 'Neue Anmeldung';
		$subject  = '[MUSTER] Jemand hat sich angemeldet';
	} else {
		$heading = 'Willkommen bei der Eurasien Gesellschaft';
		$intro   = 'Hallo Fred, schön, dass Sie Teil unseres Netzwerks sind. Wir möchten Ihnen Orientierung geben und den Einstieg so persönlich wie möglich gestalten.';
		$content = '<div style="margin:28px 0 12px;color:#102345;font:700 20px/1.35 Georgia,serif;">Was Sie bei uns erwartet</div>'
			. '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">'
			. '<tr><td style="width:38px;padding:14px 0;border-bottom:1px solid #e4e9f1;color:#e1a734;font:700 20px Georgia,serif;vertical-align:top;">01</td><td style="padding:14px 0;border-bottom:1px solid #e4e9f1;color:#34465e;"><strong style="color:#102345;">Fundierte Perspektiven</strong><br>Analysen und Einordnungen zu Entwicklungen im eurasischen Raum.</td></tr>'
			. '<tr><td style="width:38px;padding:14px 0;border-bottom:1px solid #e4e9f1;color:#e1a734;font:700 20px Georgia,serif;vertical-align:top;">02</td><td style="padding:14px 0;border-bottom:1px solid #e4e9f1;color:#34465e;"><strong style="color:#102345;">Persönliche Begegnungen</strong><br>Einladungen zu Gesprächen, Veranstaltungen und digitalen Formaten.</td></tr>'
			. '<tr><td style="width:38px;padding:14px 0;color:#e1a734;font:700 20px Georgia,serif;vertical-align:top;">03</td><td style="padding:14px 0;color:#34465e;"><strong style="color:#102345;">Ein unabhängiges Netzwerk</strong><br>Dialog über Grenzen und unterschiedliche Positionen hinweg.</td></tr>'
			. '</table>'
			. '<p style="margin:24px 0 0;">Sie erhalten von uns nur relevante Nachrichten zu Ihrem Konto, Ihren Buchungen und ausgewählten Angeboten.</p>';
		$cta_url  = eg_email_public_url( 'analysen.html' );
		$cta_text = 'Aktuelle Analysen entdecken';
		$eyebrow  = 'Persönlich für Sie';
		$subject  = '[MUSTER] Willkommen – Eurasien Gesellschaft';
	}

	$message = eg_email_layout(
		array(
			'eyebrow'   => $eyebrow,
			'heading'   => $heading,
			'preheader' => 'Eine persönliche Nachricht der Eurasien Gesellschaft',
			'intro'     => $intro,
			'content'   => $content,
			'cta_url'   => $cta_url,
			'cta_text'  => $cta_text,
			'admin'     => $is_admin,
		)
	);

	return wp_mail(
		$to,
		$subject,
		$message,
		eg_email_html_headers()
	);
}
