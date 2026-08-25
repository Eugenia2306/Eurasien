<?php
/**
 * Page header (navy band) used by archives and pages.
 *
 * @package Eurasien_Gesellschaft
 *
 * @var string $eyebrow
 * @var string $title
 * @var string $lead
 * @var string $variant  default|compact
 */

declare(strict_types=1);

$eyebrow = $args['eyebrow'] ?? '';
$title   = $args['title'] ?? get_the_title();
$lead    = $args['lead'] ?? '';
$variant = $args['variant'] ?? 'default';
$class   = 'compact' === $variant ? 'phead phead--compact' : 'phead';
?>
<div class="<?php echo esc_attr( $class ); ?>">
	<div class="wrap">
		<?php if ( $eyebrow ) : ?>
			<p class="eyebrow"><?php echo wp_kses_post( $eyebrow ); ?></p>
		<?php endif; ?>
		<h1><?php echo wp_kses_post( $title ); ?></h1>
		<?php if ( $lead ) : ?>
			<p><?php echo wp_kses_post( $lead ); ?></p>
		<?php endif; ?>
	</div>
</div>
