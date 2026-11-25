<?php
/**
 * Cart: item cards on the right, sticky order summary on the left.
 * Keeps WooCommerce's form fields (cart[key][qty], update_cart, coupon_code,
 * nonce) so all core cart behaviour keeps working.
 */
defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_cart' );
$cart  = WC()->cart;
$count = $cart->get_cart_contents_count();
$save  = 0;
?>
<div class="co-grid">
	<form class="woocommerce-cart-form co-main" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post" data-cart-form>
		<?php do_action( 'woocommerce_before_cart_table' ); ?>
		<div class="co-card">
			<div class="co-card-head">
				<h2>اقلام سبد <small><?php echo esc_html( aim_t_fa( $count ) ); ?> مورد</small></h2>
				<a class="co-link" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><i class="ic-add-line"></i>افزودن سرویس دیگر</a>
			</div>
			<ul class="ci-list">
				<?php foreach ( $cart->get_cart() as $key => $item ) : ?>
					<?php
					$_product = apply_filters( 'woocommerce_cart_item_product', $item['data'], $item, $key );
					if ( ! $_product || ! $_product->exists() || $item['quantity'] <= 0 || ! apply_filters( 'woocommerce_cart_item_visible', true, $item, $key ) ) {
						continue;
					}
					$parent   = $_product->get_parent_id() ? wc_get_product( $_product->get_parent_id() ) : $_product;
					$link     = $_product->is_visible() ? $_product->get_permalink( $item ) : '';
					$plan     = $_product->is_type( 'variation' ) ? wc_get_formatted_variation( $_product, true, false, false ) : '';
					$days     = (int) $_product->get_meta( '_aim_duration_days' ) ?: (int) $parent->get_meta( '_aim_duration_days' );
					$delivery = aim_t_delivery_label( $parent );
					$regular  = (float) $_product->get_regular_price();
					$price    = (float) $_product->get_price();
					if ( $regular > $price ) {
						$save += ( $regular - $price ) * $item['quantity'];
					}
					$max = $_product->get_max_purchase_quantity();
					?>
					<li class="ci" data-key="<?php echo esc_attr( $key ); ?>">
						<a class="ci-logo" href="<?php echo esc_url( $link ?: '#' ); ?>"><?php echo aim_t_logo( $parent, 'ci-logo-img' ); // phpcs:ignore ?></a>
						<div class="ci-info">
							<a class="ci-name" href="<?php echo esc_url( $link ?: '#' ); ?>"><?php echo esc_html( $parent->get_name() ); ?></a>
							<div class="ci-chips">
								<?php if ( $plan ) : ?><span><i class="ic-stack-line"></i><?php echo esc_html( $plan ); ?></span><?php elseif ( $days ) : ?><span><i class="ic-calendar-line"></i><?php echo esc_html( 'اشتراک ' . aim_t_days_label( $days ) ); ?></span><?php endif; ?>
								<?php if ( $delivery ) : ?><span class="is-green"><i class="ic-flashlight-line"></i><?php echo esc_html( $delivery ); ?></span><?php endif; ?>
							</div>
							<?php if ( $_product->backorders_require_notification() && $_product->is_on_backorder( $item['quantity'] ) ) : ?>
								<p class="ci-warn">موجودی این مورد کم است؛ تحویل ممکن است با تأخیر انجام شود.</p>
							<?php endif; ?>
						</div>
						<div class="ci-qty">
							<?php if ( $_product->is_sold_individually() || 1 === $max ) : ?>
								<input type="hidden" name="cart[<?php echo esc_attr( $key ); ?>][qty]" value="1"><span class="ci-one">۱ عدد</span>
							<?php else : ?>
								<div class="qty-step" data-qty>
									<button type="button" data-step="1" aria-label="افزایش"><i class="ic-add-line"></i></button>
									<input type="number" name="cart[<?php echo esc_attr( $key ); ?>][qty]" value="<?php echo esc_attr( $item['quantity'] ); ?>" min="0" <?php echo $max > 0 ? 'max="' . esc_attr( $max ) . '"' : ''; ?> inputmode="numeric" aria-label="تعداد">
									<button type="button" data-step="-1" aria-label="کاهش"><i class="ic-<?php echo $item['quantity'] > 1 ? 'subtract-line' : 'delete-bin-6-line'; ?>"></i></button>
								</div>
							<?php endif; ?>
						</div>
						<div class="ci-price">
							<?php if ( $regular > $price ) : ?><del><?php echo esc_html( aim_t_money( $regular * $item['quantity'] ) ); ?></del><?php endif; ?>
							<b><?php echo esc_html( aim_t_money( (float) $item['line_subtotal'] + ( $cart->display_prices_including_tax() ? (float) $item['line_subtotal_tax'] : 0 ) ) ); ?> <small>تومان</small></b>
						</div>
						<a class="ci-remove" href="<?php echo esc_url( wc_get_cart_remove_url( $key ) ); ?>" aria-label="<?php echo esc_attr( 'حذف ' . $parent->get_name() ); ?>"><i class="ic-close-line"></i></a>
					</li>
				<?php endforeach; ?>
			</ul>
			<button type="submit" class="visually-hidden" name="update_cart" value="1" data-update>به‌روزرسانی سبد</button>
			<?php wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>
		</div>

		<?php if ( wc_coupons_enabled() ) : ?>
			<div class="co-card co-coupon">
				<label for="coupon_code"><i class="ic-coupon-3-line"></i>کد تخفیف داری؟</label>
				<div class="co-coupon-row">
					<input type="text" name="coupon_code" id="coupon_code" value="" placeholder="کد را وارد کن" class="ltr" autocomplete="off">
					<button type="submit" name="apply_coupon" value="1">اعمال کد</button>
				</div>
			</div>
		<?php endif; ?>
		<?php do_action( 'woocommerce_cart_contents' ); ?>
		<?php do_action( 'woocommerce_after_cart_table' ); ?>
	</form>

	<aside class="co-side">
		<div class="co-card co-sum cart_totals">
			<h2>خلاصهٔ سفارش</h2>
			<dl>
				<div><dt>جمع اقلام (<?php echo esc_html( aim_t_fa( $count ) ); ?>)</dt><dd><?php echo esc_html( aim_t_money( (float) $cart->get_subtotal() + $save ) ); ?> <small>تومان</small></dd></div>
				<?php if ( $save ) : ?><div class="is-save"><dt>سود شما از تخفیف</dt><dd><?php echo esc_html( aim_t_money( $save ) ); ?> <small>تومان</small></dd></div><?php endif; ?>
				<?php foreach ( $cart->get_coupons() as $code => $coupon ) : ?>
					<div class="is-save"><dt>کد <b class="ltr"><?php echo esc_html( strtoupper( $code ) ); ?></b> <a href="<?php echo esc_url( add_query_arg( 'remove_coupon', rawurlencode( $code ), wc_get_cart_url() ) ); ?>" class="co-x" aria-label="حذف کد">حذف</a></dt><dd><?php echo esc_html( aim_t_money( $cart->get_coupon_discount_amount( $code, $cart->display_cart_ex_tax ) ) ); ?> <small>تومان</small></dd></div>
				<?php endforeach; ?>
				<?php foreach ( $cart->get_fees() as $fee ) : ?>
					<div><dt><?php echo esc_html( $fee->name ); ?></dt><dd><?php echo esc_html( aim_t_money( $fee->total ) ); ?> <small>تومان</small></dd></div>
				<?php endforeach; ?>
				<?php if ( wc_tax_enabled() && (float) $cart->get_total_tax() ) : ?>
					<div><dt>مالیات</dt><dd><?php echo esc_html( aim_t_money( $cart->get_total_tax() ) ); ?> <small>تومان</small></dd></div>
				<?php endif; ?>
				<div class="is-total"><dt>مبلغ قابل پرداخت</dt><dd><?php echo esc_html( aim_t_money( $cart->get_total( 'edit' ) ) ); ?> <small>تومان</small></dd></div>
			</dl>
			<a class="pc-btn co-go" href="<?php echo esc_url( wc_get_checkout_url() ); ?>"><?php echo esc_html( aim_t_opt( 'c_go' ) ); ?><i class="ic-arrow-left-line"></i></a>
			<?php aim_t_trust_list(); ?>
		</div>
	</aside>
</div>

<!-- Mobile: fixed total + continue -->
<div class="co-mbar">
	<div><small>مبلغ قابل پرداخت</small><b><?php echo esc_html( aim_t_money( $cart->get_total( 'edit' ) ) ); ?> <small>تومان</small></b></div>
	<a class="pc-btn" href="<?php echo esc_url( wc_get_checkout_url() ); ?>"><?php echo esc_html( aim_t_opt( 'c_go' ) ); ?></a>
</div>

<?php do_action( 'woocommerce_after_cart' ); ?>
