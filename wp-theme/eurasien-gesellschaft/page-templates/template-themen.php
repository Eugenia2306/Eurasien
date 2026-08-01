<?php
/**
 * Template Name: Themen Übersicht
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
			'eyebrow' => eg_bi( 'Arbeitsfelder', 'Fields of work' ),
			'title'   => get_the_title(),
		)
	);
	?>
	<section class="sec">
		<div class="wrap">
			<div class="topics">
				<a class="topic" href="<?php echo esc_url( eg_route( 'p-topic-geopolitik' ) ); ?>"><span class="topic__k">01</span><h3><?php eg_bi_e( 'Geopolitik', 'Geopolitics' ); ?></h3><p><?php eg_bi_e( 'Politische Systeme, Sicherheit und Stabilität im eurasischen Raum.', 'Political systems, security and stability across Eurasia.' ); ?></p></a>
				<a class="topic" href="<?php echo esc_url( eg_route( 'p-topic-energie' ) ); ?>"><span class="topic__k">02</span><h3><?php eg_bi_e( 'Energie', 'Energy' ); ?></h3><p><?php eg_bi_e( 'Versorgungssicherheit, Rohstoffe, Infrastruktur und Energiewende.', 'Supply security, resources, infrastructure and energy transition.' ); ?></p></a>
				<a class="topic" href="<?php echo esc_url( eg_route( 'p-topic-wirtschaft' ) ); ?>"><span class="topic__k">03</span><h3><?php eg_bi_e( 'Wirtschaft', 'Economy' ); ?></h3><p><?php eg_bi_e( 'Handel, Industrie, Digitalisierung und wirtschaftliche Zusammenarbeit.', 'Trade, industry, digitalisation and economic cooperation.' ); ?></p></a>
				<a class="topic" href="<?php echo esc_url( eg_route( 'p-kultur' ) ); ?>"><span class="topic__k">04</span><h3><?php eg_bi_e( 'Kultur', 'Culture' ); ?></h3><p><?php eg_bi_e( 'Sprache, Kunst, Musik, Religion, Identität und Kulturdiplomatie.', 'Language, art, music, religion, identity and cultural diplomacy.' ); ?></p></a>
				<a class="topic" href="<?php echo esc_url( eg_route( 'p-topic-wissenschaft' ) ); ?>"><span class="topic__k">05</span><h3><?php eg_bi_e( 'Wissenschaft', 'Science' ); ?></h3><p><?php eg_bi_e( 'Forschung, Bildung und internationale wissenschaftliche Kooperation.', 'Research, education and international scientific cooperation.' ); ?></p></a>
				<a class="topic" href="<?php echo esc_url( eg_route( 'p-laender' ) ); ?>"><span class="topic__k">06</span><h3><?php eg_bi_e( 'Länder & Gesellschaften', 'Countries & Societies' ); ?></h3><p><?php eg_bi_e( 'Länderporträts, Geschichte, Gesellschaft, Natur und Lebensweisen.', 'Country portraits, history, society, nature and ways of life.' ); ?></p></a>
			</div>
			<?php if ( get_the_content() ) : ?>
				<div class="entry-content" style="margin-top:40px"><?php the_content(); ?></div>
			<?php endif; ?>
		</div>
	</section>
	<?php
endwhile;

get_footer();
