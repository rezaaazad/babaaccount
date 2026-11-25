<?php
/**
 * Accounts module panels inside panel → تنظیمات فروشگاه (hook 'aim_settings_panels').
 * Delivery fields save with the main settings form; the key card has its own button.
 *
 * @var callable $field  Text input row (from the settings template).
 * @var callable $switch Toggle row.
 * @var array    $s      Current settings.
 */
defined( 'ABSPATH' ) || exit;
?>
	<section class="card" data-tab-panel="delivery" hidden>
		<div class="card-head"><h2>تحویل خودکار و یادآوری تمدید</h2></div>
		<div class="card-body form-grid">
			<div class="form-row">
				<label class="form-label" for="set-delivery_on_status">زمان تحویل خودکار</label>
				<select id="set-delivery_on_status" name="delivery_on_status">
					<option value="processing" <?php selected( $s['delivery_on_status'], 'processing' ); ?>>بلافاصله پس از پرداخت موفق</option>
					<option value="completed" <?php selected( $s['delivery_on_status'], 'completed' ); ?>>پس از تأیید دستی سفارش (تکمیل‌شده)</option>
				</select>
			</div>
			<?php
			$field( 'reminder_days', 'یادآوری تمدید (روز قبل از انقضا)', [ 'ltr' => true, 'placeholder' => '7,3,1', 'help' => 'تا ۸ عدد بین ۱ تا ۳۶۵ را با ویرگول جدا کنید؛ برای هر عدد یک پیامک و ایمیل یادآوری فرستاده می‌شود.' ] );
			$switch( 'auto_complete_order', 'سفارش پس از تحویل کامل، خودکار «تکمیل‌شده» شود' );
			?>
		</div>
	</section>

	<?php $key_status = AIM_Secret_Key::status(); ?>
	<section class="card" data-tab-panel="login" hidden>
		<div class="card-head"><h2>کلید رمزنگاری اکانت‌ها</h2></div>
		<div class="card-body">
			<?php if ( $key_status['defined'] ) : ?>
				<p style="margin:0"><span class="pill pill-success"><?php echo aim_icon( 'shield-check-line' ); // phpcs:ignore ?>کلید اختصاصی فعال است</span></p>
				<p class="form-help">اطلاعات اکانت‌ها با کلید داخل wp-config.php رمز می‌شوند. از wp-config.php جدا از نسخهٔ پشتیبان پایگاه داده نسخه نگه دارید؛ بدون آن کلید، اطلاعات اکانت‌ها خوانده نمی‌شود.</p>
			<?php else : ?>
				<p class="text-2" style="margin-top:0">الان اطلاعات اکانت‌ها با کلیدی ساخته‌شده از کلیدهای امنیتی وردپرس رمز می‌شود. با یک کلید اختصاصی در wp-config.php، حتی اگر پایگاه داده لو برود اطلاعات اکانت‌ها خوانده نمی‌شود. اکانت‌های فعلی خودکار با کلید جدید رمز می‌شوند.</p>
				<button type="button" class="btn btn-primary" data-secret-key><?php echo aim_icon( 'key-2-line' ); // phpcs:ignore ?><?php echo $key_status['writable'] ? 'ساخت و ثبت خودکار کلید' : 'ساخت کلید'; ?></button>
				<?php if ( ! $key_status['writable'] ) : ?><p class="form-help">هاست اجازهٔ تغییر wp-config.php را نمی‌دهد؛ پس از ساخت، خطی نمایش داده می‌شود که باید دستی (با فایل‌منیجر هاست) به ابتدای wp-config.php اضافه کنید.</p><?php endif; ?>
				<div class="secret-result" data-secret-result hidden>
					<p data-secret-msg></p>
					<code class="ltr" data-secret-code></code>
					<button type="button" class="btn btn-light btn-sm" data-secret-copy><?php echo aim_icon( 'file-copy-line' ); // phpcs:ignore ?>کپی</button>
					<p class="form-help" data-secret-note></p>
				</div>
			<?php endif; ?>
		</div>
	</section>

