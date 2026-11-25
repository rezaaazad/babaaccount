<?php
defined( 'ABSPATH' ) || exit;
$user = wp_get_current_user();

aim_page_head( 'ویرایش پروفایل', '', 'user' );
?>
<form class="card" data-api="me/profile" data-reload>
	<div class="card-head"><h2>اطلاعات حساب</h2></div>
	<div class="card-body form-grid">
		<div class="form-row"><label class="form-label" for="p-name">نام کامل</label><input id="p-name" class="field" name="display_name" value="<?php echo esc_attr( $user->display_name ); ?>" autocomplete="name"></div>
		<div class="form-row"><label class="form-label" for="p-phone">شمارهٔ همراه</label><input id="p-phone" class="field ltr" value="<?php echo esc_attr( aim_fa_digits( aim_user_phone( $user->ID ) ) ); ?>" disabled><p class="form-help">برای تغییر شماره با پشتیبانی تماس بگیرید.</p></div>
		<div class="form-row span-2"><label class="form-label" for="p-email">ایمیل</label><input id="p-email" class="field ltr" type="email" name="email" value="<?php echo esc_attr( $user->user_email ); ?>" placeholder="example@gmail.com" autocomplete="email"><p class="form-help">برای دریافت اطلاع‌رسانی تحویل و تمدید.</p></div>
	</div>
	<div class="card-head" style="border-top:1px solid var(--border)"><h2>گذرواژه</h2><span class="text-3" style="font-size:12px">اختیاری — برای ورود بدون پیامک</span></div>
	<div class="card-body form-grid">
		<div class="form-row"><label class="form-label" for="p-pass">گذرواژهٔ جدید</label><input id="p-pass" class="field ltr" type="password" name="password" autocomplete="new-password" minlength="8" placeholder="دست‌کم ۸ کاراکتر"></div>
		<div class="form-row"><label class="form-label" for="p-pass2">تکرار گذرواژه</label><input id="p-pass2" class="field ltr" type="password" name="password2" autocomplete="new-password"></div>
	</div>
	<div class="modal-foot" style="background:none;border-radius:0 0 var(--radius-lg) var(--radius-lg)"><button class="btn btn-primary" type="submit"><?php echo aim_icon( 'save-3-line' ); // phpcs:ignore ?>ذخیرهٔ تغییرات</button></div>
</form>
