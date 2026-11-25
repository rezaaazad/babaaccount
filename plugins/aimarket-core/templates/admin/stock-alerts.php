<?php
/** «اعلان موجودی و تخفیف»: who waits for which product (AIM_Stock_Alerts). */
defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.

$status   = in_array( $_GET['status'] ?? '', [ 'pending', 'sent' ], true ) ? $_GET['status'] : 'pending'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
$type     = sanitize_key( $_GET['type'] ?? '' );
$page     = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page = 30;
[ $rows, $total ] = AIM_Stock_Alerts::requests( $status, $type, $per_page, ( $page - 1 ) * $per_page );
$summary  = AIM_Stock_Alerts::summary();

aim_page_head( 'اعلان موجودی و تخفیف' );
?>
<?php if ( ! AIM_Stock_Alerts::enabled() ) : ?>
	<div class="notice inline notice-warning"><?php echo aim_icon( 'information-line' ); // phpcs:ignore ?><p>دکمهٔ «خبرم کن» در سایت خاموش است. از <a href="<?php echo esc_url( aim_panel_url( 'admin', 'settings' ) . '#features' ); ?>">تنظیمات ← امکانات فروشگاه</a> روشنش کنید.</p></div>
<?php else : ?>
	<div class="notice inline"><?php echo aim_icon( 'information-line' ); // phpcs:ignore ?><p>وقتی محصولی موجود شود (مثلاً با افزودن اکانت به انبار) یا قیمت ویژه بگیرد، برای همهٔ منتظرها خودکار پیامک می‌رود. کد الگوی پیامک را در <a href="<?php echo esc_url( aim_panel_url( 'admin', 'settings' ) . '#sms' ); ?>">تنظیمات ← درگاه پیامک</a> وارد کنید.</p></div>
<?php endif; ?>

<section class="card">
	<div class="card-head"><h2><?php echo aim_icon( 'line-chart-line' ); // phpcs:ignore ?>بیشترین درخواست‌ها</h2></div>
	<?php if ( $summary ) : ?>
		<div class="table-wrap">
			<table class="table">
				<thead><tr><th>محصول</th><th>نوع</th><th>منتظرها</th><th>وضعیت فعلی</th><th>آخرین درخواست</th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $summary as $row ) : ?>
					<?php
					$p = wc_get_product( $row->product_id );
					if ( ! $p ) {
						continue;
					}
					// Sale alerts go out only when the product is on sale and buyable (AIM_Stock_Alerts::send()).
					$ready = $p->is_in_stock() && ( 'stock' === $row->type || $p->is_on_sale() );
					?>
					<tr>
						<td><b><?php echo esc_html( $p->get_name() ); ?></b></td>
						<td><span class="pill <?php echo 'stock' === $row->type ? 'pill-primary' : 'pill-warning'; ?>"><?php echo esc_html( AIM_Stock_Alerts::TYPES[ $row->type ] ); ?></span></td>
						<td class="num"><b><?php echo esc_html( aim_fa_digits( $row->total ) ); ?></b> نفر</td>
						<td><?php echo ! $p->is_in_stock() ? '<span class="pill pill-danger">ناموجود</span>' : ( 'stock' === $row->type ? '<span class="pill pill-success">موجود شده</span>' : ( $ready ? '<span class="pill pill-success">تخفیف دارد</span>' : '<span class="pill">بدون تخفیف</span>' ) ); // phpcs:ignore ?></td>
						<td class="num text-2"><?php echo esc_html( aim_jdate( strtotime( $row->last_at . ' UTC' ), true ) ); ?></td>
						<td><div class="row-actions">
							<?php if ( $ready ) : ?>
								<button class="btn btn-primary btn-xs" data-action="alerts/send" data-body="<?php echo esc_attr( wp_json_encode( [ 'product_id' => (int) $row->product_id, 'type' => $row->type ] ) ); ?>"><?php echo aim_icon( 'send-plane-line' ); // phpcs:ignore ?>ارسال الان</button>
							<?php elseif ( 'stock' === $row->type && ( $restock = apply_filters( 'aim_restock_link', '', $p ) ) ) : ?>
								<?php echo $restock; // phpcs:ignore WordPress.Security.EscapeOutput -- module renders escaped HTML (the accounts module: «شارژ انبار»). ?>
							<?php else : ?>
								<a class="btn btn-light btn-xs" href="<?php echo esc_url( AIM_WP_Embed::url( 'post.php?post=' . ( $p->get_parent_id() ?: $p->get_id() ) . '&action=edit', 'products' ) ); ?>"><?php echo aim_icon( 'edit-line' ); // phpcs:ignore ?>ویرایش محصول</a>
							<?php endif; ?>
						</div></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php else : ?>
		<?php aim_empty( 'notification-3-line', 'فعلاً کسی منتظر نیست', 'درخواست‌های «خبرم کن» مشتری‌ها اینجا جمع می‌شوند.' ); ?>
	<?php endif; ?>
</section>

<section class="card">
	<?php aim_status_tabs( [ 'pending' => [ 'label' => 'در انتظار' ], 'sent' => [ 'label' => 'ارسال‌شده' ] ], $status ); ?>
	<?php if ( $rows ) : ?>
		<div class="table-wrap">
			<table class="table">
				<thead><tr><th>مشتری</th><th>موبایل</th><th>محصول</th><th>نوع</th><th>ثبت</th><th><?php echo 'sent' === $status ? 'ارسال' : ''; ?></th></tr></thead>
				<tbody>
				<?php foreach ( $rows as $r ) : ?>
					<?php $u = get_userdata( $r->user_id ); ?>
					<tr>
						<td><?php echo esc_html( $u ? $u->display_name : '—' ); ?></td>
						<td class="num ltr" style="text-align:right"><?php echo esc_html( aim_fa_digits( $r->phone ) ); ?></td>
						<td><?php echo esc_html( get_the_title( $r->product_id ) ); ?></td>
						<td><?php echo esc_html( AIM_Stock_Alerts::TYPES[ $r->type ] ?? $r->type ); ?></td>
						<td class="num text-2"><?php echo esc_html( aim_jdate( strtotime( $r->created_at . ' UTC' ), true ) ); ?></td>
						<td>
							<?php if ( 'sent' === $r->status ) : ?>
								<span class="num text-2"><?php echo esc_html( aim_jdate( strtotime( $r->sent_at . ' UTC' ), true ) ); ?></span>
							<?php else : ?>
								<button class="btn btn-ghost btn-xs" data-action="<?php echo esc_attr( 'alerts/' . $r->id . '/delete' ); ?>" data-confirm="این درخواست حذف شود؟" aria-label="حذف"><?php echo aim_icon( 'delete-bin-6-line' ); // phpcs:ignore ?></button>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php aim_pagination( $total, $per_page, $page ); ?>
	<?php else : ?>
		<?php aim_empty( 'inbox-line', 'pending' === $status ? 'درخواست در انتظاری نیست' : 'هنوز اعلانی ارسال نشده' ); ?>
	<?php endif; ?>
</section>
