<?php
/**
 * Order received.
 *
 * @var WC_Order|false $order
 */
defined( 'ABSPATH' ) || exit;

$accounts_url = function_exists( 'aim_panel_url' ) ? aim_panel_url( 'user', 'accounts' ) : wc_get_page_permalink( 'myaccount' );
?>
<div class="co-done">
	<?php if ( ! $order ) : ?>
		<div class="co-gate">
			<span class="co-empty-ico"><i class="ic-checkbox-circle-line"></i></span>
			<h2>سفارش شما ثبت شد</h2>
			<a class="pc-btn" href="<?php echo esc_url( $accounts_url ); ?>">اکانت‌های من</a>
		</div>
		<?php return; ?>
	<?php endif; ?>

	<?php
	do_action( 'woocommerce_before_thankyou', $order->get_id() );
	$failed  = $order->has_status( 'failed' );
	$paid    = $order->is_paid();
	$auto    = 0;
	$manual  = 0;
	$pending = 0;
	foreach ( $order->get_items() as $it ) {
		$mode = class_exists( 'AIM_Inventory' ) ? AIM_Inventory::delivery_mode( $it->get_product_id() ) : '';
		$done = (int) $it->get_meta( '_aim_delivered' );
		if ( 'manual' === $mode ) {
			$manual++;
		} elseif ( 'auto' === $mode ) {
			$auto++;
		}
		if ( $mode && $done < (int) $it->get_quantity() ) {
			$pending++;
		}
	}
	?>

	<div class="co-gate <?php echo $failed ? 'is-failed' : 'is-ok'; ?>">
		<?php if ( $failed ) : ?><span class="co-empty-ico"><i class="ic-close-circle-line"></i></span><?php else : ?><?php echo aim_t_illus( 'success', 'co-art' ); // phpcs:ignore ?><?php endif; ?>
		<?php if ( $failed ) : ?>
			<h2><?php echo esc_html( aim_t_opt( 'ty_failed_title' ) ); ?></h2>
			<p><?php echo esc_html( aim_t_opt( 'ty_failed_text' ) ); ?></p>
			<div class="co-done-actions">
				<a class="pc-btn" href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>">پرداخت دوباره</a>
				<a class="btn-ghost" href="<?php echo esc_url( wc_get_cart_url() ); ?>">بازگشت به سبد</a>
			</div>
		<?php else : ?>
			<h2><?php echo esc_html( aim_t_opt( $paid ? 'ty_paid' : 'ty_unpaid' ) ); ?></h2>
			<p>
				<?php
				if ( ! $paid ) {
					echo 'پس از تأیید پرداخت، اکانت در حسابت قرار می‌گیرد و پیامک می‌گیری.';
				} elseif ( ! $pending ) {
					echo 'اطلاعات ورود همهٔ اکانت‌ها همین حالا در «اکانت‌های من» آماده است.';
				} elseif ( $manual ) {
					echo 'بخشی از سفارش روی ایمیل شما فعال می‌شود؛ پس از فعال‌سازی پیامک می‌گیری.';
				} else {
					echo 'سفارش در حال آماده‌سازی است و به‌محض تحویل، با پیامک خبرت می‌کنیم.';
				}
				?>
			</p>
			<div class="co-done-actions">
				<a class="pc-btn" href="<?php echo esc_url( $accounts_url ); ?>"><i class="ic-key-2-line"></i><?php echo esc_html( aim_t_opt( 'ty_accounts' ) ); ?></a>
				<?php if ( class_exists( 'AIM_Contract' ) && AIM_Contract::of( $order ) ) : ?>
					<a class="btn-ghost" href="<?php echo esc_url( AIM_Contract::url( $order ) ); ?>" target="_blank" rel="noopener"><i class="ic-file-list-3-line"></i>قرارداد سفارش</a>
				<?php endif; ?>
				<a class="btn-ghost" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">ادامهٔ خرید</a>
			</div>
		<?php endif; ?>
	</div>

	<div class="co-card co-receipt">
		<dl class="co-totals">
			<div><dt>شمارهٔ سفارش</dt><dd>#<?php echo esc_html( aim_t_fa( $order->get_order_number() ) ); ?></dd></div>
			<div><dt>تاریخ</dt><dd><?php echo esc_html( function_exists( 'aim_jdate' ) && $order->get_date_created() ? aim_jdate( $order->get_date_created()->getTimestamp(), true ) : wc_format_datetime( $order->get_date_created() ) ); ?></dd></div>
			<?php if ( $order->get_payment_method_title() ) : ?><div><dt>روش پرداخت</dt><dd><?php echo esc_html( $order->get_payment_method_title() ); ?></dd></div><?php endif; ?>
		</dl>
		<ul class="cr-items">
			<?php foreach ( $order->get_items() as $it ) : ?>
				<?php $p = $it->get_product(); $parent = $p && $p->get_parent_id() ? wc_get_product( $p->get_parent_id() ) : $p; ?>
				<li>
					<?php echo $parent ? aim_t_logo( $parent, 'cr-logo' ) : ''; // phpcs:ignore ?>
					<span class="cr-name"><b><?php echo esc_html( $parent ? $parent->get_name() : $it->get_name() ); ?></b><small><?php echo esc_html( $p && $p->is_type( 'variation' ) ? wc_get_formatted_variation( $p, true, false, false ) : '' ); ?></small></span>
					<span class="cr-price"><?php echo esc_html( aim_t_money( $it->get_total() ) ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
		<dl class="co-totals"><div class="is-total"><dt>مبلغ پرداختی</dt><dd><?php echo esc_html( aim_t_money( $order->get_total() ) ); ?> <small>تومان</small></dd></div></dl>
	</div>

	<?php do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() ); ?>
	<?php do_action( 'woocommerce_thankyou', $order->get_id() ); ?>
</div>
