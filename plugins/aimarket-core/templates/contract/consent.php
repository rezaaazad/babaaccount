<?php
/**
 * Consent box on checkout (AIM_Contract::consent_box()).
 *
 * @var array  $s     Contract settings.
 * @var string $rules Purchase rules URL.
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="aimc-consent" data-contract-box>
	<label class="aimc-check">
		<input type="checkbox" name="aim_contract_accept" value="1" data-contract-accept>
		<span>
			<b><?php echo esc_html( $s['consent_text'] ); ?></b>
			<small><?php echo esc_html( $s['consent_help'] ); ?><?php if ( $rules ) : ?> <a href="<?php echo esc_url( $rules ); ?>" target="_blank" rel="noopener">قوانین خرید</a><?php endif; ?></small>
		</span>
	</label>
	<div class="aimc-consent-foot">
		<button type="button" class="aimc-open" data-contract-open><?php echo aim_icon( 'file-list-3-line' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>مشاهدهٔ قرارداد سفارش</button>
		<small><?php echo aim_icon( 'shield-check-line' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>زمان، IP و نسخهٔ پذیرش برای همین سفارش ثبت می‌شود.</small>
	</div>
</div>
