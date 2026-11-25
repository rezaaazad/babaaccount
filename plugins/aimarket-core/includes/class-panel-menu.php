<?php
defined( 'ABSPATH' ) || exit;

/**
 * Sidebar structure for the admin and user panels. Sections not built yet
 * carry 'soon' => true and render a placeholder page.
 *
 * Modules add their sections with the filters 'aim_admin_menu' (groups of
 * items; see insert() and add_group()) and 'aim_user_menu', and their page
 * templates with 'aim_template_file' (aim_template_path()).
 */
class AIM_Panel_Menu {

        /** Keys of admin menu items switched off in settings → «منوی پنل». */
        const HIDDEN_OPTION = 'aim_menu_hidden';

        /** Items that can never be switched off, so the panel stays reachable. */
        const LOCKED = [ 'dashboard', 'settings', 'setup', 'wordpress' ];

        /**
         * Admin sidebar. An item renders templates/admin/{key}.php, or — with
         * 'wp' => 'admin path' — that native wp-admin screen inside the panel.
         * Items switched off in settings are left out unless $all is true.
         * Items and groups marked 'wc' => true need WooCommerce; without it they
         * are left out unless $with_wc is true (render() uses that to explain why).
         */
        public static function admin( $all = false, $with_wc = false ) {
                $counts = AIM_Panel_Menu::admin_badges();
                $hpos   = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
                $wc     = fn( $tab ) => 'admin.php?page=wc-settings&tab=' . $tab;
                $stats  = fn( $path ) => 'admin.php?page=wc-admin&path=' . rawurlencode( $path );
                $menu   = [
                        [
                                'id'    => 'overview',
                                'title' => 'نمای کلی',
                                'items' => [
                                        [ 'key' => 'dashboard', 'label' => 'پیشخوان', 'icon' => 'dashboard-3-line' ],
                                        [ 'key' => 'activity', 'label' => 'فعالیت‌های اخیر', 'icon' => 'pulse-line' ],
                                        [ 'key' => 'statistics', 'label' => 'گزارش‌ها و آمار', 'icon' => 'line-chart-line', 'wc' => true, 'children' => [
                                                [ 'key' => 'stats-overview', 'label' => 'نمای کلی', 'wp' => $stats( '/analytics/overview' ) ],
                                                [ 'key' => 'stats-revenue', 'label' => 'درآمد', 'wp' => $stats( '/analytics/revenue' ) ],
                                                [ 'key' => 'stats-orders', 'label' => 'سفارش‌ها', 'wp' => $stats( '/analytics/orders' ) ],
                                                [ 'key' => 'stats-products', 'label' => 'محصولات', 'wp' => $stats( '/analytics/products' ) ],
                                                [ 'key' => 'stats-coupons', 'label' => 'کدهای تخفیف', 'wp' => $stats( '/analytics/coupons' ) ],
                                                [ 'key' => 'stats-customers', 'label' => 'مشتریان', 'wp' => $stats( '/customers' ) ],
                                        ] ],
                                ],
                        ],
                        [
                                'id'    => 'sales',
                                'title' => 'سفارش‌ها و فروش',
                                'wc'    => true,
                                'items' => [
                                        [ 'key' => 'orders', 'label' => 'سفارشات', 'icon' => 'shopping-bag-3-line', 'badge' => $counts['orders'], 'badge_tone' => 'warning' ],
                                        [ 'key' => 'order-new', 'label' => 'ثبت سفارش دستی', 'icon' => 'add-line', 'wp' => $hpos ? 'admin.php?page=wc-orders&action=new' : 'post-new.php?post_type=shop_order' ],
                                        [ 'key' => 'coupons', 'label' => 'کدهای تخفیف', 'icon' => 'coupon-3-line', 'wp' => 'edit.php?post_type=shop_coupon' ],
                                        [ 'key' => 'transactions', 'label' => 'تراکنش‌ها', 'icon' => 'exchange-funds-line', 'soon' => true ],
                                ],
                        ],
                        [
                                'id'    => 'products',
                                'title' => 'محصولات',
                                'wc'    => true,
                                'items' => [
                                        [ 'key' => 'products', 'label' => 'همهٔ محصولات', 'icon' => 'shopping-basket-2-line' ],
                                        [ 'key' => 'product-new', 'label' => 'افزودن محصول', 'icon' => 'add-line', 'wp' => 'post-new.php?post_type=product' ],
                                        [ 'key' => 'bulk-prices', 'label' => 'مدیریت گروهی قیمت', 'icon' => 'equalizer-line' ],
                                        [ 'key' => 'stock-alerts', 'label' => 'اعلان موجودی و تخفیف', 'icon' => 'notification-3-line', 'badge' => $counts['alerts'], 'badge_tone' => 'warning' ],
                                        [ 'key' => 'categories', 'label' => 'دسته‌بندی‌ها', 'icon' => 'node-tree', 'wp' => 'edit-tags.php?taxonomy=product_cat&post_type=product' ],
                                        [ 'key' => 'tags', 'label' => 'برچسب‌ها', 'icon' => 'stack-line', 'wp' => 'edit-tags.php?taxonomy=product_tag&post_type=product' ],
                                        [ 'key' => 'attributes', 'label' => 'ویژگی‌ها (مدت، نوع)', 'icon' => 'apps-2-line', 'wp' => 'edit.php?post_type=product&page=product_attributes' ],
                                        [ 'key' => 'reviews', 'label' => 'نقد و بررسی‌ها', 'icon' => 'chat-3-line', 'wp' => 'edit.php?post_type=product&page=product-reviews' ],
                                        [ 'key' => 'questions', 'label' => 'پرسش و پاسخ‌ها', 'icon' => 'questionnaire-line', 'soon' => true ],
                                ],
                        ],
                        [
                                'id'    => 'customers',
                                'title' => 'پشتیبانی و مشتریان',
                                'items' => [
                                        [ 'key' => 'users', 'label' => 'کاربران', 'icon' => 'user-3-line' ],
                                        [ 'key' => 'user-new', 'label' => 'افزودن کاربر', 'icon' => 'user-add-line', 'wp' => 'user-new.php' ],
                                        [ 'key' => 'tickets', 'label' => 'تیکت‌ها', 'icon' => 'customer-service-2-line', 'soon' => true ],
                                        [ 'key' => 'customer-club', 'label' => 'باشگاه مشتریان', 'icon' => 'vip-crown-2-line', 'soon' => true ],
                                ],
                        ],
                        [
                                'id'    => 'content',
                                'title' => 'محتوا',
                                'items' => [
                                        [ 'key' => 'pages', 'label' => 'برگه‌ها', 'icon' => 'pages-line', 'wp' => 'edit.php?post_type=page' ],
                                        [ 'key' => 'articles', 'label' => 'مقالات', 'icon' => 'article-line', 'children' => [
                                                [ 'key' => 'articles-all', 'label' => 'همهٔ مقالات', 'wp' => 'edit.php' ],
                                                [ 'key' => 'articles-new', 'label' => 'مقالهٔ جدید', 'wp' => 'post-new.php' ],
                                                [ 'key' => 'articles-cats', 'label' => 'دسته‌های مقاله', 'wp' => 'edit-tags.php?taxonomy=category' ],
                                        ] ],
                                        [ 'key' => 'comments', 'label' => 'دیدگاه‌ها', 'icon' => 'message-2-line', 'wp' => 'edit-comments.php' ],
                                        [ 'key' => 'media', 'label' => 'کتابخانهٔ رسانه', 'icon' => 'image-2-line', 'wp' => 'upload.php' ],
                                        [ 'key' => 'menus', 'label' => 'منوها', 'icon' => 'menu-line', 'wp' => 'nav-menus.php' ],
                                ],
                        ],
                        [
                                'id'    => 'settings',
                                'title' => 'تنظیمات',
                                'items' => [
                                        [ 'key' => 'settings', 'label' => 'تنظیمات سایت', 'icon' => 'settings-3-line' ],
                                        [ 'key' => 'setup', 'label' => 'راه‌اندازی سریع', 'icon' => 'rocket-2-line' ],
                                        [ 'key' => 'theme-options', 'label' => 'تنظیمات قالب (متن صفحه‌ها)', 'icon' => 'layout-masonry-line' ],
                                        [ 'key' => 'contract', 'label' => 'قرارداد سفارش', 'icon' => 'file-list-3-line', 'wc' => true ],
                                        [ 'key' => 'woocommerce', 'label' => 'تنظیمات ووکامرس', 'icon' => 'store-2-line', 'wc' => true, 'children' => [
                                                [ 'key' => 'wc-general', 'label' => 'عمومی و واحد پول', 'wp' => $wc( 'general' ) ],
                                                [ 'key' => 'wc-products', 'label' => 'محصولات و موجودی', 'wp' => $wc( 'products' ) ],
                                                [ 'key' => 'wc-payments', 'label' => 'درگاه‌های پرداخت', 'wp' => $wc( 'checkout' ) ],
                                                [ 'key' => 'wc-accounts', 'label' => 'حساب‌ها و حریم خصوصی', 'wp' => $wc( 'account' ) ],
                                                [ 'key' => 'wc-emails', 'label' => 'ایمیل‌ها', 'wp' => $wc( 'email' ) ],
                                                [ 'key' => 'wc-advanced', 'label' => 'پیشرفته', 'wp' => $wc( 'advanced' ) ],
                                                [ 'key' => 'wc-status', 'label' => 'وضعیت سیستم', 'wp' => 'admin.php?page=wc-status' ],
                                        ] ],
                                        [ 'key' => 'sms-log', 'label' => 'گزارش پیامک‌ها', 'icon' => 'message-2-line' ],
                                        [ 'key' => 'wordpress', 'label' => 'همهٔ بخش‌های وردپرس', 'icon' => 'wordpress-line' ],
                                ],
                        ],
                ];
                if ( taxonomy_exists( 'product_brand' ) ) {
                        self::insert( $menu, 'products', [ [ 'key' => 'brands', 'label' => 'برندها', 'icon' => 'vip-crown-2-line', 'wp' => 'edit-tags.php?taxonomy=product_brand&post_type=product' ] ], 'tags' );
                }
                if ( did_action( 'elementor/loaded' ) ) {
                        self::insert( $menu, 'content', [ [ 'key' => 'templates', 'label' => 'قالب‌های المنتور', 'icon' => 'layout-masonry-line', 'wp' => 'edit.php?post_type=elementor_library&tabs_group=library' ] ] );
                }
                $menu = self::mark_wc( apply_filters( 'aim_admin_menu', $menu, $counts ) );
                if ( ! $with_wc && ! class_exists( 'WooCommerce' ) ) {
                        $menu = self::without( $menu, fn( $it ) => empty( $it['wc'] ) );
                }
                return $all ? $menu : self::without_hidden( $menu );
        }

        /** A group's 'wc' flag applies to all its items and their children. */
        private static function mark_wc( array $menu ) {
                foreach ( $menu as &$group ) {
                        foreach ( $group['items'] as &$item ) {
                                $item['wc'] = ! empty( $group['wc'] ) || ! empty( $item['wc'] );
                                foreach ( $item['children'] ?? [] as &$child ) {
                                        $child['wc'] = $item['wc'] || ! empty( $child['wc'] );
                                }
                        }
                }
                return $menu;
        }

        /** Add items to a group (by id), after the item $after or at the end. */
        public static function insert( array &$menu, $group_id, array $items, $after = '' ) {
                foreach ( $menu as &$group ) {
                        if ( ( $group['id'] ?? '' ) !== $group_id ) {
                                continue;
                        }
                        $at = $after ? array_search( $after, array_column( $group['items'], 'key' ), true ) : false;
                        array_splice( $group['items'], false === $at ? count( $group['items'] ) : $at + 1, 0, $items );
                        return;
                }
        }

        /** Add a whole group after the group $after (by id), or at the end. */
        public static function add_group( array &$menu, array $group, $after = '' ) {
                $at = $after ? array_search( $after, array_column( $menu, 'id' ), true ) : false;
                array_splice( $menu, false === $at ? count( $menu ) : $at + 1, 0, [ $group ] );
        }

        public static function hidden() {
                return array_values( array_diff( (array) get_option( self::HIDDEN_OPTION, [] ), self::LOCKED ) );
        }

        /** Drop switched-off items. */
        private static function without_hidden( array $menu ) {
                $hidden = self::hidden();
                return $hidden ? self::without( $menu, fn( $it ) => ! in_array( $it['key'], $hidden, true ) ) : $menu;
        }

        /** Keep items for which $keep is true; a parent left without children and an empty group go too. */
        private static function without( array $menu, callable $keep ) {
                foreach ( $menu as $g => $group ) {
                        $items = [];
                        foreach ( array_filter( $group['items'], $keep ) as $item ) {
                                if ( isset( $item['children'] ) ) {
                                        $item['children'] = array_values( array_filter( $item['children'], $keep ) );
                                        if ( ! $item['children'] ) {
                                                continue;
                                        }
                                }
                                $items[] = $item;
                        }
                        $menu[ $g ]['items'] = $items;
                }
                return array_values( array_filter( $menu, fn( $group ) => $group['items'] ) );
        }

        /** POST /settings/menu { visible: [ key… ] } — every other item is switched off. */
        public static function rest_save( WP_REST_Request $req ) {
                $visible = array_map( 'sanitize_key', (array) $req->get_param( 'visible' ) );
                $keys    = [];
                foreach ( self::admin( true, true ) as $group ) {
                        foreach ( $group['items'] as $item ) {
                                $keys[] = $item['key'];
                                $keys   = array_merge( $keys, array_column( $item['children'] ?? [], 'key' ) );
                        }
                }
                $hidden = array_values( array_diff( $keys, $visible, self::LOCKED ) );
                update_option( self::HIDDEN_OPTION, $hidden, true );
                AIM_Activity::log( 'menu_saved', 'تغییر بخش‌های منوی پنل', 'settings' );
                return rest_ensure_response( [ 'ok' => true, 'message' => $hidden ? sprintf( 'منو ذخیره شد؛ %s بخش خاموش است.', aim_fa_digits( count( $hidden ) ) ) : 'منو ذخیره شد؛ همهٔ بخش‌ها روشن‌اند.' ] );
        }

        public static function user() {
                $items = apply_filters( 'aim_user_menu', [
                        [ 'key' => 'dashboard', 'label' => 'پیشخوان', 'icon' => 'dashboard-3-line' ],
                        [ 'key' => 'orders', 'label' => 'سفارشات من', 'icon' => 'shopping-bag-3-line', 'wc' => true ],
                        [ 'key' => 'contracts', 'label' => 'قراردادها', 'icon' => 'file-list-3-line', 'wc' => true ],
                        [ 'key' => 'profile', 'label' => 'ویرایش پروفایل', 'icon' => 'user-settings-line' ],
                        [ 'key' => 'wallet', 'label' => 'کیف پول', 'icon' => 'wallet-3-line', 'soon' => true ],
                        [ 'key' => 'tickets', 'label' => 'تیکت‌ها', 'icon' => 'customer-service-2-line', 'soon' => true ],
                        [ 'key' => 'club', 'label' => 'باشگاه مشتریان', 'icon' => 'vip-crown-2-line', 'soon' => true ],
                        [ 'key' => 'favorites', 'label' => 'علاقه‌مندی‌ها', 'icon' => 'heart-3-line', 'soon' => true, 'wc' => true ],
                ] );
                return class_exists( 'WooCommerce' ) ? $items : array_values( array_filter( $items, fn( $it ) => empty( $it['wc'] ) ) );
        }

        /** Find a section by key, searching nested children too. */
        public static function find( array $menu, $key, $nested = true ) {
                foreach ( $menu as $entry ) {
                        $items = $nested ? ( $entry['items'] ?? [] ) : [ $entry ];
                        foreach ( $items as $item ) {
                                if ( $item['key'] === $key ) {
                                        return $item;
                                }
                                foreach ( $item['children'] ?? [] as $child ) {
                                        if ( $child['key'] === $key ) {
                                                return $child;
                                        }
                                }
                        }
                }
                return null;
        }

        public static function admin_badges() {
                static $cache = null;
                if ( null !== $cache ) {
                        return $cache;
                }
                // Every status an admin must look at: unpaid (pending), paid and waiting
                // for delivery (processing), waiting for payment confirmation (on-hold).
                $orders = 0;
                if ( function_exists( 'wc_orders_count' ) ) {
                        $orders = (int) wc_orders_count( 'pending' ) + (int) wc_orders_count( 'processing' ) + (int) wc_orders_count( 'on-hold' );
                }
                $cache = apply_filters( 'aim_admin_badges', [
                        'alerts'    => class_exists( 'AIM_Stock_Alerts' ) ? AIM_Stock_Alerts::pending_count() : 0,
                        'orders'    => $orders,
                        'low_stock' => count( aim_low_stock_items() ),
                ] );
                return $cache;
        }
}
