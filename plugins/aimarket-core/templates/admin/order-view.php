<?php
/** @var WC_Order|false $order */
defined( 'ABSPATH' ) || exit;

if ( ! $order ) {
        aim_page_head( 'سفارش' );
        echo '<div class="card">';
        aim_empty( 'file-search-line', 'سفارش پیدا نشد', '', '<a class="btn btn-primary" href="' . esc_url( aim_panel_url( 'admin', 'orders' ) ) . '">بازگشت به سفارشات</a>' );
        echo '</div>';
        return;
}

$created = $order->get_date_created();
$paid    = $order->get_date_paid();

/**
 * Delivery summary from the accounts module (null when it is inactive):
 * items(bool) qty/delivered/owed(int) manual_item_ids(int[]) auto_owed(bool).
 */
$ful     = apply_filters( 'aim_order_fulfillment', null, $order );
$status  = $order->get_status();
$is_paid = $order->is_paid();
$guest   = ! $order->get_customer_id();
$waiting = in_array( $status, [ 'pending', 'on-hold' ], true );
$done_st = in_array( $status, [ 'completed', 'cancelled', 'refunded', 'failed' ], true );
// The status that starts the automatic delivery (the accounts module may deliver on processing or on completed).
$deliver_on = AIM_Settings::get( 'delivery_on_status' ) === 'completed' ? 'completed' : 'processing';

$actions = '<a class="btn btn-light" href="' . esc_url( aim_panel_url( 'admin', 'orders' ) ) . '">' . aim_icon( 'arrow-right-line' ) . 'بازگشت</a>'
        . '<a class="btn btn-light" href="' . esc_url( AIM_WP_Embed::url( str_replace( admin_url(), '', $order->get_edit_order_url() ), 'orders' ) ) . '">' . aim_icon( 'edit-line' ) . 'ویرایش کامل در ووکامرس</a>';

aim_page_head( 'سفارش #' . aim_fa_digits( $order->get_order_number() ), $actions );
?>

<?php if ( ! $done_st ) : ?>
<section class="card mb-20 aim-guide">
        <div class="card-head"><h2>مسیر رسیدگی و تحویل</h2><div class="actions"><?php echo aim_order_status_pill( $status ); // phpcs:ignore ?></div></div>
        <div class="card-body">

        <?php if ( $waiting && ! $is_paid ) : ?>
                <?php // Step 1 of the flow: the payment is not confirmed yet, delivery starts after it. ?>
                <div class="notice notice-warning"><?php echo aim_icon( 'time-line' ); // phpcs:ignore ?><p><b>پرداخت این سفارش هنوز تأیید نشده است.</b><?php if ( $order->get_payment_method_title() ) : ?> روش پرداخت: «<?php echo esc_html( $order->get_payment_method_title() ); ?>»<?php endif; ?>. اکانت‌ها بعد از تأیید پرداخت تحویل می‌شوند.</p></div>
                <form data-api="orders/<?php echo esc_attr( $order->get_id() ); ?>/status" data-reload style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                        <input type="hidden" name="status" value="<?php echo esc_attr( $deliver_on ); ?>">
                        <button class="btn btn-primary" type="submit"><?php echo aim_icon( 'checkbox-circle-line' ); // phpcs:ignore ?><?php echo 'completed' === $deliver_on ? 'تأیید پرداخت و تکمیل سفارش' : 'تأیید پرداخت و شروع تحویل'; ?></button>
                        <span class="text-3" style="font-size:12px"><?php echo 'completed' === $deliver_on ? 'با این کار سفارش «تکمیل‌شده» می‌شود و تحویل خودکار انجام می‌گیرد.' : 'با این کار سفارش به «در حال پردازش» می‌رود و تحویل خودکار انجام می‌شود.'; ?></span>
                </form>
        <?php elseif ( is_array( $ful ) && $ful['items'] ) : ?>
                <?php // The order carries account products: show the delivery progress and the next action. ?>
                <?php if ( $guest ) : ?>
                        <div class="notice notice-warning"><?php echo aim_icon( 'user-line' ); // phpcs:ignore ?><p>این سفارش به هیچ حساب کاربری متصل نیست؛ اکانت‌ها در پنل مشتری دیده نمی‌شوند. سفارش را از ویرایش کامل ووکامرس به مشتری متصل کنید.</p></div>
                <?php endif; ?>
                <?php $pct = $ful['qty'] ? (int) round( $ful['delivered'] / $ful['qty'] * 100 ) : 0; ?>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                        <b>تحویل اکانت‌ها: <?php echo esc_html( aim_fa_digits( $ful['delivered'] ) ); ?> از <?php echo esc_html( aim_fa_digits( $ful['qty'] ) ); ?></b>
                        <span class="pill <?php echo $ful['owed'] ? 'pill-warning' : 'pill-success'; ?>"><?php echo $ful['owed'] ? esc_html( aim_fa_digits( $ful['owed'] ) ) . ' تحویل‌نشده' : 'تحویل کامل'; ?></span>
                </div>
                <div class="progress <?php echo $pct && $pct < 100 ? 'is-low' : ''; ?>" role="img" aria-label="پیشرفت تحویل"><span style="width:<?php echo esc_attr( $pct ); ?>%"></span></div>
                <?php if ( $is_paid && $ful['owed'] ) : ?>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px">
                                <?php foreach ( $ful['manual_item_ids'] as $mid ) : ?>
                                        <button class="btn btn-primary" data-modal-open="deliver-<?php echo esc_attr( $mid ); ?>"><?php echo aim_icon( 'send-plane-line' ); // phpcs:ignore ?>تحویل دستی این قلم</button>
                                <?php endforeach; ?>
                                <?php if ( $ful['auto_owed'] ) : ?>
                                        <button class="btn btn-primary" data-action="orders/<?php echo esc_attr( $order->get_id() ); ?>/retry"><?php echo aim_icon( 'refresh-line' ); // phpcs:ignore ?>تحویل مجدد از انبار</button>
                                <?php endif; ?>
                        </div>
                        <p class="text-3" style="font-size:12px;margin:10px 0 0">تحویل دستی یعنی اطلاعات اکانت را در پنجرهٔ بازشده وارد می‌کنید؛ مشتری با پیامک و ایمیل باخبر می‌شود. تحویل مجدد، اکانت‌های موجود انبار را به‌صورت خودکار تخصیص می‌دهد.</p>
                <?php elseif ( ! $is_paid ) : ?>
                        <p class="text-3" style="font-size:12px;margin:10px 0 0">پس از تأیید پرداخت، دکمه‌های تحویل همین‌جا فعال می‌شوند.</p>
                <?php else : ?>
                        <div class="notice notice-success" style="margin:14px 0 0"><?php echo aim_icon( 'checkbox-circle-fill' ); // phpcs:ignore ?><p>همهٔ اکانت‌های این سفارش تحویل شده و در «اکانت‌های من» مشتری قابل مشاهده است.</p></div>
                <?php endif; ?>
        <?php elseif ( $is_paid && 'processing' === $status ) : ?>
                <?php // Paid but nothing to deliver from the accounts module: the next step is completing the order. ?>
                <div class="notice inline"><?php echo aim_icon( 'information-line' ); // phpcs:ignore ?><p>پرداخت این سفارش انجام شده و محصول اکانتی ندارد؛ نیازی به تحویل دستی نیست. برای بستن سفارش، آن را «تکمیل‌شده» کنید.</p></div>
                <form data-api="orders/<?php echo esc_attr( $order->get_id() ); ?>/status" data-reload style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                        <input type="hidden" name="status" value="completed">
                        <button class="btn btn-primary" type="submit"><?php echo aim_icon( 'checkbox-circle-line' ); // phpcs:ignore ?>تکمیل سفارش</button>
                </form>
        <?php elseif ( $is_paid ) : ?>
                <div class="notice notice-success"><?php echo aim_icon( 'checkbox-circle-fill' ); // phpcs:ignore ?><p>پرداخت این سفارش تأیید شده است؛ در «اقلام سفارش» زیر وضعیت تحویل هر قلم مشخص است.</p></div>
        <?php else : ?>
                <div class="notice notice-warning"><?php echo aim_icon( 'time-line' ); // phpcs:ignore ?><p>پرداخت این سفارش هنوز تأیید نشده است. پس از تأیید، گام‌های تحویل همین‌جا نمایش داده می‌شود.</p></div>
        <?php endif; ?>

        </div>
</section>
<?php endif; ?>

<div class="grid grid-main">
        <div>
                <section class="card mb-20">
                        <div class="card-head">
                                <h2>اقلام سفارش</h2>
                                <div class="actions"><?php echo aim_order_status_pill( $order->get_status() ); // phpcs:ignore ?></div>
                        </div>
                        <ul class="list">
                        <?php foreach ( $order->get_items() as $item_id => $item ) : ?>
                                <?php $product = $item->get_product(); ?>
                                <li style="flex-wrap:wrap">
                                        <?php echo $product ? wp_kses_post( $product->get_image( [ 84, 84 ], [ 'class' => 'thumb' ] ) ) : ''; ?>
                                        <div style="flex:1;min-width:200px">
                                                <b><?php echo esc_html( $item->get_name() ); ?></b>
                                                <div class="text-2" style="font-size:12px"><?php echo esc_html( aim_fa_digits( $item->get_quantity() ) ); ?> عدد · <?php echo aim_price( $item->get_total() ); // phpcs:ignore ?></div>
                                        </div>
                                        <?php
                                        /** Modules show per-item details here (the accounts module: delivery state and delivered accounts). */
                                        do_action( 'aim_admin_order_item', $item, $item_id, $order );
                                        ?>
                                </li>
                        <?php endforeach; ?>
                        </ul>
                        <?php do_action( 'aim_admin_order_after_items', $order ); ?>
                </section>

                <section class="card">
                        <div class="card-head"><h2>یادداشت‌های سفارش</h2></div>
                        <?php $notes = wc_get_order_notes( [ 'order_id' => $order->get_id(), 'limit' => 20 ] ); ?>
                        <?php if ( $notes ) : ?>
                                <ul class="list">
                                        <?php foreach ( $notes as $n ) : ?>
                                                <li><span class="dot-ico"><?php echo aim_icon( $n->customer_note ? 'user-line' : 'sticky-note-line' ); // phpcs:ignore ?></span><p><?php echo wp_kses_post( preg_replace_callback( '/(^|>)([^<]+)/', fn( $m ) => $m[1] . aim_fa_digits( $m[2] ), $n->content ) ); // Persian digits in text only, never inside tags/links. ?></p><span class="meta"><?php echo esc_html( aim_jdate( $n->date_created->getTimestamp(), true ) ); ?></span></li>
                                        <?php endforeach; ?>
                                </ul>
                        <?php else : ?>
                                <?php aim_empty( 'sticky-note-line', 'یادداشتی ثبت نشده' ); ?>
                        <?php endif; ?>
                </section>
        </div>

        <div>
                <section class="card mb-20">
                        <div class="card-head"><h2>مشتری</h2></div>
                        <div class="card-body">
                                <dl class="kv">
                                        <dt>نام</dt><dd><?php echo esc_html( trim( $order->get_formatted_billing_full_name() ) ?: '—' ); ?></dd>
                                        <dt>موبایل</dt><dd class="ltr" style="text-align:right"><?php echo esc_html( aim_fa_digits( $order->get_billing_phone() ) ?: '—' ); ?></dd>
                                        <dt>ایمیل</dt><dd class="ltr" style="text-align:right"><?php echo esc_html( $order->get_billing_email() ?: '—' ); ?></dd>
                                        <?php do_action( 'aim_admin_order_customer', $order ); ?>
                                </dl>
                        </div>
                </section>

                <section class="card mb-20">
                        <div class="card-head"><h2>پرداخت</h2></div>
                        <div class="card-body">
                                <dl class="kv">
                                        <dt>مبلغ کل</dt><dd><?php echo aim_price( $order->get_total() ); // phpcs:ignore ?></dd>
                                        <?php if ( $order->get_total_refunded() > 0 ) : ?>
                                                <dt>مسترد شده</dt><dd><?php echo aim_price( $order->get_total_refunded() ); // phpcs:ignore ?></dd>
                                        <?php endif; ?>
                                        <dt>روش پرداخت</dt><dd><?php echo esc_html( $order->get_payment_method_title() ?: '—' ); ?></dd>
                                        <dt>شناسهٔ تراکنش</dt><dd class="ltr" style="text-align:right"><?php echo esc_html( $order->get_transaction_id() ?: '—' ); ?></dd>
                                        <dt>تاریخ ثبت</dt><dd><?php echo esc_html( aim_jdate( $created ? $created->getTimestamp() : 0, true ) ); ?></dd>
                                        <dt>تاریخ پرداخت</dt><dd><?php echo esc_html( aim_jdate( $paid ? $paid->getTimestamp() : 0, true ) ); ?></dd>
                                </dl>
                        </div>
                </section>

                <section class="card">
                        <div class="card-head"><h2>تغییر وضعیت</h2></div>
                        <form class="card-body" data-api="orders/<?php echo esc_attr( $order->get_id() ); ?>/status" data-reload>
                                <label class="visually-hidden" for="order-status">وضعیت سفارش</label>
                                <select id="order-status" name="status" class="mb-20">
                                        <?php foreach ( aim_panel_order_statuses() as $k ) : ?>
                                                <option value="<?php echo esc_attr( substr( $k, 3 ) ); ?>" <?php selected( 'wc-' . $order->get_status(), $k ); ?>><?php echo esc_html( aim_order_status_label( $k ) ); ?></option>
                                        <?php endforeach; ?>
                                </select>
                                <button class="btn btn-primary btn-block" type="submit">ذخیرهٔ وضعیت</button>
                        </form>
                </section>
        </div>
</div>
