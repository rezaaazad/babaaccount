<?php
defined( 'ABSPATH' ) || exit;
$shop_url = wc_get_page_permalink( 'shop' );
$cart_n   = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
?><!doctype html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'sf' ); ?>>
<?php wp_body_open(); ?>

<?php
// The header is an Elementor template when one is chosen (panel → تنظیمات فروشگاه → ظاهر سایت), else the built-in one.
if ( ! aim_t_elementor_part( 'header' ) ) {
	aim_t_section( 'header' );
}
?>

<div class="drawer drawer-menu" data-drawer hidden>
	<div class="drawer-panel dm" role="dialog" aria-modal="true" aria-label="منو">
		<?php aim_t_drawer_menu(); ?>
	</div>
</div>

<div class="drawer drawer-cart" data-minicart hidden>
	<aside class="drawer-panel mc-panel" role="dialog" aria-modal="true" aria-labelledby="mc-title">
		<div class="drawer-head">
			<b id="mc-title"><i class="ic-shopping-cart-2-line"></i> سبد خرید <span class="mc-count" data-mc-count></span></b>
			<button type="button" class="sh-ico" data-minicart-close aria-label="بستن"><i class="ic-close-line"></i></button>
		</div>
		<div class="mc-flash" data-mc-flash hidden></div>
		<div class="mc-body" data-mc-body><div class="mc-loading"><span></span><span></span><span></span></div></div>
	</aside>
</div>

<nav class="mbar" aria-label="ناوبری موبایل">
	<button type="button" data-drawer-open><i class="ic-menu-line"></i><span>منو</span></button>
	<button type="button" data-search-open><i class="ic-search-line"></i><span>جست‌وجو</span></button>
	<a class="mbar-fab" href="<?php echo esc_url( $shop_url ); ?>"><span><i class="ic-store-2-line"></i></span><b>فروشگاه</b></a>
	<a href="<?php echo esc_url( wc_get_cart_url() ); ?>" data-minicart-open class="mbar-cart"><i class="ic-shopping-cart-2-line"></i><span>سبد</span><em class="mbar-count aim-cart-count-m"<?php echo $cart_n ? '' : ' hidden'; ?>><?php echo esc_html( aim_t_fa( $cart_n ) ); ?></em></a>
	<a href="<?php echo esc_url( aim_theme_account_url() ); ?>"><i class="ic-<?php echo is_user_logged_in() ? 'user-3-line' : 'login-box-line'; ?>"></i><span><?php echo is_user_logged_in() ? 'حساب' : 'ورود'; ?></span></a>
</nav>

<main class="site-main">
