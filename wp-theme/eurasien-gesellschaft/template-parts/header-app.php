<?php
/**
 * Unified members-area header for /app/ (replaces ubar + eg-app-bar + brochure mega-nav).
 *
 * @package Eurasien_Gesellschaft
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ctx = eg_app_header_context();
?>
<header class="hd hd--app">
	<div class="hd__in">
		<div class="hd__brand-group">
			<a class="brand" href="<?php echo esc_url( $ctx['brochure_home'] ); ?>" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) . ', Startseite' ); ?>">
				<img class="brand__logo" src="<?php echo esc_url( eg_logo_url() ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" width="303" height="78" decoding="async">
			</a>
			<span class="hd__badge"><?php eg_bi_e( 'Mitgliederbereich', "Members' area" ); ?></span>
		</div>

		<?php if ( $ctx['logged_in'] && $ctx['has_membership'] ) : ?>
			<nav class="nav app-nav" aria-label="<?php esc_attr_e( 'Mitgliederbereich', 'eurasien-gesellschaft' ); ?>">
				<?php foreach ( $ctx['members_nav'] as $item ) : ?>
					<a
						class="app-nav__link<?php echo eg_app_nav_is_active( $item['slug'] ) ? ' is-current' : ''; ?>"
						href="<?php echo esc_url( $item['url'] ); ?>"
					><?php eg_bi_e( $item['label'][0], $item['label'][1] ); ?></a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>

		<div class="actions actions--app">
			<?php if ( $ctx['logged_in'] ) : ?>
				<span class="app-user" title="<?php echo esc_attr( $ctx['display_name'] ); ?>"><?php echo esc_html( $ctx['display_name'] ); ?></span>
				<a class="app-link app-link--primary" href="<?php echo esc_url( $ctx['account'] ); ?>" data-analytics="account_click"><?php eg_bi_e( 'Mein Konto', 'My Account' ); ?></a>
				<?php if ( $ctx['has_membership'] ) : ?>
					<a class="app-link app-link--optional" href="<?php echo esc_url( $ctx['account'] . '#eg-plan-change' ); ?>"><?php eg_bi_e( 'Plan ändern', 'Change plan' ); ?></a>
				<?php else : ?>
					<a class="app-link app-link--optional" href="<?php echo esc_url( $ctx['signup'] ); ?>" data-analytics="membership_click"><?php eg_bi_e( 'Mitglied werden', 'Become a member' ); ?></a>
				<?php endif; ?>
				<a class="app-link app-link--logout" href="<?php echo esc_url( $ctx['logout'] ); ?>" data-analytics="logout_click"><?php eg_bi_e( 'Abmelden', 'Logout' ); ?></a>
			<?php else : ?>
				<a class="btn btn--accent btn--sm" href="<?php echo esc_url( $ctx['login'] ); ?>" data-analytics="login_click"><?php eg_bi_e( 'Anmelden', 'Log In' ); ?></a>
			<?php endif; ?>

			<span class="lang" role="group" aria-label="Sprache / Language" translate="no">
				<button type="button" data-lang="de" aria-pressed="true" translate="no">DE</button>
				<button type="button" data-lang="en" aria-pressed="false" translate="no">EN</button>
			</span>

			<a class="app-back" href="<?php echo esc_url( $ctx['brochure_home'] ); ?>">
				<span aria-hidden="true">←</span>
				<?php eg_bi_e( 'Zur Website', 'To the website' ); ?>
			</a>

			<?php if ( $ctx['logged_in'] && $ctx['has_membership'] ) : ?>
				<button class="burger" type="button" aria-label="<?php esc_attr_e( 'Menü', 'eurasien-gesellschaft' ); ?>" aria-expanded="false"><span></span><span></span><span></span></button>
			<?php endif; ?>
		</div>
	</div>
</header>
