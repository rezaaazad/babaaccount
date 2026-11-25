<?php
/**
 * Inside an order item row (hook 'aim_admin_order_item'): delivery state,
 * manual-delivery button and the accounts delivered for this item.
 *
 * @var WC_Order_Item_Product $item
 * @var int                   $item_id
 * @var WC_Order              $order
 * @var array                 $st       AIM_Delivery::item_state().
 * @var object[]              $accounts Rows of the accounts table.
 */
defined( 'ABSPATH' ) || exit;
?>
<span class="text-2" style="font-size:12px"><?php echo 'manual' === $st['mode'] ? 'تحویل دستی' : 'تحویل خودکار'; ?></span>
<span class="pill <?php echo $st['owed'] ? 'pill-warning' : 'pill-success'; ?>">
	<?php echo esc_html( aim_fa_digits( $st['delivered'] ) . ' از ' . aim_fa_digits( $st['qty'] ) . ' تحویل شد' ); ?>
</span>
<?php if ( $st['owed'] && $order->is_paid() ) : ?>
	<button class="btn btn-primary btn-sm" data-modal-open="deliver-<?php echo esc_attr( $item_id ); ?>"><?php echo aim_icon( 'send-plane-line' ); // phpcs:ignore ?>تحویل دستی</button>
<?php endif; ?>

<?php if ( $accounts ) : ?>
	<div style="flex-basis:100%;margin-top:10px">
		<table class="table">
			<thead><tr><th>شناسه</th><th>اطلاعات اکانت</th><th>انقضا</th><th></th></tr></thead>
			<tbody>
			<?php foreach ( $accounts as $a ) : ?>
				<tr>
					<td class="num">#<?php echo esc_html( aim_fa_digits( $a->id ) ); ?></td>
					<td><span class="secret ltr" id="sec-<?php echo esc_attr( $a->id ); ?>">•••••••• محرمانه</span></td>
					<td class="num text-2"><?php echo esc_html( aim_jdate( $a->expires_at ) ); ?></td>
					<td><div class="row-actions">
						<button class="btn btn-light btn-xs" data-action="accounts/<?php echo esc_attr( $a->id ); ?>/reveal" data-reveal-target="#sec-<?php echo esc_attr( $a->id ); ?>"><?php echo aim_icon( 'eye-line' ); // phpcs:ignore ?>نمایش</button>
					</div></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
