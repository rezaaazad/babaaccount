<?php
/** Customer panel → «قراردادها»: the contracts accepted with each order (AIM_Contract). */
defined( 'ABSPATH' ) || exit;
$orders = array_filter(
	wc_get_orders( [ 'type' => 'shop_order', 'customer_id' => get_current_user_id(), 'limit' => 100, 'orderby' => 'date', 'order' => 'DESC' ] ),
	fn( $o ) => (bool) AIM_Contract::of( $o )
);
aim_page_head( 'قراردادها', '', 'user' );
?>
<section class="card">
	<?php if ( $orders ) : ?>
		<div class="table-wrap">
			<table class="table">
				<thead><tr><th>سفارش</th><th>تاریخ پذیرش</th><th>مبلغ</th><th>نسخه</th><th></th></tr></thead>
				<tbody>
					<?php foreach ( $orders as $o ) : ?>
						<?php $c = AIM_Contract::of( $o ); ?>
						<tr>
							<td><b>#<?php echo esc_html( aim_fa_digits( $o->get_order_number() ) ); ?></b></td>
							<td><?php echo esc_html( aim_jdate( $c['time'], true ) ); ?></td>
							<td class="num"><?php echo aim_price( $o->get_total() ); // phpcs:ignore WordPress.Security.EscapeOutput -- digits and a fixed <small>. ?></td>
							<td><span class="ltr text-3"><?php echo esc_html( $c['versions']['contract'] ?? '' ); ?></span></td>
							<td><a class="btn btn-light btn-sm" href="<?php echo esc_url( AIM_Contract::url( $o ) ); ?>" target="_blank" rel="noopener"><?php echo aim_icon( 'file-list-3-line' ); // phpcs:ignore ?>مشاهده و چاپ</a></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<p class="form-help" style="padding:0 16px 12px">هر قرارداد همان متنی است که هنگام ثبت سفارش پذیرفته‌اید و بعداً تغییر نمی‌کند.</p>
	<?php else : ?>
		<?php aim_empty( 'file-list-3-line', 'هنوز قراردادی ندارید', 'قرارداد هر سفارش هنگام ثبت آن ساخته می‌شود و اینجا نمایش داده می‌شود.' ); ?>
	<?php endif; ?>
</section>
