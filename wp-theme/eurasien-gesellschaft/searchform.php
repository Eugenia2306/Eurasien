<?php
/**
 * Search form matching prototype chrome.
 *
 * @package Eurasien_Gesellschaft
 */

declare(strict_types=1);
?>
<form role="search" method="get" class="search search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php esc_attr_e( 'Suche / Search', 'eurasien-gesellschaft' ); ?>">
	<svg class="icn" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="16.5" y1="16.5" x2="21" y2="21"/></svg>
	<label class="screen-reader-text" for="eg-search"><?php esc_html_e( 'Suche', 'eurasien-gesellschaft' ); ?></label>
	<input id="eg-search" class="search-field" type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'Suche…', 'eurasien-gesellschaft' ); ?>">
	<button type="submit" class="search-submit"><?php esc_html_e( 'Suchen', 'eurasien-gesellschaft' ); ?></button>
</form>
