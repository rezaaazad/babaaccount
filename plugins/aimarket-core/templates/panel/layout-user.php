<?php
/**
 * @var array  $menu
 * @var string $section
 * @var array  $item
 */
defined( 'ABSPATH' ) || exit;
$user     = wp_get_current_user();
$shop     = AIM_Settings::get( 'shop_title' );
$phone    = aim_user_phone( $user->ID );
$template = aim_template_path( 'user/' . $item['key'] );
$has_page = empty( $item['soon'] ) && file_exists( $template );
$cart_n   = function_exists( 'WC' ) && WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
?><!doctype html>
<html <?php language_attributes(); ?><?php echo is_rtl() ? '' : ' dir="rtl"'; ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?php echo esc_html( $item['label'] . ' | ' . $shop ); ?></title>
<?php AIM_Router::head_assets( 'user' ); ?>
</head>
<body class="aim aim-user">
<header class="user-top">
	<div class="user-top-in">
		<a class="sidebar-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php echo aim_brand_mark(); // phpcs:ignore WordPress.Security.EscapeOutput -- core markup or the theme's logo. ?><span><?php echo esc_html( $shop ); ?></span>
		</a>
		<span style="flex:1"></span>
		<a class="btn btn-light hide-mobile" href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ) ); ?>"><?php echo aim_icon( 'apps-2-line' ); // phpcs:ignore ?>همهٔ سرویس‌ها</a>
		<button class="btn btn-light btn-icon" data-theme-toggle aria-label="تغییر حالت شب و روز"><?php echo aim_icon( 'moon-line' ); // phpcs:ignore ?></button>
		<a class="btn btn-light btn-icon" href="<?php echo esc_url( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' ) ); ?>" aria-label="سبد خرید" style="position:relative">
			<?php echo aim_icon( 'shopping-cart-2-line' ); // phpcs:ignore ?>
			<?php if ( $cart_n ) : ?><span class="badge" style="position:absolute;top:-6px;left:-6px"><?php echo esc_html( aim_fa_digits( $cart_n ) ); ?></span><?php endif; ?>
		</a>
	</div>
</header>

<div class="user-shell">
	<aside class="user-side" data-user-side>
		<div class="card">
			<div class="user-card">
				<span class="avatar"><?php echo esc_html( mb_substr( $user->display_name ?: '؟', 0, 1 ) ); ?></span>
				<b><?php echo esc_html( $user->display_name ); ?></b>
				<small class="ltr"><?php echo esc_html( aim_fa_digits( $phone ) ); ?></small>
			</div>
			<button class="btn btn-ghost menu-toggle-mobile" type="button" onclick="this.setAttribute('aria-expanded', this.closest('[data-user-side]').classList.toggle('is-open'))" aria-expanded="false" aria-label="منوی ناحیهٔ کاربری">
				<span><?php echo aim_icon( 'menu-line' ); // phpcs:ignore ?> <?php echo esc_html( $item['label'] ); ?></span><?php echo aim_icon( 'arrow-down-s-line' ); // phpcs:ignore ?>
			</button>
			<nav class="user-nav" aria-label="منوی ناحیهٔ کاربری">
				<ul class="nav-list">
					<?php foreach ( $menu as $m ) : ?>
						<li><a class="nav-link<?php echo $m['key'] === $section ? ' is-active' : ''; ?>" href="<?php echo esc_url( aim_panel_url( 'user', 'dashboard' === $m['key'] ? '' : $m['key'] ) ); ?>" <?php echo $m['key'] === $section ? 'aria-current="page"' : ''; ?>>
							<?php echo aim_icon( $m['icon'] ); // phpcs:ignore ?><span class="nav-label"><?php echo esc_html( $m['label'] ); ?></span>
							<?php if ( ! empty( $m['soon'] ) ) : ?><span class="nav-soon">به‌زودی</span><?php endif; ?>
						</a></li>
					<?php endforeach; ?>
					<li><a class="nav-link logout" href="<?php echo esc_url( AIM_Router::logout_url() ); ?>"><?php echo aim_icon( 'logout-box-r-line' ); // phpcs:ignore ?><span class="nav-label">خروج از حساب</span></a></li>
				</ul>
			</nav>
		</div>
	</aside>

	<main id="content">
		<?php if ( $has_page ) : ?>
			<?php include $template; ?>
		<?php else : ?>
			<?php aim_page_head( $item['label'], '', 'user' ); ?>
			<div class="card soon">
				<div class="soon-ico"><?php echo aim_icon( '404' === $item['key'] ? 'compass-3-line' : 'hammer-line' ); // phpcs:ignore ?></div>
				<h2><?php echo '404' === $item['key'] ? 'این صفحه پیدا نشد' : 'این بخش به‌زودی فعال می‌شود'; ?></h2>
				<p><?php echo '404' === $item['key'] ? 'آدرس را بررسی کنید یا از منوی کنار صفحه بخش موردنظر را انتخاب کنید.' : 'در این مدت می‌توانید اکانت‌ها و سفارش‌هایتان را از منوی کنار صفحه ببینید.'; ?></p>
				<?php if ( AIM_Panel_Menu::find( $menu, 'accounts', false ) ) : ?>
					<a class="btn btn-primary" href="<?php echo esc_url( aim_panel_url( 'user', 'accounts' ) ); ?>"><?php echo aim_icon( 'key-2-line' ); // phpcs:ignore ?>اکانت‌های من</a>
				<?php else : ?>
					<a class="btn btn-primary" href="<?php echo esc_url( aim_panel_url( 'user' ) ); ?>"><?php echo aim_icon( 'dashboard-3-line' ); // phpcs:ignore ?>پیشخوان</a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</main>
</div>

<div class="toast-stack" data-toasts aria-live="assertive"></div>
<?php AIM_Router::footer_assets( 'user' ); ?>
</body>
</html>
