<?php
/**
 * WooCommerce shell using the Eurasien Gesellschaft design system.
 *
 * Product-loop markup remains owned by WooCommerce so updates, AJAX cart
 * actions, and accessibility attributes continue to work normally.
 *
 * @package Eurasien_Gesellschaft
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$is_shop_archive = function_exists( 'is_shop' ) && is_shop();
if ( $is_shop_archive ) {
	add_filter( 'woocommerce_show_page_title', '__return_false' );
}

$cart_count = 0;
if ( function_exists( 'WC' ) && WC()->cart ) {
	$cart_count = WC()->cart->get_cart_contents_count();
}
?>
<div class="eg-commerce">
	<?php if ( $is_shop_archive ) : ?>
		<section class="eg-shop-hero" aria-labelledby="eg-shop-title">
			<div class="wrap eg-shop-hero__grid">
				<div>
					<p class="eyebrow"><?php eg_bi_e( 'Publikationen', 'Publications' ); ?></p>
					<h1 id="eg-shop-title"><?php eg_bi_e( 'Wissen, das Perspektiven öffnet.', 'Knowledge that opens perspectives.' ); ?></h1>
					<p class="eg-shop-hero__lead">
						<?php
						eg_bi_e(
							'Ausgewählte Publikationen und vertiefende Inhalte der Eurasien Gesellschaft.',
							'Selected publications and in-depth content from the Eurasien Gesellschaft.'
						);
						?>
					</p>
				</div>
				<a class="eg-cart-link" href="<?php echo esc_url( wc_get_cart_url() ); ?>">
					<svg viewBox="0 0 24 24" aria-hidden="true">
						<path d="M3 4h2l2.1 10.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.6L20 8H7M10 20h.01M17 20h.01"/>
					</svg>
					<span><?php eg_bi_e( 'Warenkorb', 'Cart' ); ?></span>
					<span class="eg-cart-link__count" aria-label="<?php echo esc_attr( sprintf( _n( '%d Artikel', '%d Artikel', $cart_count, 'eurasien-gesellschaft' ), $cart_count ) ); ?>">
						<?php echo esc_html( (string) $cart_count ); ?>
					</span>
				</a>
			</div>
		</section>

		<div class="eg-shop-assurance" aria-label="<?php esc_attr_e( 'Shop benefits', 'eurasien-gesellschaft' ); ?>">
			<div class="wrap eg-shop-assurance__grid">
				<span><i aria-hidden="true">✓</i><?php eg_bi_e( 'Sicherer Checkout', 'Secure checkout' ); ?></span>
				<span><i aria-hidden="true">✓</i><?php eg_bi_e( 'Direkter Zugang', 'Direct access' ); ?></span>
				<span><i aria-hidden="true">✓</i><?php eg_bi_e( 'Persönlicher Support', 'Personal support' ); ?></span>
			</div>
		</div>
	<?php endif; ?>

	<section class="sec eg-commerce__content">
		<div class="wrap">
			<div class="woocommerce">
				<?php woocommerce_content(); ?>
			</div>
		</div>
	</section>
</div>
<?php
get_footer();
