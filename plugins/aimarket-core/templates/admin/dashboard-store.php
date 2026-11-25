<?php
defined( 'ABSPATH' ) || exit;
global $wpdb;

$user = wp_get_current_user();
$tz   = wp_timezone();

// Paid orders of the last 30 days, grouped by local day.
$since  = ( new DateTime( 'today -29 days', $tz ) )->getTimestamp();
$orders = wc_get_orders( [
        'type'         => 'shop_order', // refunds share the statuses but are not sales
        'status'       => [ 'processing', 'completed' ],
        'date_created' => '>=' . $since,
        'limit'        => -1,
        'return'       => 'objects',
] );
$by_day = [];
for ( $i = 29; $i >= 0; $i-- ) {
        $by_day[ ( new DateTime( "today -$i days", $tz ) )->format( 'Y-m-d' ) ] = [ 'sum' => 0, 'count' => 0 ];
}
foreach ( $orders as $o ) {
        $d = $o->get_date_created();
        if ( ! $d ) {
                continue;
        }
        $k = $d->setTimezone( $tz )->format( 'Y-m-d' );
        if ( isset( $by_day[ $k ] ) ) {
                $by_day[ $k ]['sum']   += (float) $o->get_total();
                $by_day[ $k ]['count'] += 1;
        }
}
$today_key   = ( new DateTime( 'today', $tz ) )->format( 'Y-m-d' );
$sales_today = $by_day[ $today_key ]['sum'];
$sales_30    = array_sum( array_column( $by_day, 'sum' ) );
$orders_30   = array_sum( array_column( $by_day, 'count' ) );
$last7       = array_slice( $by_day, -7, 7, true );
$max7        = max( array_column( $last7, 'sum' ) ) ?: 1;

$processing = (int) wc_orders_count( 'processing' );
$on_hold    = (int) wc_orders_count( 'on-hold' );
$pending    = (int) wc_orders_count( 'pending' );
$attention  = $processing + $on_hold + $pending; // everything an admin must look at
$low_stock  = aim_low_stock_items();
$customers  = (int) ( count_users()['avail_roles']['customer'] ?? 0 );
// Where "low stock" links to; the accounts module points it at its inventory.
$stock_url  = apply_filters( 'aim_low_stock_url', aim_panel_url( 'admin', 'bulk-prices' ) );

/**
 * "Waiting for you" chips and the stat cards after the two sales cards.
 * Modules add their own with the filters below:
 *   chip: [ icon, count, label, url, warn ]
 *   card: [ icon, title, tone, value, unit, foot, url ]
 */
$chips = apply_filters( 'aim_admin_dashboard_pending', [
        [ 'icon' => 'shopping-bag-3-line', 'count' => $processing, 'label' => 'سفارش در حال پردازش', 'url' => aim_panel_url( 'admin', 'orders', [ 'status' => 'processing' ] ), 'warn' => (bool) $processing ],
        [ 'icon' => 'time-line', 'count' => $on_hold, 'label' => 'در انتظار تأیید پرداخت', 'url' => aim_panel_url( 'admin', 'orders', [ 'status' => 'on-hold' ] ), 'warn' => (bool) $on_hold ],
        [ 'icon' => 'wallet-3-line', 'count' => $pending, 'label' => 'در انتظار پرداخت', 'url' => aim_panel_url( 'admin', 'orders', [ 'status' => 'pending' ] ), 'warn' => (bool) $pending ],
        [ 'icon' => 'alert-line', 'count' => count( $low_stock ), 'label' => 'محصول با موجودی کم', 'url' => $stock_url, 'warn' => (bool) $low_stock ],
] );
$cards = apply_filters( 'aim_admin_dashboard_stats', [
        [ 'icon' => 'user-3-line', 'title' => 'مشتریان', 'tone' => 'warning', 'value' => $customers, 'unit' => 'نفر', 'foot' => 'از ابتدا تا امروز', 'url' => aim_panel_url( 'admin', 'users', [ 'role' => 'customer' ] ) ],
] );

$recent   = wc_get_orders( [ 'type' => 'shop_order', 'limit' => 6, 'orderby' => 'date', 'order' => 'DESC', 'status' => aim_panel_order_statuses() ] );
// Orders that need an action from the admin, newest first: unpaid, paid and
// waiting for delivery, or waiting for payment confirmation.
$todo      = $attention ? wc_get_orders( [ 'type' => 'shop_order', 'limit' => 6, 'orderby' => 'date', 'order' => 'DESC', 'status' => [ 'pending', 'processing', 'on-hold' ] ] ) : [];
$activity = AIM_Activity::query( [ 'per_page' => 7 ] )['rows'];
$weekdays = [ 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه', 'شنبه' ];

aim_page_head( 'پیشخوان' );
?>

<section class="card welcome mb-20">
        <div>
                <h2><?php echo esc_html( $user->display_name ); ?> عزیز، خوش آمدی!</h2>
                <p>موارد زیر منتظر بررسی و رسیدگی شما هستند.</p>
                <div class="pending-row">
                        <?php foreach ( $chips as $chip ) : ?>
                                <a class="pending<?php echo ! empty( $chip['warn'] ) ? ' is-warn' : ''; ?>" href="<?php echo esc_url( $chip['url'] ); ?>">
                                        <?php echo aim_icon( $chip['icon'] ); // phpcs:ignore ?>
                                        <div><b><?php echo esc_html( aim_fa_digits( (int) $chip['count'] ) ); ?></b><span><?php echo esc_html( $chip['label'] ); ?></span></div>
                                </a>
                        <?php endforeach; ?>
                </div>
        </div>
        <div class="welcome-art" aria-hidden="true"><?php echo aim_icon( 'store-2-line' ); // phpcs:ignore ?></div>
</section>

<?php if ( $attention ) : ?>
<section class="card mb-20">
        <div class="card-head">
                <h2>در انتظار رسیدگی شما</h2>
                <div class="actions"><span class="pill pill-warning"><?php echo esc_html( aim_fa_digits( $attention ) ); ?> مورد</span></div>
        </div>
        <div class="table-wrap">
                <table class="table">
                        <thead><tr><th>سفارش</th><th>مشتری</th><th>مبلغ</th><th>وضعیت</th><th>تاریخ ثبت</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ( $todo as $o ) : ?>
                                <tr>
                                        <td><a href="<?php echo esc_url( aim_panel_url( 'admin', 'orders', [ 'order' => $o->get_id() ] ) ); ?>"><b>#<?php echo esc_html( aim_fa_digits( $o->get_order_number() ) ); ?></b></a></td>
                                        <td><?php echo esc_html( trim( $o->get_formatted_billing_full_name() ) ?: aim_fa_digits( $o->get_billing_phone() ) ?: 'مهمان' ); ?></td>
                                        <td class="num"><?php echo aim_price( $o->get_total() ); // phpcs:ignore ?></td>
                                        <td><?php echo aim_order_status_pill( $o->get_status() ); // phpcs:ignore ?></td>
                                        <td class="num text-2"><?php echo esc_html( aim_jdate( $o->get_date_created() ? $o->get_date_created()->getTimestamp() : 0, true ) ); ?></td>
                                        <td><div class="row-actions"><a class="btn btn-primary btn-sm" href="<?php echo esc_url( aim_panel_url( 'admin', 'orders', [ 'order' => $o->get_id() ] ) ); ?>">بررسی و تحویل</a></div></td>
                                </tr>
                        <?php endforeach; ?>
                        </tbody>
                </table>
        </div>
        <?php if ( $attention > count( $todo ) ) : ?>
                <div class="card-body" style="border-top:1px solid var(--border)"><a class="btn btn-light btn-block" href="<?php echo esc_url( aim_panel_url( 'admin', 'orders' ) ); ?>">مشاهدهٔ همهٔ <?php echo esc_html( aim_fa_digits( $attention ) ); ?> سفارش منتظر بررسی</a></div>
        <?php endif; ?>
</section>
<?php endif; ?>

<div class="grid grid-4 mb-20">
        <div class="card stat tone-success">
                <div class="stat-head"><span class="stat-title tone-success"><?php echo aim_icon( 'money-dollar-circle-line' ); // phpcs:ignore ?>فروش امروز</span></div>
                <div class="stat-value"><?php echo aim_price( $sales_today ); // phpcs:ignore ?></div>
                <div class="stat-foot">۳۰ روز اخیر: <?php echo esc_html( aim_number( $sales_30 ) ); ?> تومان</div>
                <?php echo aim_sparkline( array_column( $last7, 'sum' ) ); // phpcs:ignore ?>
        </div>
        <div class="card stat">
                <div class="stat-head">
                        <span class="stat-title"><?php echo aim_icon( 'shopping-cart-2-line' ); // phpcs:ignore ?>سفارش‌های پرداخت‌شده</span>
                        <a class="stat-link" href="<?php echo esc_url( aim_panel_url( 'admin', 'orders' ) ); ?>">مشاهده<?php echo aim_icon( 'arrow-left-s-line' ); // phpcs:ignore ?></a>
                </div>
                <div class="stat-value"><?php echo esc_html( aim_number( $orders_30 ) ); ?> <small>سفارش</small></div>
                <div class="stat-foot">در ۳۰ روز اخیر</div>
                <?php echo aim_sparkline( array_column( $last7, 'count' ) ); // phpcs:ignore ?>
        </div>
        <?php foreach ( $cards as $card ) : ?>
                <div class="card stat">
                        <div class="stat-head">
                                <span class="stat-title tone-<?php echo esc_attr( $card['tone'] ?? 'info' ); ?>"><?php echo aim_icon( $card['icon'] ); // phpcs:ignore ?><?php echo esc_html( $card['title'] ); ?></span>
                                <?php if ( ! empty( $card['url'] ) ) : ?><a class="stat-link" href="<?php echo esc_url( $card['url'] ); ?>">مشاهده<?php echo aim_icon( 'arrow-left-s-line' ); // phpcs:ignore ?></a><?php endif; ?>
                        </div>
                        <div class="stat-value"><?php echo esc_html( aim_number( $card['value'] ) ); ?> <small><?php echo esc_html( $card['unit'] ?? '' ); ?></small></div>
                        <?php if ( ! empty( $card['foot'] ) ) : ?><div class="stat-foot"><?php echo esc_html( $card['foot'] ); ?></div><?php endif; ?>
                </div>
        <?php endforeach; ?>
</div>

<div class="grid grid-main mb-20">
        <section class="card">
                <div class="card-head"><h2>فروش ۷ روز اخیر</h2><span class="text-3" style="margin-right:auto;font-size:12px">مبالغ به تومان</span></div>
                <div class="card-body">
                        <div class="chart" role="img" aria-label="نمودار فروش هفتهٔ اخیر">
                                <?php foreach ( $last7 as $day => $v ) : ?>
                                        <?php $dt = new DateTime( $day, $tz ); ?>
                                        <div class="chart-col">
                                                <div class="chart-bar" style="height:<?php echo esc_attr( max( 2, round( $v['sum'] / $max7 * 100 ) ) ); ?>%" data-tip="<?php echo esc_attr( aim_number( $v['sum'] ) . ' تومان · ' . aim_fa_digits( $v['count'] ) . ' سفارش' ); ?>"></div>
                                                <span class="chart-label"><?php echo esc_html( $day === $today_key ? 'امروز' : $weekdays[ (int) $dt->format( 'w' ) ] ); ?></span>
                                        </div>
                                <?php endforeach; ?>
                        </div>
                </div>
        </section>

        <section class="card">
                <div class="card-head">
                        <h2>موجودی کم</h2>
                        <div class="actions"><a class="btn btn-light btn-sm" href="<?php echo esc_url( $stock_url ); ?>">مدیریت</a></div>
                </div>
                <?php if ( $low_stock ) : ?>
                        <ul class="list">
                                <?php foreach ( array_slice( $low_stock, 0, 6 ) as $s ) : ?>
                                        <li>
                                                <span class="dot-ico"><?php echo aim_icon( 'stack-line' ); // phpcs:ignore ?></span>
                                                <p><?php echo esc_html( $s['label'] ); ?></p>
                                                <span class="meta"><span class="pill <?php echo $s['available'] ? 'pill-warning' : 'pill-danger'; ?>"><?php echo esc_html( aim_fa_digits( $s['available'] ) ); ?> مانده</span></span>
                                        </li>
                                <?php endforeach; ?>
                        </ul>
                <?php else : ?>
                        <?php aim_empty( 'checkbox-circle-line', 'موجودی همهٔ محصولات کافی است' ); ?>
                <?php endif; ?>
        </section>
</div>

<div class="grid grid-main">
        <section class="card">
                <div class="card-head">
                        <h2>آخرین سفارش‌ها</h2>
                        <div class="actions"><a class="btn btn-light btn-sm" href="<?php echo esc_url( aim_panel_url( 'admin', 'orders' ) ); ?>">همهٔ سفارش‌ها</a></div>
                </div>
                <?php if ( $recent ) : ?>
                        <div class="table-wrap">
                                <table class="table">
                                        <thead><tr><th>سفارش</th><th>مشتری</th><th>مبلغ</th><th>وضعیت</th><th>تاریخ</th></tr></thead>
                                        <tbody>
                                        <?php foreach ( $recent as $o ) : ?>
                                                <tr>
                                                        <td><a href="<?php echo esc_url( aim_panel_url( 'admin', 'orders', [ 'order' => $o->get_id() ] ) ); ?>"><b>#<?php echo esc_html( aim_fa_digits( $o->get_order_number() ) ); ?></b></a></td>
                                                        <td><?php echo esc_html( trim( $o->get_formatted_billing_full_name() ) ?: aim_fa_digits( $o->get_billing_phone() ) ?: 'مهمان' ); ?></td>
                                                        <td class="num"><?php echo aim_price( $o->get_total() ); // phpcs:ignore ?></td>
                                                        <td><?php echo aim_order_status_pill( $o->get_status() ); // phpcs:ignore ?></td>
                                                        <td class="num text-2"><?php echo esc_html( aim_jdate( $o->get_date_created() ? $o->get_date_created()->getTimestamp() : 0, true ) ); ?></td>
                                                </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                </table>
                        </div>
                <?php else : ?>
                        <?php aim_empty( 'shopping-bag-3-line', 'هنوز سفارشی ثبت نشده', 'اولین سفارش‌ها اینجا نمایش داده می‌شوند.' ); ?>
                <?php endif; ?>
        </section>

        <section class="card">
                <div class="card-head">
                        <h2>فعالیت‌های اخیر</h2>
                        <div class="actions"><a class="btn btn-light btn-sm" href="<?php echo esc_url( aim_panel_url( 'admin', 'activity' ) ); ?>">همه</a></div>
                </div>
                <?php if ( $activity ) : ?>
                        <ul class="list">
                                <?php foreach ( $activity as $a ) : ?>
                                        <li>
                                                <span class="dot-ico"><?php echo aim_icon( str_starts_with( $a->action, 'order' ) ? 'shopping-bag-3-line' : ( str_starts_with( $a->action, 'account' ) ? 'key-2-line' : 'pulse-line' ) ); // phpcs:ignore ?></span>
                                                <p><?php echo esc_html( aim_fa_digits( $a->message ) ); ?></p>
                                                <span class="meta"><?php echo esc_html( aim_fa_digits( human_time_diff( strtotime( $a->created_at . ' UTC' ) ) ) ); ?> پیش</span>
                                        </li>
                                <?php endforeach; ?>
                        </ul>
                <?php else : ?>
                        <?php aim_empty( 'pulse-line', 'فعالیتی ثبت نشده' ); ?>
                <?php endif; ?>
        </section>
</div>
