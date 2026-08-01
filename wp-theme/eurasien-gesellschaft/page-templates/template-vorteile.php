<?php
/**
 * Template Name: Mitgliedschaft Vorteile
 * Description: Benefits overview (two participation paths) from prototype #p-mitgliedschaft-vorteile.
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

	get_template_part(
		'template-parts/page-header',
		null,
		array(
			'eyebrow' => eg_bi( 'Mitwirken', 'Get involved' ),
			'title'   => eg_bi( 'Mitwirken, auf zwei Wegen', 'Two ways to take part' ),
			'lead'    => eg_bi(
				'Die Eurasien Gesellschaft bietet zwei unterschiedliche Formen der Teilnahme: Leserzugang und Vereinsmitgliedschaft.',
				'The Eurasien Gesellschaft offers two distinct forms of participation: reader access and association membership.'
			),
		)
	);
	?>
	<div class="eg-migrated entry-content">
		<?php
		$html = eg_load_content_file( 'p-mitgliedschaft-vorteile.html' );
		if ( $html ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			the_content();
		}
		?>
	</div>
	<?php
endwhile;

get_footer();
