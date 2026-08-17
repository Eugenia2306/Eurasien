<?php
/**
 * One-shot seed: import brochure events into eg_event CPT.
 * URL: /app/eg-event-seed.php?k=eg-setup-2026
 * Prefer WP admin: Veranstaltungen → Import brochure.
 */
if (!isset($_GET['k']) || $_GET['k'] !== 'eg-setup-2026') {
    status_header(404);
    exit;
}

require __DIR__ . '/wp-load.php';

nocache_headers();
header('Content-Type: text/plain; charset=utf-8');

if (!function_exists('eg_event_admin_run_seed')) {
    echo "eg_event_admin_run_seed missing\n";
    exit;
}

$result = eg_event_admin_run_seed();
echo 'created=' . (int) $result['created'] . ' updated=' . (int) $result['updated'] . ' trashed=' . (int) ($result['trashed'] ?? 0) . "\n";
echo 'ids=' . implode(',', array_map('intval', $result['ids'])) . "\n";
