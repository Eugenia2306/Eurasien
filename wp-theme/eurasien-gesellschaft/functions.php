<?php
/**
 * Eurasien Gesellschaft theme bootstrap.
 *
 * @package Eurasien_Gesellschaft
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EG_THEME_VERSION', '1.1.8' );
define( 'EG_THEME_DIR', get_template_directory() );
define( 'EG_THEME_URI', get_template_directory_uri() );

require_once EG_THEME_DIR . '/inc/setup.php';
require_once EG_THEME_DIR . '/inc/enqueue.php';
require_once EG_THEME_DIR . '/inc/cpt.php';
require_once EG_THEME_DIR . '/inc/helpers.php';
require_once EG_THEME_DIR . '/inc/content.php';
require_once EG_THEME_DIR . '/inc/language.php';
require_once EG_THEME_DIR . '/inc/elementor.php';
require_once EG_THEME_DIR . '/inc/seed-cpt.php';
require_once EG_THEME_DIR . '/inc/seed-pages.php';
require_once EG_THEME_DIR . '/inc/activation.php';
