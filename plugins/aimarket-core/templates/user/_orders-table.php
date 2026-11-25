<?php
/** @var WC_Order[] $orders */
defined( 'ABSPATH' ) || exit;
$has_accounts = (bool) AIM_Panel_Menu::find( AIM_Panel_Menu::user(), 'accounts', false ); // The accounts module may be off.
?>
<div class="table-wrap">
	<table class="table">
		<thead><tr><th>سفارش</th><th>اقلام</th><th>مبلغ</th><th>وضعیت</th><th>تاریخ</th><th></th></tr></thead>
		<tbody>
		<?php foreach ( $orders as $o ) : ?>
			<?php $items = $o->get_items(); $first = $items ? reset( $items ) : null; ?>
			<tr>
				<td><b>#<?php echo esc_html( aim_fa_digits( $o->get_order_number() ) ); ?></b></td>
				<td><?php echo $first ? esc_html( $first->get_name() ) : '—'; ?><?php if ( count( $items ) > 1 ) : ?><small> و <?php echo esc_html( aim_fa_digits( count( $items ) - 1 ) ); ?> مورد دیگر</small><?php endif; ?></td>
				<td class="num"><?php echo aim_price( $o->get_total() ); // phpcs:ignore ?></td>
				<td><?php echo aim_order_status_pill( $o->get_status() ); // phpcs:ignore ?></td>
				<td class="num text-2"><?php echo esc_html( aim_jdate( $o->get_date_created() ? $o->get_date_created()->getTimestamp() : 0 ) ); ?></td>
				<td><div class="row-actions">
					<?php if ( $o->needs_payment() ) : ?>
						<a class="btn btn-primary btn-xs" href="<?php echo esc_url( $o->get_checkout_payment_url() ); ?>">پرداخت</a>
					<?php elseif ( $has_accounts && in_array( $o->get_status(), [ 'processing', 'completed' ], true ) && array_filter( $items, fn( $it ) => AIM_Inventory::is_account_product( $it->get_product_id() ) ) ) : ?>
						<a class="btn btn-light btn-xs" href="<?php echo esc_url( aim_panel_url( 'user', 'accounts' ) ); ?>">اکانت‌ها</a>
					<?php endif; ?>
				</div></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
