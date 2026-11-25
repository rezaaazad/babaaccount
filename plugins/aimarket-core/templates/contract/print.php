<?php
/**
 * Stand-alone page with the frozen contract of an order (?aim_contract=ID).
 *
 * @var WC_Order $order
 * @var array    $contract AIM_Contract::of().
 */
defined( 'ABSPATH' ) || exit;
$css = AIM_URL . 'assets/css/contract.css?ver=' . AIM_VERSION . '.' . filemtime( AIM_DIR . 'assets/css/contract.css' );
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>قرارداد سفارش <?php echo esc_html( aim_fa_digits( $order->get_order_number() ) ); ?> — <?php bloginfo( 'name' ); ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
<link rel="stylesheet" href="<?php echo esc_url( $css ); ?>">
</head>
<body class="aimc-page">
	<div class="aimc-toolbar">
		<button type="button" onclick="window.print()">چاپ / ذخیرهٔ PDF</button>
		<span>اثر انگشت دیجیتال: <code dir="ltr"><?php echo esc_html( substr( $contract['hash'], 0, 24 ) ); ?>…</code> — <?php echo AIM_Contract::verify( $contract ) ? '<b class="aimc-ok">بدون تغییر از زمان پذیرش</b>' : '<b class="aimc-bad">متن با اثر انگشت نمی‌خواند</b>'; ?></span>
	</div>
	<?php echo $contract['html']; // phpcs:ignore WordPress.Security.EscapeOutput -- frozen output of AIM_Contract::render(), escaped when it was rendered. ?>
</body>
</html>
