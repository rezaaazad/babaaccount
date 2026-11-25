<?php
/**
 * Announcement bar + site header (logo, search, actions, menu).
 *
 * @var array $args See aim_t_section_defaults( 'header' ).
 */
defined( 'ABSPATH' ) || exit;
$a      = $args;
$cart_n = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
?>
<?php if ( $a['announce'] && $a['announce_text'] ) : ?>
<div class="announce" data-announce>
	<div class="container announce-in">
		<i class="ic-megaphone-line" aria-hidden="true"></i>
		<span><?php echo esc_html( $a['announce_text'] ); ?></span>
		<?php if ( $a['announce_url'] && $a['announce_cta'] ) : ?>
			<a href="<?php echo esc_url( $a['announce_url'] ); ?>"><?php echo esc_html( $a['announce_cta'] ); ?><i class="ic-arrow-left-s-line"></i></a>
		<?php endif; ?>
		<button type="button" class="announce-x" data-announce-close aria-label="بستن"><i class="ic-close-line"></i></button>
	</div>
</div>
<?php endif; ?>

<header class="sh" data-header>
	<div class="container sh-top">
		<button type="button" class="sh-ico sh-burger" data-drawer-open aria-label="منو"><i class="ic-menu-line"></i></button>
		<a class="sh-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php if ( $a['logo'] ) : ?>
				<?php echo $a['logo']; // phpcs:ignore WordPress.Security.EscapeOutput -- wp_get_attachment_image(). ?>
			<?php else : ?>
				<?php echo aim_t_brand_mark( 'sh-logo' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<span class="sh-name"><b><?php echo aim_t_brand_name( $a['brand'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by aim_t_brand_name(). ?></b><?php if ( $a['brand_sub'] ) : ?><small><?php echo esc_html( $a['brand_sub'] ); ?></small><?php endif; ?></span>
			<?php endif; ?>
		</a>
		<?php if ( $a['search'] ) : ?>
			<form class="sh-search" role="search" action="<?php echo esc_url( home_url( '/' ) ); ?>" data-search>
				<i class="ic-search-line" aria-hidden="true"></i>
				<label class="visually-hidden" for="sh-s">جست‌وجو</label>
				<input id="sh-s" type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php echo esc_attr( $a['placeholder'] ); ?>" autocomplete="off">
				<input type="hidden" name="post_type" value="product">
				<kbd aria-hidden="true">/</kbd>
			</form>
		<?php endif; ?>
		<div class="sh-actions">
			<?php if ( $a['search'] ) : ?><button type="button" class="sh-ico sh-search-mobile" data-search-open aria-label="جست‌وجو"><i class="ic-search-line"></i></button><?php endif; ?>
			<?php if ( $a['theme_toggle'] ) : ?><button type="button" class="sh-ico sh-theme" data-theme-toggle aria-label="تغییر حالت شب و روز" title="حالت شب / روز"><i class="ic-moon-line"></i></button><?php endif; ?>
			<?php if ( $a['login'] ) : ?>
				<a class="sh-login" href="<?php echo esc_url( aim_theme_account_url() ); ?>">
					<i class="ic-user-3-line"></i><span><?php echo esc_html( is_user_logged_in() ? $a['login_in'] : $a['login_out'] ); ?></span>
				</a>
			<?php endif; ?>
			<?php if ( $a['cart'] ) : ?>
				<a class="sh-ico sh-cart" href="<?php echo esc_url( wc_get_cart_url() ); ?>" aria-label="سبد خرید" data-minicart-open>
					<i class="ic-shopping-cart-2-line"></i>
					<span class="sh-badge aim-cart-count"><?php echo esc_html( aim_t_fa( $cart_n ) ); ?></span>
				</a>
			<?php endif; ?>
		</div>
	</div>
	<nav class="container sh-nav" aria-label="منوی اصلی">
		<?php echo $a['menu']; // phpcs:ignore WordPress.Security.EscapeOutput -- from wp_nav_menu() / aim_t_header_menu(). ?>
		<?php if ( $a['track_text'] ) : ?>
			<a class="sh-track" href="<?php echo esc_url( $a['track_url'] ); ?>"><i class="ic-file-list-3-line"></i><?php echo esc_html( $a['track_text'] ); ?></a>
		<?php endif; ?>
	</nav>
</header>
