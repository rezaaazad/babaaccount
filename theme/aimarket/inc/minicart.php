<?php
/**
 * Mini-cart drawer + AJAX add to cart.
 *
 * REST (namespace aimarket/v1), all return { ok, html, count, total, message }:
 *   GET  /minicart                       current cart drawer body
 *   POST /minicart/add    { product_id, variation_id?, quantity? }
 *   POST /minicart/update { key, quantity }   (0 removes the line)
 */
defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', function () {
	$open = [ 'permission_callback' => '__return_true' ];
	register_rest_route( 'aimarket/v1', '/minicart', $open + [ 'methods' => 'GET', 'callback' => 'aim_mc_rest_get' ] );
	register_rest_route( 'aimarket/v1', '/minicart/add', $open + [ 'methods' => 'POST', 'callback' => 'aim_mc_rest_add' ] );
	register_rest_route( 'aimarket/v1', '/minicart/update', $open + [ 'methods' => 'POST', 'callback' => 'aim_mc_rest_update' ] );
} );

/** WooCommerce does not load the cart for REST requests by default. */
function aim_mc_boot( $create_session = true ) {
	if ( function_exists( 'wc_load_cart' ) && ( ! WC()->cart || ! WC()->session ) ) {
		wc_load_cart();
	}
	if ( $create_session && WC()->session && ! WC()->session->has_session() ) {
		WC()->session->set_customer_session_cookie( true );
	}
}

/** Errors WooCommerce queued while changing the cart, as plain text; the queue is cleared. */
function aim_mc_take_errors() {
	$msgs = array_map( fn( $n ) => wp_strip_all_tags( is_array( $n ) ? $n['notice'] : $n ), wc_get_notices( 'error' ) );
	wc_clear_notices();
	return trim( implode( ' ', $msgs ) );
}

function aim_mc_response( $message = '', $ok = true, $extra = [] ) {
	$cart = WC()->cart;
	return new WP_REST_Response( [
		'ok'      => $ok,
		'message' => $message,
		'count'   => $cart ? (int) $cart->get_cart_contents_count() : 0,
		'total'   => $cart ? aim_t_money( $cart->get_total( 'edit' ) ) : '۰',
		'html'    => aim_mc_html(),
	] + $extra, $ok ? 200 : 400 );
}

function aim_mc_rest_get() {
	aim_mc_boot( false );
	return aim_mc_response();
}

function aim_mc_rest_add( WP_REST_Request $req ) {
	aim_mc_boot();
	$pid = absint( $req->get_param( 'product_id' ) );
	$vid = absint( $req->get_param( 'variation_id' ) );
	$qty = max( 1, absint( $req->get_param( 'quantity' ) ?: 1 ) );
	$product = wc_get_product( $vid ?: $pid );
	if ( ! $product || ! $product->is_purchasable() ) {
		return aim_mc_response( 'این محصول قابل خرید نیست.', false );
	}
	$attrs = $vid && $product->is_type( 'variation' ) ? $product->get_variation_attributes() : [];
	$key   = WC()->cart->add_to_cart( $vid ? $product->get_parent_id() : $pid, $qty, $vid, $attrs );
	if ( ! $key ) {
		return aim_mc_response( aim_mc_take_errors() ?: 'افزودن به سبد ناموفق بود.', false );
	}
	wc_clear_notices();
	$parent = $vid ? wc_get_product( $product->get_parent_id() ) : $product;
	return aim_mc_response( '«' . $parent->get_name() . '» به سبد خرید اضافه شد.', true, [ 'added_key' => $key ] );
}

function aim_mc_rest_update( WP_REST_Request $req ) {
	aim_mc_boot();
	$key  = sanitize_text_field( (string) $req->get_param( 'key' ) );
	$qty  = absint( $req->get_param( 'quantity' ) );
	WC()->cart->get_cart(); // Loads the cart from the session; get_cart_item() alone does not.
	$line = WC()->cart->get_cart_item( $key );
	if ( ! $line ) {
		return aim_mc_response( 'این مورد در سبد نیست.', false );
	}
	if ( 0 === $qty ) {
		WC()->cart->remove_cart_item( $key );
		return aim_mc_response( 'از سبد حذف شد.' );
	}
	$product = $line['data'];
	if ( $product->managing_stock() && ! $product->backorders_allowed() && $qty > (int) $product->get_stock_quantity() ) {
		return aim_mc_response( sprintf( 'از «%s» فقط %s عدد موجود است.', $product->get_name(), aim_t_fa( max( 0, (int) $product->get_stock_quantity() ) ) ), false );
	}
	WC()->cart->set_quantity( $key, $qty );
	return aim_mc_response( aim_mc_take_errors() );
}

/** Drawer body: items, totals and buttons (or the empty state). */
function aim_mc_html() {
	$cart = WC()->cart;
	ob_start();
	if ( ! $cart || $cart->is_empty() ) {
		?>
		<div class="mc-empty">
			<?php echo aim_t_illus( 'cart', 'mc-art' ); // phpcs:ignore ?>
			<b><?php echo esc_html( aim_t_opt( 'mc_empty_title' ) ); ?></b>
			<small><?php echo esc_html( aim_t_opt( 'mc_empty_text' ) ); ?></small>
			<a class="pc-btn" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">مشاهدهٔ سرویس‌ها</a>
		</div>
		<?php
		return ob_get_clean();
	}
	?>
	<ul class="mc-list">
		<?php foreach ( $cart->get_cart() as $key => $item ) : ?>
			<?php
			$p = $item['data'];
			if ( ! $p || ! $p->exists() ) {
				continue;
			}
			$parent = $p->get_parent_id() ? wc_get_product( $p->get_parent_id() ) : $p;
			$plan   = $p->is_type( 'variation' ) ? wc_get_formatted_variation( $p, true, false, false ) : '';
			$max    = $p->get_max_purchase_quantity();
			?>
			<li class="mc-item" data-key="<?php echo esc_attr( $key ); ?>">
				<a href="<?php echo esc_url( $parent->get_permalink() ); ?>"><?php echo aim_t_logo( $parent, 'mc-logo' ); // phpcs:ignore ?></a>
				<div class="mc-info">
					<a class="mc-name" href="<?php echo esc_url( $parent->get_permalink() ); ?>"><?php echo esc_html( $parent->get_name() ); ?></a>
					<?php if ( $plan ) : ?><small><?php echo esc_html( $plan ); ?></small><?php endif; ?>
					<div class="mc-row">
						<div class="qty-step sm">
							<button type="button" data-mc-qty="<?php echo esc_attr( $item['quantity'] + 1 ); ?>" <?php disabled( $max > 0 && $item['quantity'] >= $max ); ?> aria-label="افزایش"><i class="ic-add-line"></i></button>
							<span><?php echo esc_html( aim_t_fa( $item['quantity'] ) ); ?></span>
							<button type="button" data-mc-qty="<?php echo esc_attr( $item['quantity'] - 1 ); ?>" aria-label="<?php echo $item['quantity'] > 1 ? 'کاهش' : 'حذف'; ?>"><i class="ic-<?php echo $item['quantity'] > 1 ? 'subtract-line' : 'delete-bin-6-line'; ?>"></i></button>
						</div>
						<b class="mc-price"><?php echo esc_html( aim_t_money( (float) $item['line_subtotal'] + ( $cart->display_prices_including_tax() ? (float) $item['line_subtotal_tax'] : 0 ) ) ); ?> <small>تومان</small></b>
					</div>
				</div>
				<button type="button" class="mc-remove" data-mc-qty="0" aria-label="<?php echo esc_attr( 'حذف ' . $parent->get_name() ); ?>"><i class="ic-close-line"></i></button>
			</li>
		<?php endforeach; ?>
	</ul>
	<div class="mc-foot">
		<?php if ( $cart->get_discount_total() ) : ?>
			<div class="mc-sum is-save"><span>تخفیف</span><b>−<?php echo esc_html( aim_t_money( $cart->get_discount_total() ) ); ?> <small>تومان</small></b></div>
		<?php endif; ?>
		<div class="mc-sum"><span>مبلغ قابل پرداخت</span><b><?php echo esc_html( aim_t_money( $cart->get_total( 'edit' ) ) ); ?> <small>تومان</small></b></div>
		<a class="pc-btn mc-go" href="<?php echo esc_url( wc_get_checkout_url() ); ?>">ادامه و پرداخت<i class="ic-arrow-left-line"></i></a>
		<a class="btn-ghost mc-cart" href="<?php echo esc_url( wc_get_cart_url() ); ?>"><i class="ic-shopping-cart-2-line"></i>مشاهدهٔ سبد خرید</a>
	</div>
	<?php
	return ob_get_clean();
}
