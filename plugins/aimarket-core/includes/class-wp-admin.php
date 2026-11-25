<?php
defined( 'ABSPATH' ) || exit;

/**
 * The store admin panel, hosted in wp-admin as one full-screen page:
 *   wp-admin/admin.php?page=aimarket&section={key}
 * WordPress gets a single menu entry; the panel brings its own sidebar,
 * topbar and sections (AIM_Panel_Menu::admin()), and WordPress's own menu,
 * toolbar and footer are hidden on this page.
 */
class AIM_WP_Admin {

        const SLUG = 'aimarket';

        /** Sections with a stricter capability than manage_woocommerce. */
        const CAPS = [
                'settings'      => 'manage_options',
                'setup'         => 'manage_options',
                'theme-options' => 'manage_options',
                'contract'      => 'manage_options',
                'sms-log'       => 'manage_options', // shows login codes in test mode
                'users'         => 'list_users',
                'user-new'      => 'create_users',
        ];

        public static function init() {
                add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
                add_action( 'admin_enqueue_scripts', [ __CLASS__, 'assets' ] );
                add_action( 'admin_enqueue_scripts', [ __CLASS__, 'menu_assets' ], 5 ); // styled menu entry on all wp-admin screens
                add_filter( 'admin_body_class', [ __CLASS__, 'body_class' ] );
                add_action( 'admin_head', [ __CLASS__, 'head' ], 1 );
                // The icon is a baked PNG (pixels cannot fail like the SVG gradient
                // did) and must survive optimiser plugins that drop stylesheet CSS:
                // a direct <style> in <head> plus a JS fallback that paints last.
                add_action( 'admin_head', [ __CLASS__, 'menu_icon_head' ], 99 );
                add_action( 'admin_print_footer_scripts', [ __CLASS__, 'menu_icon_fallback' ], 99 );
        }

        public static function url( $section = '', $args = [] ) {
                $query = [ 'page' => self::SLUG ];
                if ( $section && 'dashboard' !== $section ) {
                        $query['section'] = $section;
                }
                return add_query_arg( array_merge( $query, $args ), admin_url( 'admin.php' ) );
        }

        public static function is_panel() {
                return isset( $_GET['page'] ) && self::SLUG === $_GET['page']; // phpcs:ignore WordPress.Security.NonceVerification
        }

        /** Current section key ('dashboard' by default). */
        public static function current() {
                $s = isset( $_GET['section'] ) ? sanitize_key( $_GET['section'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
                return $s ?: 'dashboard';
        }

        public static function menu() {
                $badges = AIM_Panel_Menu::admin_badges();
                $count  = $badges['orders'] + $badges['low_stock'];
                add_menu_page(
                        'هوش‌مارکت',
                        'هوش‌مارکت' . ( $count ? ' <span class="awaiting-mod"><span>' . esc_html( aim_fa_digits( $count ) ) . '</span></span>' : '' ),
                        'manage_woocommerce',
                        self::SLUG,
                        [ __CLASS__, 'render' ],
                        self::menu_icon(),
                        3
                );
        }

        /**
         * The menu tile as real PNG pixels (assets/img/menu-icon.png): the
         * gradient tile with the white zap baked into the file. An earlier
         * version drew this with an inline SVG gradient (fill="url(#g)"); on
         * the user's site the SVG loaded (its rounded corners were visible)
         * but the gradient paint reference broke and the browser painted the
         * tile WHITE — an empty blob, “the logo looks like nothing”. A PNG has
         * no paint-server references: pixels render identically in every
         * browser. WordPress prints it as a plain <img> (data URI, no asset
         * request); a solid-fill SVG below covers the impossible case of a
         * missing bundled file — without any url() reference left to break.
         */
        public static function menu_icon() {
                static $uri = null;
                if ( null !== $uri ) {
                        return $uri;
                }
                $file = AIM_DIR . 'assets/img/menu-icon.png';
                if ( is_readable( $file ) ) {
                        $uri = 'data:image/png;base64,' . base64_encode( (string) file_get_contents( $file ) );
                        return $uri;
                }
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none">'
                        . '<rect x="2" y="2" width="20" height="20" rx="5.5" fill="#504EEC"/>'
                        . '<path d="M9 8L5 12C4.63098 12.36902 4.63098 13 5 13H9C9.55228 13 10 13.44772 10 14V20C10 20.36902 10.63098 20.36902 11 20L19 12C19.36902 11.63098 19.36902 11 19 11H15C14.44772 11 14 10.55228 14 10V4C14 3.63098 13.36902 3.63098 13 4L9 8Z" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>'
                        . '</svg>';
                $uri = 'data:image/svg+xml;base64,' . base64_encode( $svg );
                return $uri;
        }

        /** The panel's menu entry must pop on every wp-admin screen, not only inside the panel. */
        public static function menu_assets() {
                wp_enqueue_style( 'aim-menu', AIM_URL . 'assets/css/menu.css', [], AIM_VERSION . '.' . filemtime( AIM_DIR . 'assets/css/menu.css' ) );
        }

        /** CSS selector list for the menu entry's icon container. */
        private static function icon_selectors() {
                $s = '#adminmenu #toplevel_page_' . self::SLUG;
                return [ $s . ' .wp-menu-image', $s . ' .wp-menu-image.svg' ];
        }

        /**
         * Paint the icon straight in <head>, not through a stylesheet: optimiser and
         * security plugins sometimes drop or defer enqueued/inline stylesheets, which
         * made the tile disappear on some sites. A plain <style> tag cannot be dropped.
         * With a PNG data URI WordPress renders the icon as a plain <img> (old builds
         * and menu-editor plugins may still use the .svg background div — both shapes
         * are covered; the background is scoped to .svg only, so the normal <img>
         * path never gets a second, offset copy of the tile painted behind it).
         */
        public static function menu_icon_head() {
                $uri = self::menu_icon();
                [ $a, $b ] = self::icon_selectors();
                printf(
                        '<style id="aim-menu-icon">%2$s{background-image:url("%3$s")!important;background-repeat:no-repeat!important;background-position:50%% 50%%!important;background-size:20px 20px!important}'
                        . '%1$s img,%2$s img{width:20px!important;height:20px!important;opacity:1!important}</style>',
                        esc_attr( $a ),
                        esc_attr( $b ),
                        $uri // phpcs:ignore WordPress.Security.EscapeOutput -- base64 data URI built by menu_icon().
                );
        }

        /**
         * Last-resort check after the admin menu is in the DOM: if nothing painted the
         * tile (stylesheet dropped, old WordPress that esc_url()s the icon away, or a
         * menu-editor plugin that rebuilt the markup), paint it here — CSS by JS.
         */
        public static function menu_icon_fallback() {
                $uri = wp_json_encode( self::menu_icon() );
                [ $a ] = self::icon_selectors(); // $a already ends in .wp-menu-image — do not append it twice.
                echo '<script>try{(function(){var el=document.querySelector(' . wp_json_encode( $a ) . ');if(!el)return;var uri=' . $uri . ';' // phpcs:ignore WordPress.Security.EscapeOutput -- static strings.
                        . 'var bg=(getComputedStyle(el).backgroundImage||\'\');'
                        . 'if(bg.indexOf(\'data:image\')!==-1)return;'
                        . 'var img=el.querySelector(\'img\');'
                        . 'if(img){if(!img.getAttribute(\'src\'))img.setAttribute(\'src\',uri);img.style.width=\'20px\';img.style.height=\'20px\';img.style.opacity=\'1\';return;}'
                        . 'el.style.backgroundImage=\'url("\'+uri+\'")\';'
                        . 'el.style.backgroundRepeat=\'no-repeat\';el.style.backgroundPosition=\'50% 50%\';el.style.backgroundSize=\'20px 20px\';})();}catch(e){}</script>';
        }

        public static function body_class( $classes ) {
                return self::is_panel() ? $classes . ' aim-admin-page' : $classes;
        }

        /** Apply the saved colour mode before first paint. */
        public static function head() {
                if ( self::is_panel() ) {
                        echo aim_color_mode_script( 'aim-theme-admin' ); // phpcs:ignore
                }
        }

        public static function assets() {
                if ( ! self::is_panel() ) {
                        return;
                }
                $fonts = apply_filters( 'aim_font_css', 'https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css' );
                $icons = apply_filters( 'aim_icon_css', AIM_URL . 'assets/css/icons.css' );
                wp_enqueue_style( 'aim-font', $fonts, [], null );
                wp_enqueue_style( 'aim-icons', $icons, [], AIM_VERSION . '.' . filemtime( AIM_DIR . 'assets/css/icons.css' ) );
                wp_enqueue_style( 'aim-panel-wp', AIM_URL . 'assets/css/panel-wp.css', [], AIM_VERSION . '.' . filemtime( AIM_DIR . 'assets/css/panel-wp.css' ) );
                $deps = [];
                if ( 'settings' === self::current() && current_user_can( 'unfiltered_html' ) ) {
                        // CodeMirror (bundled with WordPress) for the custom-code boxes.
                        $editors = [];
                        foreach ( [ 'css' => 'text/css', 'js' => 'javascript', 'head' => 'text/html', 'footer' => 'text/html' ] as $k => $type ) {
                                $editors[ $k ] = wp_enqueue_code_editor( [ 'type' => $type, 'codemirror' => [ 'direction' => 'ltr', 'lineWrapping' => true ] ] );
                        }
                        wp_add_inline_script( 'code-editor', 'window.AIM_CODE_EDITORS = ' . wp_json_encode( $editors ) . ';' );
                        $deps[] = 'code-editor';
                }
                if ( in_array( self::current(), [ 'theme-options', 'contract' ], true ) ) {
                        wp_enqueue_media(); // image fields (logos, trust seals)
                }
                wp_enqueue_script( 'aim-panel', AIM_URL . 'assets/js/panel.js', $deps, AIM_VERSION . '.' . filemtime( AIM_DIR . 'assets/js/panel.js' ), true );
                if ( 'setup' === self::current() ) {
                        wp_enqueue_style( 'aim-setup', AIM_URL . 'assets/css/setup.css', [ 'aim-panel-wp' ], AIM_VERSION . '.' . filemtime( AIM_DIR . 'assets/css/setup.css' ) );
                        wp_enqueue_script( 'aim-setup', AIM_URL . 'assets/js/setup.js', [ 'aim-panel' ], AIM_VERSION . '.' . filemtime( AIM_DIR . 'assets/js/setup.js' ), true );
                }
                wp_add_inline_script( 'aim-panel', 'window.AIM=' . wp_json_encode( [
                        'rest'     => esc_url_raw( rest_url( 'aimarket/v1/' ) ),
                        'nonce'    => wp_create_nonce( 'wp_rest' ),
                        'home'     => home_url( '/' ),
                        'bundle'   => 'wp-admin',
                        'shop'     => AIM_Settings::get( 'shop_title' ),
                        'slug'     => self::SLUG,
                        'fullLoad' => [ 'settings', 'setup', 'theme-options', 'contract' ], // Sections with their own scripts skip AJAX navigation.
                ] ) . ';', 'before' );
        }

        /** Whether the current user may open a section. */
        public static function can( $section ) {
                return current_user_can( self::CAPS[ $section ] ?? 'manage_woocommerce' );
        }

        /** Leave out the sections the current user may not open. */
        private static function allowed( array $menu ) {
                foreach ( $menu as $g => $group ) {
                        $items = [];
                        foreach ( $group['items'] as $item ) {
                                if ( ! self::can( $item['key'] ) ) {
                                        continue;
                                }
                                if ( isset( $item['children'] ) ) {
                                        $item['children'] = array_values( array_filter( $item['children'], fn( $c ) => self::can( $c['key'] ) ) );
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

        public static function render() {
                $section = self::current();
                $menu    = self::allowed( AIM_Panel_Menu::admin() );
                // Switched-off items only leave the sidebar; their pages still open from links.
                $item    = AIM_Panel_Menu::find( AIM_Panel_Menu::admin( true, true ), $section, true );
                if ( ! $item ) {
                        $item = [ 'key' => '404', 'label' => 'صفحه پیدا نشد', 'soon' => true ];
                }
                // A parent item ("گزارش‌ها و آمار") has no page of its own: open its first child.
                if ( ! empty( $item['children'] ) && empty( $item['wp'] ) && ! file_exists( aim_template_path( 'admin/' . $item['key'] ) ) ) {
                        $item    = $item['children'][0];
                        $section = $item['key'];
                }
                if ( ! self::can( $section ) ) {
                        $item = [ 'key' => 'forbidden', 'label' => 'دسترسی ندارید', 'soon' => true ];
                }
                // Sections that need WooCommerce explain how to install it while it is missing.
                if ( ! empty( $item['wc'] ) && ! class_exists( 'WooCommerce' ) && ! AIM_WP_Embed::request_path() ) {
                        $item = [ 'key' => 'needs-woocommerce', 'label' => $item['label'] ];
                }
                // A native wp-admin screen: the item's own ('wp' => path) or any page opened from a section (?path=).
                $embed = 'forbidden' === $item['key'] ? '' : ( AIM_WP_Embed::request_path() ?: AIM_WP_Embed::safe_path( $item['wp'] ?? '' ) );
                aim_template( 'panel/layout-admin', [
                        'menu'       => $menu,
                        'section'    => $section,
                        'item'       => $item,
                        'embed_path' => $embed,
                ] );
        }

        /** Hidden fields that keep GET filter forms on the same panel section. */
        public static function form_fields() {
                if ( self::is_panel() ) {
                        printf( '<input type="hidden" name="page" value="%s">', esc_attr( self::SLUG ) );
                        if ( 'dashboard' !== self::current() ) {
                                printf( '<input type="hidden" name="section" value="%s">', esc_attr( self::current() ) );
                        }
                }
        }
}
