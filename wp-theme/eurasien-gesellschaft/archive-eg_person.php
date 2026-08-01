<?php
/**
 * Personen archive (Vorstand & Experten listing).
 *
 * @package Eurasien_Gesellschaft
 */

get_header();

get_template_part(
	'template-parts/page-header',
	null,
	array(
		'eyebrow' => eg_bi( 'Menschen', 'People' ),
		'title'   => eg_bi( 'Vorstand & Expertennetzwerk', 'Board & expert network' ),
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
					$role = (string) get_post_meta( get_the_ID(), 'eg_person_role_label', true );
					?>
					<a class="card card--link" href="<?php the_permalink(); ?>" style="text-decoration:none">
						<?php if ( has_post_thumbnail() ) : ?>
							<?php the_post_thumbnail( 'eg-person' ); ?>
						<?php endif; ?>
						<?php if ( $role ) : ?>
							<span class="card__k"><?php echo esc_html( $role ); ?></span>
						<?php endif; ?>
						<h3 class="card__t"><?php the_title(); ?></h3>
						<?php if ( has_excerpt() ) : ?>
							<p class="muted small"><?php echo esc_html( get_the_excerpt() ); ?></p>
						<?php endif; ?>
					</a>
					<?php
				endwhile;
				?>
			<?php else : ?>
				<p class="empty"><?php eg_bi_e( 'Noch keine Personenprofile. Legen Sie welche unter Personen an.', 'No person profiles yet. Add some under Personen.' ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</section>

<?php
get_footer();
