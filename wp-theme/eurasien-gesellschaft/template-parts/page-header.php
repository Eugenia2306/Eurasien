<?php
/**
 * Page header (navy band) used by archives and pages.
 *
 * @package Eurasien_Gesellschaft
 *
 * @var string $eyebrow
 * @var string $title
 * @var string $lead
 */

declare(strict_types=1);

$eyebrow = $args['eyebrow'] ?? '';
$title   = $args['title'] ?? get_the_title();
$lead    = $args['lead'] ?? '';
?>
<div class="phead">
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
