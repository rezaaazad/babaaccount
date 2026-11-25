<?php
/**
 * A native wp-admin screen inside the panel (see AIM_WP_Embed).
 *
 * @var array  $item       Current menu item.
 * @var string $embed_path Admin path to show, e.g. "post-new.php?post_type=product".
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="embed" data-embed data-section="<?php echo esc_attr( $item['key'] ); ?>" data-default="<?php echo esc_attr( $item['wp'] ?? '' ); ?>">
	<div class="embed-bar">
		<button type="button" class="btn btn-light btn-icon btn-sm" data-embed-back aria-label="بازگشت"><?php echo aim_icon( 'arrow-right-line' ); // phpcs:ignore ?></button>
		<h1 class="embed-title" data-embed-title><?php echo esc_html( $item['label'] ); ?></h1>
		<span class="spacer"></span>
		<button type="button" class="btn btn-light btn-icon btn-sm" data-embed-reload aria-label="بارگذاری دوباره"><?php echo aim_icon( 'refresh-line' ); // phpcs:ignore ?></button>
	</div>
	<div class="embed-frame">
		<div class="embed-loading" data-embed-loading><span class="spinner-ring"></span>در حال بارگذاری…</div>
		<iframe src="<?php echo esc_url( admin_url( $embed_path ) ); ?>" title="<?php echo esc_attr( $item['label'] ); ?>" data-embed-frame></iframe>
	</div>
</div>
