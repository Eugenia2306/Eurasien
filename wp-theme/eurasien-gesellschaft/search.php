<?php
/**
 * Search results.
 *
 * @package Eurasien_Gesellschaft
 */

get_header();

get_template_part(
	'template-parts/page-header',
	null,
	array(
		'eyebrow' => eg_bi( 'Suche', 'Search' ),
		/* translators: %s: search query */
		'title'   => sprintf( esc_html__( 'Ergebnisse für „%s“', 'eurasien-gesellschaft' ), esc_html( get_search_query() ) ),
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
				<p class="empty"><?php eg_bi_e( 'Keine Treffer.', 'No results.' ); ?></p>
			<?php endif; ?>
		</div>
		<?php the_posts_pagination(); ?>
	</div>
</section>
<?php
get_footer();
