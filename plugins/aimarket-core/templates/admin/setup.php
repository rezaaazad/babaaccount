<?php
/**
 * «راه‌اندازی سریع»: full-screen glass wizard over the panel (AIM_Setup).
 * Steps: welcome → business type → details → features → run → done.
 * assets/js/setup.js drives the steps and runs the tasks one by one.
 */
defined( 'ABSPATH' ) || exit;

$state    = AIM_Setup::state();
$types    = AIM_Setup::types();
$settings = AIM_Settings::all();
$info     = $state['info'] + [
	'name'    => get_bloginfo( 'name' ),
	'tagline' => get_bloginfo( 'description' ),
	'phone'   => $settings['support_phone'],
	'email'   => $settings['support_email'],
	'hours'   => $settings['response_time'],
	'mode'    => $settings['theme_mode'],
];
$features = [];
foreach ( array_keys( $types ) as $t ) {
	$features[ $t ] = AIM_Setup::features( $t );
}
$has_accounts = AIM_Setup::plugin_installed( AIM_Setup::ACCOUNTS );
$woo_ready    = class_exists( 'WooCommerce' );
$rocket       = '<svg viewBox="0 0 220 160" aria-hidden="true"><defs><linearGradient id="su-a" x1="0" x2="1" y1="0" y2="1"><stop offset="0" stop-color="#7fb4ff"/><stop offset="1" stop-color="#2563eb"/></linearGradient><linearGradient id="su-g" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#fde68a"/><stop offset="1" stop-color="#f59e0b"/></linearGradient></defs><circle cx="70" cy="128" r="18" fill="currentColor" opacity=".12"/><circle cx="110" cy="134" r="24" fill="currentColor" opacity=".12"/><circle cx="150" cy="128" r="18" fill="currentColor" opacity=".12"/><path d="M98 96h24c0 18-6 30-12 40-6-10-12-22-12-40z" fill="url(#su-g)"/><path d="M88 70l-20 30 24-5zM132 70l20 30-24-5z" fill="url(#su-a)"/><path d="M110 8c24 16 33 42 29 74l-4 22H85l-4-22C77 50 86 24 110 8z" fill="#fff" stroke="url(#su-a)" stroke-width="3"/><circle cx="110" cy="56" r="13" fill="url(#su-a)"/><circle cx="106" cy="52" r="4" fill="#fff" opacity=".8"/><path d="M40 40l3 8 8 3-8 3-3 8-3-8-8-3 8-3zM178 30l2 6 6 2-6 2-2 6-2-6-6-2 6-2z" fill="#7fb4ff"/></svg>';
?>
<div class="aim-setup" data-setup
	data-features="<?php echo esc_attr( wp_json_encode( $features ) ); ?>"
	data-accounts="<?php echo $has_accounts ? '1' : ''; ?>"
	data-type="<?php echo esc_attr( $state['type'] ); ?>"
	data-panel="<?php echo esc_url( aim_panel_url( 'admin' ) ); ?>">
	<div class="su-glow" aria-hidden="true"><i></i><i></i><i></i></div>

	<div class="su-card" role="dialog" aria-modal="true" aria-labelledby="su-title">
		<a class="su-close" href="<?php echo esc_url( aim_panel_url( 'admin' ) ); ?>" aria-label="بستن"><?php echo aim_icon( 'close-line' ); // phpcs:ignore ?></a>

		<!-- 0. Welcome -->
		<section class="su-step is-active" data-step="0">
			<div class="su-art"><?php echo $rocket; // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></div>
			<?php if ( $state['done'] ) : ?>
				<h1 id="su-title">سلام! خوش برگشتید</h1>
				<p class="su-lead">این سایت قبلاً به‌عنوان «<?php echo esc_html( $types[ $state['type'] ]['label'] ?? '—' ); ?>» راه‌اندازی شده است. اگر کسب‌وکار یا تنظیماتتان عوض شده، دوباره راه‌اندازی کنید؛ چیزی پاک نمی‌شود.</p>
			<?php else : ?>
				<h1 id="su-title">به پنل مدیریت خوش آمدید</h1>
				<p class="su-lead">چند سؤال کوتاه می‌پرسیم و بعد همه‌چیز را خودکار برایتان تنظیم می‌کنیم: افزونه‌های لازم، فروشگاه، برگه‌ها، منوی پنل و تنظیمات پایه. کمتر از دو دقیقه طول می‌کشد.</p>
			<?php endif; ?>
		</section>

		<!-- 1. Business type -->
		<section class="su-step" data-step="1" hidden>
			<h2>کسب‌وکار شما چیست؟</h2>
			<p class="su-lead">بر اساس انتخاب شما، بخش‌های لازم روشن و بقیه خاموش می‌شوند.</p>
			<div class="su-types" role="radiogroup" aria-label="نوع کسب‌وکار">
				<?php foreach ( $types as $key => $t ) : ?>
					<label class="su-type">
						<input type="radio" name="su-type" value="<?php echo esc_attr( $key ); ?>" <?php checked( $state['type'], $key ); ?>>
						<span class="su-type-ico"><?php echo aim_icon( $t['icon'] ); // phpcs:ignore ?></span>
						<b><?php echo esc_html( $t['label'] ); ?></b>
						<small><?php echo esc_html( $t['text'] ); ?></small>
					</label>
				<?php endforeach; ?>
			</div>
		</section>

		<!-- 2. Details -->
		<section class="su-step" data-step="2" hidden>
			<h2>مشخصات کسب‌وکار</h2>
			<p class="su-lead">در سایت، پیامک‌ها و برگه‌ها از همین اطلاعات استفاده می‌شود. بعداً هم قابل تغییر است.</p>
			<div class="su-form">
				<label class="su-field su-wide"><span>نام کسب‌وکار <em>*</em></span><input data-info="name" value="<?php echo esc_attr( $info['name'] ); ?>" placeholder="مثلاً موبایل‌شاپ ایرانیان"></label>
				<label class="su-field su-wide"><span>شعار یا توضیح کوتاه</span><input data-info="tagline" value="<?php echo esc_attr( $info['tagline'] ); ?>" placeholder="مثلاً خرید مطمئن موبایل و لوازم جانبی"></label>
				<label class="su-field"><span>تلفن پشتیبانی</span><input class="ltr" data-info="phone" inputmode="tel" value="<?php echo esc_attr( $info['phone'] ); ?>" placeholder="021-12345678"></label>
				<label class="su-field"><span>ایمیل</span><input class="ltr" type="email" data-info="email" value="<?php echo esc_attr( $info['email'] ); ?>" placeholder="info@example.ir"></label>
				<label class="su-field su-wide"><span>ساعات پاسخگویی</span><input data-info="hours" value="<?php echo esc_attr( $info['hours'] ); ?>" placeholder="شنبه تا پنج‌شنبه، ۹ تا ۱۸"></label>
				<div class="su-field su-wide">
					<span>ظاهر پیش‌فرض سایت</span>
					<div class="su-seg" role="radiogroup" aria-label="ظاهر پیش‌فرض">
						<?php foreach ( [ 'light' => 'روشن', 'dark' => 'تیره', 'auto' => 'مطابق دستگاه' ] as $k => $l ) : ?>
							<label><input type="radio" name="su-mode" data-info="mode" value="<?php echo esc_attr( $k ); ?>" <?php checked( $info['mode'], $k ); ?>><span><?php echo esc_html( $l ); ?></span></label>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		</section>

		<!-- 3. Features -->
		<section class="su-step" data-step="3" hidden>
			<h2>امکانات پیشنهادی</h2>
			<p class="su-lead">برای «<span data-type-label></span>» این‌ها را پیشنهاد می‌کنیم. هر کدام را خواستید عوض کنید.</p>
			<div class="su-feats" data-feats></div>
			<p class="su-note" data-woo-note hidden><?php echo aim_icon( 'information-line' ); // phpcs:ignore ?><span><?php echo $woo_ready ? 'ووکامرس روی سایت فعال است.' : 'ووکامرس نصب نیست؛ در مرحلهٔ بعد خودکار از مخزن وردپرس نصب و فعال می‌شود.'; ?></span></p>
		</section>

		<!-- 4. Run -->
		<section class="su-step" data-step="4" hidden>
			<h2 data-run-title>در حال راه‌اندازی…</h2>
			<p class="su-lead" data-run-lead>این پنجره را نبندید؛ چند لحظه طول می‌کشد.</p>
			<div class="su-progress"><i data-progress></i></div>
			<ul class="su-tasks" data-tasks></ul>
		</section>

		<!-- 5. Done -->
		<section class="su-step" data-step="5" hidden>
			<div class="su-art su-done"><?php echo aim_icon( 'checkbox-circle-fill' ); // phpcs:ignore ?></div>
			<h2>سایت شما آماده است!</h2>
			<p class="su-lead">همه‌چیز طبق نوع کسب‌وکارتان تنظیم شد. هر بخشی را که لازم داشتید، از «تنظیمات سایت ← منوی پنل» روشن کنید.</p>
		</section>

		<footer class="su-foot">
			<a class="su-btn su-ghost" data-cancel href="<?php echo esc_url( aim_panel_url( 'admin' ) ); ?>">لغو</a>
			<div class="su-dots" aria-hidden="true"><i class="is-on"></i><i></i><i></i><i></i></div>
			<div class="su-actions">
				<button type="button" class="su-btn su-ghost" data-back hidden>قبلی</button>
				<button type="button" class="su-btn su-primary" data-next><?php echo $state['done'] ? 'راه‌اندازی دوباره' : 'شروع کنیم'; ?></button>
			</div>
		</footer>
	</div>
</div>
