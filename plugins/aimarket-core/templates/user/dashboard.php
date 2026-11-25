<?php
defined( 'ABSPATH' ) || exit;
$uid    = get_current_user_id();
$user   = wp_get_current_user();
$store  = class_exists( 'WooCommerce' );
$orders = $store ? wc_get_orders( [ 'customer_id' => $uid, 'limit' => 5, 'orderby' => 'date', 'order' => 'DESC' ] ) : [];
$open   = $store ? count( wc_get_orders( [ 'customer_id' => $uid, 'status' => [ 'processing', 'on-hold', 'pending' ], 'limit' => -1, 'return' => 'ids' ] ) ) : 0;
/** Summary chips: [ icon, count, label, url, warn ]; modules add theirs (the accounts module: active and ending subscriptions). */
$chips  = apply_filters( 'aim_user_dashboard_pending', $store ? [
	[ 'icon' => 'time-line', 'count' => $open, 'label' => 'سفارش در جریان', 'url' => aim_panel_url( 'user', 'orders' ) ],
] : [], $uid );

aim_page_head( 'پیشخوان', '', 'user' );
?>
<section class="card welcome mb-20">
	<div>
		<h2><?php echo esc_html( $user->display_name ); ?> عزیز، خوش آمدی!</h2>
		<p>خلاصهٔ وضعیت حساب شما:</p>
		<div class="pending-row">
			<?php foreach ( $chips as $chip ) : ?>
				<a class="pending<?php echo ! empty( $chip['warn'] ) ? ' is-warn' : ''; ?>" href="<?php echo esc_url( $chip['url'] ); ?>"><?php echo aim_icon( $chip['icon'] ); // phpcs:ignore ?><div><b><?php echo esc_html( aim_fa_digits( (int) $chip['count'] ) ); ?></b><span><?php echo esc_html( $chip['label'] ); ?></span></div></a>
			<?php endforeach; ?>
		</div>
	</div>
	<div class="welcome-art" aria-hidden="true"><?php echo aim_icon( 'user-3-line' ); // phpcs:ignore ?></div>
</section>

<?php if ( ! aim_user_phone( $uid ) ) : ?>
	<div class="notice inline notice-warning"><?php echo aim_icon( 'smartphone-line' ); // phpcs:ignore ?><p>شمارهٔ موبایل برای حساب شما ثبت نشده است؛ پیامک‌های سفارش برایتان ارسال نمی‌شود.</p></div>
<?php endif; ?>

<?php if ( ! $store ) : ?>
	<section class="card"><?php aim_empty( 'user-settings-line', 'حساب شما فعال است', 'اطلاعات حسابتان را از «ویرایش پروفایل» به‌روز کنید.', '<a class="btn btn-primary" href="' . esc_url( aim_panel_url( 'user', 'profile' ) ) . '">ویرایش پروفایل</a>' ); ?></section>
	<?php return; ?>
<?php endif; ?>
<section class="card">
	<div class="card-head"><h2>آخرین سفارش‌ها</h2><div class="actions"><a class="btn btn-light btn-sm" href="<?php echo esc_url( aim_panel_url( 'user', 'orders' ) ); ?>">همه</a></div></div>
	<?php if ( $orders ) : ?>
		<?php aim_template( 'user/_orders-table', [ 'orders' => $orders ] ); ?>
	<?php else : ?>
		<?php aim_empty( 'shopping-bag-3-line', 'هنوز سفارشی ثبت نکرده‌اید', '', '<a class="btn btn-primary" href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">مشاهدهٔ سرویس‌ها</a>' ); ?>
	<?php endif; ?>
</section>
