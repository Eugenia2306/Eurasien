<?php
/**
 * Members /app/ header context and helpers.
 *
 * @package Eurasien_Gesellschaft
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the current front-end view is an /app/ auth or membership utility page.
 */
function eg_is_app_utility_page(): bool {
	if ( is_admin() || ! did_action( 'wp' ) ) {
		return false;
	}
	$slugs = array(
		'login',
		'log-in',
		'membership-account',
		'membership-levels',
		'membership-checkout',
		'membership-confirmation',
		'membership-billing',
		'membership-cancel',
		'membership-orders',
		'your-profile',
		'my-account',
		'cart',
		'checkout',
		'shop',
		'mitglieder',
		'positionen',
		'dossiers',
		'studien',
	);
	if ( is_singular() ) {
		$slug = (string) get_post_field( 'post_name', get_queried_object_id() );
		if ( in_array( $slug, $slugs, true ) ) {
			return true;
		}
		$parent = (int) wp_get_post_parent_id( get_queried_object_id() );
		if ( $parent > 0 ) {
			$parent_slug = (string) get_post_field( 'post_name', $parent );
			if ( 'mitglieder' === $parent_slug ) {
				return true;
			}
		}
	}
	return false;
}

/**
 * Auth-focused pages (login / reset / checkout) get a quieter chrome than libraries.
 */
function eg_is_app_auth_page(): bool {
	if ( ! eg_is_app_utility_page() ) {
		return false;
	}
	$slug = is_singular() ? (string) get_post_field( 'post_name', get_queried_object_id() ) : '';
	$auth = array(
		'login',
		'log-in',
		'membership-checkout',
		'membership-confirmation',
		'membership-billing',
		'membership-cancel',
		'membership-levels',
		'cart',
		'checkout',
		'my-account',
	);
	return in_array( $slug, $auth, true );
}

/**
 * Context for template-parts/header-app.php.
 *
 * @return array<string, mixed>
 */
function eg_app_header_context(): array {
	$logged_in      = is_user_logged_in();
	$user           = $logged_in ? wp_get_current_user() : null;
	$has_membership = $logged_in
		&& function_exists( 'pmpro_hasMembershipLevel' )
		&& pmpro_hasMembershipLevel();

	$brochure_home = function_exists( 'eg_brochure_public_url' )
		? eg_brochure_public_url()
		: '/';
	$account       = home_url( '/membership-account/' );
	$login         = function_exists( 'eg_member_login_url' )
		? eg_member_login_url( $account )
		: wp_login_url( $account );
	$logout        = function_exists( 'eg_member_logout_url' )
		? eg_member_logout_url( '/' )
		: wp_logout_url( '/' );
	$signup        = function_exists( 'eg_membership_signup_url' )
		? eg_membership_signup_url()
		: '/mitgliedschaft.html#membership-registration';

	$display_name = '';
	if ( $logged_in && $user instanceof WP_User ) {
		$display_name = $user->display_name ? $user->display_name : $user->user_email;
	}

	return array(
		'logged_in'      => $logged_in,
		'has_membership' => $has_membership,
		'display_name'   => $display_name,
		'brochure_home'  => $brochure_home,
		'account'        => $account,
		'login'          => $login,
		'logout'         => $logout,
		'signup'         => $signup,
		'members_nav'    => array(
			array(
				'slug'  => 'positionen',
				'label' => array( 'Positionen', 'Positions' ),
				'url'   => home_url( '/mitglieder/positionen/' ),
			),
			array(
				'slug'  => 'dossiers',
				'label' => array( 'Dossiers', 'Dossiers' ),
				'url'   => home_url( '/mitglieder/dossiers/' ),
			),
			array(
				'slug'  => 'studien',
				'label' => array( 'Studien', 'Studies' ),
				'url'   => home_url( '/mitglieder/studien/' ),
			),
		),
	);
}

/**
 * Active state for a members sub-page link.
 */
function eg_app_nav_is_active( string $slug ): bool {
	if ( ! is_page() ) {
		return false;
	}
	$qid = get_queried_object_id();
	if ( ! $qid ) {
		return false;
	}
	$page_slug = (string) get_post_field( 'post_name', $qid );
	if ( $page_slug === $slug ) {
		return true;
	}
	$parent_id = (int) wp_get_post_parent_id( $qid );
	if ( $parent_id <= 0 ) {
		return false;
	}
	$parent_slug = (string) get_post_field( 'post_name', $parent_id );
	return 'mitglieder' === $parent_slug && $page_slug === $slug;
}
