<?php
defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.

$roles    = [ 'owner', 'manager', 'customer', 'system' ];
$role     = sanitize_key( $_GET['role'] ?? '' );
$role     = in_array( $role, $roles, true ) ? $role : '';
$page     = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page = 30;
$result   = AIM_Activity::query( [ 'role' => $role, 'page' => $page, 'per_page' => $per_page ] );
$tabs     = [ '' => [ 'label' => 'همه' ] ];
foreach ( $roles as $r ) {
	$tabs[ $r ] = [ 'label' => AIM_Activity::role_label( $r ) ];
}

aim_page_head( 'فعالیت‌های اخیر' );
?>
<section class="card">
	<?php aim_status_tabs( $tabs, $role, 'role' ); ?>
	<?php if ( $result['rows'] ) : ?>
		<div class="table-wrap">
			<table class="table">
				<thead><tr><th>گزارش</th><th>انجام‌دهنده</th><th>IP</th><th>تاریخ</th></tr></thead>
				<tbody>
				<?php foreach ( $result['rows'] as $a ) : ?>
					<?php $u = $a->user_id ? get_userdata( (int) $a->user_id ) : null; ?>
					<tr>
						<td><?php echo esc_html( aim_fa_digits( $a->message ) ); ?></td>
						<td><?php echo esc_html( $u ? $u->display_name : ( $a->user_id ? 'کاربر حذف‌شده' : 'سیستم' ) ); ?> <span class="pill"><?php echo esc_html( AIM_Activity::role_label( $a->role ) ); ?></span></td>
						<td class="num ltr text-3" style="text-align:right"><?php echo esc_html( $a->ip ); ?></td>
						<td class="num text-2"><?php echo esc_html( aim_jdate( $a->created_at, true ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php aim_pagination( $result['total'], $per_page, $page ); ?>
	<?php else : ?>
		<?php if ( $result['total'] ) : // A page past the end: offer the way back. ?>
			<?php aim_empty( 'pulse-line', 'این صفحه خالی است' ); ?>
			<?php aim_pagination( $result['total'], $per_page, $page ); ?>
		<?php else : ?>
			<?php aim_empty( 'pulse-line', 'فعالیتی ثبت نشده' ); ?>
		<?php endif; ?>
	<?php endif; ?>
</section>
