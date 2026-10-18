<?php
/**
 * Default page template — same page-head pattern as the brochure.
 *
 * @package Eurasien_Gesellschaft
 */

get_header();

while ( have_posts() ) :
	the_post();

	if ( function_exists( 'eg_is_elementor_page' ) && eg_is_elementor_page() ) {
		?>
		<main id="content" class="eg-elementor-canvas">
			<?php the_content(); ?>
		</main>
		<?php
		continue;
	}

	$is_auth = function_exists( 'eg_is_app_auth_page' ) && eg_is_app_auth_page();
	$is_app  = function_exists( 'eg_is_app_utility_page' ) && eg_is_app_utility_page();
	$is_cart_page = function_exists( 'is_cart' ) && is_cart();
	$is_checkout_page = function_exists( 'is_checkout' ) && is_checkout();
	$is_order_received = function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' );
	$is_commerce_flow = $is_cart_page || $is_checkout_page;
	$slug    = (string) get_post_field( 'post_name', get_the_ID() );

	$header_args = array(
		'title' => get_the_title(),
	);

	/* Slight context only — same phead pattern as static brochure pages. */
	if ( $is_app ) {
		$header_args['eyebrow'] = eg_bi( 'Mitgliederbereich', "Members' area" );
	}
	if ( $is_commerce_flow ) {
		if ( $is_order_received ) {
			$header_args['eyebrow'] = eg_bi( 'Bestellung erfolgreich', 'Order successful' );
			$header_args['title'] = eg_bi( 'Bestellung bestätigt', 'Order confirmed' );
			$header_args['lead'] = eg_bi(
				'Vielen Dank. Ihre Bestellung wurde angenommen; Ihre gekauften Inhalte stehen unten bereit.',
				'Thank you. Your order has been received and your purchased content is ready below.'
			);
		} else {
			$header_args['eyebrow'] = eg_bi( 'Sicher einkaufen', 'Secure shopping' );
			$header_args['title'] = $is_cart_page
				? eg_bi( 'Ihr Warenkorb', 'Your cart' )
				: eg_bi( 'Bestellung abschließen', 'Complete your order' );
			$header_args['lead'] = $is_cart_page
				? eg_bi( 'Prüfen Sie Ihre Auswahl, bevor Sie sicher zur Kasse gehen.', 'Review your selection before proceeding securely to checkout.' )
				: eg_bi( 'Nur noch wenige Angaben – anschließend können Sie Ihre Bestellung prüfen und absenden.', 'Just a few details remain, then you can review and place your order.' );
		}
	}
	if ( $is_auth && in_array( $slug, array( 'login', 'log-in' ), true ) ) {
		$header_args['title'] = eg_bi( 'Anmelden', 'Log In' );
		$header_args['lead']  = eg_bi(
			'Melden Sie sich an, um Ihr Konto und den Mitgliederbereich zu öffnen.',
			'Sign in to open your account and the members’ area.'
		);
	}

	get_template_part( 'template-parts/page-header', null, $header_args );

	$sec_class = 'sec';
	if ( $is_commerce_flow ) {
		$sec_class .= ' sec--commerce-flow';
	} elseif ( $is_auth ) {
		$sec_class .= ' sec--app-auth';
	} elseif ( $is_app ) {
		$sec_class .= ' sec--app';
	}
	?>
	<section class="<?php echo esc_attr( $sec_class ); ?>">
		<?php if ( $is_commerce_flow ) : ?>
			<nav class="wrap eg-checkout-steps" aria-label="<?php esc_attr_e( 'Bestellfortschritt', 'eurasien-gesellschaft' ); ?>">
				<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">
					<span>1</span><?php eg_bi_e( 'Auswahl', 'Selection' ); ?>
				</a>
				<a class="<?php echo $is_cart_page ? 'is-current' : 'is-complete'; ?>" href="<?php echo esc_url( wc_get_cart_url() ); ?>"<?php echo $is_cart_page ? ' aria-current="step"' : ''; ?>>
					<span>2</span><?php eg_bi_e( 'Warenkorb', 'Cart' ); ?>
				</a>
				<span class="<?php echo $is_checkout_page ? 'is-current' : ''; ?>"<?php echo $is_checkout_page ? ' aria-current="step"' : ''; ?>>
					<span>3</span>
					<?php if ( $is_order_received ) : ?>
						<?php eg_bi_e( 'Bestätigt', 'Confirmed' ); ?>
					<?php else : ?>
						<?php eg_bi_e( 'Kasse', 'Checkout' ); ?>
					<?php endif; ?>
				</span>
			</nav>
		<?php endif; ?>
		<div class="wrap <?php echo $is_commerce_flow ? 'eg-commerce-flow' : ( $is_auth ? 'wrap--auth' : 'wrap-narrow' ); ?> entry-content">
			<?php the_content(); ?>
			<?php if ( $is_auth && in_array( $slug, array( 'login', 'log-in' ), true ) && ! is_user_logged_in() ) : ?>
				<p class="eg-auth-aside">
					<?php eg_bi_e( 'Noch kein Zugang?', 'No account yet?' ); ?>
					<a href="<?php echo esc_url( function_exists( 'eg_membership_signup_url' ) ? eg_membership_signup_url( 'expert' ) : '/mitgliedschaft.html?plan=expert#membership-registration' ); ?>" data-analytics="membership_click">
						<?php eg_bi_e( 'Mitgliedschaft beantragen', 'Apply for membership' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
	</section>
	<?php
endwhile;

get_footer();
