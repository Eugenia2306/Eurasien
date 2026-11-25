<?php
/**
 * Serves a static page with its marked areas filled from WordPress (see wp-content/mu-plugins/eg-render-engine.php).
 *
 * - Fast path: a cached copy, valid while it is newer than the page file, the event archive,
 *   the WordPress change stamp, at most one hour old and from today (event status changes at midnight).
 * - Slow path: loads WordPress once, renders, stores the cache.
 * - If anything fails (WordPress error, fatal error, no data): the last cached copy, otherwise the
 *   static page file itself. Visitors never see an error because of WordPress.
 *
 * Called by .htaccess (veranstaltungen.html, llms.txt) and by index.php (homepage, sets $eg_render_page).
 * Header X-EG-Render says which copy was served (cache / fresh / last-good / static); reasons go to .eg-cache/render.log.
 */

if (!defined('EG_RENDER_ROOT')) {
    define('EG_RENDER_ROOT', __DIR__);
}

/** page key => [template file, content type] */
function eg_render_pages()
{
    return array(
        'index'           => array('index.html', 'text/html; charset=utf-8'),
        'veranstaltungen' => array('veranstaltungen.html', 'text/html; charset=utf-8'),
        'llms'            => array('llms.txt', 'text/plain; charset=utf-8'),
        'analysen'        => array('analysen.html', 'text/html; charset=utf-8'),
        'regionen'        => array('regionen.html', 'text/html; charset=utf-8'),
    );
}

function eg_render_send($body, $type, $source)
{
    if (!headers_sent()) {
        header('Content-Type: ' . $type);
        header('Cache-Control: no-cache, max-age=0, must-revalidate');
        header('CDN-Cache-Control: no-store');
        header('X-EG-Render: ' . $source);
    }
    echo $body;
}

/** Internal reason log (not web-readable); the public header only says which copy was served. */
function eg_render_log($msg)
{
    @file_put_contents(EG_RENDER_ROOT . '/.eg-cache/render.log', '[' . gmdate('c') . '] ' . $msg . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Fast path. Returns null when the page was served from cache, otherwise the state for rendering.
 *
 * @return array<string,string>|null
 */
function eg_render_begin($page)
{
    $pages = eg_render_pages();
    if (!isset($pages[$page])) {
        http_response_code(404);
        exit;
    }
    list($file, $type) = $pages[$page];
    $template = EG_RENDER_ROOT . '/' . $file;
    if (!is_readable($template)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'page missing';
        exit;
    }
    $cache_dir = EG_RENDER_ROOT . '/.eg-cache';
    if (!is_dir($cache_dir)) {
        @mkdir($cache_dir, 0755);
    }
    if (!is_file($cache_dir . '/.htaccess')) {
        @file_put_contents($cache_dir . '/.htaccess', "Require all denied\n");
    }
    $berlin = new DateTime('now', new DateTimeZone('Europe/Berlin'));
    $cache = $cache_dir . '/' . $page . '-' . $berlin->format('Ymd') . '.cache';
    $deps = array(
        $template,
        EG_RENDER_ROOT . '/assets/data/event-archive.json',
        EG_RENDER_ROOT . '/app/wp-content/eg-render-stamp',
        EG_RENDER_ROOT . '/app/wp-content/mu-plugins/eg-render-engine.php',
        EG_RENDER_ROOT . '/app/wp-content/mu-plugins/eg-analysen.php',
        __FILE__,
    );
    $newest = 0;
    foreach ($deps as $d) {
        $m = @filemtime($d);
        if ($m && $m > $newest) {
            $newest = $m;
        }
    }
    $cm = @filemtime($cache);
    if ($cm && $cm >= $newest && (time() - $cm) < 3600) {
        eg_render_send((string) file_get_contents($cache), $type, 'cache');
        return null;
    }
    return array(
        'page'      => $page,
        'type'      => $type,
        'template'  => $template,
        'cache'     => $cache,
        'cache_dir' => $cache_dir,
        'last_good' => $cache_dir . '/' . $page . '-last.cache',
    );
}

function eg_render_fallback($st, $why)
{
    eg_render_log($st['page'] . ' fallback: ' . $why);
    if (is_readable($st['last_good'])) {
        eg_render_send((string) file_get_contents($st['last_good']), $st['type'], 'last-good');
    } else {
        eg_render_send((string) file_get_contents($st['template']), $st['type'], 'static');
    }
}

function eg_render_finish($st)
{
    if (!function_exists('eg_render_apply')) {
        throw new RuntimeException('render engine missing');
    }
    $out = eg_render_apply($st['page'], (string) file_get_contents($st['template']));
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $tmp = $st['cache'] . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, $out) !== false) {
        @rename($tmp, $st['cache']);
        @copy($st['cache'], $st['last_good']);
    }
    foreach (glob($st['cache_dir'] . '/' . $st['page'] . '-2*.cache') ?: array() as $old) {
        if ($old !== $st['cache']) {
            @unlink($old);
        }
    }
    eg_render_send($out, $st['type'], 'fresh');
}

/* ---------- entry (global scope: WordPress must be loaded outside any function) ---------- */

$eg_render_state = eg_render_begin(isset($eg_render_page) ? (string) $eg_render_page : (isset($_GET['p']) ? (string) $_GET['p'] : ''));
if ($eg_render_state !== null) {
    $eg_render_done = false;
    // A fatal error inside WordPress cannot be caught: answer from the shutdown handler instead.
    register_shutdown_function(static function () use (&$eg_render_done, $eg_render_state) {
        if ($eg_render_done) {
            return;
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $err = error_get_last();
        eg_render_fallback($eg_render_state, 'fatal' . ($err ? ' ' . $err['message'] . ' @ ' . basename((string) $err['file']) . ':' . $err['line'] : ''));
    });
    ob_start();
    try {
        if (!is_readable(EG_RENDER_ROOT . '/app/wp-load.php')) {
            throw new RuntimeException('wp-load missing');
        }
        require_once EG_RENDER_ROOT . '/app/wp-load.php';
        eg_render_finish($eg_render_state);
        $eg_render_done = true;
    } catch (Throwable $eg_render_e) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $eg_render_done = true;
        eg_render_fallback($eg_render_state, get_class($eg_render_e) . ': ' . $eg_render_e->getMessage());
    }
}
