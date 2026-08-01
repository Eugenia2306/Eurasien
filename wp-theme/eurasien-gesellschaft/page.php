<?php
/**
 * Default page template.
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
	<section class="sec">
		<div class="wrap wrap-narrow entry-content">
			<?php the_content(); ?>
		</div>
	</section>
	<?php
endwhile;

get_footer();
