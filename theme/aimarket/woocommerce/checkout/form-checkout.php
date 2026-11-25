<?php
/**
 * Checkout: customer details + payment on the right, order summary on the left.
 * Account products are delivered to the customer's panel, so a signed-in
 * account (phone + one-time code) is required before the form is shown.
 *
 * @var WC_Checkout $checkout
 */
defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() ) :
        $login = function_exists( 'aim_login_url' ) ? aim_login_url( wc_get_checkout_url() ) : wp_login_url( wc_get_checkout_url() );
        ?>
        <div class="co-gate">
                <?php echo aim_t_illus( 'step-login', 'co-art' ); // phpcs:ignore ?>
                <h2><?php echo esc_html( aim_t_opt( 'co_login_title' ) ); ?></h2>
                <p><?php echo esc_html( aim_t_opt( 'co_login_text' ) ); ?></p>
                <a class="pc-btn" href="<?php echo esc_url( $login ); ?>"><i class="ic-login-box-line"></i><?php echo esc_html( aim_t_opt( 'co_login_button' ) ); ?></a>
                <a class="co-link" href="<?php echo esc_url( wc_get_cart_url() ); ?>"><i class="ic-arrow-right-line"></i>بازگشت به سبد خرید</a>
        </div>
        <?php
        return;
endif;

do_action( 'woocommerce_before_checkout_form', $checkout );
?>

<form name="checkout" method="post" class="checkout woocommerce-checkout cx-grid" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data" aria-label="تکمیل سفارش">
        <div class="cx-main">
                <div class="cx-card" id="customer_details">
                        <div class="cx-card-head">
                                <span class="cx-ico"><i class="ic-user-3-line"></i></span>
                                <div><h2><?php echo esc_html( aim_t_opt( 'co_buyer' ) ); ?></h2><small>لطفاً اطلاعات را برای تأیید خرید تکمیل کنید؛ سفارش و پیگیری آن با همین مشخصات ثبت می‌شود.</small></div>
                        </div>
                        <div class="cx-card-body co-fields">
                                <?php if ( $checkout->get_checkout_fields() ) : ?>
                                        <?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>
                                        <?php do_action( 'woocommerce_checkout_billing' ); ?>
                                        <?php do_action( 'woocommerce_checkout_shipping' ); ?>
                                        <?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>
                                <?php endif; ?>
                                <?php do_action( 'aim_checkout_consent' ); // aimarket-core: order contract consent box ?>
                                <p class="cx-note"><i class="ic-information-line"></i><?php echo esc_html( aim_t_opt( 'co_account_note' ) ); ?></p>
                        </div>
                </div>
        </div>

        <aside class="cx-side">
                <div class="cx-card cx-sum">
                        <div class="cx-card-head">
                                <span class="cx-ico"><i class="ic-file-list-3-line"></i></span>
                                <div><h2 id="order_review_heading">خلاصهٔ سفارش</h2><small>پیش از ورود به درگاه، سفارش را بررسی کنید.</small></div>
                        </div>
                        <?php do_action( 'woocommerce_checkout_before_order_review' ); ?>
                        <div id="order_review" class="woocommerce-checkout-review-order">
                                <?php do_action( 'woocommerce_checkout_order_review' ); ?>
                        </div>
                        <?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
                </div>
        </aside>
</form>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
