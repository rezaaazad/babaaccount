<?php
/**
 * Payment methods as cards + the place-order button. Keeps WooCommerce's ids
 * and classes (#payment, .wc_payment_methods, #place_order) for its scripts.
 *
 * @var WC_Payment_Gateway[] $available_gateways
 * @var string               $order_button_text
 */
defined( 'ABSPATH' ) || exit;
if ( ! wp_doing_ajax() ) {
	do_action( 'woocommerce_review_order_before_payment' );
}
?>
<div id="payment" class="woocommerce-checkout-payment cx-pay">
	<?php if ( WC()->cart && WC()->cart->needs_payment() ) : ?>
		<div class="cx-pay-head">
			<span class="cx-ico is-sm"><i class="ic-bank-card-line"></i></span>
			<div><b><?php echo esc_html( aim_t_opt( 'co_payment' ) ); ?></b><small>پس از تأیید، به روش انتخاب‌شده منتقل می‌شوید.</small></div>
			<span class="cx-safe"><i class="ic-shield-check-line"></i>امن</span>
		</div>
		<ul class="wc_payment_methods payment_methods methods cx-methods">
			<?php
			if ( ! empty( $available_gateways ) ) {
				$first = true;
				foreach ( $available_gateways as $gateway ) {
					wc_get_template( 'checkout/payment-method.php', [ 'gateway' => $gateway, 'first' => $first ] );
					$first = false;
				}
			} else {
				echo '<li class="cx-nopay">' . esc_html( apply_filters( 'woocommerce_no_available_payment_methods_message', 'روش پرداختی فعال نیست. با پشتیبانی تماس بگیرید.' ) ) . '</li>';
			}
			?>
		</ul>
		<p class="cx-chosen" data-cx-chosen><i class="ic-information-line"></i>روش انتخاب‌شده: <b></b></p>
	<?php endif; ?>
	<div class="form-row place-order">
		<noscript><button type="submit" class="button alt" name="woocommerce_checkout_update_totals" value="به‌روزرسانی مبلغ">به‌روزرسانی مبلغ</button></noscript>
		<?php wc_get_template( 'checkout/terms.php' ); ?>
		<?php do_action( 'woocommerce_review_order_before_submit' ); ?>
		<?php echo apply_filters( 'woocommerce_order_button_html', '<button type="submit" class="button alt pc-btn cx-go" name="woocommerce_checkout_place_order" id="place_order" value="' . esc_attr( $order_button_text ) . '" data-value="' . esc_attr( $order_button_text ) . '"><i class="ic-lock-2-line"></i>' . esc_html( $order_button_text ) . '</button>' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<?php do_action( 'woocommerce_review_order_after_submit' ); ?>
		<?php wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' ); ?>
		<p class="cx-bank-note"><i class="ic-shield-check-line"></i>اطلاعات کارت بانکی در سایت ذخیره نمی‌شود.</p>
	</div>
</div>
<?php
if ( ! wp_doing_ajax() ) {
	do_action( 'woocommerce_review_order_after_payment' );
}
