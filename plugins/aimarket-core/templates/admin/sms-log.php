<?php
defined( 'ABSPATH' ) || exit;
$log    = get_option( 'aim_sms_log', [] );
$events = apply_filters( 'aim_sms_events', [ 'otp' => 'کد ورود', 'alert_stock' => 'اعلان موجودی', 'alert_sale' => 'اعلان تخفیف' ] );
$driver = AIM_Settings::get( 'sms_driver' );

aim_page_head( 'گزارش پیامک‌ها' );
?>
<?php if ( 'log' === $driver ) : ?>
	<div class="notice inline notice-warning"><?php echo aim_icon( 'flask-line' ); // phpcs:ignore ?><p>درگاه پیامک روی حالت «آزمایشی» است: پیامکی واقعاً ارسال نمی‌شود و فقط اینجا ثبت می‌شود. برای راه‌اندازی، از <a href="<?php echo esc_url( aim_panel_url( 'admin', 'settings' ) . '#sms' ); ?>">تنظیمات ← پیامک</a> درگاه را انتخاب کنید.</p></div>
<?php else : ?>
	<div class="notice inline"><?php echo aim_icon( 'information-line' ); // phpcs:ignore ?><p>در حالت ارسال واقعی، فقط خطاهای ارسال در «فعالیت‌های اخیر» ثبت می‌شوند.</p></div>
<?php endif; ?>
<section class="card">
	<?php if ( $log ) : ?>
		<div class="table-wrap">
			<table class="table">
				<thead><tr><th>گیرنده</th><th>نوع</th><th>متغیرها</th><th>زمان</th></tr></thead>
				<tbody>
				<?php foreach ( $log as $l ) : ?>
					<tr>
						<td class="num ltr" style="text-align:right"><?php echo esc_html( aim_fa_digits( $l['phone'] ) ); ?></td>
						<td><span class="pill pill-primary"><?php echo esc_html( $events[ $l['event'] ] ?? $l['event'] ); ?></span></td>
						<td class="mono ltr" style="text-align:right">
							<?php foreach ( (array) $l['vars'] as $k => $v ) : ?>
								<span class="secret is-revealed" style="margin-left:4px"><?php echo esc_html( $k . ': ' . $v ); ?></span>
							<?php endforeach; ?>
						</td>
						<td class="num text-2"><?php echo esc_html( aim_jdate( $l['time'], true ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php else : ?>
		<?php aim_empty( 'message-2-line', 'پیامکی ثبت نشده' ); ?>
	<?php endif; ?>
</section>
