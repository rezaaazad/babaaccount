<?php
/** Panel home for sites without a store (services, immigration, company…). */
defined( 'ABSPATH' ) || exit;

$user     = wp_get_current_user();
$users    = (int) count_users()['total_users'];
$week     = count( get_users( [ 'fields' => 'ID', 'date_query' => [ [ 'after' => '7 days ago' ] ] ] ) );
$pending  = (int) wp_count_comments()->moderated;
$posts    = (int) wp_count_posts()->publish;
$pages    = (int) wp_count_posts( 'page' )->publish;
$activity = AIM_Activity::query( [ 'per_page' => 8 ] )['rows'];
$latest   = get_users( [ 'number' => 6, 'orderby' => 'registered', 'order' => 'DESC' ] );

$chips = apply_filters( 'aim_admin_dashboard_pending', [
	[ 'icon' => 'user-add-line', 'count' => $week, 'label' => 'کاربر جدید (۷ روز)', 'url' => aim_panel_url( 'admin', 'users' ) ],
	[ 'icon' => 'message-2-line', 'count' => $pending, 'label' => 'دیدگاه در انتظار تأیید', 'url' => AIM_WP_Embed::url( 'edit-comments.php?comment_status=moderated', 'comments' ), 'warn' => (bool) $pending ],
] );
$cards = apply_filters( 'aim_admin_dashboard_stats', [
	[ 'icon' => 'user-3-line', 'title' => 'کاربران', 'tone' => 'warning', 'value' => $users, 'unit' => 'نفر', 'foot' => 'از ابتدا تا امروز', 'url' => aim_panel_url( 'admin', 'users' ) ],
	[ 'icon' => 'article-line', 'title' => 'مقاله‌ها', 'tone' => 'info', 'value' => $posts, 'unit' => 'مقاله', 'foot' => 'منتشرشده', 'url' => aim_panel_url( 'admin', 'articles-all' ) ],
	[ 'icon' => 'pages-line', 'title' => 'برگه‌ها', 'tone' => 'success', 'value' => $pages, 'unit' => 'برگه', 'foot' => 'منتشرشده', 'url' => aim_panel_url( 'admin', 'pages' ) ],
] );

aim_page_head( 'پیشخوان' );
?>
<section class="card welcome mb-20">
	<div>
		<h2><?php echo esc_html( $user->display_name ); ?> عزیز، خوش آمدی!</h2>
		<p>خلاصهٔ وضعیت سایت:</p>
		<div class="pending-row">
			<?php foreach ( $chips as $chip ) : ?>
				<a class="pending<?php echo ! empty( $chip['warn'] ) ? ' is-warn' : ''; ?>" href="<?php echo esc_url( $chip['url'] ); ?>">
					<?php echo aim_icon( $chip['icon'] ); // phpcs:ignore ?>
					<div><b><?php echo esc_html( aim_fa_digits( (int) $chip['count'] ) ); ?></b><span><?php echo esc_html( $chip['label'] ); ?></span></div>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
	<div class="welcome-art" aria-hidden="true"><?php echo aim_icon( 'dashboard-3-line' ); // phpcs:ignore ?></div>
</section>

<div class="grid grid-3 mb-20">
	<?php foreach ( $cards as $card ) : ?>
		<div class="card stat">
			<div class="stat-head">
				<span class="stat-title tone-<?php echo esc_attr( $card['tone'] ?? 'info' ); ?>"><?php echo aim_icon( $card['icon'] ); // phpcs:ignore ?><?php echo esc_html( $card['title'] ); ?></span>
				<?php if ( ! empty( $card['url'] ) ) : ?><a class="stat-link" href="<?php echo esc_url( $card['url'] ); ?>">مشاهده<?php echo aim_icon( 'arrow-left-s-line' ); // phpcs:ignore ?></a><?php endif; ?>
			</div>
			<div class="stat-value"><?php echo esc_html( aim_number( $card['value'] ) ); ?> <small><?php echo esc_html( $card['unit'] ?? '' ); ?></small></div>
			<?php if ( ! empty( $card['foot'] ) ) : ?><div class="stat-foot"><?php echo esc_html( $card['foot'] ); ?></div><?php endif; ?>
		</div>
	<?php endforeach; ?>
</div>

<div class="grid grid-main">
	<section class="card">
		<div class="card-head">
			<h2>فعالیت‌های اخیر</h2>
			<div class="actions"><a class="btn btn-light btn-sm" href="<?php echo esc_url( aim_panel_url( 'admin', 'activity' ) ); ?>">همه</a></div>
		</div>
		<?php if ( $activity ) : ?>
			<ul class="list">
				<?php foreach ( $activity as $a ) : ?>
					<li>
						<span class="dot-ico"><?php echo aim_icon( 'pulse-line' ); // phpcs:ignore ?></span>
						<p><?php echo esc_html( aim_fa_digits( $a->message ) ); ?></p>
						<span class="meta"><?php echo esc_html( aim_fa_digits( human_time_diff( strtotime( $a->created_at . ' UTC' ) ) ) ); ?> پیش</span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<?php aim_empty( 'pulse-line', 'فعالیتی ثبت نشده' ); ?>
		<?php endif; ?>
	</section>

	<section class="card">
		<div class="card-head">
			<h2>تازه‌ترین کاربران</h2>
			<div class="actions"><a class="btn btn-light btn-sm" href="<?php echo esc_url( aim_panel_url( 'admin', 'users' ) ); ?>">همه</a></div>
		</div>
		<ul class="list">
			<?php foreach ( $latest as $u ) : ?>
				<li>
					<span class="avatar avatar-sm"><?php echo esc_html( mb_substr( $u->display_name ?: '؟', 0, 1 ) ); ?></span>
					<p><?php echo esc_html( $u->display_name ); ?></p>
					<span class="meta"><?php echo esc_html( aim_jdate( $u->user_registered ) ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
</div>
