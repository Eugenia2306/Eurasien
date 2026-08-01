<?php
/**
 * 404 template.
 *
 * @package Eurasien_Gesellschaft
 */

get_header();

get_template_part(
	'template-parts/page-header',
	null,
	array(
		'title' => '404',
		'lead'  => eg_bi( 'Seite nicht gefunden.', 'Page not found.' ),
	)
);
?>
<section class="sec">
	<div class="wrap wrap-narrow">
		<p><a class="btn btn--nav" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php eg_bi_e( 'Zur Startseite', 'Back to home' ); ?></a></p>
	</div>
</section>
<?php
get_footer();
