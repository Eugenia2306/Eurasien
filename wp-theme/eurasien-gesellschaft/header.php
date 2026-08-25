<!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip" href="#main"><span class="de">Zum Inhalt springen</span><span class="en" hidden>Skip to content</span></a>

<?php
$ctx = function_exists( 'eg_app_header_context' ) ? eg_app_header_context() : array(
	'logged_in'      => is_user_logged_in(),
	'has_membership' => false,
	'display_name'   => '',
	'brochure_home'  => function_exists( 'eg_brochure_public_url' ) ? eg_brochure_public_url() : home_url( '/' ),
	'account'        => home_url( '/membership-account/' ),
	'login'          => function_exists( 'eg_member_login_url' ) ? eg_member_login_url() : home_url( '/login/' ),
	'logout'         => function_exists( 'eg_member_logout_url' ) ? eg_member_logout_url( '/' ) : wp_logout_url( '/' ),
	'signup'         => function_exists( 'eg_membership_signup_url' ) ? eg_membership_signup_url() : '/mitgliedschaft.html#membership-registration',
);
$home = $ctx['brochure_home'];
?>

<!-- ============ UTILITY BAR (same pattern as brochure) ============ -->
<div class="ubar">
	<div class="ubar__in">
		<span class="ubar__l"><?php eg_bi_e( 'Unabhängige, gemeinnützige Plattform für Dialog und Verständigung im eurasischen Raum', 'Independent non-profit platform for dialogue and understanding in the Eurasian space' ); ?></span>
		<span class="ubar__r">
			<?php if ( ! empty( $ctx['logged_in'] ) ) : ?>
				<a href="<?php echo esc_url( $ctx['account'] ); ?>" data-analytics="account_click"><?php eg_bi_e( 'Mein Konto', 'My Account' ); ?></a>
				<?php if ( ! empty( $ctx['has_membership'] ) ) : ?>
					<a href="<?php echo esc_url( home_url( '/mitglieder/positionen/' ) ); ?>"><?php eg_bi_e( 'Mitgliederbereich', "Members' Area" ); ?></a>
				<?php endif; ?>
				<a href="<?php echo esc_url( $ctx['logout'] ); ?>" data-analytics="logout_click"><?php eg_bi_e( 'Abmelden', 'Logout' ); ?></a>
			<?php else : ?>
				<a href="<?php echo esc_url( $ctx['login'] ); ?>" data-analytics="login_click"><?php eg_bi_e( 'Anmelden', 'Login' ); ?></a>
			<?php endif; ?>
			<span class="lang" role="group" aria-label="Sprache / Language" translate="no">
				<button type="button" data-lang="de" aria-pressed="true" translate="no">DE</button>
				<button type="button" data-lang="en" aria-pressed="false" translate="no">EN</button>
			</span>
		</span>
	</div>
</div>

<!-- ============ MASTHEAD (same pattern as brochure) ============ -->
<header class="hd">
	<div class="hd__in">
		<a class="brand" href="<?php echo esc_url( $home ); ?>" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) . ', Startseite' ); ?>">
			<img class="brand__logo" src="<?php echo esc_url( eg_logo_url() ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" width="303" height="78" decoding="async">
		</a>

		<?php if ( has_nav_menu( 'primary' ) ) : ?>
			<nav class="nav" aria-label="<?php esc_attr_e( 'Hauptnavigation', 'eurasien-gesellschaft' ); ?>">
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'container'      => false,
						'menu_class'     => 'nav menu',
						'depth'          => 2,
						'fallback_cb'    => false,
					)
				);
				?>
			</nav>
		<?php else : ?>
			<?php get_template_part( 'template-parts/nav', 'fallback' ); ?>
		<?php endif; ?>

		<div class="actions">
			<?php get_search_form(); ?>
			<?php if ( ! empty( $ctx['logged_in'] ) ) : ?>
				<a class="btn btn--accent btn--sm" href="<?php echo esc_url( $ctx['account'] ); ?>" data-analytics="account_click"><?php eg_bi_e( 'Mein Konto', 'My Account' ); ?></a>
			<?php else : ?>
				<a class="btn btn--accent btn--sm" href="<?php echo esc_url( $ctx['signup'] ); ?>" data-analytics="membership_click"><?php eg_bi_e( 'Mitglied werden', 'Become a member' ); ?></a>
			<?php endif; ?>
			<button class="burger" type="button" aria-label="<?php esc_attr_e( 'Menü', 'eurasien-gesellschaft' ); ?>" aria-expanded="false"><span></span><span></span><span></span></button>
		</div>
	</div>
</header>

<main id="main">
