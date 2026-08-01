# Eurasien Gesellschaft WordPress Theme

Custom theme ported from `eurasien_gesellschaft_.html`. Visual system (navy / paper / oxblood, Source Serif 4 + Libre Franklin) is preserved in `assets/css/main.css`.

## Install

1. Copy `wp-theme/eurasien-gesellschaft` into `wp-content/themes/eurasien-gesellschaft`.
2. Activate **Eurasien Gesellschaft** under Appearance → Themes.
3. Open **Appearance → Eurasien Setup** and click **Seed pages & taxonomies**.
4. Settings → Permalinks → Save (flush rewrite rules).
5. Settings → Reading: create a page “Start” if needed, set it as the static front page (or leave the theme `front-page.php` as the site root).
6. Appearance → Menus: build **Hauptnavigation** from the seeded pages (optional; a fallback mega-nav is included).

## Prototype → WordPress map

| Prototype `#p-*` | WordPress |
|---|---|
| Home | `front-page.php` |
| Mission, Partner, Impressum, Themen pages | Pages (seeded) |
| Analysen | CPT `eg_analyse` + archive `/analysen/` |
| Veranstaltungen | CPT `eg_event` + archive `/veranstaltungen/` |
| Mediathek | CPT `eg_recording` + archive `/mediathek/` |
| Vorstand / Personen | CPT `eg_person` + Vorstand page template |
| Gesellschaftsnachrichten | Core posts / blog index |
| Mitgliedschaft gates | Page template + membership plugin |

Taxonomies: `eg_format`, `eg_topic`, `eg_region`, `eg_role`.

Event meta: `eg_event_start` (ISO date), `eg_event_location`.  
Recording meta: `eg_youtube_url`.

## Bilingual (DE / EN)

The prototype used client-side `.de` / `.en` spans. The theme keeps that UI toggle for chrome copy. For editable bilingual **content**, install **Polylang** or **WPML** and translate posts/pages properly.

## Payments & members area

Do not rebuild the prototype payment form for production. Use:

- MemberPress, or
- WooCommerce + Memberships / Subscriptions, or
- Restrict Content Pro

Then gate Positionen / Dossiers / Studien (format taxonomy or dedicated pages) with that plugin.

## Content migration

Prototype HTML for each `#p-*` page lives in `content/*.html` (links rewritten to WP paths, page heads stripped, inline base64 images cleared).

1. Activate the theme.
2. Open **Appearance → Eurasien Setup**.
3. Click **Seed / fill empty pages** (first run) or **Re-migrate (overwrite content)** to force-refresh from `content/`.
4. This creates/updates pages and the five **Personen** CPT profiles with real copy.

Reference-only extracts (not auto-imported as CPT cards yet): `p-analysen.html`, `p-veranstaltungen.html`, `p-mediathek.html`.

The Regionen map SVG is at `assets/images/eurasia-map.svg`.

## Local / hosting notes

- Requires PHP 8.0+ and WordPress 6.4+.
- Theme URI expects the live domain later; update canonical / SEO plugins as needed.
- Keep `eurasien_gesellschaft_.html` as the design reference until content migration is finished.
