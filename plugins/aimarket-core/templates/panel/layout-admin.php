<?php
/**
 * Admin panel shell, rendered inside the wp-admin page (WordPress's own
 * menu, toolbar and footer are hidden there by panel-wp.css).
 *
 * @var array  $menu
 * @var string $section
 * @var array  $item
 * @var string $embed_path Native wp-admin screen to show instead of a panel page ('' = none).
 */
defined( 'ABSPATH' ) || exit;
$user     = wp_get_current_user();
$shop     = AIM_Settings::get( 'shop_title' );
$badges   = AIM_Panel_Menu::admin_badges();
$template = aim_template_path( $embed_path ? 'admin/embed' : 'admin/' . $item['key'] );
$has_page = $embed_path || ( empty( $item['soon'] ) && file_exists( $template ) );
$initial  = mb_substr( $user->display_name ?: 'م', 0, 1 );

/** Render one nav item (and its children). */
$render_item = function ( $it ) use ( $section ) {
        $children = $it['children'] ?? [];
        $active   = $it['key'] === $section || in_array( $section, array_column( $children, 'key' ), true );
        echo '<li class="nav-item' . ( $children && $active ? ' is-open' : '' ) . '">';
        if ( $children ) {
                printf(
                        '<a href="#" class="nav-link%s" data-submenu aria-expanded="%s">%s<span class="nav-label">%s</span><i class="ic-arrow-left-s-line chev"></i></a>',
                        $active ? ' is-active' : '',
                        $active ? 'true' : 'false',
                        aim_icon( $it['icon'] ?? 'circle-line' ), // phpcs:ignore
                        esc_html( $it['label'] )
                );
                echo '<ul class="nav-sub">';
                foreach ( $children as $c ) {
                        printf(
                                '<li><a href="%s" class="nav-link%s"><span class="nav-label">%s</span>%s</a></li>',
                                esc_url( aim_panel_url( 'admin', $c['key'] ) ),
                                $c['key'] === $section ? ' is-active' : '',
                                esc_html( $c['label'] ),
                                ! empty( $c['soon'] ) ? '<span class="nav-soon">به‌زودی</span>' : ''
                        );
                }
                echo '</ul>';
        } else {
                $badge = '';
                if ( ! empty( $it['badge'] ) ) {
                        $badge = '<span class="badge' . ( ! empty( $it['badge_tone'] ) ? ' badge-' . esc_attr( $it['badge_tone'] ) : '' ) . '">' . esc_html( aim_fa_digits( $it['badge'] ) ) . '</span>';
                } elseif ( ! empty( $it['soon'] ) ) {
                        $badge = '<span class="nav-soon">به‌زودی</span>';
                }
                printf(
                        '<a href="%s" class="nav-link%s"%s>%s<span class="nav-label">%s</span>%s</a>',
                        esc_url( aim_panel_url( 'admin', $it['key'] ) ),
                        $active ? ' is-active' : '',
                        $active ? ' aria-current="page"' : '',
                        aim_icon( $it['icon'] ?? 'circle-line' ), // phpcs:ignore
                        esc_html( $it['label'] ),
                        $badge // phpcs:ignore
                );
        }
        echo '</li>';
};
?>
<div class="aim aim-app aim-shell">
        <div class="nav-progress" aria-hidden="true"></div>
        <aside class="sidebar" aria-label="منوی پنل مدیریت">
                <div class="sidebar-head">
                        <a class="sidebar-brand" href="<?php echo esc_url( aim_panel_url( 'admin' ) ); ?>">
                                <?php echo aim_brand_mark(); // phpcs:ignore WordPress.Security.EscapeOutput -- core markup or the theme's logo. ?>
                                <span><?php echo esc_html( $shop ); ?></span>
                        </a>
                        <span class="sidebar-version"><?php echo esc_html( aim_fa_digits( AIM_VERSION ) ); ?></span>
                        <button class="btn btn-ghost btn-icon btn-sm sidebar-close" data-sidebar-close aria-label="بستن منو"><?php echo aim_icon( 'close-line' ); // phpcs:ignore ?></button>
                </div>
                <div class="sidebar-profile">
                        <span class="avatar"><?php echo esc_html( $initial ); ?></span>
                        <div>
                                <b><?php echo esc_html( $user->display_name ); ?></b>
                                <small><?php echo current_user_can( 'manage_options' ) ? 'مدیر کل' : 'مدیر فروشگاه'; ?></small>
                        </div>
                </div>
                <nav class="sidebar-nav">
                        <?php foreach ( $menu as $group ) : ?>
                                <div class="nav-group-title"><?php echo esc_html( $group['title'] ); ?></div>
                                <ul class="nav-list"><?php array_map( $render_item, $group['items'] ); ?></ul>
                        <?php endforeach; ?>
                </nav>
        </aside>
        <div class="sidebar-backdrop" data-sidebar-close></div>

        <div class="main">
                <header class="topbar">
                        <button class="btn btn-icon" data-sidebar-toggle aria-label="باز و بسته کردن منو"><?php echo aim_icon( 'menu-fold-line' ); // phpcs:ignore ?></button>
                        <a class="btn btn-light hide-mobile" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener"><?php echo aim_icon( 'store-2-line' ); // phpcs:ignore ?>مشاهدهٔ فروشگاه</a>
                        <a class="btn btn-light hide-mobile" href="<?php echo esc_url( admin_url() ); ?>" title="پیشخوان اصلی وردپرس، با منوی خود وردپرس"><?php echo aim_icon( 'wordpress-line' ); // phpcs:ignore ?>پیشخوان وردپرس</a>
                        <span class="spacer"></span>
                        <button class="btn btn-icon hide-mobile" data-fullscreen aria-label="تمام‌صفحه"><?php echo aim_icon( 'fullscreen-line' ); // phpcs:ignore ?></button>
                        <button class="btn btn-icon" data-theme-toggle aria-label="تغییر حالت شب و روز"><?php echo aim_icon( 'moon-line' ); // phpcs:ignore ?></button>
                        <?php // The bell counts every order awaiting review (unpaid + paid-undelivered + payment-confirmation) and opens the orders list. ?>
                        <?php $bell = function_exists( 'wc_orders_count' ) ? (int) wc_orders_count( 'pending' ) + (int) wc_orders_count( 'processing' ) + (int) wc_orders_count( 'on-hold' ) : 0; ?>
                        <a class="btn btn-icon" href="<?php echo esc_url( aim_panel_url( 'admin', 'orders' ) ); ?>" title="سفارش‌های منتظر بررسی" aria-label="سفارش‌های منتظر بررسی">
                                <?php echo aim_icon( 'notification-3-line' ); // phpcs:ignore ?>
                                <?php if ( $bell ) : ?><span class="badge badge-warning dot"><?php echo esc_html( aim_fa_digits( $bell ) ); ?></span><?php endif; ?>
                        </a>
                        <?php if ( current_user_can( 'manage_options' ) ) : ?>
                                <a class="btn btn-icon" href="<?php echo esc_url( aim_panel_url( 'admin', 'settings' ) ); ?>" aria-label="تنظیمات"><?php echo aim_icon( 'settings-3-line' ); // phpcs:ignore ?></a>
                        <?php endif; ?>
                        <div class="dropdown">
                                <button class="topbar-user" data-dropdown aria-haspopup="true">
                                        <span class="avatar avatar-sm"><?php echo esc_html( $initial ); ?></span>
                                        <div><b><?php echo esc_html( $user->display_name ); ?></b><small>مدیریت</small></div>
                                        <?php echo aim_icon( 'arrow-down-s-line' ); // phpcs:ignore ?>
                                </button>
                                <div class="dropdown-menu" role="menu">
                                        <a href="<?php echo esc_url( AIM_WP_Embed::url( 'profile.php', 'users' ) ); ?>"><?php echo aim_icon( 'user-settings-line' ); // phpcs:ignore ?>ویرایش پروفایل</a>
                                        <a href="<?php echo esc_url( aim_panel_url( 'admin', 'product-new' ) ); ?>"><?php echo aim_icon( 'add-line' ); // phpcs:ignore ?>محصول جدید</a>
                                        <a href="<?php echo esc_url( aim_panel_url( 'admin', 'wordpress' ) ); ?>"><?php echo aim_icon( 'wordpress-line' ); // phpcs:ignore ?>همهٔ بخش‌های وردپرس</a>
                                        <a href="<?php echo esc_url( admin_url() ); ?>"><?php echo aim_icon( 'wordpress-line' ); // phpcs:ignore ?>پیشخوان اصلی وردپرس</a>
                                        <hr>
                                        <a class="danger" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php echo aim_icon( 'logout-box-r-line' ); // phpcs:ignore ?>خروج از حساب</a>
                                </div>
                        </div>
                </header>

                <main class="content" id="content">
                        <hr class="wp-header-end">
                        <?php if ( $has_page ) : ?>
                                <?php include $template; ?>
                        <?php else : ?>
                                <?php aim_page_head( $item['label'] ); ?>
                                <div class="card soon">
                                        <?php if ( 'forbidden' === $item['key'] ) : ?>
                                                <div class="soon-ico"><?php echo aim_icon( 'lock-2-line' ); // phpcs:ignore ?></div>
                                                <h2>به این بخش دسترسی ندارید</h2>
                                                <p>این بخش فقط برای مدیر کل است.</p>
                                        <?php elseif ( '404' === $item['key'] ) : ?>
                                                <div class="soon-ico"><?php echo aim_icon( 'compass-3-line' ); // phpcs:ignore ?></div>
                                                <h2>این صفحه پیدا نشد</h2>
                                                <p>از منوی کنار صفحه بخش موردنظر را انتخاب کنید.</p>
                                        <?php else : ?>
                                                <div class="soon-ico"><?php echo aim_icon( 'hammer-line' ); // phpcs:ignore ?></div>
                                                <h2>«<?php echo esc_html( $item['label'] ); ?>» در فازهای بعدی ساخته می‌شود</h2>
                                                <p>این بخش در فهرست امکانات ثبت شده و جای آن در منو از الان مشخص است.</p>
                                        <?php endif; ?>
                                        <a class="btn btn-primary" href="<?php echo esc_url( aim_panel_url( 'admin' ) ); ?>"><?php echo aim_icon( 'dashboard-3-line' ); // phpcs:ignore ?>بازگشت به پیشخوان</a>
                                </div>
                        <?php endif; ?>
                </main>
        </div>
        <div class="toast-stack" data-toasts aria-live="assertive"></div>
</div>
