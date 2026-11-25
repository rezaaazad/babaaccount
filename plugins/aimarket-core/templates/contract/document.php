<?php
/**
 * The contract document (AIM_Contract::render()). Self-contained markup styled by
 * assets/css/contract.css, so the same HTML works in the checkout window, the
 * print page and the frozen copy kept with the order.
 *
 * @var array    $data     AIM_Contract::from_cart() / from_order().
 * @var array    $accept   Acceptance record (final copy only).
 * @var array    $s        Contract settings.
 * @var callable $text     Fills {seller}, {site}, {website}, {buyer}.
 * @var array    $versions contract / consent / rules.
 */
defined( 'ABSPATH' ) || exit;
$seller = $data['seller'];
$buyer  = $data['buyer'];
$final  = 'final' === $data['status'];
$money  = fn( $n ) => aim_fa_digits( number_format( (float) $n ) ) . ' ' . aim_currency_label();
$num    = fn( $n ) => aim_fa_digits( (string) $n );

$block = function ( $name ) use ( $data, $seller, $buyer, $money, $num, $final ) {
	switch ( $name ) {
		case 'parties':
			$rows = [
				'فروشنده' => array_filter( [
					'نام'          => $seller['legal'] . ( $seller['brand'] !== $seller['legal'] ? ' (' . $seller['brand'] . ')' : '' ),
					'شناسهٔ ملی'   => $num( $seller['national_id'] ),
					'شمارهٔ ثبت'   => $num( $seller['reg_no'] ),
					'وب‌سایت'      => $seller['website'],
					'تلفن'         => $num( $seller['phone'] ),
					'ایمیل'        => $seller['email'],
					'نشانی'        => $num( $seller['address'] ),
				] ),
				'خریدار'  => array_filter( [
					'نام'              => $buyer['name'],
					'تلفن همراه'       => $num( $buyer['phone'] ),
					'ایمیل'            => $buyer['email'],
					'شناسهٔ کاربری'    => $buyer['user_id'] ? $num( $buyer['user_id'] ) : '',
					'IP هنگام خرید'    => $buyer['ip'],
				] ),
			];
			echo '<div class="aimc-parties">';
			foreach ( $rows as $who => $fields ) {
				echo '<div class="aimc-party"><b class="aimc-party-title">' . esc_html( $who ) . '</b><dl>';
				foreach ( $fields as $k => $v ) {
					$ltr = in_array( $k, [ 'وب‌سایت', 'ایمیل', 'IP هنگام خرید' ], true );
					echo '<div><dt>' . esc_html( $k ) . '</dt><dd' . ( $ltr ? ' dir="ltr"' : '' ) . '>' . esc_html( $v ) . '</dd></div>';
				}
				echo '</dl></div>';
			}
			echo '</div>';
			break;

		case 'specs':
			foreach ( $data['items'] as $item ) {
				echo '<div class="aimc-item"><b class="aimc-item-title">' . esc_html( $item['name'] ) . ( $item['qty'] > 1 ? ' × ' . esc_html( $num( $item['qty'] ) ) : '' ) . '</b><dl class="aimc-specs">';
				foreach ( $item['specs'] as $key => [ $label, $value ] ) {
					if ( 'guarantee' === $key ) {
						continue; // article 9
					}
					echo '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd></div>';
				}
				echo '</dl></div>';
			}
			break;

		case 'payment':
			echo '<table class="aimc-table"><tbody>';
			echo '<tr><th>شمارهٔ سفارش</th><td>' . esc_html( $final ? $num( $data['number'] ) : 'هنگام ثبت سفارش درج می‌شود' ) . '</td></tr>';
			foreach ( $data['items'] as $item ) {
				echo '<tr><th>' . esc_html( $item['name'] . ( $item['plan'] ? ' — ' . $item['plan'] : '' ) . ( $item['qty'] > 1 ? ' × ' . $num( $item['qty'] ) : '' ) ) . '</th><td>' . esc_html( $money( $item['total'] ) ) . '</td></tr>';
			}
			if ( $data['discount'] > 0 ) {
				echo '<tr><th>تخفیف</th><td>−' . esc_html( $money( $data['discount'] ) ) . '</td></tr>';
			}
			echo '<tr class="is-total"><th>مبلغ کل قرارداد</th><td>' . esc_html( $money( $data['total'] ) ) . '</td></tr>';
			echo '</tbody></table>';
			break;

		case 'guarantee':
			echo '<ul class="aimc-list">';
			foreach ( $data['items'] as $item ) {
				$g = $item['specs']['guarantee'][1] ?? 'طبق شرایط درج‌شده در صفحهٔ محصول';
				echo '<li><b>' . esc_html( $item['name'] ) . ':</b> ' . esc_html( $g ) . '</li>';
			}
			echo '</ul>';
			break;
	}
};
?>
<article class="aimc-doc<?php echo $final ? ' is-final' : ' is-draft'; ?>">
	<header class="aimc-head">
		<div class="aimc-brand">
			<?php if ( $seller['logo'] ) : ?><img src="<?php echo esc_url( $seller['logo'] ); ?>" alt=""><?php else : ?><span class="aimc-mark"><?php echo esc_html( mb_substr( $seller['brand'], 0, 1 ) ); ?></span><?php endif; ?>
			<span><b><?php echo esc_html( $seller['brand'] ); ?></b><?php if ( $seller['legal'] !== $seller['brand'] ) : ?><small><?php echo esc_html( $seller['legal'] ); ?></small><?php endif; ?></span>
		</div>
		<span class="aimc-basmala">بسمه تعالی</span>
		<dl class="aimc-meta">
			<div><dt>شماره:</dt><dd><?php echo esc_html( $final ? $num( $data['number'] ) : 'پس از ثبت سفارش' ); ?></dd></div>
			<div><dt>تاریخ:</dt><dd><?php echo esc_html( aim_jdate( $data['date'] ) ); ?></dd></div>
			<div><dt>پیوست:</dt><dd><?php echo trim( (string) $s['rules'] ) ? 'دارد' : 'ندارد'; ?></dd></div>
		</dl>
	</header>

	<h1 class="aimc-title"><?php echo esc_html( $s['title'] ); ?></h1>
	<p class="aimc-version">
		<span dir="ltr"><?php echo esc_html( $versions['contract'] ); ?></span>
		<span class="aimc-state"><?php echo $final ? 'نسخهٔ نهایی پذیرفته‌شده' : 'پیش‌نویس پیش از پرداخت'; ?></span>
	</p>
	<?php if ( ! $final ) : ?>
		<p class="aimc-note">این پیش‌نویس از سبد خرید و اطلاعات فرم شما ساخته شده است. نسخهٔ نهایی هنگام ثبت سفارش با شمارهٔ سفارش، زمان و IP پذیرش ثابت می‌شود و در حساب کاربری ← «قراردادها» قابل مشاهده و چاپ است.</p>
	<?php endif; ?>

	<?php foreach ( array_values( (array) $s['articles'] ) as $i => $article ) : ?>
		<section class="aimc-article">
			<h2><span>مادهٔ <?php echo esc_html( $num( $i + 1 ) ); ?></span><?php echo esc_html( $article['title'] ); ?></h2>
			<?php
			$n = 0;
			foreach ( preg_split( '/\R/u', (string) $article['body'] ) as $line ) {
				$line = trim( $line );
				if ( '' === $line ) {
					continue;
				}
				if ( preg_match( '/^\[(parties|specs|payment|guarantee)\]$/', $line, $m ) ) {
					$block( $m[1] );
					continue;
				}
				++$n;
				echo '<p><span class="aimc-clause">' . esc_html( $num( ( $i + 1 ) . '-' . $n ) ) . '</span>' . esc_html( $text( $line ) ) . '</p>';
			}
			?>
		</section>
	<?php endforeach; ?>

	<?php if ( trim( (string) $s['rules'] ) ) : ?>
		<section class="aimc-article aimc-annex">
			<h2><span>پیوست</span>قوانین خرید</h2>
			<ul class="aimc-list">
				<?php foreach ( preg_split( '/\R/u', (string) $s['rules'] ) as $rule ) : ?>
					<?php if ( trim( $rule ) ) : ?><li><?php echo esc_html( $text( trim( $rule ) ) ); ?></li><?php endif; ?>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<footer class="aimc-sign">
		<dl class="aimc-versions">
			<div><dt>نسخهٔ متن قرارداد</dt><dd dir="ltr"><?php echo esc_html( $versions['contract'] ); ?></dd></div>
			<div><dt>نسخهٔ متن پذیرش</dt><dd dir="ltr"><?php echo esc_html( $versions['consent'] ); ?></dd></div>
			<div><dt>نسخهٔ قوانین خرید</dt><dd dir="ltr"><?php echo esc_html( $versions['rules'] ); ?></dd></div>
		</dl>
		<div class="aimc-parties-sign">
			<div>
				<b>خریدار</b>
				<span><?php echo esc_html( $buyer['name'] ); ?></span>
				<?php if ( $accept ) : ?>
					<small>پذیرش: <?php echo esc_html( $accept['method'] ); ?></small>
					<small>زمان: <?php echo esc_html( aim_jdate( $accept['time'], true ) ); ?></small>
					<small>IP: <span dir="ltr"><?php echo esc_html( $accept['ip'] ); ?></span></small>
				<?php else : ?>
					<small>پذیرش با تیک قرارداد و ثبت سفارش</small>
				<?php endif; ?>
			</div>
			<div>
				<b>فروشنده</b>
				<span><?php echo esc_html( $seller['legal'] ); ?></span>
				<small>امضای الکترونیکی (داده‌پیام)</small>
				<?php if ( ! empty( $accept['hash'] ) ) : ?>
					<small>اثر انگشت دیجیتال: <span dir="ltr" class="aimc-hash"><?php echo esc_html( $accept['hash'] ); ?></span></small>
				<?php endif; ?>
			</div>
		</div>
	</footer>
</article>
