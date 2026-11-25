<?php
/**
 * Order summary on checkout: items with their plan, totals and the coupon box.
 * The wrapper keeps the class WooCommerce replaces after each AJAX refresh.
 */
defined( 'ABSPATH' ) || exit;
$cart  = WC()->cart;
$money = fn( $n ) => aim_t_money( $n ) . '<small>تومان</small>';
?>
<div class="woocommerce-checkout-review-order-table cx-review">
	<ul class="cx-items">
		<?php do_action( 'woocommerce_review_order_before_cart_contents' ); ?>
		<?php foreach ( $cart->get_cart() as $key => $item ) : ?>
			<?php
			$_product = apply_filters( 'woocommerce_cart_item_product', $item['data'], $item, $key );
			if ( ! $_product || ! $_product->exists() || $item['quantity'] <= 0 || ! apply_filters( 'woocommerce_checkout_cart_item_visible', true, $item, $key ) ) {
				continue;
			}
			$parent = $_product->get_parent_id() ? wc_get_product( $_product->get_parent_id() ) : $_product;
			$lines  = [];
			if ( $_product->is_type( 'variation' ) ) {
				foreach ( $_product->get_variation_attributes() as $attr => $value ) {
					$tax     = str_replace( 'attribute_', '', $attr );
					$lines[] = [ wc_attribute_label( $tax, $_product ), $value ];
				}
			}
			$type     = $parent->get_meta( '_aim_type_label' );
			$delivery = aim_t_delivery_label( $parent );
			if ( $type ) {
				$lines[] = [ 'نوع', $type ];
			}
			if ( $delivery ) {
				$lines[] = [ 'تحویل', $delivery ];
			}
			?>
			<li class="cx-item">
				<?php echo aim_t_logo( $parent, 'cx-logo' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<div class="cx-item-body">
					<b><?php echo esc_html( $parent->get_name() ); ?></b>
					<?php foreach ( $lines as [ $k, $v ] ) : ?>
						<small><?php echo esc_html( $k ); ?>: <span><?php echo esc_html( $v ); ?></span></small>
					<?php endforeach; ?>
					<span class="cx-item-price"><?php echo $money( (float) $item['line_subtotal'] + ( $cart->display_prices_including_tax() ? (float) $item['line_subtotal_tax'] : 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				</div>
				<div class="cx-item-side">
					<a class="cx-remove" href="<?php echo esc_url( wc_get_cart_remove_url( $key ) ); ?>" aria-label="<?php echo esc_attr( 'حذف ' . $parent->get_name() ); ?>"><i class="ic-close-line"></i></a>
					<span class="cx-qty">×<?php echo esc_html( aim_t_fa( $item['quantity'] ) ); ?></span>
				</div>
			</li>
		<?php endforeach; ?>
		<?php do_action( 'woocommerce_review_order_after_cart_contents' ); ?>
	</ul>

	<dl class="cx-totals">
		<div><dt>جمع کالا</dt><dd><?php echo $money( $cart->get_subtotal() ); // phpcs:ignore ?></dd></div>
		<?php foreach ( $cart->get_coupons() as $code => $coupon ) : ?>
			<div class="is-save"><dt>کد <b class="ltr"><?php echo esc_html( strtoupper( $code ) ); ?></b> <a href="<?php echo esc_url( add_query_arg( 'remove_coupon', rawurlencode( $code ), wc_get_checkout_url() ) ); ?>" class="woocommerce-remove-coupon co-x" data-coupon="<?php echo esc_attr( $code ); ?>">حذف</a></dt><dd>−<?php echo $money( $cart->get_coupon_discount_amount( $code, $cart->display_cart_ex_tax ) ); // phpcs:ignore ?></dd></div>
		<?php endforeach; ?>
		<?php foreach ( $cart->get_fees() as $fee ) : ?>
			<div><dt><?php echo esc_html( $fee->name ); ?></dt><dd><?php echo $money( $fee->total ); // phpcs:ignore ?></dd></div>
		<?php endforeach; ?>
		<?php if ( wc_tax_enabled() && (float) $cart->get_total_tax() ) : ?>
			<div><dt>مالیات</dt><dd><?php echo $money( $cart->get_total_tax() ); // phpcs:ignore ?></dd></div>
		<?php endif; ?>
		<?php if ( ! $cart->get_fees() ) : ?>
			<div><dt><?php echo esc_html( aim_t_opt( 'co_fee_label' ) ); ?></dt><dd class="is-free">رایگان</dd></div>
		<?php endif; ?>
		<?php do_action( 'woocommerce_review_order_before_order_total' ); ?>
		<div class="is-total"><dt>مبلغ قابل پرداخت<small>همهٔ هزینه‌ها محاسبه شده است</small></dt><dd><?php echo $money( $cart->get_total( 'edit' ) ); // phpcs:ignore ?></dd></div>
		<?php do_action( 'woocommerce_review_order_after_order_total' ); ?>
	</dl>

	<?php if ( wc_coupons_enabled() ) : ?>
		<details class="cx-coupon">
			<summary><span class="cx-ico is-sm"><i class="ic-coupon-3-line"></i></span>کد تخفیف دارید؟<i class="ic-add-line cx-coupon-plus"></i></summary>
			<div class="cx-coupon-row" data-cx-coupon>
				<label class="visually-hidden" for="cx-coupon">کد تخفیف</label>
				<input id="cx-coupon" type="text" placeholder="کد تخفیف را وارد کنید" autocomplete="off" dir="ltr">
				<button type="button" data-cx-coupon-apply>اعمال</button>
			</div>
		</details>
	<?php endif; ?>
</div>
