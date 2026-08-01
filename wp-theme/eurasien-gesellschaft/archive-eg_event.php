<?php
/**
 * Veranstaltungen archive.
 *
 * @package Eurasien_Gesellschaft
 */

get_header();

get_template_part(
	'template-parts/page-header',
	null,
	array(
		'eyebrow' => eg_bi( 'Begegnung', 'Encounter' ),
		'title'   => eg_bi( 'Veranstaltungen', 'Events' ),
		'lead'    => eg_bi( 'Kalender und Archiv der Eurasien Gesellschaft.', 'Calendar and archive of the Eurasien Gesellschaft.' ),
	)
);
?>

<section class="sec">
	<div class="wrap">
		<?php if ( have_posts() ) : ?>
			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/content', 'event' );
			endwhile;
			the_posts_pagination();
			?>
		<?php else : ?>
			<p class="empty"><?php eg_bi_e( 'Keine Veranstaltungen gefunden.', 'No events found.' ); ?></p>
		<?php endif; ?>
	</div>
</section>

<?php
get_footer();
