<?php
/**
 * Default page template.
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
			'title' => get_the_title(),
		)
	);
	?>
	<section class="sec">
		<div class="wrap wrap-narrow entry-content">
			<?php the_content(); ?>
		</div>
	</section>
	<?php
endwhile;

get_footer();
