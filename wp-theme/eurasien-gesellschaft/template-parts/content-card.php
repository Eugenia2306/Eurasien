<?php
/**
 * Card for Analysen / posts.
 *
 * @package Eurasien_Gesellschaft
 */

declare(strict_types=1);

$kicker = '';
$terms  = get_the_terms( get_the_ID(), 'eg_format' );
if ( $terms && ! is_wp_error( $terms ) ) {
	$kicker = $terms[0]->name;
} elseif ( 'post' === get_post_type() ) {
	$kicker = __( 'Gesellschaftsnachrichten', 'eurasien-gesellschaft' );
}
?>
<a class="card card--link" href="<?php the_permalink(); ?>" style="text-decoration:none">
	<?php if ( $kicker ) : ?>
		<span class="card__k"><?php echo esc_html( $kicker ); ?></span>
	<?php endif; ?>
	<h3 class="card__t"><?php the_title(); ?></h3>
	<?php if ( has_excerpt() ) : ?>
		<p class="muted small"><?php echo esc_html( get_the_excerpt() ); ?></p>
	<?php endif; ?>
	<span class="link-more"><?php eg_bi_e( 'Weiterlesen', 'Read more' ); ?></span>
</a>
