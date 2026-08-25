<?php
/**
 * Fallback mega-nav when no WP menu is assigned yet.
 *
 * @package Eurasien_Gesellschaft
 */

declare(strict_types=1);
?>
<nav class="nav" aria-label="<?php esc_attr_e( 'Hauptnavigation', 'eurasien-gesellschaft' ); ?>">
	<div class="nav-item">
		<a class="nav-link" href="<?php echo esc_url( eg_route( 'p-mission' ) ); ?>"><?php eg_bi_e( 'Über uns', 'About us' ); ?></a>
		<div class="mega">
			<a href="<?php echo esc_url( eg_route( 'p-mission' ) ); ?>"><?php eg_bi_e( 'Mission', 'Mission' ); ?></a>
			<a href="<?php echo esc_url( eg_route( 'p-vorstand' ) ); ?>"><?php eg_bi_e( 'Vorstand & Experten', 'Board & Experts' ); ?></a>
			<a href="<?php echo esc_url( eg_route( 'p-partner' ) ); ?>"><?php eg_bi_e( 'Partner', 'Partners' ); ?></a>
			<a href="<?php echo esc_url( eg_route( 'p-news' ) ); ?>"><?php eg_bi_e( 'Gesellschaftsnachrichten', 'Society News' ); ?></a>
		</div>
	</div>
	<div class="nav-item">
		<a class="nav-link" href="<?php echo esc_url( eg_route( 'p-themen' ) ); ?>"><?php eg_bi_e( 'Themen', 'Topics' ); ?></a>
		<div class="mega">
			<a href="<?php echo esc_url( eg_route( 'p-topic-geopolitik' ) ); ?>"><?php eg_bi_e( 'Geopolitik', 'Geopolitics' ); ?></a>
			<a href="<?php echo esc_url( eg_route( 'p-topic-energie' ) ); ?>"><?php eg_bi_e( 'Energie', 'Energy' ); ?></a>
			<a href="<?php echo esc_url( eg_route( 'p-topic-wirtschaft' ) ); ?>"><?php eg_bi_e( 'Wirtschaft', 'Economy' ); ?></a>
			<a href="<?php echo esc_url( eg_route( 'p-kultur' ) ); ?>"><?php eg_bi_e( 'Kultur', 'Culture' ); ?></a>
			<a href="<?php echo esc_url( eg_route( 'p-topic-wissenschaft' ) ); ?>"><?php eg_bi_e( 'Wissenschaft', 'Science' ); ?></a>
			<a href="<?php echo esc_url( eg_route( 'p-laender' ) ); ?>"><?php eg_bi_e( 'Länder & Gesellschaften', 'Countries & Societies' ); ?></a>
		</div>
	</div>
	<div class="nav-item">
		<a class="nav-link" href="<?php echo esc_url( eg_route( 'p-analysen' ) ); ?>"><?php eg_bi_e( 'Analysen', 'Analysis' ); ?></a>
		<div class="mega">
			<a href="<?php echo esc_url( eg_route( 'p-analysen' ) ); ?>"><?php eg_bi_e( 'Aktuelles', 'Current Affairs' ); ?></a>
			<a href="<?php echo esc_url( add_query_arg( 'format', 'stellungnahmen', eg_route( 'p-analysen' ) ) ); ?>"><?php eg_bi_e( 'Stellungnahmen', 'Statements' ); ?></a>
			<a href="<?php echo esc_url( eg_route( 'p-members-positionen' ) ); ?>"><?php eg_bi_e( 'Positionen', 'Positions' ); ?></a>
			<a href="<?php echo esc_url( eg_route( 'p-members-dossiers' ) ); ?>"><?php eg_bi_e( 'Dossiers', 'Dossiers' ); ?></a>
			<a href="<?php echo esc_url( eg_route( 'p-members-studien' ) ); ?>"><?php eg_bi_e( 'Studien', 'Studies' ); ?></a>
		</div>
	</div>
	<div class="nav-item">
		<a class="nav-link" href="<?php echo esc_url( eg_route( 'p-regionen' ) ); ?>"><?php eg_bi_e( 'Regionen', 'Regions' ); ?></a>
		<div class="mega">
			<a href="<?php echo esc_url( eg_route( 'p-regionen' ) ); ?>"><?php eg_bi_e( 'Europa', 'Europe' ); ?></a>
			<a href="<?php echo esc_url( eg_route( 'p-regionen' ) ); ?>"><?php eg_bi_e( 'Kaukasus und Kaspischer Raum', 'Caucasus & Caspian region' ); ?></a>
			<a href="<?php echo esc_url( eg_route( 'p-regionen' ) ); ?>"><?php eg_bi_e( 'Westasien und Naher Osten', 'West Asia & Middle East' ); ?></a>
			<a href="<?php echo esc_url( eg_route( 'p-regionen' ) ); ?>"><?php eg_bi_e( 'Ostasien', 'East Asia' ); ?></a>
			<a href="<?php echo esc_url( eg_route( 'p-regionen' ) ); ?>"><?php eg_bi_e( 'Osteuropa und Russland', 'Eastern Europe & Russia' ); ?></a>
			<a href="<?php echo esc_url( eg_route( 'p-regionen' ) ); ?>"><?php eg_bi_e( 'Südasien', 'South Asia' ); ?></a>
			<a href="<?php echo esc_url( eg_route( 'p-regionen' ) ); ?>"><?php eg_bi_e( 'Zentralasien', 'Central Asia' ); ?></a>
		</div>
	</div>
	<div class="nav-item">
		<a class="nav-link" href="<?php echo esc_url( eg_route( 'p-veranstaltungen' ) ); ?>"><?php eg_bi_e( 'Veranstaltungen', 'Events' ); ?></a>
		<div class="mega">
			<a href="<?php echo esc_url( eg_route( 'p-veranstaltungen' ) ); ?>"><?php eg_bi_e( 'Kalender', 'Calendar' ); ?></a>
			<a href="<?php echo esc_url( add_query_arg( 'when', 'upcoming', eg_route( 'p-veranstaltungen' ) ) ); ?>"><?php eg_bi_e( 'Bevorstehende Veranstaltungen', 'Upcoming Events' ); ?></a>
			<a href="<?php echo esc_url( add_query_arg( 'when', 'past', eg_route( 'p-veranstaltungen' ) ) ); ?>"><?php eg_bi_e( 'Vergangene Veranstaltungen', 'Past Events' ); ?></a>
		</div>
	</div>
	<div class="nav-item">
		<a class="nav-link" href="<?php echo esc_url( eg_route( 'p-mediathek' ) ); ?>"><?php eg_bi_e( 'Mediathek', 'Media Library' ); ?></a>
		<div class="mega">
			<a href="https://www.youtube.com/@EurasienGesellschaft" target="_blank" rel="noopener noreferrer"><?php eg_bi_e( 'YouTube-Kanal', 'YouTube Channel' ); ?></a>
			<a href="<?php echo esc_url( eg_route( 'p-recordings-archive' ) ); ?>"><?php eg_bi_e( 'Aufzeichnungen', 'Recordings' ); ?></a>
			<a href="<?php echo esc_url( eg_route( 'p-mediathek' ) ); ?>"><?php eg_bi_e( 'Presse', 'Press' ); ?></a>
		</div>
	</div>
	<div class="nav-item">
		<a class="nav-link" href="<?php echo esc_url( eg_route( 'p-mitgliedschaft-vorteile' ) ); ?>" data-analytics="membership_click"><?php eg_bi_e( 'Mitgliedschaft', 'Membership' ); ?></a>
		<div class="mega">
			<a href="<?php echo esc_url( eg_route( 'p-mitgliedschaft-vorteile' ) ); ?>" data-analytics="membership_click"><?php eg_bi_e( 'Vorteile', 'Benefits' ); ?></a>
			<a href="<?php echo esc_url( eg_route( 'p-mitgliedschaft' ) ); ?>" data-analytics="membership_click"><?php eg_bi_e( 'Antrag', 'Application' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/mitglieder/positionen/' ) ); ?>" data-analytics="membership_click"><?php eg_bi_e( 'Mitgliederbereich', 'Members’ Area' ); ?></a>
		</div>
	</div>
</nav>
