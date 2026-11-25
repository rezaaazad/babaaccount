<?php
defined( 'ABSPATH' ) || exit;
global $wpdb;

// Products, orders and delivery need the store; show the core's "store is off" page instead of fataling.
if ( ! class_exists( 'WooCommerce' ) ) {
	aim_template( 'user/needs-woocommerce', [ 'item' => [ 'label' => 'اکانت‌های من' ] ] );
	return;
}

$rows = $wpdb->get_results( $wpdb->prepare(
	'SELECT * FROM ' . AIM_Inventory::table() . " WHERE user_id = %d AND status = 'assigned' ORDER BY (expires_at IS NOT NULL AND expires_at < %s), assigned_at DESC",
	get_current_user_id(),
	gmdate( 'Y-m-d H:i:s' )
) );

// Paid items still waiting for delivery (manual products or out-of-stock).
$waiting = [];
foreach ( wc_get_orders( [ 'type' => 'shop_order', 'customer_id' => get_current_user_id(), 'status' => [ 'processing', 'on-hold' ], 'limit' => -1 ] ) as $o ) {
	foreach ( $o->get_items() as $it ) {
		$st = AIM_Delivery::item_state( $it );
		if ( $st['mode'] && $st['owed'] ) {
			$waiting[] = [ 'order' => $o, 'item' => $it, 'owed' => $st['owed'], 'mode' => $st['mode'] ];
		}
	}
}

aim_page_head( 'اکانت‌های من', '', 'user' );
?>

<?php foreach ( $waiting as $w ) : ?>
	<div class="notice inline notice-warning">
		<?php echo aim_icon( 'time-line' ); // phpcs:ignore ?>
		<p><b><?php echo esc_html( $w['item']->get_name() ); ?></b> (سفارش #<?php echo esc_html( aim_fa_digits( $w['order']->get_order_number() ) ); ?>) در حال آماده‌سازی است.
		<?php echo 'manual' === $w['mode'] ? 'اشتراک روی ایمیل شما فعال می‌شود و پس از آن همین‌جا اطلاع داده می‌شود.' : 'به‌محض آماده شدن، با پیامک خبرتان می‌کنیم.'; ?></p>
	</div>
<?php endforeach; ?>

<?php if ( ! $rows ) : ?>
	<section class="card">
		<?php aim_empty( 'key-2-line', 'هنوز اکانتی ندارید', 'پس از خرید، اطلاعات ورود اکانت‌ها اینجا نمایش داده می‌شود.', '<a class="btn btn-primary" href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">' . aim_icon( 'apps-2-line' ) . 'مشاهدهٔ سرویس‌ها</a>' ); ?>
	</section>
	<?php return; ?>
<?php endif; ?>

<?php foreach ( $rows as $r ) : ?>
	<?php
	$product  = wc_get_product( (int) $r->product_id );
	$total    = (int) $r->duration_days;
	$expires  = $r->expires_at ? strtotime( $r->expires_at . ' UTC' ) : 0;
	$left     = $expires ? (int) ceil( ( $expires - time() ) / DAY_IN_SECONDS ) : null;
	$expired  = null !== $left && $left <= 0;
	$pct      = ( $total && null !== $left ) ? max( 0, min( 100, round( $left / $total * 100 ) ) ) : 100;
	$tone     = $expired ? 'danger' : ( null !== $left && $left <= 7 ? 'warning' : 'success' );
	$renew    = $product ? $product->get_permalink() : '';
	?>
	<article class="card acc-card" id="acc-<?php echo esc_attr( $r->id ); ?>">
		<?php echo $product ? wp_kses_post( $product->get_image( [ 104, 104 ], [ 'class' => 'acc-logo' ] ) ) : '<span class="acc-logo"></span>'; ?>
		<div>
			<div class="acc-title"><?php echo esc_html( AIM_Inventory::product_label( (int) $r->product_id, (int) $r->variation_id ) ); ?></div>
			<div class="acc-meta">
				<span><?php echo aim_icon( 'shopping-bag-3-line' ); // phpcs:ignore ?>سفارش #<?php echo esc_html( aim_fa_digits( $r->order_id ) ); ?></span>
				<span><?php echo aim_icon( 'calendar-line' ); // phpcs:ignore ?>تحویل: <?php echo esc_html( aim_jdate( $r->assigned_at ) ); ?></span>
				<span class="pill pill-<?php echo esc_attr( $tone ); ?>"><?php echo esc_html( $expired ? 'منقضی شده' : 'فعال' ); ?></span>
			</div>
		</div>
		<div class="acc-expire">
			<?php if ( null === $left ) : ?>
				<small>بدون تاریخ انقضا</small>
			<?php else : ?>
				<div style="display:flex;justify-content:space-between;font-size:12px"><span class="text-2">انقضا: <?php echo esc_html( aim_jdate( $r->expires_at ) ); ?></span><b style="color:var(--<?php echo esc_attr( $tone ); ?>)"><?php echo esc_html( $expired ? 'تمام شده' : aim_fa_digits( $left ) . ' روز مانده' ); ?></b></div>
				<div class="progress <?php echo $expired ? 'is-empty' : ( 'warning' === $tone ? 'is-low' : '' ); ?>"><span style="width:<?php echo esc_attr( $pct ); ?>%;<?php echo 'success' === $tone ? 'background:var(--success)' : ''; ?>"></span></div>
			<?php endif; ?>
			<div style="display:flex;gap:6px;margin-top:8px;justify-content:flex-end">
				<button class="btn btn-primary btn-sm" data-action="me/accounts/<?php echo esc_attr( $r->id ); ?>/reveal" data-reveal-target="#cred-<?php echo esc_attr( $r->id ); ?>" data-open-card="#acc-<?php echo esc_attr( $r->id ); ?>"><?php echo aim_icon( 'eye-line' ); // phpcs:ignore ?>اطلاعات ورود</button>
				<?php if ( $renew && ( $expired || ( null !== $left && $left <= 7 ) ) ) : ?>
					<a class="btn btn-success btn-sm" href="<?php echo esc_url( $renew ); ?>"><?php echo aim_icon( 'refresh-line' ); // phpcs:ignore ?>تمدید</a>
				<?php endif; ?>
			</div>
		</div>
		<div class="acc-cred">
			<pre id="cred-<?php echo esc_attr( $r->id ); ?>"></pre>
			<div style="display:flex;gap:6px;flex-wrap:wrap">
				<button class="btn btn-light btn-sm" data-copy="#cred-<?php echo esc_attr( $r->id ); ?>"><?php echo aim_icon( 'file-copy-line' ); // phpcs:ignore ?>کپی اطلاعات</button>
				<span class="text-3" style="font-size:12px;align-self:center">این اطلاعات را با کسی به اشتراک نگذارید. در صورت مشکل با پشتیبانی تماس بگیرید.</span>
			</div>
		</div>
	</article>
<?php endforeach; ?>
<script>
document.addEventListener('aim:revealed', function (e) {
	var sel = e.target.dataset.openCard;
	var card = sel && document.querySelector(sel);
	if (card) card.classList.add('is-open');
});
</script>
