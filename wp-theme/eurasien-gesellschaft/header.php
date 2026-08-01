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

<div class="ubar">
	<div class="ubar__in">
		<span class="ubar__l"><?php eg_bi_e( 'Unabhängige, gemeinnützige Plattform für Dialog und Verständigung im eurasischen Raum', 'Independent non-profit platform for dialogue and understanding in the Eurasian space' ); ?></span>
		<span class="ubar__r">
			<a href="<?php echo esc_url( eg_route( 'p-login' ) ); ?>" data-analytics="login_click"><?php eg_bi_e( 'Anmelden', 'Login' ); ?></a>
			<span class="lang" role="group" aria-label="Sprache / Language">
				<button type="button" data-lang="de" aria-pressed="true">DE</button>
				<button type="button" data-lang="en" aria-pressed="false">EN</button>
			</span>
		</span>
	</div>
</div>

<header class="hd">
	<div class="hd__in">
		<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) . ', Startseite' ); ?>">
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
			<a class="btn btn--accent btn--sm" href="<?php echo esc_url( eg_route( 'p-mitgliedschaft-vorteile' ) ); ?>" data-analytics="membership_click"><?php eg_bi_e( 'Mitglied werden', 'Become a member' ); ?></a>
			<button class="burger" type="button" aria-label="<?php esc_attr_e( 'Menü', 'eurasien-gesellschaft' ); ?>" aria-expanded="false"><span></span><span></span><span></span></button>
		</div>
	</div>
</header>

<main id="main">
