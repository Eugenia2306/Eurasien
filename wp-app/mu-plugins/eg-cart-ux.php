<?php
/**
 * Plugin Name: EG Cart UX
 * Description: Reliable cart quantity updates and checkout session recovery for virtual products.
 * Author: d4rl1ngt0n
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Disable WooCommerce cart AJAX on the cart page.
 * Theme HTML is large; cart.js parseHTML can miss .woocommerce-cart-form and
 * falsely swap in "Your cart is currently empty."
 */
add_action('wp_enqueue_scripts', function () {
    if (!function_exists('is_cart') || !is_cart()) {
        return;
    }
    wp_dequeue_script('wc-cart');
    wp_deregister_script('wc-cart');
}, 1000);

/**
 * Keep Woo session cookies readable across /app/cart and /app/checkout.
 */
add_filter('woocommerce_set_cookie_options', function ($options) {
    if (!is_array($options)) {
        $options = array();
    }
    $options['path'] = '/';
    $options['secure'] = is_ssl();
    $options['httponly'] = true;
    if (PHP_VERSION_ID >= 70300) {
        $options['samesite'] = 'Lax';
    }
    return $options;
}, 20);

/**
 * After a classic Update cart POST, always return to the cart URL.
 */
add_filter('wp_redirect', function ($location, $status = 302) {
    if (empty($_POST['update_cart']) || !function_exists('wc_get_cart_url')) {
        return $location;
    }
    if (wp_doing_ajax()) {
        return $location;
    }
    return wc_get_cart_url();
}, 1, 2);

/**
 * Backup cart contents so checkout can recover if the browser drops the session.
 * Uses our own eg_cart_token cookie (survives a new Woo session id).
 */
function eg_cart_token(): string
{
    $cookie = 'eg_cart_token';
    $token = isset($_COOKIE[$cookie]) ? preg_replace('/[^a-zA-Z0-9]/', '', (string) $_COOKIE[$cookie]) : '';
    if (strlen($token) < 16) {
        $token = wp_generate_password(32, false, false);
        if (!headers_sent()) {
            $opts = array(
                'expires' => time() + 2 * DAY_IN_SECONDS,
                'path' => '/',
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            );
            if (PHP_VERSION_ID >= 70300) {
                setcookie($cookie, $token, $opts);
            } else {
                setcookie($cookie, $token, $opts['expires'], '/', '', $opts['secure'], true);
            }
        }
        $_COOKIE[$cookie] = $token;
    }
    return $token;
}

function eg_cart_backup_key(): string
{
    $token = eg_cart_token();
    return $token !== '' ? ('eg_cart_backup_' . md5($token)) : '';
}

function eg_cart_write_backup(): void
{
    if (!function_exists('WC') || !WC()->cart || !WC()->session) {
        return;
    }
    $key = eg_cart_backup_key();
    if ($key === '' || WC()->cart->is_empty()) {
        return;
    }
    $payload = array(
        'items' => array(),
        'time' => time(),
    );
    foreach (WC()->cart->get_cart() as $item) {
        $payload['items'][] = array(
            'product_id' => (int) ($item['product_id'] ?? 0),
            'variation_id' => (int) ($item['variation_id'] ?? 0),
            'quantity' => (float) ($item['quantity'] ?? 1),
            'variation' => isset($item['variation']) && is_array($item['variation']) ? $item['variation'] : array(),
        );
    }
    set_transient($key, $payload, 2 * DAY_IN_SECONDS);
}

function eg_cart_restore_backup(): bool
{
    if (!function_exists('WC') || !WC()->cart || !WC()->session) {
        return false;
    }
    if (!WC()->cart->is_empty()) {
        return false;
    }
    $key = eg_cart_backup_key();
    if ($key === '') {
        return false;
    }
    $payload = get_transient($key);
    if (!is_array($payload) || empty($payload['items']) || !is_array($payload['items'])) {
        return false;
    }
    $added = 0;
    foreach ($payload['items'] as $row) {
        $pid = (int) ($row['product_id'] ?? 0);
        $qty = (float) ($row['quantity'] ?? 0);
        if ($pid < 1 || $qty < 1) {
            continue;
        }
        $vid = (int) ($row['variation_id'] ?? 0);
        $variation = isset($row['variation']) && is_array($row['variation']) ? $row['variation'] : array();
        $result = WC()->cart->add_to_cart($pid, $qty, $vid, $variation);
        if ($result) {
            $added++;
        }
    }
    if ($added > 0) {
        WC()->cart->calculate_totals();
        if (WC()->session) {
            WC()->session->save_data();
        }
        eg_cart_write_backup();
        return true;
    }
    return false;
}

add_action('woocommerce_cart_updated', 'eg_cart_write_backup', 20);
add_action('woocommerce_add_to_cart', 'eg_cart_write_backup', 20);
add_action('woocommerce_after_calculate_totals', 'eg_cart_write_backup', 20);

/**
 * True when this request is checkout page, checkout AJAX, or Place order POST.
 */
function eg_is_checkout_flow_request(): bool
{
    if (isset($_POST['woocommerce-process-checkout-nonce']) || isset($_POST['woocommerce_checkout_place_order'])) {
        return true;
    }
    $ajax = isset($_REQUEST['wc-ajax']) ? (string) $_REQUEST['wc-ajax'] : '';
    if (in_array($ajax, array('update_order_review', 'checkout', 'eg_update_cart_qty'), true)) {
        return true;
    }
    $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    if ($uri !== '' && stripos($uri, '/checkout') !== false) {
        return true;
    }
    if (function_exists('is_checkout') && is_checkout()) {
        return true;
    }
    return false;
}

/**
 * Restore backed-up cart as early as possible in checkout flows.
 * Critical: update_order_review AJAX can start a new empty session and
 * overwrite cookies; restore must run before Woo marks the session expired.
 */
function eg_cart_maybe_restore_for_checkout(): void
{
    if (!eg_is_checkout_flow_request()) {
        return;
    }
    if (!function_exists('WC') || !WC()->cart) {
        return;
    }
    if (!WC()->cart->is_empty()) {
        eg_cart_write_backup();
        return;
    }
    eg_cart_restore_backup();
}

add_action('woocommerce_cart_loaded_from_session', function () {
    eg_cart_maybe_restore_for_checkout();
}, 30);

add_action('wp_loaded', function () {
    eg_cart_maybe_restore_for_checkout();
}, 30);

add_action('wc_ajax_update_order_review', function () {
    eg_cart_maybe_restore_for_checkout();
}, 0);

add_action('woocommerce_before_checkout_process', function () {
    eg_cart_maybe_restore_for_checkout();
}, 0);

/**
 * Checkout pages must never be cached (stale nonce = "unable to process your order").
 */
add_action('template_redirect', function () {
    if (!function_exists('is_checkout') || !is_checkout()) {
        return;
    }
    nocache_headers();
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    if (function_exists('WC') && WC()->session && method_exists(WC()->session, 'has_session') && !WC()->session->has_session()) {
        WC()->session->set_customer_session_cookie(true);
    }
}, 0);

/**
 * Fresh Place order nonce for AJAX (survives payment-block replacement / cache).
 */
add_action('wc_ajax_eg_checkout_nonce', function () {
    if (function_exists('eg_cart_maybe_restore_for_checkout')) {
        eg_cart_maybe_restore_for_checkout();
    }
    wp_send_json_success(
        array(
            'nonce' => wp_create_nonce('woocommerce-process_checkout'),
        )
    );
});

/**
 * Sticky nonce outside #payment so update_order_review / Stripe cannot drop it.
 */
add_action('woocommerce_checkout_after_customer_details', function () {
    $nonce = wp_create_nonce('woocommerce-process_checkout');
    echo '<div id="eg-sticky-checkout-nonce-wrap" class="eg-sticky-checkout-nonce-wrap" style="display:none" aria-hidden="true">';
    echo '<input type="hidden" id="eg-sticky-checkout-nonce" name="woocommerce-process-checkout-nonce" value="' . esc_attr($nonce) . '" />';
    echo '</div>';
}, 5);

/**
 * Keep the Place order nonce fresh whenever checkout totals refresh.
 */
add_filter('woocommerce_update_order_review_fragments', function ($fragments) {
    if (!is_array($fragments)) {
        $fragments = array();
    }
    $nonce = wp_create_nonce('woocommerce-process_checkout');
    $fragments['#woocommerce-process-checkout-nonce'] = sprintf(
        '<input type="hidden" id="woocommerce-process-checkout-nonce" name="woocommerce-process-checkout-nonce" value="%s" />',
        esc_attr($nonce)
    );
    $fragments['#eg-sticky-checkout-nonce'] = sprintf(
        '<input type="hidden" id="eg-sticky-checkout-nonce" name="woocommerce-process-checkout-nonce" value="%s" />',
        esc_attr($nonce)
    );
    return $fragments;
}, 20);

/**
 * Restore cart before Place order AJAX, but never block/reload the request.
 * A previous reload interceptor prevented orders from being created.
 */
add_action('wc_ajax_checkout', function () {
    eg_cart_maybe_restore_for_checkout();
}, 0);

/**
 * Restore backed-up cart before checkout/cart render if the session went empty.
 * Quiet restore (no banner) so it does not look like an error state.
 */
add_action('template_redirect', function () {
    if (!function_exists('is_checkout') || (!is_checkout() && !(function_exists('is_cart') && is_cart()))) {
        return;
    }
    if (!function_exists('WC') || !WC()->cart) {
        return;
    }
    if (!WC()->cart->is_empty()) {
        return;
    }
    eg_cart_restore_backup();
}, 5);

/**
 * After payment, auto-complete virtual/downloadable orders so downloads unlock.
 */
add_action('woocommerce_payment_complete', function ($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }
    if (!$order->has_status(array('processing', 'on-hold', 'pending'))) {
        return;
    }
    $only_virtual = true;
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if (!$product || (!$product->is_virtual() && !$product->is_downloadable())) {
            $only_virtual = false;
            break;
        }
    }
    if ($only_virtual && $order->has_status('processing')) {
        $order->update_status('completed', 'EG: virtual/downloadable order auto-completed.');
    }
}, 20);

/**
 * Make sure thank-you / order-received URLs stay under /app/.
 */
add_filter('woocommerce_get_checkout_order_received_url', function ($url, $order) {
    if (!is_string($url) || $url === '') {
        return $url;
    }
    // Force absolute /app/ thank-you URL when possible.
    if (function_exists('wc_get_endpoint_url') && $order) {
        $checkout = wc_get_checkout_url();
        $received = wc_get_endpoint_url('order-received', $order->get_id(), $checkout);
        return add_query_arg('key', $order->get_order_key(), $received);
    }
    return $url;
}, 20, 2);

/**
 * Safe qty update via frontend wc-ajax (same cookie scope as cart/checkout).
 */
function eg_cart_ajax_update_qty()
{
    if (!function_exists('WC') || !WC()->cart) {
        wp_send_json_error(array('message' => 'Cart unavailable'), 400);
    }

    if (WC()->session && method_exists(WC()->session, 'has_session') && !WC()->session->has_session()) {
        WC()->session->set_customer_session_cookie(true);
    }

    $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'eg_cart_update_qty')) {
        wp_send_json_error(array('message' => 'Security check failed. Refresh the page and try again.'), 403);
    }

    $cart_key = isset($_REQUEST['cart_key']) ? sanitize_text_field(wp_unslash($_REQUEST['cart_key'])) : '';
    $qty = isset($_REQUEST['qty']) ? wc_stock_amount(wp_unslash($_REQUEST['qty'])) : 0;

    if ($cart_key === '' || $qty < 1) {
        wp_send_json_error(array('message' => 'Quantity must be at least 1.'), 400);
    }

    if (WC()->cart->is_empty()) {
        eg_cart_restore_backup();
    }

    $item = WC()->cart->get_cart_item($cart_key);
    if (!$item) {
        wp_send_json_error(array(
            'message' => 'That cart item was not found. Refresh the page.',
            'cart_count' => WC()->cart->get_cart_contents_count(),
            'cart_empty' => WC()->cart->is_empty(),
        ), 404);
    }

    $passed = apply_filters('woocommerce_update_cart_validation', true, $cart_key, $item, $qty);
    if (!$passed) {
        wp_send_json_error(array('message' => 'Could not update quantity.'), 400);
    }

    WC()->cart->set_quantity($cart_key, $qty, true);
    WC()->cart->calculate_totals();
    eg_cart_write_backup();
    if (WC()->session) {
        WC()->session->save_data();
    }

    if (WC()->cart->is_empty()) {
        wp_send_json_error(array('message' => 'Cart became empty unexpectedly.'), 500);
    }

    $lines = array();
    foreach (WC()->cart->get_cart() as $key => $cart_item) {
        $product = $cart_item['data'];
        $lines[$key] = array(
            'qty' => $cart_item['quantity'],
            'line_html' => WC()->cart->get_product_subtotal($product, $cart_item['quantity']),
            'line_raw' => (float) $cart_item['line_subtotal'],
        );
    }

    wp_send_json_success(array(
        'lines' => $lines,
        'subtotal_html' => WC()->cart->get_cart_subtotal(),
        'total_html' => WC()->cart->get_total(),
        'cart_count' => WC()->cart->get_cart_contents_count(),
        'cart_hash' => WC()->cart->get_cart_hash(),
        'checkout_url' => wc_get_checkout_url(),
    ));
}

add_action('wc_ajax_eg_update_cart_qty', 'eg_cart_ajax_update_qty');
add_action('wp_ajax_eg_cart_update_qty', 'eg_cart_ajax_update_qty');
add_action('wp_ajax_nopriv_eg_cart_update_qty', 'eg_cart_ajax_update_qty');

/**
 * Auto-refresh totals when quantity changes (AJAX, no full page reload).
 */
add_action('wp_footer', function () {
    if (!function_exists('is_cart') || !is_cart()) {
        return;
    }
    $ajax_url = add_query_arg('wc-ajax', 'eg_update_cart_qty', home_url('/'));
    $nonce = wp_create_nonce('eg_cart_update_qty');
    $checkout_url = function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url('/checkout/');
    ?>
<script id="eg-cart-ux">
(function () {
  var timer = null;
  var pending = null;
  var ajaxUrl = <?php echo wp_json_encode($ajax_url); ?>;
  var nonce = <?php echo wp_json_encode($nonce); ?>;
  var checkoutUrl = <?php echo wp_json_encode($checkout_url); ?>;

  function enableUpdateButtons() {
    document.querySelectorAll('button[name="update_cart"], input[name="update_cart"]').forEach(function (btn) {
      btn.disabled = false;
      btn.removeAttribute('aria-disabled');
      btn.classList.remove('disabled');
      btn.style.pointerEvents = 'auto';
      btn.style.opacity = '1';
    });
  }

  function parseMoney(text) {
    if (!text) return NaN;
    var cleaned = String(text).replace(/[^\d.,-]/g, '').replace(/\s/g, '');
    if (!cleaned) return NaN;
    if (cleaned.indexOf(',') > -1 && cleaned.indexOf('.') > -1) {
      if (cleaned.lastIndexOf(',') > cleaned.lastIndexOf('.')) {
        cleaned = cleaned.replace(/\./g, '').replace(',', '.');
      } else {
        cleaned = cleaned.replace(/,/g, '');
      }
    } else if (cleaned.indexOf(',') > -1) {
      cleaned = cleaned.replace(',', '.');
    }
    return parseFloat(cleaned);
  }

  function formatMoney(n) {
    return (Math.round(n * 100) / 100).toFixed(2);
  }

  function setAmount(el, value) {
    if (!el) return;
    var bdi = el.querySelector('bdi') || el;
    var symbol = bdi.querySelector('.woocommerce-Price-currencySymbol');
    var symHtml = symbol ? symbol.outerHTML : '&euro;';
    bdi.innerHTML = symHtml + formatMoney(value);
  }

  function refreshLocalPrices() {
    var total = 0;
    document.querySelectorAll('.woocommerce-cart-form .cart_item').forEach(function (row) {
      var qtyInput = row.querySelector('.qty');
      var priceWrap = row.querySelector('.product-price .woocommerce-Price-amount');
      var subWrap = row.querySelector('.product-subtotal .woocommerce-Price-amount');
      if (!qtyInput || !priceWrap) return;
      var qty = parseFloat(String(qtyInput.value).replace(',', '.'));
      var unit = parseMoney(priceWrap.textContent);
      if (isNaN(qty) || qty < 1 || isNaN(unit)) return;
      var line = unit * qty;
      total += line;
      setAmount(subWrap, line);
    });
    if (total > 0) {
      document.querySelectorAll('.cart_totals .cart-subtotal .woocommerce-Price-amount').forEach(function (el) {
        setAmount(el, total);
      });
      document.querySelectorAll('.cart_totals .order-total .woocommerce-Price-amount').forEach(function (el) {
        setAmount(el, total);
      });
    }
  }

  function qtyIsValid(input) {
    var n = parseFloat(String(input.value).replace(',', '.'));
    return !isNaN(n) && n >= 1;
  }

  function cartKeyFromInput(input) {
    var name = input.getAttribute('name') || '';
    var m = name.match(/^cart\[([^\]]+)\]\[qty\]$/);
    return m ? m[1] : '';
  }

  function applyServerTotals(data) {
    if (!data || !data.lines) return;
    Object.keys(data.lines).forEach(function (key) {
      var line = data.lines[key];
      var input = document.querySelector('.woocommerce-cart-form input[name="cart[' + key + '][qty]"]');
      if (!input) return;
      input.value = line.qty;
      input.dataset.egOriginal = String(line.qty);
      var row = input.closest('.cart_item');
      if (!row) return;
      var subWrap = row.querySelector('.product-subtotal');
      if (subWrap && line.line_html) subWrap.innerHTML = line.line_html;
    });
    var subCell = document.querySelector('.cart_totals .cart-subtotal td[data-title], .cart_totals .cart-subtotal td');
    var totCell = document.querySelector('.cart_totals .order-total td[data-title], .cart_totals .order-total td');
    if (subCell && data.subtotal_html) subCell.innerHTML = data.subtotal_html;
    if (totCell && data.total_html) {
      var strong = totCell.querySelector('strong');
      if (strong) strong.innerHTML = data.total_html;
      else totCell.innerHTML = '<strong>' + data.total_html + '</strong>';
    }
    if (typeof jQuery !== 'undefined') {
      jQuery(document.body).trigger('updated_cart_totals');
      jQuery(document.body).trigger('wc_fragment_refresh');
    }
  }

  function updateQtyAjax(input) {
    var key = cartKeyFromInput(input);
    if (!key || !qtyIsValid(input)) return Promise.resolve(null);
    refreshLocalPrices();

    var body = new URLSearchParams();
    body.set('nonce', nonce);
    body.set('cart_key', key);
    body.set('qty', String(parseFloat(String(input.value).replace(',', '.'))));

    pending = fetch(ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    }).then(function (r) { return r.json(); }).then(function (json) {
      if (!json || !json.success) {
        console.warn('EG cart update failed', json);
        return null;
      }
      applyServerTotals(json.data);
      return json.data;
    }).catch(function (err) {
      console.warn('EG cart update error', err);
      return null;
    }).finally(function () {
      pending = null;
    });

    return pending;
  }

  function scheduleUpdate(input) {
    enableUpdateButtons();
    if (!qtyIsValid(input)) return;
    refreshLocalPrices();
    if (String(parseFloat(String(input.value).replace(',', '.'))) === String(input.dataset.egOriginal)) return;
    if (timer) clearTimeout(timer);
    timer = setTimeout(function () {
      if (!qtyIsValid(input)) return;
      updateQtyAjax(input);
    }, 350);
  }

  function bindQty() {
    document.querySelectorAll('.woocommerce-cart-form .qty, .woocommerce-cart-form input.qty').forEach(function (input) {
      if (input.dataset.egBound) return;
      input.dataset.egBound = '1';
      input.dataset.egOriginal = String(parseFloat(String(input.value).replace(',', '.')) || 1);
      input.setAttribute('min', '1');
      input.addEventListener('input', function () { scheduleUpdate(input); });
      input.addEventListener('change', function () { scheduleUpdate(input); });
      input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          if (!qtyIsValid(input)) input.value = '1';
          if (timer) clearTimeout(timer);
          updateQtyAjax(input);
        }
      });
    });
  }

  function goCheckout(url) {
    var target = url || checkoutUrl;
    var input = document.querySelector('.woocommerce-cart-form .qty');
    var run = function () { window.location.href = target; };
    if (timer) { clearTimeout(timer); timer = null; }
    if (input && qtyIsValid(input) && String(parseFloat(String(input.value).replace(',', '.'))) !== String(input.dataset.egOriginal)) {
      updateQtyAjax(input).then(run);
      return;
    }
    if (pending && typeof pending.then === 'function') {
      pending.then(run);
      return;
    }
    run();
  }

  document.addEventListener('DOMContentLoaded', function () {
    var stale = document.getElementById('eg-cart-qty-hint');
    if (stale) stale.remove();
    enableUpdateButtons();
    bindQty();

    var form = document.querySelector('form.woocommerce-cart-form');
    if (form) {
      form.addEventListener('submit', function (e) {
        var submitter = e.submitter;
        var isUpdate = submitter && submitter.getAttribute('name') === 'update_cart';
        if (isUpdate || (document.activeElement && document.activeElement.classList.contains('qty'))) {
          e.preventDefault();
          var input = form.querySelector('.qty');
          if (input) updateQtyAjax(input);
        }
      });
    }

    document.querySelectorAll('a.checkout-button, a.wc-forward[href*="checkout"]').forEach(function (a) {
      a.addEventListener('click', function (e) {
        e.preventDefault();
        goCheckout(a.href || checkoutUrl);
      });
    });
  });
})();
</script>
    <?php
}, 99);

/**
 * Quantity fields must stay at least 1.
 */
add_filter('woocommerce_quantity_input_args', function ($args) {
    if (!is_array($args)) {
        return $args;
    }
    $args['min_value'] = 1;
    if (isset($args['input_value']) && (float) $args['input_value'] < 1) {
        $args['input_value'] = 1;
    }
    return $args;
}, 20);

/**
 * Make sure jQuery is present on cart/checkout.
 */
add_action('wp_enqueue_scripts', function () {
    if (!function_exists('is_cart') || (!is_cart() && !is_checkout())) {
        return;
    }
    wp_enqueue_script('jquery');
    if (is_checkout()) {
        wp_enqueue_script('wc-checkout');
    }
}, 100);

/**
 * Prefer billing-only checkout (virtual books).
 */
add_filter('woocommerce_cart_needs_shipping', function ($needs) {
    if (!function_exists('WC') || !WC()->cart) {
        return $needs;
    }
    foreach (WC()->cart->get_cart() as $item) {
        $product = isset($item['data']) ? $item['data'] : null;
        if ($product && !$product->is_virtual()) {
            return true;
        }
    }
    return false;
});

/**
 * True when Woo Stripe publishable key is a short Connect key that cannot charge cards.
 */
function eg_stripe_keys_are_broken(): bool
{
    $s = get_option('woocommerce_stripe_settings', array());
    if (!is_array($s)) {
        return false;
    }
    $testmode = ($s['testmode'] ?? 'yes') === 'yes';
    $pk = $testmode
        ? (string) ($s['test_publishable_key'] ?? '')
        : (string) ($s['publishable_key'] ?? '');
    return $pk !== '' && strlen($pk) < 80;
}

/**
 * Hide Stripe at checkout while Connect keys are in use.
 */
add_filter('woocommerce_available_payment_gateways', function ($gateways) {
    if (!is_array($gateways) || !eg_stripe_keys_are_broken()) {
        return $gateways;
    }
    foreach (array_keys($gateways) as $id) {
        if ($id === 'stripe' || strpos((string) $id, 'stripe_') === 0) {
            unset($gateways[$id]);
        }
    }
    return $gateways;
}, 100);

/**
 * Drop sticky payment failure notices left from a previous attempt.
 */
add_action('template_redirect', function () {
    if (!function_exists('is_checkout') || (!is_checkout() && !(function_exists('is_cart') && is_cart()))) {
        return;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        return;
    }
    if (!function_exists('WC') || !WC()->session) {
        return;
    }
    $notices = WC()->session->get('wc_notices', array());
    if (empty($notices) || !is_array($notices)) {
        return;
    }
    $patterns = array(
        'Unable to process this payment',
        'There was an error processing the payment',
        'platform-owned payment method',
        'No such PaymentMethod',
        'Card payment is temporarily unavailable',
        'Sorry, your session has expired',
        'We restored your cart so you can continue',
        'We were unable to process your order, please try again',
    );
    $changed = false;
    foreach ($notices as $type => $items) {
        if (!is_array($items)) {
            continue;
        }
        $kept = array();
        foreach ($items as $item) {
            $text = is_array($item) ? (string) ($item['notice'] ?? '') : (string) $item;
            $drop = false;
            foreach ($patterns as $needle) {
                if ($text !== '' && stripos($text, $needle) !== false) {
                    $drop = true;
                    break;
                }
            }
            if ($drop) {
                $changed = true;
                continue;
            }
            $kept[] = $item;
        }
        if ($kept) {
            $notices[$type] = $kept;
        } else {
            unset($notices[$type]);
        }
    }
    if ($changed) {
        WC()->session->set('wc_notices', $notices);
    }
}, 1);

/**
 * Soft hint only when bank transfer is the working path.
 */
add_action('woocommerce_before_checkout_form', function () {
    if (!eg_stripe_keys_are_broken()) {
        return;
    }
    wc_print_notice(
        'Card payments are paused on this staging site. Please choose Ueberweisung / Bank transfer to place your order.',
        'notice'
    );
}, 5);

/**
 * Force-disable Stripe Express Checkout (GPay / Apple Pay / Link).
 */
add_filter('option_woocommerce_stripe_settings', function ($s) {
    if (!is_array($s)) {
        return $s;
    }
    $s['payment_request'] = 'no';
    $s['express_checkout'] = 'no';
    $s['payment_request_button_locations'] = array();
    $s['express_checkout_button_locations'] = array();
    return $s;
}, 20);

add_filter('wc_stripe_show_payment_request_on_checkout', '__return_false', 99);
add_filter('wc_stripe_show_payment_request_on_cart', '__return_false', 99);
add_filter('wc_stripe_hide_payment_request_on_product_page', '__return_true', 99);

/**
 * Block Express Checkout clear-cart AJAX so GPay cannot wipe the cart.
 */
add_action('wc_ajax_wc_stripe_clear_cart', function () {
    wp_send_json_success(array('eg_blocked' => true));
    exit;
}, 0);
add_action('wp_ajax_wc_stripe_clear_cart', function () {
    wp_send_json_success(array('eg_blocked' => true));
    exit;
}, 0);
add_action('wp_ajax_nopriv_wc_stripe_clear_cart', function () {
    wp_send_json_success(array('eg_blocked' => true));
    exit;
}, 0);

/**
 * Hide leftover Express Checkout / GPay markup on cart and checkout.
 */
add_action('wp_footer', function () {
    if (!function_exists('is_checkout') || (!is_checkout() && !(function_exists('is_cart') && is_cart()))) {
        return;
    }
    ?>
<style id="eg-hide-express-pay">
#wc-stripe-express-checkout-element,
#wc-stripe-express-checkout-button-separator,
.wc-stripe-payment-request-wrapper,
.wc-stripe-payment-request-button-separator,
button.gpay-button,
.gpay-card-info-container,
.ApplePay-button {
  display: none !important;
}
</style>
<script id="eg-hide-express-pay-js">
(function () {
  function hideExpress() {
    document.querySelectorAll(
      '#wc-stripe-express-checkout-element, #wc-stripe-express-checkout-button-separator, .wc-stripe-payment-request-wrapper, .wc-stripe-payment-request-button-separator'
    ).forEach(function (el) {
      el.style.display = 'none';
      el.setAttribute('hidden', 'hidden');
    });
  }
  document.addEventListener('DOMContentLoaded', hideExpress);
  if (typeof jQuery !== 'undefined') {
    jQuery(document.body).on('updated_checkout updated_cart_totals payment_method_selected', hideExpress);
  }
  setTimeout(hideExpress, 500);
  setTimeout(hideExpress, 1500);
})();
</script>
    <?php
}, 100);

/**
 * Do not treat an empty cart during update_order_review as session expiry
 * when we can restore from the eg_cart_token backup.
 */
add_filter('woocommerce_checkout_update_order_review_expired', function ($expired) {
    if (!function_exists('WC') || !WC()->cart) {
        return $expired;
    }
    if (function_exists('eg_cart_restore_backup')) {
        eg_cart_restore_backup();
    }
    if (!WC()->cart->is_empty()) {
        return false;
    }
    if (function_exists('eg_cart_token') && eg_cart_token() !== '') {
        $key = function_exists('eg_cart_backup_key') ? eg_cart_backup_key() : '';
        if ($key && get_transient($key)) {
            return false;
        }
    }
    return $expired;
}, 1);

/**
 * On checkout: refresh nonce right before Place order, and recover after nonce errors.
 */
add_action('wp_footer', function () {
    if (!function_exists('is_checkout') || !is_checkout() || is_order_received_page()) {
        return;
    }
    $ajax = function_exists('WC_AJAX') && method_exists('WC_AJAX', 'get_endpoint')
        ? WC_AJAX::get_endpoint('eg_checkout_nonce')
        : home_url('/?wc-ajax=eg_checkout_nonce');
    ?>
<script id="eg-checkout-nonce-recover">
(function () {
  var key = 'eg_checkout_reloaded';
  var nonceReady = false;
  var nonceUrl = <?php echo wp_json_encode($ajax); ?>;
  if (typeof jQuery === 'undefined') return;

  function applyNonce(nonce) {
    if (!nonce) return;
    var $fields = jQuery('input[name="woocommerce-process-checkout-nonce"]');
    if ($fields.length) {
      $fields.val(nonce);
    } else {
      jQuery('form.checkout').append(
        jQuery('<input>', {
          type: 'hidden',
          id: 'eg-sticky-checkout-nonce',
          name: 'woocommerce-process-checkout-nonce',
          value: nonce
        })
      );
    }
  }

  // Always fetch a fresh nonce before the checkout AJAX fires.
  jQuery(function () {
    var $form = jQuery('form.checkout');
    if (!$form.length) return;
    $form.on('checkout_place_order', function () {
      if (nonceReady) {
        nonceReady = false;
        return true;
      }
      jQuery.ajax({
        type: 'POST',
        url: nonceUrl,
        dataType: 'json',
        timeout: 15000
      }).done(function (resp) {
        var nonce = resp && resp.data && resp.data.nonce ? resp.data.nonce : null;
        applyNonce(nonce);
        nonceReady = true;
        $form.trigger('submit');
      }).fail(function () {
        // Fall through with whatever nonce is already in the form.
        nonceReady = true;
        $form.trigger('submit');
      });
      return false;
    });
  });

  jQuery(document.body).on('checkout_error', function (e, html) {
    var text = ((html || '') + '').toLowerCase();
    if (text.indexOf('unable to process your order') === -1 && text.indexOf('session has expired') === -1) {
      return;
    }
    if (sessionStorage.getItem(key) === '1') {
      return;
    }
    sessionStorage.setItem(key, '1');
    jQuery.ajax({
      type: 'POST',
      url: nonceUrl,
      dataType: 'json',
      timeout: 15000
    }).always(function (resp) {
      var nonce = resp && resp.data && resp.data.nonce ? resp.data.nonce : null;
      applyNonce(nonce);
      jQuery(document.body).trigger('update_checkout');
    });
  });
  jQuery(document.body).on('updated_checkout checkout_place_order_success', function () {
    sessionStorage.removeItem(key);
  });
})();
</script>
    <?php
}, 110);
