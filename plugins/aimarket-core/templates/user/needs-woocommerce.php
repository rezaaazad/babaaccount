<?php
/** Customer panel while the store (WooCommerce) is switched off. */
defined( 'ABSPATH' ) || exit;
aim_page_head( $item['label'] ?? 'حساب کاربری', '', 'user' );
?>
<section class="card soon">
	<div class="soon-ico"><?php echo aim_icon( 'time-line' ); // phpcs:ignore ?></div>
	<h2>فروشگاه موقتاً در دسترس نیست</h2>
	<p>در حال به‌روزرسانی فروشگاه هستیم. کمی بعد دوباره سر بزنید.</p>
</section>
