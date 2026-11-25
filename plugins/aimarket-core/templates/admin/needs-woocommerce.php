<?php
/** Shown instead of a panel section while WooCommerce is not active (AIM_WP_Admin::render()). */
defined( 'ABSPATH' ) || exit;
$installed = file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' );
aim_page_head( $item['label'] ?? 'پنل مدیریت' );
?>
<section class="card soon needs-wc">
	<div class="soon-ico"><?php echo aim_icon( 'store-2-line' ); // phpcs:ignore ?></div>
	<h2>برای استفاده از پنل، افزونهٔ ووکامرس لازم است</h2>
	<p>
		<?php if ( $installed ) : ?>
			ووکامرس روی سایت نصب است اما فعال نیست. محصولات، سفارش‌ها، کاربران و بقیهٔ بخش‌های پنل بدون ووکامرس کار نمی‌کنند؛ آن را فعال کنید تا پنل باز شود.
		<?php else : ?>
			محصولات، سفارش‌ها، کاربران و بقیهٔ بخش‌های پنل روی ووکامرس ساخته شده‌اند و بدون آن کار نمی‌کنند. ووکامرس را نصب و فعال کنید تا پنل باز شود.
		<?php endif; ?>
	</p>
	<div class="needs-wc-actions">
		<?php if ( $installed ) : ?>
			<a class="btn btn-primary btn-lg" href="<?php echo esc_url( AIM_WP_Embed::url( 'plugins.php?plugin_status=inactive' ) ); ?>"><?php echo aim_icon( 'check-line' ); // phpcs:ignore ?>فعال‌سازی ووکامرس</a>
		<?php else : ?>
			<a class="btn btn-primary btn-lg" href="<?php echo esc_url( AIM_WP_Embed::url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ) ); ?>"><?php echo aim_icon( 'add-line' ); // phpcs:ignore ?>نصب ووکامرس</a>
		<?php endif; ?>
		<a class="btn btn-light btn-lg" href="<?php echo esc_url( admin_url() ); ?>"><?php echo aim_icon( 'wordpress-line' ); // phpcs:ignore ?>پیشخوان وردپرس</a>
	</div>
</section>
