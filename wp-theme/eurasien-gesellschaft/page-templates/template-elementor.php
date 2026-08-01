<?php
/**
 * Template Name: Elementor Full Width
 * Description: Full-width content area for Elementor. No page header chrome.
 *
 * @package Eurasien_Gesellschaft
 */

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<main id="content" class="eg-elementor-canvas">
		<?php the_content(); ?>
	</main>
	<?php
endwhile;

get_footer();
