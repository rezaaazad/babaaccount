<?php
defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
global $wpdb;

$filter   = sanitize_key( $_GET['filter'] ?? ( isset( $_GET['expiring'] ) ? 'expiring' : 'active' ) );
$page     = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page = 20;
$table    = AIM_Inventory::table();
$now      = gmdate( 'Y-m-d H:i:s' );
$week     = gmdate( 'Y-m-d H:i:s', time() + 7 * DAY_IN_SECONDS );

$conds = [
	'active'   => $wpdb->prepare( "status = 'assigned' AND (expires_at IS NULL OR expires_at > %s)", $now ),
	'expiring' => $wpdb->prepare( "status = 'assigned' AND expires_at BETWEEN %s AND %s", $now, $week ),
	'expired'  => $wpdb->prepare( "status = 'assigned' AND expires_at <= %s", $now ),
];
$filter = isset( $conds[ $filter ] ) ? $filter : 'active';
$tabs   = [];
foreach ( [ 'active' => 'فعال', 'expiring' => 'رو به پایان (۷ روز)', 'expired' => 'منقضی‌شده' ] as $k => $label ) {
	$tabs[ $k ] = [ 'label' => $label, 'count' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE {$conds[ $k ]}" ) ]; // phpcs:ignore
}
$order = 'expired' === $filter ? 'expires_at DESC' : 'expires_at IS NULL, expires_at ASC';
$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE {$conds[ $filter ]} ORDER BY $order LIMIT %d OFFSET %d", $per_page, ( $page - 1 ) * $per_page ) ); // phpcs:ignore

aim_page_head( 'اشتراک‌های فعال' );
?>
<section class="card">
	<?php aim_status_tabs( $tabs, $filter, 'filter' ); ?>
	<?php if ( $rows ) : ?>
		<div class="table-wrap">
			<table class="table">
				<thead><tr><th>مشتری</th><th>سرویس</th><th>سفارش</th><th>تاریخ تحویل</th><th>انقضا</th><th>مانده</th></tr></thead>
				<tbody>
				<?php foreach ( $rows as $r ) : ?>
					<?php
					$u    = get_userdata( (int) $r->user_id );
					$left = $r->expires_at ? (int) ceil( ( strtotime( $r->expires_at . ' UTC' ) - time() ) / DAY_IN_SECONDS ) : null;
					$tone = null === $left ? 'primary' : ( $left <= 0 ? 'danger' : ( $left <= 7 ? 'warning' : 'success' ) );
					?>
					<tr>
						<td><div class="cell-user"><span class="avatar avatar-sm"><?php echo esc_html( $u ? mb_substr( $u->display_name, 0, 1 ) : '؟' ); ?></span><div><b><?php echo esc_html( $u ? $u->display_name : ( (int) $r->user_id ? 'کاربر حذف‌شده' : 'مهمان' ) ); ?></b><small class="ltr"><?php echo esc_html( $u ? aim_fa_digits( aim_user_phone( $u->ID ) ) : '' ); ?></small></div></div></td>
						<td><?php echo esc_html( AIM_Inventory::product_label( (int) $r->product_id, (int) $r->variation_id ) ); ?></td>
						<td><a href="<?php echo esc_url( aim_panel_url( 'admin', 'orders', [ 'order' => $r->order_id ] ) ); ?>">#<?php echo esc_html( aim_fa_digits( $r->order_id ) ); ?></a></td>
						<td class="num text-2"><?php echo esc_html( aim_jdate( $r->assigned_at ) ); ?></td>
						<td class="num text-2"><?php echo esc_html( $r->expires_at ? aim_jdate( $r->expires_at ) : 'بدون انقضا' ); ?></td>
						<td><span class="pill pill-<?php echo esc_attr( $tone ); ?>"><?php echo esc_html( null === $left ? 'نامحدود' : ( $left <= 0 ? 'منقضی' : aim_fa_digits( $left ) . ' روز' ) ); ?></span></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php aim_pagination( $tabs[ $filter ]['count'], $per_page, $page ); ?>
	<?php else : ?>
		<?php aim_empty( 'calendar-check-line', 'موردی در این بخش نیست' ); ?>
	<?php endif; ?>
</section>
