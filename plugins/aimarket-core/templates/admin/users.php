<?php
defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.

$role     = sanitize_key( $_GET['role'] ?? '' );
$search   = aim_en_digits( sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ) );
$page     = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page = 20;
$roles    = [ '' => 'همه', 'customer' => 'مشتری', 'shop_manager' => 'مدیر فروشگاه', 'administrator' => 'مدیر کل' ];
$counts   = count_users();
// Other roles that have users (e.g. «مشترک», the default role of OTP sign-ups) get their own tab too.
foreach ( $counts['avail_roles'] as $k => $n ) {
	if ( $n && ! isset( $roles[ $k ] ) && wp_roles()->is_role( $k ) ) {
		$roles[ $k ] = translate_user_role( wp_roles()->get_names()[ $k ] );
	}
}
if ( ! isset( $roles[ $role ] ) ) {
	$role = '';
}

$args = [ 'number' => $per_page, 'paged' => $page, 'orderby' => 'registered', 'order' => 'DESC', 'count_total' => true ];
if ( $role ) {
	$args['role'] = $role;
}
if ( '' !== $search ) {
	$phone = aim_normalize_phone( $search );
	if ( $phone ) {
		$args['meta_key']   = 'aim_phone';
		$args['meta_value'] = $phone;
	} else {
		$args['search']         = '*' . $search . '*';
		$args['search_columns'] = [ 'display_name', 'user_email', 'user_login' ]; // Phone users have logins like u0912…, so partial numbers match too.
	}
}
$query = new WP_User_Query( $args );
$tabs  = [];
foreach ( $roles as $k => $label ) {
	$tabs[ $k ] = [ 'label' => $label, 'count' => $k ? (int) ( $counts['avail_roles'][ $k ] ?? 0 ) : (int) $counts['total_users'] ];
}

$actions = current_user_can( 'create_users' ) ? '<button class="btn btn-primary" data-modal-open="add-user">' . aim_icon( 'user-add-line' ) . 'افزودن کاربر</button>' : '';
aim_page_head( 'کاربران', $actions );

$me_phone = aim_user_phone( get_current_user_id() );
?>
<?php if ( ! $me_phone ) : ?>
	<div class="notice inline notice-warning"><?php echo aim_icon( 'smartphone-line' ); // phpcs:ignore ?><p>برای حساب خودتان شمارهٔ موبایل ثبت نشده است. با ثبت شماره می‌توانید از صفحهٔ «ورود» با کد یک‌بار مصرف وارد شوید. <button class="link-btn inline" data-modal-open="my-phone">ثبت شماره</button></p></div>
<?php endif; ?>

<section class="card">
	<?php aim_status_tabs( $tabs, $role, 'role' ); ?>
	<form class="toolbar" method="get">
		<?php AIM_WP_Admin::form_fields(); ?>
		<?php if ( $role ) : ?><input type="hidden" name="role" value="<?php echo esc_attr( $role ); ?>"><?php endif; ?>
		<label class="search"><?php echo aim_icon( 'search-line' ); // phpcs:ignore ?><span class="visually-hidden">جستجو</span><input class="field" type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="نام، ایمیل یا شمارهٔ موبایل…"></label>
		<button class="btn btn-light" type="submit">جستجو</button>
	</form>
	<?php if ( $query->get_results() ) : ?>
		<?php
		/** Extra columns from modules: [ 'label' => header, 'cell' => fn( WP_User ) => HTML ]. */
		$columns = [];
		if ( class_exists( 'WooCommerce' ) ) { // Store columns: orders and total spent.
			$columns[] = [ 'label' => 'سفارش‌ها', 'cell' => fn( $u ) => '<span class="num">' . esc_html( aim_fa_digits( ( new WC_Customer( $u->ID ) )->get_order_count() ) ) . '</span>' ];
			$columns[] = [ 'label' => 'مجموع خرید', 'cell' => fn( $u ) => aim_price( ( new WC_Customer( $u->ID ) )->get_total_spent() ) ];
		}
		$columns = apply_filters( 'aim_admin_user_columns', $columns );
		?>
		<div class="table-wrap">
			<table class="table">
				<thead><tr><th>کاربر</th><th>شمارهٔ همراه</th><th>نقش</th><?php foreach ( $columns as $col ) : ?><th><?php echo esc_html( $col['label'] ); ?></th><?php endforeach; ?><th>تاریخ پیوستن</th></tr></thead>
				<tbody>
				<?php foreach ( $query->get_results() as $u ) : ?>
					<?php
					$rkey  = $u->roles ? reset( $u->roles ) : ''; // roles[] keys are not always 0.
					$rname = $rkey ? ( $roles[ $rkey ] ?? translate_user_role( wp_roles()->get_names()[ $rkey ] ?? $rkey ) ) : '—';
					?>
					<tr>
						<td><div class="cell-user"><span class="avatar avatar-sm"><?php echo esc_html( mb_substr( $u->display_name ?: '؟', 0, 1 ) ); ?></span><div><b><?php if ( current_user_can( 'edit_user', $u->ID ) ) : ?><a href="<?php echo esc_url( AIM_WP_Embed::url( 'user-edit.php?user_id=' . $u->ID, 'users' ) ); ?>" title="ویرایش کاربر"><?php echo esc_html( $u->display_name ); ?></a><?php else : ?><?php echo esc_html( $u->display_name ); ?><?php endif; ?></b><small class="ltr"><?php echo esc_html( $u->user_email ); ?></small></div></div></td>
						<td class="num ltr" style="text-align:right"><?php echo esc_html( aim_fa_digits( aim_user_phone( $u->ID ) ) ?: '—' ); ?></td>
						<td><span class="pill"><?php echo esc_html( $rname ); ?></span></td>
						<?php foreach ( $columns as $col ) : ?><td><?php echo $col['cell']( $u ); // phpcs:ignore WordPress.Security.EscapeOutput -- module renders escaped HTML. ?></td><?php endforeach; ?>
						<td class="num text-2"><?php echo esc_html( aim_jdate( $u->user_registered ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php aim_pagination( $query->get_total(), $per_page, $page ); ?>
	<?php else : ?>
		<?php aim_empty( 'user-search-line', 'کاربری پیدا نشد' ); ?>
	<?php endif; ?>
</section>

<?php if ( current_user_can( 'create_users' ) ) : ?>
<div class="modal" id="add-user" hidden role="dialog" aria-modal="true" aria-labelledby="add-user-title">
	<form class="modal-dialog" data-api="users/add" data-reload>
		<div class="modal-head"><h3 id="add-user-title">افزودن کاربر</h3><button type="button" class="btn btn-ghost btn-icon btn-sm" data-modal-close aria-label="بستن"><?php echo aim_icon( 'close-line' ); // phpcs:ignore ?></button></div>
		<div class="modal-body">
			<div class="form-row"><label class="form-label" for="nu-name">نام کامل</label><input id="nu-name" class="field" name="name" placeholder="مثال: علی رضایی"></div>
			<div class="form-row" style="margin:0"><label class="form-label" for="nu-phone">شمارهٔ همراه</label><input id="nu-phone" class="field ltr" name="phone" type="tel" inputmode="numeric" required placeholder="09123456789"><p class="form-help">کاربر با همین شماره و کد یک‌بار مصرف وارد می‌شود.</p></div>
		</div>
		<div class="modal-foot"><button type="button" class="btn btn-light" data-modal-close>انصراف</button><button type="submit" class="btn btn-primary">افزودن</button></div>
	</form>
</div>
<?php endif; ?>

<div class="modal" id="my-phone" hidden role="dialog" aria-modal="true" aria-labelledby="my-phone-title">
	<form class="modal-dialog" data-api="users/<?php echo esc_attr( get_current_user_id() ); ?>/phone" data-reload>
		<div class="modal-head"><h3 id="my-phone-title">ثبت شمارهٔ موبایل حساب شما</h3><button type="button" class="btn btn-ghost btn-icon btn-sm" data-modal-close aria-label="بستن"><?php echo aim_icon( 'close-line' ); // phpcs:ignore ?></button></div>
		<div class="modal-body"><label class="form-label" for="mp-phone">شمارهٔ همراه</label><input id="mp-phone" class="field ltr" name="phone" type="tel" inputmode="numeric" required placeholder="09123456789"></div>
		<div class="modal-foot"><button type="button" class="btn btn-light" data-modal-close>انصراف</button><button type="submit" class="btn btn-primary">ثبت شماره</button></div>
	</form>
</div>
