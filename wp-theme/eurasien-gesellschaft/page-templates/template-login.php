<?php
/**
 * Template Name: Anmelden
 * Description: Login / account entry. Prefer wp-login or a membership plugin shortcode.
 *
 * @package Eurasien_Gesellschaft
 */

get_header();

while ( have_posts() ) :
	the_post();
	get_template_part(
		'template-parts/page-header',
		null,
		array(
			'title' => get_the_title(),
		)
	);
	?>
	<section class="sec" id="p-login">
		<div class="eg-migrated entry-content">
			<?php the_content(); ?>
		</div>
		<div class="wrap wrap-narrow" style="margin-top:32px">
			<?php if ( is_user_logged_in() ) : ?>
				<p><?php eg_bi_e( 'Sie sind angemeldet.', 'You are logged in.' ); ?>
					<a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php eg_bi_e( 'Abmelden', 'Log out' ); ?></a>
				</p>
			<?php else : ?>
				<h2 class="vm-method-h"><?php eg_bi_e( 'WordPress-Anmeldung', 'WordPress login' ); ?></h2>
				<?php
				wp_login_form(
					array(
						'redirect'       => home_url( '/' ),
						'label_username' => __( 'E-Mail oder Benutzername', 'eurasien-gesellschaft' ),
						'label_password' => __( 'Passwort', 'eurasien-gesellschaft' ),
						'label_log_in'   => __( 'Anmelden', 'eurasien-gesellschaft' ),
					)
				);
				?>
			<?php endif; ?>
		</div>
	</section>
	<?php
endwhile;

get_footer();
