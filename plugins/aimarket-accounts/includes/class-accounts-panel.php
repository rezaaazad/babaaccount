<?php
defined( 'ABSPATH' ) || exit;

/**
 * Plugs the accounts module into the AIM Panel core through its hooks:
 * menu sections, page templates, dashboard chips and cards, order / product /
 * user columns, order-view delivery, settings tab and SMS templates.
 */
class AIM_Accounts_Panel {

        public static function init() {
                add_filter( 'aim_admin_menu', [ __CLASS__, 'admin_menu' ], 10, 2 );
                add_filter( 'aim_user_menu', [ __CLASS__, 'user_menu' ] );
                add_filter( 'aim_template_file', [ __CLASS__, 'template_file' ], 10, 2 );

                add_filter( 'aim_low_stock_url', fn() => aim_panel_url( 'admin', 'accounts' ) );
                add_filter( 'aim_admin_dashboard_pending', [ __CLASS__, 'dashboard_pending' ] );
                add_filter( 'aim_admin_dashboard_stats', [ __CLASS__, 'dashboard_stats' ] );
                add_filter( 'aim_user_dashboard_pending', [ __CLASS__, 'user_dashboard_pending' ], 10, 2 );
                add_filter( 'aim_admin_badges', [ __CLASS__, 'admin_badges' ] );

                add_filter( 'aim_admin_order_columns', [ __CLASS__, 'order_columns' ] );
                add_filter( 'aim_order_fulfillment', [ __CLASS__, 'order_fulfillment' ], 10, 2 );
                add_action( 'aim_admin_order_item', [ __CLASS__, 'order_item' ], 10, 3 );
                add_action( 'aim_admin_order_after_items', [ __CLASS__, 'order_after_items' ] );
                add_action( 'aim_admin_order_customer', [ __CLASS__, 'order_customer' ] );
                add_filter( 'aim_admin_product_columns', [ __CLASS__, 'product_columns' ] );
                add_filter( 'aim_admin_product_actions', [ __CLASS__, 'product_actions' ], 10, 2 );
                add_filter( 'aim_admin_user_columns', [ __CLASS__, 'user_columns' ] );
                add_filter( 'aim_restock_link', [ __CLASS__, 'restock_link' ], 10, 2 );

                add_filter( 'aim_settings_defaults', [ __CLASS__, 'settings_defaults' ] );
                add_filter( 'aim_settings_validate', [ __CLASS__, 'settings_validate' ], 10, 2 );
                add_action( 'aim_settings_tabs', [ __CLASS__, 'settings_tab' ] );
                add_action( 'aim_settings_sms_patterns', [ __CLASS__, 'settings_sms' ] );
                add_action( 'aim_settings_panels', [ __CLASS__, 'settings_panels' ], 10, 3 );
                add_filter( 'aim_sms_events', fn( $events ) => $events + [ 'delivery' => 'تحویل اکانت', 'reminder' => 'یادآوری تمدید' ] );

                add_action( 'admin_enqueue_scripts', [ __CLASS__, 'admin_assets' ] );
        }

        // ---- Menu and templates ----

        public static function admin_menu( array $menu, array $counts ) {
                AIM_Panel_Menu::add_group( $menu, [
                        'id'    => 'accounts',
                        'title' => 'اکانت‌ها',
                        'wc'    => true,
                        'items' => [
                                [ 'key' => 'accounts', 'label' => 'انبار اکانت', 'icon' => 'key-2-line', 'badge' => $counts['low_stock'], 'badge_tone' => 'warning' ],
                                [ 'key' => 'subscriptions', 'label' => 'اشتراک‌های فعال', 'icon' => 'calendar-check-line', 'badge' => $counts['expiring'], 'badge_tone' => 'warning' ],
                                [ 'key' => 'replacements', 'label' => 'درخواست‌های تعویض', 'icon' => 'refresh-line', 'soon' => true ],
                        ],
                ], 'sales' );
                return $menu;
        }

        public static function user_menu( array $items ) {
                array_splice( $items, 1, 0, [ [ 'key' => 'accounts', 'label' => 'اکانت‌های من', 'icon' => 'key-2-line' ] ] );
                return $items;
        }

        /** Pages the core does not have come from this plugin's templates folder. */
        public static function template_file( $file, $name ) {
                if ( file_exists( $file ) ) {
                        return $file;
                }
                $own = AIMA_DIR . 'templates/' . $name . '.php';
                return file_exists( $own ) ? $own : $file;
        }

        public static function admin_assets() {
                if ( AIM_WP_Admin::is_panel() ) {
                        wp_enqueue_script( 'aim-accounts-admin', AIMA_URL . 'assets/js/accounts-admin.js', [ 'aim-panel' ], AIMA_VERSION . '.' . filemtime( AIMA_DIR . 'assets/js/accounts-admin.js' ), true );
                }
        }

        // ---- Dashboards ----

        private static function count_assigned( $where, ...$args ) {
                global $wpdb;
                return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . AIM_Inventory::table() . " WHERE status = 'assigned' AND $where", ...$args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        /** Assigned accounts that expire within the next 7 days — the renewals waiting for the admin. */
        public static function expiring_count() {
                return self::count_assigned( 'expires_at BETWEEN %s AND %s', gmdate( 'Y-m-d H:i:s' ), gmdate( 'Y-m-d H:i:s', time() + 7 * DAY_IN_SECONDS ) );
        }

        /** Adds the module's own counters to the sidebar badges (orange pills). */
        public static function admin_badges( array $counts ) {
                $counts['expiring'] = self::expiring_count();
                return $counts;
        }

        public static function dashboard_pending( array $chips ) {
                $chips[]  = [ 'icon' => 'calendar-close-line', 'count' => self::expiring_count(), 'label' => 'اشتراک رو به پایان (۷ روز)', 'url' => aim_panel_url( 'admin', 'subscriptions', [ 'expiring' => 7 ] ) ];
                return $chips;
        }

        public static function dashboard_stats( array $cards ) {
                $c = AIM_Inventory::status_counts();
                array_unshift( $cards, [
                        'icon'  => 'key-2-line',
                        'title' => 'اکانت آماده تحویل',
                        'tone'  => 'info',
                        'value' => $c['available'],
                        'unit'  => 'اکانت',
                        'foot'  => 'تاکنون ' . aim_number( $c['assigned'] ) . ' اکانت تحویل شده',
                        'url'   => aim_panel_url( 'admin', 'accounts' ),
                ] );
                return $cards;
        }

        public static function user_dashboard_pending( array $chips, $uid ) {
                $now    = gmdate( 'Y-m-d H:i:s' );
                $active = self::count_assigned( 'user_id = %d AND (expires_at IS NULL OR expires_at > %s)', $uid, $now );
                $soon   = self::count_assigned( 'user_id = %d AND expires_at BETWEEN %s AND %s', $uid, $now, gmdate( 'Y-m-d H:i:s', time() + 7 * DAY_IN_SECONDS ) );
                $url    = aim_panel_url( 'user', 'accounts' );
                return array_merge( [
                        [ 'icon' => 'key-2-line', 'count' => $active, 'label' => 'اشتراک فعال', 'url' => $url ],
                        [ 'icon' => 'calendar-close-line', 'count' => $soon, 'label' => 'نزدیک به پایان', 'url' => $url, 'warn' => (bool) $soon ],
                ], $chips );
        }

        // ---- Orders ----

        /**
         * Delivery summary for the core order view (مسیر رسیدگی و تحویل) and the
         * orders list: how many accounts the order carries, how many are delivered
         * and which items still need a manual delivery.
         */
        public static function order_fulfillment( $summary, WC_Order $order ) {
                $qty       = 0;
                $delivered = 0;
                $manual    = [];
                $auto_owed = false;
                $items     = false;
                foreach ( $order->get_items() as $item_id => $item ) {
                        $st = AIM_Delivery::item_state( $item );
                        if ( ! $st['mode'] ) {
                                continue;
                        }
                        $items     = true;
                        $qty      += $st['qty'];
                        $delivered += $st['delivered'];
                        if ( $st['owed'] ) {
                                if ( 'manual' === $st['mode'] ) {
                                        $manual[] = (int) $item_id;
                                } else {
                                        $auto_owed = true;
                                }
                        }
                }
                return [
                        'items'           => $items,
                        'qty'             => $qty,
                        'delivered'       => $delivered,
                        'owed'            => max( 0, $qty - $delivered ),
                        'manual_item_ids' => $manual,
                        'auto_owed'       => $auto_owed,
                ];
        }

        public static function order_columns( array $columns ) {
                $columns[] = [
                        'label' => 'تحویل',
                        'cell'  => function ( WC_Order $o ) {
                                $owed = 0;
                                $acc  = 0;
                                foreach ( $o->get_items() as $it ) {
                                        $st = AIM_Delivery::item_state( $it );
                                        if ( $st['mode'] ) {
                                                $acc  += $st['qty'];
                                                $owed += $st['owed'];
                                        }
                                }
                                if ( ! $acc ) {
                                        return '<span class="text-3">—</span>';
                                }
                                if ( ! $owed ) {
                                        return '<span class="pill pill-success">تحویل‌شده</span>';
                                }
                                // on-hold = waiting for a bank transfer etc., i.e. not paid yet; nothing is owed for cancelled/failed/refunded orders.
                                if ( $o->is_paid() ) {
                                        return '<span class="pill pill-warning">' . esc_html( aim_fa_digits( $owed ) ) . ' تحویل‌نشده</span>';
                                }
                                return in_array( $o->get_status(), [ 'pending', 'on-hold' ], true ) ? '<span class="pill">منتظر پرداخت</span>' : '<span class="text-3">—</span>';
                        },
                ];
                return $columns;
        }

        /** Delivery modals are printed after the item list (a <li> cannot hold them). */
        private static $modals  = '';
        private static $any_owed = false;

        public static function order_item( WC_Order_Item_Product $item, $item_id, WC_Order $order ) {
                $st = AIM_Delivery::item_state( $item );
                if ( ! $st['mode'] ) {
                        return;
                }
                self::$any_owed = self::$any_owed || ( 'auto' === $st['mode'] && $st['owed'] );
                aim_template( 'admin/order-item-delivery', [
                        'item'     => $item,
                        'item_id'  => $item_id,
                        'order'    => $order,
                        'st'       => $st,
                        'accounts' => self::order_accounts( $order )[ (int) $item_id ] ?? [],
                ] );
                if ( $st['owed'] && $order->is_paid() ) {
                        ob_start();
                        aim_template( 'admin/order-deliver-modal', [ 'item' => $item, 'item_id' => $item_id, 'order' => $order ] );
                        self::$modals .= ob_get_clean();
                }
        }

        public static function order_after_items( WC_Order $order ) {
                echo self::$modals; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped while buffering.
                if ( self::$any_owed && $order->is_paid() ) {
                        ?>
                        <div class="card-body" style="border-top:1px solid var(--border)">
                                <div class="notice inline notice-warning" style="margin:0 0 12px"><?php echo aim_icon( 'alert-line' ); // phpcs:ignore ?><p>بخشی از این سفارش تحویل نشده است. اگر به انبار اکانت اضافه کرده‌اید، «تحویل مجدد از انبار» را بزنید.</p></div>
                                <button class="btn btn-primary" data-action="orders/<?php echo esc_attr( $order->get_id() ); ?>/retry"><?php echo aim_icon( 'refresh-line' ); // phpcs:ignore ?>تحویل مجدد از انبار</button>
                        </div>
                        <?php
                }
        }

        public static function order_customer( WC_Order $order ) {
                $activation = $order->get_meta( '_aim_activation_email' );
                if ( $activation ) {
                        echo '<dt>ایمیل فعال‌سازی</dt><dd class="ltr" style="text-align:right">' . esc_html( $activation ) . '</dd>';
                }
        }

        /** Delivered accounts of an order, grouped by order item id. */
        private static function order_accounts( WC_Order $order ) {
                static $cache = [];
                if ( ! isset( $cache[ $order->get_id() ] ) ) {
                        global $wpdb;
                        $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . AIM_Inventory::table() . ' WHERE order_id = %d ORDER BY id ASC', $order->get_id() ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                        $by   = [];
                        foreach ( $rows as $a ) {
                                $by[ (int) $a->order_item_id ][] = $a;
                        }
                        $cache[ $order->get_id() ] = $by;
                }
                return $cache[ $order->get_id() ];
        }

        // ---- Products, users, alerts ----

        public static function product_columns( array $columns ) {
                $columns[] = [
                        'label' => 'نوع تحویل',
                        'cell'  => function ( WC_Product $p ) {
                                $mode = AIM_Inventory::delivery_mode( $p->get_id() );
                                return 'auto' === $mode ? '<span class="pill pill-primary">خودکار</span>' : ( 'manual' === $mode ? '<span class="pill pill-info">دستی</span>' : '<span class="pill">عادی</span>' );
                        },
                ];
                return $columns;
        }

        public static function product_actions( $html, WC_Product $p ) {
                if ( ! AIM_Inventory::delivery_mode( $p->get_id() ) ) {
                        return $html;
                }
                return $html . '<a class="btn btn-light btn-xs" href="' . esc_url( aim_panel_url( 'admin', 'accounts' ) ) . '" title="انبار اکانت">' . aim_icon( 'key-2-line' ) . '</a>';
        }

        public static function user_columns( array $columns ) {
                $columns[] = [
                        'label' => 'اکانت فعال',
                        'cell'  => function ( WP_User $u ) {
                                $active = self::count_assigned( 'user_id = %d AND (expires_at IS NULL OR expires_at > %s)', $u->ID, gmdate( 'Y-m-d H:i:s' ) );
                                return $active ? '<span class="pill pill-success">' . esc_html( aim_fa_digits( $active ) ) . '</span>' : '<span class="text-3">—</span>';
                        },
                ];
                return $columns;
        }

        public static function restock_link( $html, WC_Product $p ) {
                if ( ! AIM_Inventory::is_account_product( $p->get_id() ) ) {
                        return $html;
                }
                return '<a class="btn btn-light btn-xs" href="' . esc_url( aim_panel_url( 'admin', 'accounts' ) ) . '">' . aim_icon( 'key-2-line' ) . 'شارژ انبار</a>';
        }

        // ---- Settings and SMS ----

        public static function settings_defaults( array $defaults ) {
                return $defaults + [
                        'sms_pattern_delivery' => '',
                        'sms_pattern_reminder' => '',
                        'delivery_on_status'   => 'processing', // processing | completed.
                        'auto_complete_order'  => 1,
                        'reminder_days'        => '7,3,1',
                ];
        }

        public static function settings_validate( $error, array $values ) {
                if ( $error ) {
                        return $error;
                }
                if ( isset( $values['delivery_on_status'] ) && ! in_array( $values['delivery_on_status'], [ 'processing', 'completed' ], true ) ) {
                        return 'زمان تحویل خودکار نامعتبر است.';
                }
                if ( isset( $values['reminder_days'] ) ) {
                        // The Persian keyboard types «،»; AIM_Reminders::days() accepts both commas.
                        $days = str_replace( '،', ',', aim_en_digits( (string) $values['reminder_days'] ) );
                        if ( ! preg_match( '/^\s*\d{1,3}(\s*,\s*\d{1,3})*\s*$/', $days ) ) {
                                return 'روزهای یادآوری را به‌صورت عدد و جداشده با ویرگول وارد کنید (مثلاً ۷,۳,۱).';
                        }
                        $days = array_map( 'intval', explode( ',', $days ) );
                        if ( min( $days ) < 1 || max( $days ) > 365 || count( array_unique( $days ) ) > 8 ) {
                                return 'روزهای یادآوری باید بین ۱ تا ۳۶۵ و حداکثر ۸ عدد باشند.';
                        }
                }
                return '';
        }

        public static function settings_tab() {
                echo '<button type="button" class="seg-tab" data-tab="delivery" role="tab">' . aim_icon( 'send-plane-line' ) . 'تحویل و تمدید</button>'; // phpcs:ignore WordPress.Security.EscapeOutput
        }

        public static function settings_sms( callable $field ) {
                $field( 'sms_pattern_delivery', 'کد الگوی «تحویل اکانت»', [ 'ltr' => true, 'help' => 'متغیرها: order، count' ] );
                $field( 'sms_pattern_reminder', 'کد الگوی «یادآوری تمدید»', [ 'ltr' => true, 'help' => 'متغیرها: service، days' ] );
        }

        public static function settings_panels( callable $field, callable $switch, array $s ) {
                aim_template( 'admin/settings-accounts', [ 'field' => $field, 'switch' => $switch, 's' => $s ] );
        }
}
