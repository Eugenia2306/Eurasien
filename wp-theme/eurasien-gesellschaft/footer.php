</main>

<?php
$eg_slim_ft = function_exists( 'eg_is_app_utility_page' ) && eg_is_app_utility_page();
$brochure   = function_exists( 'eg_brochure_public_url' ) ? eg_brochure_public_url() : home_url( '/' );
$impressum  = function_exists( 'eg_route' ) ? eg_route( 'p-impressum' ) : $brochure . 'impressum.html';
$datenschutz = function_exists( 'eg_brochure_public_url' )
	? eg_brochure_public_url( 'datenschutz.html' )
	: $brochure . 'datenschutz.html';
?>

<?php if ( $eg_slim_ft ) : ?>
<footer class="ft ft--app">
	<div class="wrap ft--app__in">
		<p class="ft--app__brand"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
		<p class="ft--app__links">
			<a href="<?php echo esc_url( $brochure ); ?>"><?php eg_bi_e( 'Zur Website', 'To the website' ); ?></a>
			<span aria-hidden="true">·</span>
			<a href="<?php echo esc_url( $impressum ); ?>">Impressum</a>
			<span aria-hidden="true">·</span>
			<a href="<?php echo esc_url( $datenschutz ); ?>"><?php eg_bi_e( 'Datenschutz', 'Privacy' ); ?></a>
		</p>
		<p class="ft--app__copy">&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> Eurasien Gesellschaft e. V. · Berlin</p>
	</div>
</footer>
<?php else : ?>
<footer class="ft">
	<div class="wrap">
		<div class="ft__grid">
			<div>
				<div class="ft__logo" style="margin-bottom:12px">
					<img src="<?php echo esc_url( eg_logo_url() ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" width="303" height="78" decoding="async">
				</div>
				<p style="color:#9fb0c4;font-size:.9rem;max-width:34ch;margin:0 0 6px">
					<?php eg_bi_e( 'Unabhängige, gemeinnützige Plattform für Dialog und Verständigung im eurasischen Raum.', 'Independent non-profit platform for dialogue and understanding in the Eurasian space.' ); ?>
				</p>
				<div class="ft__soc">
					<a href="https://www.youtube.com/@EurasienGesellschaft" target="_blank" rel="noopener noreferrer">YouTube</a>
					<a href="https://www.linkedin.com/company/eurasien-gesellschaft/" target="_blank" rel="noopener noreferrer">LinkedIn</a>
					<a href="https://t.me/EurasienGesellschaft" target="_blank" rel="noopener noreferrer">Telegram</a>
					<a href="mailto:kontakt@eurasien-gesellschaft.org">E-Mail</a>
				</div>
			</div>
			<div>
				<h4><?php eg_bi_e( 'Über uns', 'About us' ); ?></h4>
				<?php
				if ( has_nav_menu( 'footer_about' ) ) {
					wp_nav_menu(
						array(
							'theme_location' => 'footer_about',
							'container'      => false,
							'depth'          => 1,
							'fallback_cb'    => false,
						)
					);
				} else {
					?>
					<a href="<?php echo esc_url( eg_route( 'p-mission' ) ); ?>">Mission</a>
					<a href="<?php echo esc_url( eg_route( 'p-vorstand' ) ); ?>"><?php eg_bi_e( 'Vorstand & Experten', 'Board & Experts' ); ?></a>
					<a href="<?php echo esc_url( eg_route( 'p-partner' ) ); ?>"><?php eg_bi_e( 'Partner', 'Partners' ); ?></a>
					<a href="<?php echo esc_url( eg_route( 'p-news' ) ); ?>"><?php eg_bi_e( 'Gesellschaftsnachrichten', 'Society News' ); ?></a>
					<?php
				}
				?>
			</div>
			<div>
				<h4><?php eg_bi_e( 'Themen & Analysen', 'Topics & Analysis' ); ?></h4>
				<?php if ( has_nav_menu( 'footer_topics' ) ) : ?>
					<?php
					wp_nav_menu(
						array(
							'theme_location' => 'footer_topics',
							'container'      => false,
							'depth'          => 1,
							'fallback_cb'    => false,
						)
					);
					?>
				<?php else : ?>
					<a href="<?php echo esc_url( eg_route( 'p-themen' ) ); ?>"><?php eg_bi_e( 'Themen', 'Topics' ); ?></a>
					<a href="<?php echo esc_url( eg_route( 'p-kultur' ) ); ?>"><?php eg_bi_e( 'Kultur', 'Culture' ); ?></a>
					<a href="<?php echo esc_url( eg_route( 'p-laender' ) ); ?>"><?php eg_bi_e( 'Länder & Gesellschaften', 'Countries & Societies' ); ?></a>
					<a href="<?php echo esc_url( eg_route( 'p-regionen' ) ); ?>"><?php eg_bi_e( 'Regionen', 'Regions' ); ?></a>
					<a href="<?php echo esc_url( eg_route( 'p-analysen' ) ); ?>"><?php eg_bi_e( 'Aktuelles & Studien', 'Current Affairs & Studies' ); ?></a>
				<?php endif; ?>
			</div>
			<div>
				<h4>Service</h4>
				<?php if ( has_nav_menu( 'footer_service' ) ) : ?>
					<?php
					wp_nav_menu(
						array(
							'theme_location' => 'footer_service',
							'container'      => false,
							'depth'          => 1,
							'fallback_cb'    => false,
						)
					);
					?>
				<?php else : ?>
					<a href="<?php echo esc_url( eg_route( 'p-veranstaltungen' ) ); ?>"><?php eg_bi_e( 'Veranstaltungen', 'Events' ); ?></a>
					<a href="<?php echo esc_url( eg_route( 'p-mediathek' ) ); ?>"><?php eg_bi_e( 'Mediathek', 'Media Library' ); ?></a>
					<a href="<?php echo esc_url( eg_route( 'p-mitgliedschaft' ) ); ?>#membership-registration" data-analytics="membership_click"><?php eg_bi_e( 'Mitglied werden', 'Become a member' ); ?></a>
					<a href="<?php echo esc_url( function_exists( 'eg_member_login_url' ) ? eg_member_login_url() : eg_route( 'p-login' ) ); ?>" data-analytics="login_click"><?php eg_bi_e( 'Anmelden', 'Login' ); ?></a>
					<a href="<?php echo esc_url( eg_route( 'p-impressum' ) ); ?>">Impressum</a>
				<?php endif; ?>
			</div>
		</div>
		<div class="ft__b">
			<span>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> Eurasien Gesellschaft e. V. · Berlin</span>
			<span>
				<a href="<?php echo esc_url( eg_route( 'p-impressum' ) ); ?>">Impressum</a> ·
				<a href="<?php echo esc_url( $datenschutz ); ?>"><?php eg_bi_e( 'Datenschutz', 'Privacy' ); ?></a>
			</span>
		</div>
	</div>
</footer>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
