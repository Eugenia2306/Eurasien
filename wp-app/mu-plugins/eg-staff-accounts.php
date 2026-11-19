<?php
/**
 * Plugin Name: EG Staff Accounts
 * Description: Welcome e-mail (German) for website team accounts, sent through the admin-only REST route eg/v1/staff-welcome; team members land in the dashboard after login.
 * Author: d4rl1ngt0n
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Roles that count as website team (not members). */
function eg_staff_roles() {
	return array( 'administrator', 'editor', 'author' );
}

function eg_is_staff_user( $user ) {
	return $user instanceof WP_User && (bool) array_intersect( eg_staff_roles(), (array) $user->roles );
}

/**
 * REST (admins only): POST /wp-json/eg/v1/staff-welcome {"user_id": N} sends the welcome e-mail
 * to one team account and reports whether the mail server accepted it.
 */
add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'eg/v1',
			'/staff-welcome',
			array(
				'methods'             => 'POST',
				'permission_callback' => static function () {
					return current_user_can( 'create_users' );
				},
				'args'                => array(
					'user_id' => array(
						'required'          => true,
						'validate_callback' => static function ( $v ) {
							return is_numeric( $v ) && (int) $v > 0;
						},
					),
				),
				'callback'            => static function ( WP_REST_Request $request ) {
					$user = get_userdata( (int) $request['user_id'] );
					if ( ! $user ) {
						return new WP_Error( 'eg_no_user', 'User not found', array( 'status' => 404 ) );
					}
					if ( ! eg_is_staff_user( $user ) ) {
						return new WP_Error( 'eg_not_staff', 'Only team accounts (administrator, editor, author) get this e-mail', array( 'status' => 400 ) );
					}
					$result  = array( 'sent' => null, 'error' => '' );
					$ok      = static function () use ( &$result ) {
						$result['sent'] = true;
					};
					$fail    = static function ( $err ) use ( &$result ) {
						$result['sent']  = false;
						$result['error'] = is_wp_error( $err ) ? $err->get_error_message() : 'unknown';
					};
					add_action( 'wp_mail_succeeded', $ok );
					add_action( 'wp_mail_failed', $fail );
					wp_new_user_notification( $user->ID, null, 'user' );
					remove_action( 'wp_mail_succeeded', $ok );
					remove_action( 'wp_mail_failed', $fail );
					return array(
						'user'  => $user->user_login,
						'sent'  => $result['sent'],
						'error' => $result['error'],
					);
				},
			)
		);
	}
);

/**
 * Set-password links stay valid for 7 days (default 1 day is too short for a welcome e-mail).
 */
add_filter(
	'password_reset_expiration',
	static function () {
		return 7 * DAY_IN_SECONDS;
	}
);

/**
 * Welcome e-mail for team accounts. Member e-mails (PMPro handoff) keep their own text.
 */
add_filter(
	'wp_new_user_notification_email',
	static function ( $email, $user ) {
		if ( ! eg_is_staff_user( $user ) || ! function_exists( 'eg_email_layout' ) ) {
			return $email;
		}
		if ( ! preg_match( '/[?&]key=([^&\s>]+)/', (string) $email['message'], $m ) ) {
			return $email;
		}
		$set_url = add_query_arg(
			array(
				'action' => 'rp',
				'key'    => rawurlencode( $m[1] ),
				'login'  => rawurlencode( $user->user_login ),
			),
			home_url( '/login/' )
		);
		$admin   = in_array( 'administrator', (array) $user->roles, true );
		$name    = trim( $user->first_name ) !== '' ? trim( $user->first_name . ' ' . $user->last_name ) : $user->display_name;
		$login   = admin_url();
		$p       = static function ( $html ) {
			return '<p style="margin:0 0 14px;color:#34465e;font:400 15px/1.65 Arial,sans-serif;">' . $html . '</p>';
		};

		$de = $p( 'Guten Tag ' . esc_html( $name ) . ',' )
			. $p(
				'wir haben für Sie ein Konto auf der Website der Eurasien Gesellschaft (WordPress) angelegt. '
				. ( $admin
					? 'Als Administrator können Sie Inhalte bearbeiten und die Website verwalten (Benutzer, Einstellungen).'
					: 'Damit können Sie Veranstaltungen, Beiträge und Seiten anlegen und bearbeiten.' )
			)
			. $p( 'Ihr Benutzername: <strong>' . esc_html( $user->user_login ) . '</strong>' )
			. $p( 'Bei Ihrer ersten Anmeldung legen Sie Ihr eigenes Passwort fest. Klicken Sie dazu auf diesen Button (der Link ist 7 Tage gültig):' )
			. '<table role="presentation" cellspacing="0" cellpadding="0" style="margin:8px 0 18px;"><tr><td style="border-radius:8px;background:#da291c;">'
			. '<a href="' . esc_url( $set_url ) . '" style="display:inline-block;padding:14px 22px;color:#ffffff;font:800 14px/1.2 Arial,sans-serif;text-decoration:none;">Passwort festlegen</a></td></tr></table>'
			. $p( 'Danach melden Sie sich jederzeit mit Benutzername und Passwort hier an: <a href="' . esc_url( $login ) . '">' . esc_html( $login ) . '</a>' )
			. $p( 'Ist der Link abgelaufen, klicken Sie auf der Anmeldeseite auf „Passwort vergessen?“. Bei Fragen: <a href="mailto:kontakt@eurasien-gesellschaft.org">kontakt@eurasien-gesellschaft.org</a>' );

		$email['subject'] = 'Ihr Konto auf der Website der Eurasien Gesellschaft';
		$email['message'] = eg_email_layout(
			array(
				'eyebrow'   => 'Website-Zugang',
				'heading'   => 'Ihr Konto auf der Website',
				'preheader' => 'Ihr Benutzername: ' . $user->user_login . '. Bitte legen Sie Ihr Passwort fest.',
				'content'   => $de,
			)
		);
		$email['headers'] = function_exists( 'eg_email_html_headers' ) ? eg_email_html_headers( $email['headers'] ) : $email['headers'];
		return $email;
	},
	20,
	2
);

/**
 * Team members go to the dashboard after login (members keep landing in their account).
 */
add_filter(
	'login_redirect',
	static function ( $redirect_to, $requested, $user ) {
		if ( ! eg_is_staff_user( $user ) ) {
			return $redirect_to;
		}
		if ( empty( $requested ) || false !== strpos( (string) $redirect_to, '/membership-account/' ) ) {
			return admin_url();
		}
		return $requested;
	},
	100,
	3
);
