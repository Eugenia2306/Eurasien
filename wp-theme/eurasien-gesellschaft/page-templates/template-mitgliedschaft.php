<?php
/**
 * Template Name: Mitgliedschaft
 * Description: Membership landing. Wire payment forms via MemberPress / WooCommerce later.
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
			'eyebrow' => eg_bi( 'Mitwirken', 'Get involved' ),
			'title'   => get_the_title(),
			'lead'    => eg_bi(
				'Registrierung und Antrag für Leserzugang oder Vereinsmitgliedschaft.',
				'Registration and application for reader access or association membership.'
			),
		)
	);
	?>
	<section class="sec" id="p-mitgliedschaft">
		<div class="wrap entry-content">
			<?php the_content(); ?>
			<?php if ( ! get_the_content() || false !== strpos( (string) get_post()->post_content, 'HTML-Prototyp' ) ) : ?>
				<div class="grid grid-2" style="margin-top:24px">
					<div class="card">
						<span class="card__k"><?php eg_bi_e( 'Nächster Schritt', 'Next step' ); ?></span>
						<h3 class="card__t"><?php eg_bi_e( 'Zahlungen anbinden', 'Connect payments' ); ?></h3>
						<p class="muted"><?php eg_bi_e(
							'Empfohlen: MemberPress oder WooCommerce Memberships für Beiträge, SEPA und Mitglieder-Login. Das Prototyp-Formular (vm-*) kann als Designvorlage dienen.',
							'Recommended: MemberPress or WooCommerce Memberships for dues, SEPA and member login. The prototype form (vm-*) can serve as a design reference.'
						); ?></p>
					</div>
					<div class="card">
						<span class="card__k"><?php eg_bi_e( 'Gesperrte Inhalte', 'Gated content' ); ?></span>
						<h3 class="card__t"><?php eg_bi_e( 'Positionen, Dossiers, Studien', 'Positions, dossiers, studies' ); ?></h3>
						<p class="muted"><?php eg_bi_e(
							'Diese Formate sind im Prototyp mit Locked-Gates versehen. Im Theme als Format-Taxonomie + Membership-Plugin absichern.',
							'These formats use locked gates in the prototype. Protect them in the theme via the format taxonomy plus a membership plugin.'
						); ?></p>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</section>
	<?php
endwhile;

get_footer();
