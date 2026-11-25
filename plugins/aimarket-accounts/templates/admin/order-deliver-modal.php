<?php
/**
 * «تحویل دستی» modal for one order item (printed after the item list).
 *
 * @var WC_Order_Item_Product $item
 * @var int                   $item_id
 * @var WC_Order              $order
 */
defined( 'ABSPATH' ) || exit;
$activation = $order->get_meta( '_aim_activation_email' );
?>
<div class="modal" id="deliver-<?php echo esc_attr( $item_id ); ?>" hidden role="dialog" aria-modal="true" aria-labelledby="dlv-title-<?php echo esc_attr( $item_id ); ?>">
	<form class="modal-dialog" data-api="orders/<?php echo esc_attr( $order->get_id() ); ?>/deliver" data-reload>
		<div class="modal-head">
			<h3 id="dlv-title-<?php echo esc_attr( $item_id ); ?>">تحویل دستی — <?php echo esc_html( $item->get_name() ); ?></h3>
			<button type="button" class="btn btn-ghost btn-icon btn-sm" data-modal-close aria-label="بستن"><?php echo aim_icon( 'close-line' ); // phpcs:ignore ?></button>
		</div>
		<div class="modal-body">
			<?php if ( $activation ) : ?>
				<div class="notice inline"><?php echo aim_icon( 'mail-line' ); // phpcs:ignore ?><p>ایمیل فعال‌سازی مشتری: <b class="ltr"><?php echo esc_html( $activation ); ?></b> <button type="button" class="link-btn inline" data-copy="<?php echo esc_attr( $activation ); ?>">کپی</button></p></div>
			<?php endif; ?>
			<input type="hidden" name="item_id" value="<?php echo esc_attr( $item_id ); ?>">
			<div class="form-row">
				<label class="form-label" for="secret-<?php echo esc_attr( $item_id ); ?>">اطلاعات اکانت یا توضیح فعال‌سازی</label>
				<textarea class="ltr" id="secret-<?php echo esc_attr( $item_id ); ?>" name="secret" required placeholder="email: user@example.com&#10;password: ••••••&#10;یا: اشتراک روی ایمیل شما فعال شد."></textarea>
				<p class="form-help">این متن رمزنگاری می‌شود و فقط در «اکانت‌های من» مشتری نمایش داده می‌شود. هر بار یک اکانت تحویل می‌شود.</p>
			</div>
		</div>
		<div class="modal-foot">
			<button type="button" class="btn btn-light" data-modal-close>انصراف</button>
			<button type="submit" class="btn btn-primary"><?php echo aim_icon( 'send-plane-line' ); // phpcs:ignore ?>تحویل و اطلاع به مشتری</button>
		</div>
	</form>
</div>
