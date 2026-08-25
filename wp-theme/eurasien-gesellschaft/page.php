<?php
/**
 * Default page template — same page-head pattern as the brochure.
 *
 * @package Eurasien_Gesellschaft
 */

get_header();

while ( have_posts() ) :
	the_post();

	if ( function_exists( 'eg_is_elementor_page' ) && eg_is_elementor_page() ) {
		?>
		<main id="content" class="eg-elementor-canvas">
			<?php the_content(); ?>
		</main>
		<?php
		continue;
	}

	$is_auth = function_exists( 'eg_is_app_auth_page' ) && eg_is_app_auth_page();
	$is_app  = function_exists( 'eg_is_app_utility_page' ) && eg_is_app_utility_page();
	$slug    = (string) get_post_field( 'post_name', get_the_ID() );

	$header_args = array(
		'title' => get_the_title(),
	);

	/* Slight context only — same phead pattern as static brochure pages. */
	if ( $is_app ) {
		$header_args['eyebrow'] = eg_bi( 'Mitgliederbereich', "Members' area" );
	}
	if ( $is_auth && in_array( $slug, array( 'login', 'log-in' ), true ) ) {
		$header_args['title'] = eg_bi( 'Anmelden', 'Log In' );
		$header_args['lead']  = eg_bi(
			'Melden Sie sich an, um Ihr Konto und den Mitgliederbereich zu öffnen.',
			'Sign in to open your account and the members’ area.'
		);
	}

	get_template_part( 'template-parts/page-header', null, $header_args );

	$sec_class = 'sec';
	if ( $is_auth ) {
		$sec_class .= ' sec--app-auth';
	} elseif ( $is_app ) {
		$sec_class .= ' sec--app';
	}
	?>
	<section class="<?php echo esc_attr( $sec_class ); ?>">
		<div class="wrap <?php echo $is_auth ? 'wrap--auth' : 'wrap-narrow'; ?> entry-content">
			<?php the_content(); ?>
			<?php if ( $is_auth && in_array( $slug, array( 'login', 'log-in' ), true ) && ! is_user_logged_in() ) : ?>
				<p class="eg-auth-aside">
					<?php eg_bi_e( 'Noch kein Zugang?', 'No account yet?' ); ?>
					<a href="<?php echo esc_url( function_exists( 'eg_membership_signup_url' ) ? eg_membership_signup_url() : '/mitgliedschaft.html#membership-registration' ); ?>" data-analytics="membership_click">
						<?php eg_bi_e( 'Mitglied werden', 'Become a member' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
	</section>
	<?php
endwhile;

get_footer();
