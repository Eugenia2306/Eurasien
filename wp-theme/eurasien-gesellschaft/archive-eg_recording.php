<?php
/**
 * Mediathek / recordings archive.
 *
 * @package Eurasien_Gesellschaft
 */

get_header();

get_template_part(
	'template-parts/page-header',
	null,
	array(
		'eyebrow' => eg_bi( 'Mediathek', 'Media library' ),
		'title'   => eg_bi( 'Aufzeichnungen', 'Recordings' ),
	)
);
?>

<section class="sec">
	<div class="wrap">
		<div class="media">
			<?php if ( have_posts() ) : ?>
				<?php
				while ( have_posts() ) :
					the_post();
					$yt = (string) get_post_meta( get_the_ID(), 'eg_youtube_url', true );
					?>
					<div class="mcard">
						<span class="mcard__k"><?php eg_bi_e( 'Aufzeichnung', 'Recording' ); ?></span>
						<h3 class="mcard__t"><?php the_title(); ?></h3>
						<?php if ( has_excerpt() ) : ?>
							<p class="mcard__by"><?php echo esc_html( get_the_excerpt() ); ?></p>
						<?php endif; ?>
						<?php if ( $yt ) : ?>
							<a class="btn btn--ghost btn--sm ext" href="<?php echo esc_url( $yt ); ?>" target="_blank" rel="noopener noreferrer"><?php eg_bi_e( 'Auf YouTube ansehen', 'Watch on YouTube' ); ?></a>
						<?php else : ?>
							<a class="btn btn--ghost btn--sm" href="<?php the_permalink(); ?>"><?php eg_bi_e( 'Details', 'Details' ); ?></a>
						<?php endif; ?>
					</div>
					<?php
				endwhile;
				?>
			<?php else : ?>
				<p class="empty"><?php eg_bi_e( 'Noch keine Aufzeichnungen.', 'No recordings yet.' ); ?></p>
			<?php endif; ?>
		</div>
		<?php the_posts_pagination(); ?>
	</div>
</section>

<?php
get_footer();
