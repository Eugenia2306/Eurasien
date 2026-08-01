<?php
/**
 * Single post / CPT entry.
 *
 * @package Eurasien_Gesellschaft
 */

get_header();

while ( have_posts() ) :
	the_post();
	$type = get_post_type();
	$eyebrow = match ( $type ) {
		'eg_analyse'   => eg_bi( 'Analyse', 'Analysis' ),
		'eg_event'     => eg_bi( 'Veranstaltung', 'Event' ),
		'eg_person'    => eg_bi( 'Person', 'Person' ),
		'eg_recording' => eg_bi( 'Aufzeichnung', 'Recording' ),
		default        => eg_bi( 'Beitrag', 'Post' ),
	};

	get_template_part(
		'template-parts/page-header',
		null,
		array(
			'eyebrow' => $eyebrow,
			'title'   => get_the_title(),
		)
	);
	?>
	<section class="sec">
		<div class="<?php echo in_array( $type, array( 'eg_person', 'eg_event' ), true ) ? 'wrap' : 'wrap wrap-narrow'; ?> entry-content">
			<?php if ( 'eg_event' === $type ) : ?>
				<?php
				$loc = (string) get_post_meta( get_the_ID(), 'eg_event_location', true );
				$start = (string) get_post_meta( get_the_ID(), 'eg_event_start', true );
				?>
				<p class="muted">
					<?php if ( $start ) : ?>
						<strong><?php echo esc_html( $start ); ?></strong>
					<?php endif; ?>
					<?php if ( $loc ) : ?>
						· <?php echo esc_html( $loc ); ?>
					<?php endif; ?>
				</p>
			<?php endif; ?>

			<?php if ( 'eg_recording' === $type ) : ?>
				<?php $yt = (string) get_post_meta( get_the_ID(), 'eg_youtube_url', true ); ?>
				<?php if ( $yt ) : ?>
					<p><a class="btn btn--nav ext" href="<?php echo esc_url( $yt ); ?>" target="_blank" rel="noopener noreferrer"><?php eg_bi_e( 'Auf YouTube ansehen', 'Watch on YouTube' ); ?></a></p>
				<?php endif; ?>
			<?php endif; ?>

			<?php the_content(); ?>
		</div>
	</section>
	<?php
endwhile;

get_footer();
