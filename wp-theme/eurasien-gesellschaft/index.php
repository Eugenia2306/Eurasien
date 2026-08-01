<?php
/**
 * Blog / fallback index (Gesellschaftsnachrichten).
 *
 * @package Eurasien_Gesellschaft
 */

get_header();

get_template_part(
	'template-parts/page-header',
	null,
	array(
		'eyebrow' => eg_bi( 'Nachrichten', 'News' ),
		'title'   => eg_bi( 'Gesellschaftsnachrichten', 'Society News' ),
		'lead'    => eg_bi( 'Neuigkeiten aus der Eurasien Gesellschaft.', 'News from the Eurasien Gesellschaft.' ),
	)
);
?>

<section class="sec">
	<div class="wrap">
		<div class="grid grid-3">
			<?php if ( have_posts() ) : ?>
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/content', 'card' );
				endwhile;
				?>
			<?php else : ?>
				<p class="empty"><?php eg_bi_e( 'Keine Beiträge gefunden.', 'No posts found.' ); ?></p>
			<?php endif; ?>
		</div>
		<?php the_posts_pagination(); ?>
	</div>
</section>

<?php
get_footer();
