<?php
/**
 * Template Name: Migrated prototype content
 * Description: Renders full HTML migrated from the prototype (keeps section layout).
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
	<div class="eg-migrated entry-content">
		<?php the_content(); ?>
	</div>
	<?php
endwhile;

get_footer();
