<?php
/**
 * Template Name: Content page (Mission-style)
 * Description: Navy page head + narrow content column.
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
			'lead'  => has_excerpt() ? get_the_excerpt() : '',
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
