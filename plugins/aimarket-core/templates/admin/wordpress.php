<?php
/** Every wp-admin menu entry (core and plugins), each opening inside the panel. */
defined( 'ABSPATH' ) || exit;
$groups = AIM_WP_Embed::wp_menu();

aim_page_head( 'همهٔ بخش‌های وردپرس' );
?>
<div class="notice inline"><?php echo aim_icon( 'information-line' ); // phpcs:ignore ?><p>همهٔ بخش‌های وردپرس و افزونه‌های نصب‌شده اینجا هستند و داخل همین پنل باز می‌شوند. افزونهٔ جدیدی که نصب کنید هم خودکار به این فهرست اضافه می‌شود.</p></div>
<div class="wp-grid">
	<?php foreach ( $groups as $g ) : ?>
		<section class="card wp-group">
			<a class="wp-group-head" href="<?php echo esc_url( AIM_WP_Embed::url( $g['path'] ) ); ?>">
				<?php if ( str_starts_with( $g['icon'], 'dashicons-' ) ) : ?>
					<span class="wp-ico dashicons <?php echo esc_attr( $g['icon'] ); ?>"></span>
				<?php else : ?>
					<span class="wp-ico"><?php echo aim_icon( 'apps-2-line' ); // phpcs:ignore ?></span>
				<?php endif; ?>
				<b><?php echo esc_html( $g['label'] ); ?></b>
				<?php echo aim_icon( 'arrow-left-s-line' ); // phpcs:ignore ?>
			</a>
			<?php if ( count( $g['children'] ) > 1 ) : ?>
				<ul>
					<?php foreach ( $g['children'] as $c ) : ?>
						<li><a href="<?php echo esc_url( AIM_WP_Embed::url( $c['path'] ) ); ?>"><?php echo esc_html( $c['label'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
	<?php endforeach; ?>
</div>
