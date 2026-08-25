<?php
/**
 * @deprecated 1.2.4 Chrome is unified in header.php (brochure ubar + masthead).
 * Kept so old includes do not fatal; prefer get_header() only.
 *
 * @package Eurasien_Gesellschaft
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// No-op: header.php renders the shared brochure chrome.
