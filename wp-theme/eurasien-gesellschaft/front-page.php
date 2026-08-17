<?php
/**
 * Front page: matches HTML prototype home structure.
 *
 * @package Eurasien_Gesellschaft
 */

get_header();
?>

<section class="page page--home" id="p-home">
	<div class="hero">
		<div class="hero__in">
			<div>
				<p class="eyebrow"><?php eg_bi_e( 'Think Tank und Plattform für Dialog · Berlin', 'Think Tank and Dialogue Platform · Berlin' ); ?></p>
				<h1><?php eg_bi_e( 'Europa und Asien verstehen.', 'Understanding Europe and Asia.' ); ?></h1>
				<p class="lead"><?php eg_bi_e(
					'Die Eurasien Gesellschaft e. V. ist ein unabhängiger, gemeinnütziger Think Tank für Dialog und Verständigung. Sie erarbeitet Analysen zu Entwicklungen im eurasischen Raum und bringt Wissenschaft, Kultur, Wirtschaft und Politik miteinander ins Gespräch.',
					'The Eurasien Gesellschaft e. V. is an independent, non-profit think tank for dialogue and understanding. It produces analyses of developments across the Eurasian space and brings science, culture, business and politics into dialogue.'
				); ?></p>
				<div class="hero__cta">
					<a class="btn btn--nav" href="<?php echo esc_url( eg_route( 'p-analysen' ) ); ?>"><?php eg_bi_e( 'Analysen & Perspektiven', 'Analysis & perspectives' ); ?></a>
					<a class="btn btn--ghost" href="<?php echo esc_url( eg_route( 'p-mission' ) ); ?>"><?php eg_bi_e( 'Über uns', 'About us' ); ?></a>
				</div>
				<div class="hero__stats" aria-label="Website overview">
					<div class="hero-stat"><strong>6</strong><?php eg_bi_e( 'Themenfelder', 'fields of work' ); ?></div>
					<div class="hero-stat"><strong>7</strong><?php eg_bi_e( 'Makroregionen', 'macro-regions' ); ?></div>
					<div class="hero-stat hero-stat--lang"><strong>DE · EN</strong><?php eg_bi_e( 'zweisprachig', 'bilingual' ); ?></div>
				</div>
			</div>
			<a class="hero__map hero__map-link" href="<?php echo esc_url( eg_route( 'p-regionen' ) ); ?>">
				<span class="sr-only"><?php eg_bi_e( 'Regionen erkunden', 'Explore regions' ); ?></span>
				<img class="hero__map-svg eg-region-map" src="<?php echo esc_url( EG_THEME_URI . '/assets/images/hero-map.svg' ); ?>" alt="<?php echo esc_attr__( 'Karte Eurasiens', 'eurasien-gesellschaft' ); ?>" width="1000" height="521" decoding="async">
				<div class="hero__cap-row">
					<p class="hero__cap"><?php eg_bi_e( 'Weltkarte des eurasischen Raums, keine amtliche Grenzkarte.', 'World map of the Eurasian region, not an official boundary map.' ); ?></p>
					<span class="hero__map-cta" aria-hidden="true"><?php eg_bi_e( 'Regionen erkunden →', 'Explore regions →' ); ?></span>
				</div>
			</a>
		</div>
	</div>

	<nav class="home-quick" id="home-quick-access" aria-label="Schnellzugriff / Quick access">
		<div class="wrap home-quick__grid">
			<a class="home-quick__item home-quick__item--blue" href="<?php echo esc_url( eg_route( 'p-analysen' ) ); ?>">
				<span class="home-quick__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 19V9m6 10V5m6 14v-7m4 7H2"/></svg></span>
				<span><b class="de">Analysen</b><b class="en" hidden>Analysis</b><small class="de">Kontext und Perspektiven</small><small class="en" hidden>Context and perspectives</small></span>
				<i aria-hidden="true">↗</i>
			</a>
			<a class="home-quick__item home-quick__item--red" href="<?php echo esc_url( eg_route( 'p-veranstaltungen' ) ); ?>">
				<span class="home-quick__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 3v3m12-3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Z"/></svg></span>
				<span><b class="de">Veranstaltungen</b><b class="en" hidden>Events</b><small class="de">Kalender und Begegnung</small><small class="en" hidden>Calendar and dialogue</small></span>
				<i aria-hidden="true">↗</i>
			</a>
			<a class="home-quick__item home-quick__item--gold" href="<?php echo esc_url( eg_route( 'p-regionen' ) ); ?>">
				<span class="home-quick__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 4.2 6 4.2 9S15 18 12 21c-3-3-4.2-6-4.2-9S9 6 12 3Z"/></svg></span>
				<span><b class="de">Regionen</b><b class="en" hidden>Regions</b><small class="de">Eurasien interaktiv erkunden</small><small class="en" hidden>Explore Eurasia interactively</small></span>
				<i aria-hidden="true">↗</i>
			</a>
			<a class="home-quick__item home-quick__item--ink" href="<?php echo esc_url( eg_route( 'p-mitgliedschaft-vorteile' ) ); ?>">
				<span class="home-quick__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M3.5 20c.5-4.2 2.4-6.3 5.5-6.3s5 2.1 5.5 6.3M17 8v6m-3-3h6"/></svg></span>
				<span><b class="de">Mitglied werden</b><b class="en" hidden>Become a member</b><small class="de">Dialog aktiv mitgestalten</small><small class="en" hidden>Help shape the dialogue</small></span>
				<i aria-hidden="true">↗</i>
			</a>
		</div>
	</nav>

	<section class="sec">
		<div class="wrap">
			<div class="shead">
				<div>
					<p class="eyebrow"><?php eg_bi_e( 'Aktuelles & Analysen', 'Current affairs & analysis' ); ?></p>
					<h2><?php eg_bi_e( 'Kontext & Perspektiven', 'Context & perspectives' ); ?></h2>
				</div>
				<a class="link-more" href="<?php echo esc_url( eg_route( 'p-analysen' ) ); ?>"><?php eg_bi_e( 'Zu den Analysen', 'All analysis' ); ?></a>
			</div>
			<div class="grid grid-3">
				<?php
				$analyses = new WP_Query(
					array(
						'post_type'      => 'eg_analyse',
						'posts_per_page' => 3,
						'no_found_rows'  => true,
					)
				);
				if ( $analyses->have_posts() ) :
					while ( $analyses->have_posts() ) :
						$analyses->the_post();
						get_template_part( 'template-parts/content', 'card' );
					endwhile;
					wp_reset_postdata();
				else :
					?>
					<a class="card card--feat card--link" href="<?php echo esc_url( eg_route( 'p-veranstaltungen' ) ); ?>">
						<span class="card__k"><?php eg_bi_e( 'Podiumsdiskussion · Berlin', 'Panel discussion · Berlin' ); ?></span>
						<h3 class="card__t">2026: <?php eg_bi_e( 'Krieg oder Frieden?', 'War or peace?' ); ?></h3>
						<p><?php eg_bi_e(
							'26. März 2026 · Eine Diskussionsrunde zum Stand der internationalen Beziehungen im eurasischen Raum.',
							'26 March 2026 · A panel on the state of international relations across Eurasia.'
						); ?></p>
						<span class="link-more"><?php eg_bi_e( 'Zur Veranstaltung', 'View event' ); ?></span>
					</a>
					<a class="card card--link" href="<?php echo esc_url( eg_route( 'p-analysen' ) ); ?>" style="text-decoration:none">
						<span class="card__k"><?php eg_bi_e( 'Stellungnahme', 'Statement' ); ?></span>
						<h3 class="card__t"><?php eg_bi_e( 'Wer kann mit Russland sprechen?', 'Who can talk to Russia?' ); ?></h3>
						<p class="muted small"><?php eg_bi_e( 'Kurzbeitrag zu Gesprächskanälen und Diplomatie.', 'Short contribution on channels of dialogue and diplomacy.' ); ?></p>
						<span class="link-more"><?php eg_bi_e( 'Zur Übersicht', 'Overview' ); ?></span>
					</a>
					<a class="card card--link" href="<?php echo esc_url( eg_route( 'p-mediathek' ) ); ?>" style="text-decoration:none">
						<span class="card__k"><?php eg_bi_e( 'Aufzeichnung', 'Recording' ); ?></span>
						<h3 class="card__t"><?php eg_bi_e( 'Von der passiven zur aktiven Abschreckung', 'From passive to active deterrence' ); ?></h3>
						<p class="muted small"><?php eg_bi_e( 'Vortrag & Diskussion auf YouTube.', 'Lecture & discussion on YouTube.' ); ?></p>
						<span class="link-more"><?php eg_bi_e( 'Zur Mediathek', 'Media library' ); ?></span>
					</a>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<section class="sec sec--paper">
		<div class="wrap">
			<div class="shead">
				<div>
					<p class="eyebrow"><?php eg_bi_e( 'Arbeitsfelder', 'Fields of work' ); ?></p>
					<h2><?php eg_bi_e( 'Themen', 'Topics' ); ?></h2>
				</div>
				<a class="link-more" href="<?php echo esc_url( eg_route( 'p-themen' ) ); ?>"><?php eg_bi_e( 'Alle Themen', 'All topics' ); ?></a>
			</div>
			<div class="topics">
				<a class="topic" href="<?php echo esc_url( eg_route( 'p-topic-geopolitik' ) ); ?>"><span class="topic__k">01</span><h3><?php eg_bi_e( 'Geopolitik', 'Geopolitics' ); ?></h3><p><?php eg_bi_e( 'Politische Systeme, Sicherheit und Stabilität im eurasischen Raum.', 'Political systems, security and stability across Eurasia.' ); ?></p></a>
				<a class="topic" href="<?php echo esc_url( eg_route( 'p-topic-energie' ) ); ?>"><span class="topic__k">02</span><h3><?php eg_bi_e( 'Energie', 'Energy' ); ?></h3><p><?php eg_bi_e( 'Versorgungssicherheit, Rohstoffe, Infrastruktur und Energiewende.', 'Supply security, resources, infrastructure and energy transition.' ); ?></p></a>
				<a class="topic" href="<?php echo esc_url( eg_route( 'p-topic-wirtschaft' ) ); ?>"><span class="topic__k">03</span><h3><?php eg_bi_e( 'Wirtschaft', 'Economy' ); ?></h3><p><?php eg_bi_e( 'Handel, Industrie, Digitalisierung und wirtschaftliche Zusammenarbeit.', 'Trade, industry, digitalisation and economic cooperation.' ); ?></p></a>
				<a class="topic" href="<?php echo esc_url( eg_route( 'p-kultur' ) ); ?>"><span class="topic__k">04</span><h3><?php eg_bi_e( 'Kultur', 'Culture' ); ?></h3><p><?php eg_bi_e( 'Sprache, Kunst, Musik, Religion, Identität und Kulturdiplomatie.', 'Language, art, music, religion, identity and cultural diplomacy.' ); ?></p></a>
				<a class="topic" href="<?php echo esc_url( eg_route( 'p-topic-wissenschaft' ) ); ?>"><span class="topic__k">05</span><h3><?php eg_bi_e( 'Wissenschaft', 'Science' ); ?></h3><p><?php eg_bi_e( 'Forschung, Bildung und internationale wissenschaftliche Kooperation.', 'Research, education and international scientific cooperation.' ); ?></p></a>
				<a class="topic" href="<?php echo esc_url( eg_route( 'p-laender' ) ); ?>"><span class="topic__k">06</span><h3><?php eg_bi_e( 'Länder & Gesellschaften', 'Countries & Societies' ); ?></h3><p><?php eg_bi_e( 'Länderporträts, Geschichte, Gesellschaft, Natur und Lebensweisen.', 'Country portraits, history, society, nature and ways of life.' ); ?></p></a>
			</div>
		</div>
	</section>

	<section class="sec">
		<div class="wrap">
			<div class="grid grid-2" style="align-items:center;gap:46px">
				<div>
					<p class="eyebrow"><?php eg_bi_e( 'Regionen', 'Regions' ); ?></p>
					<h2><?php eg_bi_e( 'Der eurasische Raum in Makroregionen', 'The Eurasian space by macro-region' ); ?></h2>
					<p class="muted"><?php eg_bi_e(
						'Von Europa und Osteuropa über den Kaukasus und Zentralasien bis Ost- und Südasien und den Nahen Osten: Wir ordnen Entwicklungen nach Makroregionen und verbinden sie mit Analysen, Veranstaltungen und Experten.',
						'From Europe and Eastern Europe through the Caucasus and Central Asia to East and South Asia and the Middle East: we organise developments by macro-region and connect them with analysis, events and experts.'
					); ?></p>
					<p><a class="btn btn--ghost" href="<?php echo esc_url( eg_route( 'p-regionen' ) ); ?>"><?php eg_bi_e( 'Regionen erkunden', 'Explore regions' ); ?></a></p>
				</div>
				<a class="reg-map reg-map-link" href="<?php echo esc_url( eg_route( 'p-regionen' ) ); ?>">
					<img class="wm2 eg-region-map" src="<?php echo esc_url( EG_THEME_URI . '/assets/images/hero-map.svg' ); ?>" alt="<?php echo esc_attr__( 'Karte Eurasiens', 'eurasien-gesellschaft' ); ?>" width="1000" height="521" loading="lazy" decoding="async">
				</a>
			</div>
		</div>
	</section>

	<section class="sec sec--stone">
		<div class="wrap">
			<div class="shead">
				<div>
					<p class="eyebrow"><?php eg_bi_e( 'Begegnung', 'Encounter' ); ?></p>
					<h2><?php eg_bi_e( 'Veranstaltungen', 'Events' ); ?></h2>
				</div>
				<a class="link-more" href="<?php echo esc_url( eg_route( 'p-veranstaltungen' ) ); ?>"><?php eg_bi_e( 'Kalender & Archiv', 'Calendar & archive' ); ?></a>
			</div>
			<?php
			$events = new WP_Query(
				array(
					'post_type'      => 'eg_event',
					'posts_per_page' => 3,
					'no_found_rows'  => true,
					'meta_key'       => 'eg_event_start',
					'orderby'        => 'meta_value',
					'order'          => 'DESC',
				)
			);
			if ( $events->have_posts() ) :
				while ( $events->have_posts() ) :
					$events->the_post();
					get_template_part( 'template-parts/content', 'event' );
				endwhile;
				wp_reset_postdata();
			else :
				?>
				<p class="empty"><?php eg_bi_e( 'Noch keine Veranstaltungen. Unter Eurasien Setup Inhalte importieren.', 'No events yet. Import content under Eurasien Setup.' ); ?></p>
			<?php endif; ?>
		</div>
	</section>

	<section class="sec">
		<div class="wrap">
			<div class="shead">
				<div>
					<p class="eyebrow"><?php eg_bi_e( 'Menschen', 'People' ); ?></p>
					<h2><?php eg_bi_e( 'Vorstand & Expertennetzwerk', 'Board & expert network' ); ?></h2>
				</div>
				<a class="link-more" href="<?php echo esc_url( eg_route( 'p-vorstand' ) ); ?>"><?php eg_bi_e( 'Zum Vorstand', 'Meet the board' ); ?></a>
			</div>
			<div class="grid grid-2" style="gap:34px;align-items:center">
				<p class="muted" style="margin:0"><?php eg_bi_e(
					'Als gemeinnützige Plattform bringt die Eurasien Gesellschaft Fachleute, Wissenschaftler:innen, Kulturschaffende und Journalist:innen zusammen.',
					'As a non-profit platform, the Eurasien Gesellschaft brings together experts, scholars, cultural figures and journalists.'
				); ?></p>
				<div class="grid grid-2" style="gap:14px">
					<a class="card card--link" href="<?php echo esc_url( eg_route( 'p-vorstand' ) ); ?>" style="text-decoration:none;padding:18px"><span class="card__k"><?php eg_bi_e( 'Vorstand', 'Board' ); ?></span><h3 class="card__t" style="font-size:1.05rem"><?php eg_bi_e( 'Fünf öffentlich benannte Mitglieder', 'Five publicly named members' ); ?></h3></a>
					<a class="card card--link" href="<?php echo esc_url( eg_route( 'p-vorstand' ) ); ?>" style="text-decoration:none;padding:18px"><span class="card__k"><?php eg_bi_e( 'Experten', 'Experts' ); ?></span><h3 class="card__t" style="font-size:1.05rem"><?php eg_bi_e( 'Interdisziplinäres Netzwerk', 'Interdisciplinary network' ); ?></h3></a>
				</div>
			</div>
		</div>
	</section>

	<section class="sec sec--paper">
		<div class="wrap">
			<div class="shead">
				<div>
					<p class="eyebrow"><?php eg_bi_e( 'Mediathek', 'Media library' ); ?></p>
					<h2><?php eg_bi_e( 'Aufzeichnungen', 'Recordings' ); ?></h2>
				</div>
				<a class="link-more ext" href="https://www.youtube.com/@EurasienGesellschaft" target="_blank" rel="noopener"><?php eg_bi_e( 'YouTube-Kanal', 'YouTube channel' ); ?></a>
			</div>
			<div class="media">
				<?php
				$recs = new WP_Query(
					array(
						'post_type'      => 'eg_recording',
						'posts_per_page' => 3,
						'no_found_rows'  => true,
					)
				);
				if ( $recs->have_posts() ) :
					while ( $recs->have_posts() ) :
						$recs->the_post();
						$yt = (string) get_post_meta( get_the_ID(), 'eg_youtube_url', true );
						?>
						<div class="mcard">
							<span class="mcard__k"><?php eg_bi_e( 'Aufzeichnung', 'Recording' ); ?></span>
							<h3 class="mcard__t"><?php the_title(); ?></h3>
							<?php if ( has_excerpt() ) : ?>
								<p class="mcard__by"><?php echo esc_html( get_the_excerpt() ); ?></p>
							<?php endif; ?>
							<?php if ( $yt ) : ?>
								<a class="btn btn--ghost btn--sm ext" href="<?php echo esc_url( $yt ); ?>" target="_blank" rel="noopener"><?php eg_bi_e( 'Auf YouTube ansehen', 'Watch on YouTube' ); ?></a>
							<?php endif; ?>
						</div>
						<?php
					endwhile;
					wp_reset_postdata();
				else :
					?>
					<div class="mcard">
						<span class="mcard__k"><?php eg_bi_e( 'Aufzeichnung', 'Recording' ); ?></span>
						<h3 class="mcard__t"><?php eg_bi_e( 'Chancen für Frieden in der Ukraine', 'Opportunities for peace in Ukraine' ); ?></h3>
						<p class="mcard__by">H. Kujat, Gy. Varga · 2025</p>
						<a class="btn btn--ghost btn--sm ext" href="https://www.youtube.com/watch?v=K5UEodKM_ds" target="_blank" rel="noopener"><?php eg_bi_e( 'Auf YouTube ansehen', 'Watch on YouTube' ); ?></a>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<section class="sec sec--navy">
		<div class="wrap">
			<div class="news-cta">
				<div>
					<p class="eyebrow" style="color:#dcae9c"><?php eg_bi_e( 'Mitwirken', 'Get involved' ); ?></p>
					<h2 style="color:#fff"><?php eg_bi_e( 'Werden Sie Mitglied', 'Become a member' ); ?></h2>
					<p style="color:#c6d2e0;margin:0"><?php eg_bi_e(
						'Teilnahme an Veranstaltungen, Zugang zum Mitgliederbereich und Teil eines Netzwerks aus Wissenschaft, Kultur, Wirtschaft und Zivilgesellschaft.',
						'Participation in events, access to the members’ area and part of a network spanning science, culture, economy and civil society.'
					); ?></p>
				</div>
				<div style="display:flex;gap:12px;flex-wrap:wrap;justify-content:flex-end;align-items:center">
					<a class="btn btn--accent" href="<?php echo esc_url( function_exists( 'eg_membership_signup_url' ) ? eg_membership_signup_url() : eg_route( 'p-mitgliedschaft' ) ); ?>" data-analytics="membership_click"><?php eg_bi_e( 'Mitglied werden', 'Become a member' ); ?></a>
					<a class="btn btn--onnavy" href="<?php echo esc_url( eg_route( 'p-partner' ) ); ?>"><?php eg_bi_e( 'Partner werden', 'Become a partner' ); ?></a>
				</div>
			</div>
		</div>
	</section>

	<section class="sec sec--navy" style="padding-top:0">
		<div class="wrap">
			<div class="news-cta" style="border-top:1px solid #2a3e56;padding-top:40px">
				<div>
					<h3 style="color:#fff"><?php eg_bi_e( 'Rundbrief', 'Newsletter' ); ?></h3>
					<p style="color:#c6d2e0;margin:0"><?php eg_bi_e(
						'Analysen, Veranstaltungshinweise und Neuigkeiten aus dem eurasischen Raum, regelmäßig und kostenfrei.',
						'Analysis, event notices and news from the Eurasian space, regular and free of charge.'
					); ?></p>
				</div>
				<a class="btn btn--onnavy" href="<?php echo esc_url( eg_route( 'p-news' ) ); ?>"><?php eg_bi_e( 'Mehr erfahren', 'Learn more' ); ?></a>
			</div>
		</div>
	</section>
</section>

<?php
get_footer();
