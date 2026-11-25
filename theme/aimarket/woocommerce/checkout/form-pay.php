<?php
/**
 * Pay for an existing (pending/failed) order.
 *
 * @var WC_Order $order
 * @var array    $available_gateways
 * @var string   $order_button_text
 */
defined( 'ABSPATH' ) || exit;
$totals = $order->get_order_item_totals();
?>
<form id="order_review" method="post" class="co-grid">
	<div class="co-main">
		<div class="co-card co-pay">
			<div class="co-card-head">
				<h2><span class="co-num"><i class="ic-bank-card-line"></i></span>پرداخت سفارش #<?php echo esc_html( aim_t_fa( $order->get_order_number() ) ); ?></h2>
				<small>روش پرداخت را انتخاب کن</small>
			</div>
			<div id="payment">
				<?php if ( $order->needs_payment() ) : ?>
					<ul class="wc_payment_methods payment_methods methods">
						<?php
						if ( ! empty( $available_gateways ) ) {
							foreach ( $available_gateways as $gateway ) {
								wc_get_template( 'checkout/payment-method.php', [ 'gateway' => $gateway ] );
							}
						} else {
							echo '<li class="sf-notice is-info"><i class="ic-information-fill"></i><div>در حال حاضر روش پرداختی فعال نیست. با پشتیبانی تماس بگیر.</div></li>';
						}
						?>
					</ul>
				<?php endif; ?>
				<div class="form-row place-order">
					<input type="hidden" name="woocommerce_pay" value="1">
					<?php wc_get_template( 'checkout/terms.php' ); ?>
					<?php do_action( 'woocommerce_pay_order_before_submit' ); ?>
					<?php echo apply_filters( 'woocommerce_pay_order_button_html', '<button type="submit" class="button alt" id="place_order" value="' . esc_attr( $order_button_text ) . '" data-value="' . esc_attr( $order_button_text ) . '">' . esc_html( $order_button_text ) . '</button>' ); // phpcs:ignore ?>
					<?php do_action( 'woocommerce_pay_order_after_submit' ); ?>
					<?php wp_nonce_field( 'woocommerce-pay', 'woocommerce-pay-nonce' ); ?>
				</div>
			</div>
		</div>
	</div>
	<aside class="co-side">
		<div class="co-card co-sum">
			<h2>خلاصهٔ سفارش</h2>
			<ul class="cr-items">
				<?php foreach ( $order->get_items() as $item ) : ?>
					<?php
					$p      = $item->get_product();
					$parent = $p && $p->get_parent_id() ? wc_get_product( $p->get_parent_id() ) : $p;
					?>
					<li>
						<?php echo $parent ? aim_t_logo( $parent, 'cr-logo' ) : ''; // phpcs:ignore ?>
						<span class="cr-name"><b><?php echo esc_html( $parent ? $parent->get_name() : $item->get_name() ); ?></b><small><?php echo esc_html( $p && $p->is_type( 'variation' ) ? wc_get_formatted_variation( $p, true, false, false ) : '' ); ?><?php echo $item->get_quantity() > 1 ? ' × ' . esc_html( aim_t_fa( $item->get_quantity() ) ) : ''; ?></small></span>
						<span class="cr-price"><?php echo esc_html( aim_t_money( $item->get_total() ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
			<dl class="co-totals"><div class="is-total"><dt>مبلغ قابل پرداخت</dt><dd><?php echo esc_html( aim_t_money( $order->get_total() ) ); ?> <small>تومان</small></dd></div></dl>
			<?php aim_t_trust_list(); ?>
		</div>
	</aside>
</form>
