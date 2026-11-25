<?php
/** @var string $redirect */
defined( 'ABSPATH' ) || exit;
$shop     = AIM_Settings::get( 'shop_title' );
$password = (bool) AIM_Settings::get( 'password_login' );
$terms    = aim_terms_url();
$logo_id  = get_theme_mod( 'custom_logo' );
?><!doctype html>
<html <?php language_attributes(); ?><?php echo is_rtl() ? '' : ' dir="rtl"'; // language_attributes() already prints dir="rtl" on a Persian site. ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>ورود یا ثبت‌نام | <?php echo esc_html( $shop ); ?></title>
<?php AIM_Router::head_assets( 'login' ); ?>
</head>
<body class="aim aim-auth">
<button type="button" class="auth-theme" data-theme-toggle aria-label="تغییر حالت شب و روز"><?php echo aim_icon( 'moon-line' ); // phpcs:ignore ?></button>
<main class="auth-wrap">
	<a class="auth-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
		<?php if ( $logo_id ) : ?>
			<?php echo wp_get_attachment_image( $logo_id, 'medium', false, [ 'alt' => $shop ] ); ?>
		<?php else : ?>
			<?php echo aim_brand_mark(); // phpcs:ignore WordPress.Security.EscapeOutput -- core markup or the theme's logo. ?>
			<span><?php echo esc_html( $shop ); ?></span>
		<?php endif; ?>
	</a>

	<section class="auth-card" data-auth data-redirect="<?php echo esc_attr( $redirect ); ?>" aria-live="polite">

		<!-- Step 1: phone -->
		<form class="auth-step is-active" data-step="phone" novalidate>
			<h1 class="auth-title">ورود به حساب <span>یا</span> ثبت‌نام</h1>
			<p class="auth-sub">شمارهٔ موبایل خود را وارد کنید. کد تأیید برایتان پیامک می‌شود.</p>
			<label class="visually-hidden" for="aim-phone">شمارهٔ موبایل</label>
			<input id="aim-phone" class="field field-lg ltr text-center" name="phone" type="tel" inputmode="numeric" autocomplete="tel" placeholder="شمارهٔ موبایل همراه با صفر" maxlength="14" required autofocus>
			<p class="field-error" data-error></p>
			<button class="btn btn-primary btn-block" type="submit"><span>ادامه</span></button>
			<p class="auth-terms">ورود شما به منزلهٔ پذیرش <?php if ( $terms ) : ?><a href="<?php echo esc_url( $terms ); ?>" target="_blank" rel="noopener">قوانین و شرایط</a><?php else : ?>قوانین و شرایط<?php endif; ?> است.</p>
			<?php if ( $password ) : ?>
				<button type="button" class="link-btn" data-go="password">ورود با گذرواژه</button>
			<?php endif; ?>
		</form>

		<!-- Step 2: one-time code -->
		<form class="auth-step" data-step="code" novalidate>
			<button type="button" class="auth-back" data-go="phone" aria-label="بازگشت"><?php echo aim_icon( 'arrow-right-line' ); // phpcs:ignore ?></button>
			<h1 class="auth-title">کد تأیید را وارد کنید</h1>
			<p class="auth-sub">کد به شمارهٔ <b class="ltr" data-phone-label></b> ارسال شد. <button type="button" class="link-btn inline" data-go="phone">ویرایش شماره</button></p>
			<div class="otp-boxes ltr" data-otp></div>
			<input type="hidden" name="code">
			<p class="field-error" data-error></p>
			<button class="btn btn-primary btn-block" type="submit"><span>تأیید و ورود</span></button>
			<p class="auth-resend">
				<span data-timer-wrap>ارسال مجدد کد تا <b data-timer>۰۰:۰۰</b></span>
				<button type="button" class="link-btn" data-resend hidden>دریافت مجدد کد</button>
			</p>
		</form>

		<?php if ( $password ) : ?>
		<!-- Alt: password -->
		<form class="auth-step" data-step="password" novalidate>
			<button type="button" class="auth-back" data-go="phone" aria-label="بازگشت"><?php echo aim_icon( 'arrow-right-line' ); // phpcs:ignore ?></button>
			<h1 class="auth-title">ورود با گذرواژه</h1>
			<p class="auth-sub">برای ثبت‌نام اولیه و تأیید شماره، باید با کد یک‌بار مصرف وارد شوید.</p>
			<input class="field ltr text-center" name="phone" type="tel" inputmode="numeric" autocomplete="tel" placeholder="شمارهٔ موبایل همراه با صفر" required>
			<div class="field-pass">
				<input class="field ltr" name="password" type="password" autocomplete="current-password" placeholder="گذرواژه" required>
				<button type="button" class="field-eye" data-toggle-pass aria-label="نمایش گذرواژه"><?php echo aim_icon( 'eye-line' ); // phpcs:ignore ?></button>
			</div>
			<p class="field-error" data-error></p>
			<button class="btn btn-primary btn-block" type="submit"><span>ورود</span></button>
			<button type="button" class="link-btn" data-go="phone">ورود با کد یک‌بار مصرف</button>
		</form>
		<?php endif; ?>
	</section>

	<nav class="auth-links">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>">صفحهٔ نخست</a>
		<span aria-hidden="true">•</span>
		<a href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ) ); ?>">همهٔ سرویس‌ها</a>
	</nav>
</main>
<div class="toast-stack" data-toasts aria-live="assertive"></div>
<?php AIM_Router::footer_assets( 'login' ); ?>
</body>
</html>
