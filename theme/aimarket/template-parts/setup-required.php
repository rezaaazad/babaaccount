<?php
/** Storefront while WooCommerce is not active (inc/setup.php). Stand-alone page: no WooCommerce calls. */
defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?><?php echo is_rtl() ? '' : ' dir="rtl"'; ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?php echo esc_html( get_bloginfo( 'name' ) . ' — در حال راه‌اندازی' ); ?></title>
<link rel="stylesheet" href="<?php echo esc_url( get_template_directory_uri() . '/assets/css/fonts.css' ); ?>">
<link rel="stylesheet" href="<?php echo esc_url( get_template_directory_uri() . '/assets/css/setup.css?ver=' . filemtime( get_template_directory() . '/assets/css/setup.css' ) ); ?>">
</head>
<body class="aimt-body">
	<div class="aimt-setup">
		<div class="aimt-glow" aria-hidden="true"><i></i><i></i></div>
		<main class="aimt-card aimt-soon">
			<div class="aimt-art"><?php echo aim_t_setup_rocket(); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></div>
			<h1><?php bloginfo( 'name' ); ?> به‌زودی باز می‌شود</h1>
			<p class="aimt-lead">در حال آماده‌سازی فروشگاه هستیم. کمی بعد دوباره سر بزنید.</p>
			<?php if ( current_user_can( 'activate_plugins' ) ) : ?>
				<p class="aimt-alert"><span><b>برای مدیر سایت:</b> این صفحه به این خاطر نمایش داده می‌شود که افزونهٔ ووکامرس فعال نیست.</span></p>
				<div class="aimt-actions"><a class="aimt-btn aimt-primary" href="<?php echo esc_url( admin_url( 'themes.php?page=' . AIM_T_SETUP_PAGE ) ); ?>">نصب و راه‌اندازی خودکار</a></div>
			<?php endif; ?>
		</main>
	</div>
</body>
</html>
