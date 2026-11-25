<?php
/** Panel → «قرارداد سفارش»: seller details, articles, purchase rules (AIM_Contract). */
defined( 'ABSPATH' ) || exit;
$s     = AIM_Contract::all();
$text  = function ( $key, $label, $args = [] ) use ( $s ) {
	$args += [ 'help' => '', 'ltr' => false, 'span' => false, 'type' => 'text' ];
	printf( '<div class="form-row%s"><label class="form-label" for="ct-%s">%s</label>', $args['span'] ? ' span-2' : '', esc_attr( $key ), esc_html( $label ) );
	if ( 'textarea' === $args['type'] ) {
		printf( '<textarea id="ct-%1$s" class="field" rows="4" data-c="%1$s">%2$s</textarea>', esc_attr( $key ), esc_textarea( (string) $s[ $key ] ) );
	} else {
		printf( '<input id="ct-%1$s" class="field%3$s" data-c="%1$s" value="%2$s">', esc_attr( $key ), esc_attr( (string) $s[ $key ] ), $args['ltr'] ? ' ltr' : '' );
	}
	if ( $args['help'] ) {
		echo '<p class="form-help">' . esc_html( $args['help'] ) . '</p>';
	}
	echo '</div>';
};
$article = function ( array $row ) {
	echo '<div class="opt-row" data-row>';
	echo '<label class="opt-cell"><span>عنوان ماده</span><input class="field" data-sub="title" value="' . esc_attr( $row['title'] ?? '' ) . '"></label>';
	echo '<label class="opt-cell is-wide"><span>متن (هر خط یک بند)</span><textarea class="field" rows="4" data-sub="body">' . esc_textarea( $row['body'] ?? '' ) . '</textarea></label>';
	echo '<div class="opt-tools"><button type="button" class="btn btn-ghost btn-icon btn-sm" data-row-up aria-label="بالا">' . aim_icon( 'arrow-down-s-line' ) . '</button><button type="button" class="btn btn-ghost btn-icon btn-sm" data-row-remove aria-label="حذف">' . aim_icon( 'delete-bin-6-line' ) . '</button></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</div>';
};

aim_page_head( 'قرارداد سفارش' );
?>
<div class="notice inline"><?php echo aim_icon( 'information-line' ); // phpcs:ignore ?><p>خریدار پیش از پرداخت، پیش‌نویس قرارداد همان سفارش را می‌بیند و می‌پذیرد. هنگام ثبت سفارش نسخهٔ نهایی با شمارهٔ سفارش، زمان، IP و اثر انگشت دیجیتال ثابت ذخیره می‌شود؛ تغییر این متن روی سفارش‌های قبلی اثری ندارد. <b>متن پیش‌فرض نمونه است؛ پیش از استفاده آن را با مشاور حقوقی خود بازبینی کنید.</b></p></div>

<form data-contract>
	<section class="card">
		<div class="card-head"><h2>وضعیت و نسخه</h2></div>
		<div class="card-body form-grid">
			<div class="form-row span-2"><label class="switch"><input type="checkbox" data-c="enabled" <?php checked( (int) $s['enabled'], 1 ); ?>>قرارداد سفارش فعال باشد (بدون پذیرش آن، سفارش ثبت نمی‌شود)</label></div>
			<?php
			$text( 'title', 'عنوان قرارداد' );
			$text( 'version', 'نسخهٔ متن قرارداد', [ 'ltr' => true, 'help' => 'هر بار متن را تغییر دادید، نسخه را هم عوض کنید (مثلاً v2). در هر سفارش ثبت می‌شود.' ] );
			$text( 'consent_text', 'متن کنار تیک پذیرش', [ 'span' => true ] );
			$text( 'consent_help', 'توضیح زیر تیک پذیرش', [ 'span' => true ] );
			$text( 'rules_url', 'لینک «قوانین خرید»', [ 'ltr' => true, 'span' => true, 'help' => 'خالی = برگهٔ شرایط و قوانین ووکامرس یا حریم خصوصی.' ] );
			?>
		</div>
	</section>

	<section class="card">
		<div class="card-head"><h2>مشخصات فروشنده</h2></div>
		<div class="card-body form-grid">
			<?php
			$text( 'seller_legal', 'نام حقوقی (شرکت / شخص)' );
			$text( 'seller_brand', 'نام تجاری' );
			$text( 'national_id', 'شناسهٔ ملی / کد ملی', [ 'ltr' => true ] );
			$text( 'reg_no', 'شمارهٔ ثبت', [ 'ltr' => true ] );
			$text( 'phone', 'تلفن', [ 'ltr' => true, 'help' => 'خالی = تلفن پشتیبانی تنظیمات سایت.' ] );
			$text( 'email', 'ایمیل', [ 'ltr' => true ] );
			$text( 'address', 'نشانی', [ 'span' => true ] );
			$text( 'postal', 'کد پستی', [ 'ltr' => true ] );
			?>
			<div class="form-row span-2">
				<span class="form-label">لوگوی سربرگ قرارداد</span>
				<div class="opt-image" data-image><span class="opt-thumb"><?php echo $s['logo'] ? '<img src="' . esc_url( $s['logo'] ) . '" alt="">' : aim_icon( 'image-2-line' ); // phpcs:ignore ?></span><input class="field ltr" data-c="logo" value="<?php echo esc_attr( $s['logo'] ); ?>" placeholder="خالی = حرف اول نام تجاری"><button type="button" class="btn btn-light btn-sm" data-image-pick><?php echo aim_icon( 'image-2-line' ); // phpcs:ignore ?>انتخاب</button></div>
			</div>
		</div>
	</section>

	<section class="card">
		<div class="card-head"><h2>مواد قرارداد</h2></div>
		<div class="card-body">
			<p class="form-help" style="margin-top:0">هر خط متن یک بند شماره‌دار می‌شود. خط‌های ویژه جدول خودکار می‌سازند: <code>[parties]</code> مشخصات طرفین، <code>[specs]</code> مشخصات پلن‌های خریداری‌شده، <code>[payment]</code> مبلغ و شمارهٔ سفارش، <code>[guarantee]</code> ضمانت هر محصول. در متن: <code>{seller}</code> فروشنده، <code>{buyer}</code> خریدار، <code>{site}</code> نام سایت، <code>{website}</code> دامنه. مشخصات هر محصول (ضمانت، پیش‌نیاز، زمان تحویل…) در ویرایش محصول ← تب «نمایش در فروشگاه» است.</p>
			<div class="opt-list" data-list="articles">
				<?php foreach ( (array) $s['articles'] as $row ) { $article( (array) $row ); } ?>
			</div>
			<template data-row-template="articles"><?php $article( [] ); ?></template>
			<button type="button" class="btn btn-light btn-sm" data-row-add="articles"><?php echo aim_icon( 'add-line' ); // phpcs:ignore ?>افزودن ماده</button>
		</div>
	</section>

	<section class="card">
		<div class="card-head"><h2>پیوست: قوانین خرید</h2></div>
		<div class="card-body form-grid">
			<?php $text( 'rules', 'هر خط یک قانون', [ 'type' => 'textarea', 'span' => true, 'help' => 'نسخهٔ قوانین خودکار از روی همین متن ساخته و در هر سفارش ثبت می‌شود (الان: ' . AIM_Contract::rules_version() . ').' ] ); ?>
		</div>
	</section>

	<div class="save-bar">
		<button class="btn btn-primary btn-lg" type="submit"><?php echo aim_icon( 'save-3-line' ); // phpcs:ignore ?>ذخیرهٔ قرارداد</button>
	</div>
</form>
