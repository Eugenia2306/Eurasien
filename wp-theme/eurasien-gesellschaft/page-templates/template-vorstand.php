<?php
/**
 * Template Name: Vorstand
 * Description: Lists eg_person posts (board + experts).
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
			'eyebrow' => eg_bi( 'Menschen', 'People' ),
			'title'   => get_the_title(),
		)
	);
	?>
	<section class="sec">
		<div class="wrap">
			<?php if ( get_the_content() ) : ?>
				<div class="entry-content wrap-narrow" style="margin-bottom:40px"><?php the_content(); ?></div>
			<?php endif; ?>
			<div class="grid grid-3">
				<?php
				$people = new WP_Query(
					array(
						'post_type'      => 'eg_person',
						'posts_per_page' => 50,
						'orderby'        => 'title',
						'order'          => 'ASC',
					)
				);
				if ( $people->have_posts() ) :
					while ( $people->have_posts() ) :
						$people->the_post();
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
						</a>
						<?php
					endwhile;
					wp_reset_postdata();
				else :
					?>
					<p class="empty"><?php eg_bi_e( 'Legen Sie Personenprofile unter Personen an (Rollen: Vorstand, Experte, …).', 'Create person profiles under Personen (roles: Board, Expert, …).' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	</section>
	<?php
endwhile;

get_footer();
