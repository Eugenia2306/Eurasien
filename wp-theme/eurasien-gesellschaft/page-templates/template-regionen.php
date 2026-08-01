<?php
/**
 * Template Name: Regionen
 * Description: Interactive regions explorer from the HTML prototype. Loads markup from theme/content so SVG is not stripped by WordPress.
 *
 * @package Eurasien_Gesellschaft
 */

get_header();

while ( have_posts() ) :
	the_post();

	// Elementor owns this page: render builder output instead of the file-based map.
	if ( function_exists( 'eg_is_elementor_page' ) && eg_is_elementor_page() ) {
		?>
		<main id="content" class="eg-elementor-canvas">
			<?php the_content(); ?>
		</main>
		<?php
		continue;
	}

	get_template_part(
		'template-parts/page-header',
		null,
		array(
			'eyebrow' => eg_bi( 'Geografie', 'Geography' ),
			'title'   => eg_bi( 'Regionen', 'Regions' ),
			'lead'    => eg_bi(
				'Der eurasische Raum in sieben Makroregionen. Karte und Liste sind interaktiv.',
				'The Eurasian space in seven macro-regions. Map and list are interactive.'
			),
		)
	);

	$html = eg_load_content_file( 'p-regionen.html' );
	if ( $html ) {
		// Theme-owned markup (includes inline SVG + #p-regionen). Do not kses-strip SVG.
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	} else {
		?>
		<section class="sec" id="p-regionen">
			<div class="wrap">
				<p class="empty"><?php eg_bi_e(
					'Regionen-Inhalt fehlt (content/p-regionen.html). Theme erneut hochladen.',
					'Regions content missing (content/p-regionen.html). Re-upload the theme.'
				); ?></p>
			</div>
		</section>
		<?php
	}
endwhile;

get_footer();
