<?php
/**
 * Seed pages, taxonomies, persons, and migrated prototype HTML.
 *
 * Appearance → Eurasien Setup
 *
 * @package Eurasien_Gesellschaft
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pages to create (title DE, slug, template, optional content file).
 *
 * @return list<array{title:string,slug:string,template?:string,content?:string,excerpt?:string}>
 */
function eg_seed_page_defs(): array {
	$migrated = 'page-templates/template-migrated.php';

	return array(
		array(
			'title'    => 'Mission',
			'slug'     => 'mission',
			'template' => $migrated,
			'content'  => 'p-mission.html',
			'excerpt'  => 'Unabhängige, gemeinnützige Plattform für Dialog und Verständigung im eurasischen Raum.',
		),
		array(
			'title'    => 'Vorstand & Experten',
			'slug'     => 'vorstand',
			'template' => 'page-templates/template-vorstand.php',
			'content'  => 'p-vorstand.html',
		),
		array(
			'title'    => 'Partner',
			'slug'     => 'partner',
			'template' => $migrated,
			'content'  => 'p-partner.html',
		),
		array(
			'title'    => 'Gesellschaftsnachrichten',
			'slug'     => 'gesellschaftsnachrichten',
			'template' => $migrated,
			'content'  => 'p-news.html',
		),
		array(
			'title'    => 'Themen',
			'slug'     => 'themen',
			'template' => $migrated,
			'content'  => 'p-themen.html',
		),
		array(
			'title'    => 'Geopolitik',
			'slug'     => 'geopolitik',
			'template' => $migrated,
			'content'  => 'p-topic-geopolitik.html',
		),
		array(
			'title'    => 'Energie',
			'slug'     => 'energie',
			'template' => $migrated,
			'content'  => 'p-topic-energie.html',
		),
		array(
			'title'    => 'Wirtschaft',
			'slug'     => 'wirtschaft',
			'template' => $migrated,
			'content'  => 'p-topic-wirtschaft.html',
		),
		array(
			'title'    => 'Kultur',
			'slug'     => 'kultur',
			'template' => $migrated,
			'content'  => 'p-kultur.html',
		),
		array(
			'title'    => 'Wissenschaft',
			'slug'     => 'wissenschaft',
			'template' => $migrated,
			'content'  => 'p-topic-wissenschaft.html',
		),
		array(
			'title'    => 'Länder & Gesellschaften',
			'slug'     => 'laender-gesellschaften',
			'template' => $migrated,
			'content'  => 'p-laender.html',
		),
		array(
			'title'    => 'Regionen',
			'slug'     => 'regionen',
			'template' => 'page-templates/template-regionen.php',
			// Body is loaded from theme/content/p-regionen.html (inline SVG) by the template.
			'content'  => '',
			'excerpt'  => 'Interactive regions explorer (theme template).',
		),
		array(
			'title'    => 'Mitgliedschaft',
			'slug'     => 'mitgliedschaft',
			'template' => $migrated,
			'content'  => 'p-mitgliedschaft.html',
			'excerpt'  => 'Registrierung und Antrag: Leserzugang und Vereinsmitgliedschaft.',
		),
		array(
			'title'    => 'Vorteile',
			'slug'     => 'mitgliedschaft-vorteile',
			'template' => 'page-templates/template-vorteile.php',
			'content'  => 'p-mitgliedschaft-vorteile.html',
			'excerpt'  => 'Mitwirken, auf zwei Wegen: Leserzugang und Vereinsmitgliedschaft.',
		),
		array(
			'title'    => 'Aufzeichnungen',
			'slug'     => 'aufzeichnungen',
			'template' => $migrated,
			'content'  => 'p-recordings-archive.html',
		),
		array(
			'title'    => 'Anmelden',
			'slug'     => 'anmelden',
			'template' => 'page-templates/template-login.php',
			'content'  => 'p-login.html',
		),
		array(
			'title'    => 'Impressum',
			'slug'     => 'impressum',
			'template' => $migrated,
			'content'  => 'p-impressum.html',
		),
		array( 'title' => 'Positionen', 'slug' => 'positionen', 'template' => $migrated ),
		array( 'title' => 'Dossiers', 'slug' => 'dossiers', 'template' => $migrated ),
		array( 'title' => 'Studien', 'slug' => 'studien', 'template' => $migrated ),
	);
}

/**
 * @return list<string>
 */
function eg_seed_topics(): array {
	return array( 'Geopolitik', 'Energie', 'Wirtschaft', 'Kultur', 'Wissenschaft', 'Länder & Gesellschaften' );
}

/**
 * @return list<string>
 */
function eg_seed_regions(): array {
	return array(
		'Europa',
		'Kaukasus und Kaspischer Raum',
		'Westasien und Naher Osten',
		'Ostasien',
		'Osteuropa und Russland',
		'Südasien',
		'Zentralasien',
	);
}

/**
 * @return list<string>
 */
function eg_seed_formats(): array {
	return array( 'Aktuelles', 'Stellungnahmen', 'Positionen', 'Dossiers', 'Studien' );
}

/**
 * @return list<string>
 */
function eg_seed_roles(): array {
	return array( 'Vorstand', 'Experte', 'Autor', 'Referent' );
}

/**
 * Upsert a page with optional migrated HTML body.
 *
 * @param array{title:string,slug:string,template?:string,content?:string,excerpt?:string} $def
 * @return array{id:int,action:string}
 */
function eg_upsert_page( array $def, bool $overwrite_content ): array {
	$existing = get_page_by_path( $def['slug'] );
	$body     = '';
	if ( ! empty( $def['content'] ) ) {
		$body = eg_load_content_file( $def['content'] );
	}

	$postarr = array(
		'post_title'   => $def['title'],
		'post_name'    => $def['slug'],
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_excerpt' => $def['excerpt'] ?? '',
	);

	if ( $existing instanceof WP_Post ) {
		$postarr['ID'] = $existing->ID;
		$should_write  = $overwrite_content || '' === trim( (string) $existing->post_content ) || false !== strpos( (string) $existing->post_content, 'HTML-Prototyp' );
		if ( $should_write && '' !== $body ) {
			$postarr['post_content'] = $body;
		}
		$id = wp_update_post( $postarr, true );
		$action = 'updated';
	} else {
		$postarr['post_content'] = '' !== $body ? $body : '<!-- Inhalt aus dem HTML-Prototyp hier einfügen. -->';
		$id                      = wp_insert_post( $postarr, true );
		$action                  = 'created';
	}

	if ( is_wp_error( $id ) || ! $id ) {
		return array( 'id' => 0, 'action' => 'error' );
	}

	if ( ! empty( $def['template'] ) ) {
		update_post_meta( (int) $id, '_wp_page_template', $def['template'] );
	}

	return array( 'id' => (int) $id, 'action' => $action );
}

/**
 * Upsert person CPT entries from migrated profiles.
 *
 * @return array{created:int,updated:int}
 */
function eg_seed_persons( bool $overwrite_content ): array {
	$created = 0;
	$updated = 0;

	foreach ( eg_person_seed_defs() as $def ) {
		$body = eg_load_content_file( $def['file'] );
		$existing = get_page_by_path( $def['slug'], OBJECT, 'eg_person' );

		$postarr = array(
			'post_title'  => $def['title'],
			'post_name'   => $def['slug'],
			'post_status' => 'publish',
			'post_type'   => 'eg_person',
		);

		if ( $existing instanceof WP_Post ) {
			$postarr['ID'] = $existing->ID;
			if ( $overwrite_content || '' === trim( (string) $existing->post_content ) ) {
				$postarr['post_content'] = $body;
			}
			$id = wp_update_post( $postarr, true );
			if ( ! is_wp_error( $id ) && $id ) {
				++$updated;
			}
		} else {
			$postarr['post_content'] = $body;
			$id                      = wp_insert_post( $postarr, true );
			if ( ! is_wp_error( $id ) && $id ) {
				++$created;
			}
		}

		if ( is_wp_error( $id ) || ! $id ) {
			continue;
		}

		update_post_meta( (int) $id, 'eg_person_role_label', $def['role'] );
		if ( ! empty( $def['roles'] ) ) {
			wp_set_object_terms( (int) $id, $def['roles'], 'eg_role', false );
		}
	}

	return array(
		'created' => $created,
		'updated' => $updated,
	);
}

/**
 * Run the full seed / migration.
 *
 * @return array{pages_created:int,pages_updated:int,terms:int,persons_created:int,persons_updated:int}
 */
function eg_run_seed( bool $overwrite_content = false ): array {
	$pages_created = 0;
	$pages_updated = 0;
	$terms         = 0;

	foreach ( eg_seed_page_defs() as $def ) {
		$result = eg_upsert_page( $def, $overwrite_content );
		if ( 'created' === $result['action'] ) {
			++$pages_created;
		} elseif ( 'updated' === $result['action'] ) {
			++$pages_updated;
		}
	}

	$tax_map = array(
		'eg_topic'  => eg_seed_topics(),
		'eg_region' => eg_seed_regions(),
		'eg_format' => eg_seed_formats(),
		'eg_role'   => eg_seed_roles(),
	);

	foreach ( $tax_map as $tax => $names ) {
		foreach ( $names as $name ) {
			if ( ! term_exists( $name, $tax ) ) {
				$r = wp_insert_term( $name, $tax );
				if ( ! is_wp_error( $r ) ) {
					++$terms;
				}
			}
		}
	}

	$persons = eg_seed_persons( $overwrite_content );
	$cpts    = eg_seed_cpt_from_content( $overwrite_content );

	// Prefer theme front-page.php over the default blog index.
	if ( 'page' !== get_option( 'show_on_front' ) ) {
		$front = get_page_by_path( 'start' );
		if ( ! $front instanceof WP_Post ) {
			$front_id = wp_insert_post(
				array(
					'post_title'   => 'Start',
					'post_name'    => 'start',
					'post_status'  => 'publish',
					'post_type'    => 'page',
					'post_content' => '',
				),
				true
			);
		} else {
			$front_id = (int) $front->ID;
		}
		if ( ! is_wp_error( $front_id ) && $front_id ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', (int) $front_id );
		}
	}

	update_option( 'eg_seeded', 1 );
	update_option( 'eg_migrated_at', gmdate( 'c' ) );
	flush_rewrite_rules( false );

	return array(
		'pages_created'    => $pages_created,
		'pages_updated'    => $pages_updated,
		'terms'            => $terms,
		'persons_created'  => $persons['created'],
		'persons_updated'  => $persons['updated'],
		'analyses'         => $cpts['analyses'],
		'events'           => $cpts['events'],
		'recordings'       => $cpts['recordings'],
	);
}

add_action(
	'admin_init',
	static function (): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( empty( $_GET['eg_seed_pages'] ) && empty( $_GET['eg_migrate_content'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$overwrite = ! empty( $_GET['eg_migrate_content'] ) || ! empty( $_GET['eg_overwrite'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$result    = eg_run_seed( $overwrite );

		wp_safe_redirect(
			add_query_arg(
				array(
					'eg_seed_done'      => 1,
					'pages_created'     => $result['pages_created'],
					'pages_updated'     => $result['pages_updated'],
					'terms'             => $result['terms'],
					'persons_created'   => $result['persons_created'],
					'persons_updated'   => $result['persons_updated'],
					'analyses'          => $result['analyses'],
					'events'            => $result['events'],
					'recordings'        => $result['recordings'],
					'overwrote'         => $overwrite ? 1 : 0,
				),
				admin_url( 'themes.php?page=eg-setup' )
			)
		);
		exit;
	}
);

add_action(
	'admin_menu',
	static function (): void {
		add_theme_page(
			__( 'Eurasien Setup', 'eurasien-gesellschaft' ),
			__( 'Eurasien Setup', 'eurasien-gesellschaft' ),
			'manage_options',
			'eg-setup',
			static function (): void {
				$done = isset( $_GET['eg_seed_done'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$migrated_at = get_option( 'eg_migrated_at' );
				?>
				<div class="wrap">
					<h1><?php esc_html_e( 'Eurasien Gesellschaft Setup', 'eurasien-gesellschaft' ); ?></h1>
					<?php if ( $done ) : ?>
						<div class="notice notice-success"><p>
							<?php
							printf(
								/* translators: counts */
								esc_html__( 'Done. Pages +%1$d/~%2$d. Persons +%3$d/~%4$d. Analysen: %5$d. Events: %6$d. Recordings: %7$d.', 'eurasien-gesellschaft' ),
								(int) ( $_GET['pages_created'] ?? 0 ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
								(int) ( $_GET['pages_updated'] ?? 0 ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
								(int) ( $_GET['persons_created'] ?? 0 ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
								(int) ( $_GET['persons_updated'] ?? 0 ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
								(int) ( $_GET['analyses'] ?? 0 ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
								(int) ( $_GET['events'] ?? 0 ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
								(int) ( $_GET['recordings'] ?? 0 ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
							);
							?>
						</p></div>
					<?php endif; ?>

					<?php if ( $migrated_at ) : ?>
						<p><strong><?php esc_html_e( 'Last migration:', 'eurasien-gesellschaft' ); ?></strong> <?php echo esc_html( (string) $migrated_at ); ?></p>
					<?php endif; ?>

					<p><?php esc_html_e( 'Imports sitemap pages and person profiles from the HTML prototype content stored in the theme (/content).', 'eurasien-gesellschaft' ); ?></p>

					<p>
						<a class="button button-primary" href="<?php echo esc_url( admin_url( 'themes.php?page=eg-setup&eg_migrate_content=1' ) ); ?>">
							<?php esc_html_e( 'Import pages + content now', 'eurasien-gesellschaft' ); ?>
						</a>
						<a class="button" href="<?php echo esc_url( admin_url( 'options-permalink.php' ) ); ?>">
							<?php esc_html_e( 'Open Permalinks (then click Save)', 'eurasien-gesellschaft' ); ?>
						</a>
					</p>
					<p>
						<a class="button" href="<?php echo esc_url( admin_url( 'themes.php?page=eg-setup&eg_seed_pages=1' ) ); ?>">
							<?php esc_html_e( 'Seed / fill empty pages only', 'eurasien-gesellschaft' ); ?>
						</a>
					</p>

					<h2><?php esc_html_e( 'Migrated into pages', 'eurasien-gesellschaft' ); ?></h2>
					<ul>
						<li>Mission, Partner, Impressum, Themen + topic pages</li>
						<li>Kultur, Länder &amp; Gesellschaften, Regionen, Mitgliedschaft</li>
						<li>Gesellschaftsnachrichten, Aufzeichnungen, Anmelden</li>
					</ul>

					<h2><?php esc_html_e( 'Migrated into Personen CPT', 'eurasien-gesellschaft' ); ?></h2>
					<ul>
						<li>Alexander Rahr, Dr. Christian Wipperfürth, Dr. Alexander Neu, Christoph Polajner, Andreas Schraps</li>
					</ul>

					<h2><?php esc_html_e( 'Still manual / plugin work', 'eurasien-gesellschaft' ); ?></h2>
					<ol>
						<li><?php esc_html_e( 'Settings → Permalinks → Save.', 'eurasien-gesellschaft' ); ?></li>
						<li><?php esc_html_e( 'Convert Analysen / Veranstaltungen / Mediathek cards into CPT entries (content files p-analysen, p-veranstaltungen, p-mediathek are reference only for now).', 'eurasien-gesellschaft' ); ?></li>
						<li><?php esc_html_e( 'Replace inline portrait placeholders (data-eg-inline) with Media Library images.', 'eurasien-gesellschaft' ); ?></li>
						<li><?php esc_html_e( 'Polylang/WPML for real bilingual content; MemberPress (or similar) for payments.', 'eurasien-gesellschaft' ); ?></li>
					</ol>
				</div>
				<?php
			}
		);
	}
);
