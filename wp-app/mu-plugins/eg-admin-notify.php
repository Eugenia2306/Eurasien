<?php
/**
 * Plugin Name: EG Admin notification recipients
 * Description: Also send WordPress, WooCommerce, PMPro and event admin notices to kontakt@eurasien-gesellschaft.org.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Addresses that should receive operational notifications.
 *
 * @return list<string>
 */
function eg_admin_notification_recipients(): array {
	$emails = array(
		'kontakt@eurasien-gesellschaft.org',
		(string) get_option( 'admin_email' ),
	);
	$emails = array_map( 'sanitize_email', $emails );
	$emails = array_values( array_unique( array_filter( $emails, 'is_email' ) ) );
	return $emails;
}

function eg_admin_notification_recipient_string(): string {
	return implode( ', ', eg_admin_notification_recipients() );
}

function eg_mail_list_includes_admin( $to ): bool {
	$admin = sanitize_email( (string) get_option( 'admin_email' ) );
	if ( ! is_email( $admin ) ) {
		return false;
	}
	$tos = is_array( $to ) ? $to : explode( ',', (string) $to );
	foreach ( $tos as $addr ) {
		$addr = sanitize_email( (string) $addr );
		if ( strcasecmp( $addr, $admin ) === 0 ) {
			return true;
		}
	}
	return false;
}

/**
 * If mail is going to the WP admin inbox, also send it to kontakt@.
 *
 * @param array<string,mixed> $args
 * @return array<string,mixed>
 */
function eg_wp_mail_add_kontakt( array $args ): array {
	if ( ! eg_mail_list_includes_admin( $args['to'] ?? '' ) ) {
		return $args;
	}
	$tos = is_array( $args['to'] ) ? $args['to'] : array_map( 'trim', explode( ',', (string) $args['to'] ) );
	foreach ( eg_admin_notification_recipients() as $extra ) {
		$have = false;
		foreach ( $tos as $addr ) {
			if ( strcasecmp( sanitize_email( (string) $addr ), $extra ) === 0 ) {
				$have = true;
				break;
			}
		}
		if ( ! $have ) {
			$tos[] = $extra;
		}
	}
	$args['to'] = $tos;
	return $args;
}
add_filter( 'wp_mail', 'eg_wp_mail_add_kontakt', 20 );

function eg_append_kontakt_recipient( $recipient, $object = null ) {
	unset( $object );
	$list = array_filter( array_map( 'trim', explode( ',', (string) $recipient ) ) );
	foreach ( eg_admin_notification_recipients() as $extra ) {
		$have = false;
		foreach ( $list as $addr ) {
			if ( strcasecmp( sanitize_email( $addr ), $extra ) === 0 ) {
				$have = true;
				break;
			}
		}
		if ( ! $have ) {
			$list[] = $extra;
		}
	}
	return implode( ', ', $list );
}

add_filter( 'woocommerce_email_recipient_new_order', 'eg_append_kontakt_recipient', 20, 2 );
add_filter( 'woocommerce_email_recipient_cancelled_order', 'eg_append_kontakt_recipient', 20, 2 );
add_filter( 'woocommerce_email_recipient_failed_order', 'eg_append_kontakt_recipient', 20, 2 );

add_filter(
	'pmpro_email_recipient',
	static function ( $email, $pmpro_email = null ) {
		$admin = sanitize_email( (string) get_option( 'admin_email' ) );
		$template = is_object( $pmpro_email ) ? (string) ( $pmpro_email->template ?? '' ) : '';
		$is_admin = ( strpos( $template, 'admin' ) !== false )
			|| ( is_email( $admin ) && strcasecmp( sanitize_email( (string) $email ), $admin ) === 0 );
		if ( ! $is_admin ) {
			return $email;
		}
		return eg_append_kontakt_recipient( $email );
	},
	20,
	2
);
