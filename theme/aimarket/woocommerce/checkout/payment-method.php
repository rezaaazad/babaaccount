<?php
/**
 * One payment method card.
 *
 * @var WC_Payment_Gateway $gateway
 * @var bool               $first   The first method gets the «پیشنهادی» tag.
 */
defined( 'ABSPATH' ) || exit;
$icon = $gateway->get_icon();
?>
<li class="wc_payment_method payment_method_<?php echo esc_attr( $gateway->id ); ?> cx-method">
	<input id="payment_method_<?php echo esc_attr( $gateway->id ); ?>" type="radio" class="input-radio" name="payment_method" value="<?php echo esc_attr( $gateway->id ); ?>" <?php checked( $gateway->chosen, true ); ?> data-order_button_text="<?php echo esc_attr( $gateway->order_button_text ); ?>" />
	<label for="payment_method_<?php echo esc_attr( $gateway->id ); ?>">
		<span class="cx-method-ico"><?php echo $icon ? wp_kses_post( $icon ) : '<i class="ic-bank-card-line"></i>'; ?></span>
		<span class="cx-method-text">
			<b><?php echo wp_kses_post( $gateway->get_title() ); ?></b>
			<?php if ( $gateway->get_description() ) : ?><small><?php echo wp_kses_post( wp_strip_all_tags( $gateway->get_description() ) ); ?></small><?php endif; ?>
			<?php if ( ! empty( $first ) ) : ?><em class="cx-tag">پیشنهادی</em><?php endif; ?>
		</span>
		<span class="cx-radio" aria-hidden="true"></span>
	</label>
	<?php if ( $gateway->has_fields() ) : ?>
		<div class="payment_box payment_method_<?php echo esc_attr( $gateway->id ); ?>" <?php if ( ! $gateway->chosen ) : ?>style="display:none;"<?php endif; ?>>
			<?php $gateway->payment_fields(); ?>
		</div>
	<?php endif; ?>
</li>
