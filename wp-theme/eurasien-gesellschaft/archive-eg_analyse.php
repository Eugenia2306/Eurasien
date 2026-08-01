<?php
/**
 * Analysen archive.
 *
 * @package Eurasien_Gesellschaft
 */

get_header();

get_template_part(
	'template-parts/page-header',
	null,
	array(
		'eyebrow' => eg_bi( 'Publikationen', 'Publications' ),
		'title'   => eg_bi( 'Analysen', 'Analysis' ),
		'lead'    => eg_bi(
			'Aktuelles, Stellungnahmen, Positionen, Dossiers und Studien.',
			'Current affairs, statements, positions, dossiers and studies.'
		),
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
				<p class="empty"><?php eg_bi_e( 'Noch keine Analysen.', 'No analyses yet.' ); ?></p>
			<?php endif; ?>
		</div>
		<?php the_posts_pagination(); ?>
	</div>
</section>

<?php
get_footer();
