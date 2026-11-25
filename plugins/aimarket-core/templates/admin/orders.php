<?php
defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.

$order_id = absint( $_GET['order'] ?? 0 );
if ( $order_id ) {
        $order = wc_get_order( $order_id );
        // Refunds share the order ID space but are not orders (no customer/billing data).
        aim_template( 'admin/order-view', [ 'order' => $order instanceof WC_Order ? $order : false ] );
        return;
}

$statuses = aim_panel_order_statuses(); // "wc-" prefixed.
$status   = sanitize_key( $_GET['status'] ?? '' );
$status   = in_array( 'wc-' . $status, $statuses, true ) ? $status : '';
$search   = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
$page     = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page = 20;

$args = [
        'type'     => 'shop_order', // Without this refunds are listed too (and break the row rendering).
        'limit'    => $per_page,
        'paged'    => $page,
        'paginate' => true,
        'orderby'  => 'date',
        'order'    => 'DESC',
        'status'   => $status ? [ 'wc-' . $status ] : $statuses,
];
$result = null;
if ( '' !== $search ) {
        $q = trim( aim_en_digits( ltrim( $search, '#' ) ) );
        if ( aim_normalize_phone( $q ) ) {
                $args['billing_phone'] = aim_normalize_phone( $q );
        } else {
                if ( ctype_digit( $q ) ) {
                        // Search by order number: look the order up directly (wc_get_orders ignores "include" on some stores).
                        $found = wc_get_order( (int) $q );
                        if ( $found instanceof WC_Order && in_array( 'wc-' . $found->get_status(), $args['status'], true ) ) {
                                $result = (object) [ 'orders' => [ $found ], 'total' => 1 ];
                        }
                }
                if ( ! $result ) {
                        // Name, email or part of the phone. HPOS supports "s"; the posts store needs the IDs from WooCommerce's own order search.
                        $hpos = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
                        if ( $hpos ) {
                                $args['s'] = $q;
                        } else {
                                $args['post__in'] = wc_order_search( $q ) ?: [ 0 ];
                        }
                }
        }
}
if ( ! $result ) {
        $result = wc_get_orders( $args );
}

$tabs = [ '' => [ 'label' => 'همه' ] ];
foreach ( [ 'processing' => 'در حال پردازش', 'on-hold' => 'در انتظار بررسی', 'pending' => 'در انتظار پرداخت', 'completed' => 'تکمیل‌شده', 'cancelled' => 'لغوشده' ] as $k => $label ) {
        $tabs[ $k ] = [ 'label' => $label, 'count' => (int) wc_orders_count( $k ) ];
}

aim_page_head( 'سفارشات', '<span class="text-3 hide-mobile" data-refresh-note>به‌روزرسانی خودکار هر دقیقه</span><button type="button" class="btn btn-light" data-reload-page>' . aim_icon( 'refresh-line' ) . 'به‌روزرسانی</button>' );
?>
<section class="card" data-autorefresh="60">
        <?php aim_status_tabs( $tabs, $status ); ?>
        <form class="toolbar" method="get">
                <?php AIM_WP_Admin::form_fields(); ?>
                <?php if ( $status ) : ?><input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>"><?php endif; ?>
                <label class="search">
                        <?php echo aim_icon( 'search-line' ); // phpcs:ignore ?>
                        <span class="visually-hidden">جستجو</span>
                        <input class="field" type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="شمارهٔ سفارش، نام یا موبایل مشتری…">
                </label>
                <button class="btn btn-light" type="submit">جستجو</button>
        </form>

        <?php
        /** Extra columns from modules: [ 'label' => header, 'cell' => fn( WC_Order ) => HTML ]. */
        $columns = apply_filters( 'aim_admin_order_columns', [] );
        ?>
        <?php if ( $result->orders ) : ?>
                <div class="table-wrap">
                        <table class="table">
                                <thead>
                                        <tr><th>شمارهٔ سفارش</th><th>مشتری</th><th>اقلام</th><?php foreach ( $columns as $col ) : ?><th><?php echo esc_html( $col['label'] ); ?></th><?php endforeach; ?><th>مبلغ</th><th>وضعیت</th><th>تاریخ ثبت</th><th></th></tr>
                                </thead>
                                <tbody>
                                <?php foreach ( $result->orders as $o ) : ?>
                                        <?php
                                        $items = $o->get_items();
                                        $first = $items ? reset( $items ) : null;
                                        $url   = aim_panel_url( 'admin', 'orders', [ 'order' => $o->get_id() ] );
                                        // Paid but not fully delivered → the row action invites the admin to deliver.
                                        $ful    = apply_filters( 'aim_order_fulfillment', null, $o );
                                        $undone = is_array( $ful ) && $ful['items'] && $ful['owed'] && $o->is_paid();
                                        ?>
                                        <tr>
                                                <td><a href="<?php echo esc_url( $url ); ?>"><b>#<?php echo esc_html( aim_fa_digits( $o->get_order_number() ) ); ?></b></a></td>
                                                <td>
                                                        <div class="cell-user">
                                                                <div>
                                                                        <b><?php echo esc_html( trim( $o->get_formatted_billing_full_name() ) ?: 'بدون نام' ); ?></b>
                                                                        <small class="ltr"><?php echo esc_html( aim_fa_digits( $o->get_billing_phone() ) ); ?></small>
                                                                </div>
                                                        </div>
                                                </td>
                                                <td>
                                                        <?php echo $first ? esc_html( $first->get_name() ) : '—'; ?>
                                                        <?php if ( count( $items ) > 1 ) : ?><small> و <?php echo esc_html( aim_fa_digits( count( $items ) - 1 ) ); ?> مورد دیگر</small><?php endif; ?>
                                                </td>
                                                <?php foreach ( $columns as $col ) : ?><td><?php echo $col['cell']( $o ); // phpcs:ignore WordPress.Security.EscapeOutput -- module renders escaped HTML. ?></td><?php endforeach; ?>
                                                <td class="num"><?php echo aim_price( $o->get_total() ); // phpcs:ignore ?></td>
                                                <td><?php echo aim_order_status_pill( $o->get_status() ); // phpcs:ignore ?></td>
                                                <td class="num text-2"><?php echo esc_html( aim_jdate( $o->get_date_created() ? $o->get_date_created()->getTimestamp() : 0, true ) ); ?></td>
                                                <td><div class="row-actions"><a class="btn <?php echo $undone ? 'btn-primary' : 'btn-light'; ?> btn-sm" href="<?php echo esc_url( $url ); ?>"><?php echo $undone ? 'تحویل سفارش' : 'بررسی'; ?></a></div></td>
                                        </tr>
                                <?php endforeach; ?>
                                </tbody>
                        </table>
                </div>
                <?php aim_pagination( $result->total, $per_page, $page ); ?>
        <?php else : ?>
                <?php aim_empty( 'shopping-bag-3-line', '' !== $search ? 'سفارشی با این مشخصات پیدا نشد' : 'سفارشی در این وضعیت نیست', '' !== $search ? 'عبارت جستجو را تغییر دهید.' : '' ); ?>
        <?php endif; ?>
</section>
