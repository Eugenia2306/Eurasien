<?php
/**
 * Event row for archives and home.
 *
 * @package Eurasien_Gesellschaft
 */

declare(strict_types=1);

$parts    = eg_event_date_parts( get_the_ID() );
$location = (string) get_post_meta( get_the_ID(), 'eg_event_location', true );
$past     = eg_event_is_past( get_the_ID() );
?>
<a class="ev" href="<?php the_permalink(); ?>" style="text-decoration:none;color:inherit">
	<?php if ( $parts ) : ?>
		<div class="ev__date">
			<div class="ev__d"><?php echo esc_html( $parts['d'] ); ?></div>
			<div class="ev__m"><?php echo esc_html( $parts['m'] ); ?></div>
			<div class="ev__y"><?php echo esc_html( $parts['y'] ); ?></div>
		</div>
	<?php endif; ?>
	<div>
		<h3 class="ev__t"><?php the_title(); ?></h3>
		<div class="ev__meta">
			<?php if ( $location ) : ?>
				<span><?php echo esc_html( $location ); ?></span>
			<?php endif; ?>
			<?php if ( has_excerpt() ) : ?>
				<span><?php echo esc_html( wp_strip_all_tags( get_the_excerpt() ) ); ?></span>
			<?php endif; ?>
		</div>
	</div>
	<div class="ev__side">
		<span class="badge <?php echo $past ? 'badge--past' : 'badge--up'; ?>">
			<?php
			if ( $past ) {
				eg_bi_e( 'Vergangen', 'Past' );
			} else {
				eg_bi_e( 'Bevorstehend', 'Upcoming' );
			}
			?>
		</span>
	</div>
</a>
