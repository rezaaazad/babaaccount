<?php
/** «تنظیمات قالب»: texts of pages the free Elementor cannot build (AIM_Theme_Options). */
defined( 'ABSPATH' ) || exit;

$schema = AIM_Theme_Options::schema();
aim_page_head( 'تنظیمات قالب' );

if ( ! $schema ) {
	echo '<div class="card">';
	aim_empty( 'layout-masonry-line', 'قالب فعال تنظیمات قابل ویرایش ندارد', 'این بخش با قالب هوش‌مارکت کار می‌کند.' );
	echo '</div>';
	return;
}
$values = AIM_Theme_Options::values();
$value  = fn( $key, $field ) => array_key_exists( $key, $values ) && ( ( '' !== $values[ $key ] && [] !== $values[ $key ] ) || ( [] === $values[ $key ] && ! empty( $field['allow_empty'] ) ) ) ? $values[ $key ] : $field['default'];
$first  = array_key_first( $schema['groups'] );

/**
 * One input for a field or list sub-field. $attr is data-opt="key" or data-sub="sub";
 * $placeholder is the default shown in an empty box.
 */
$control = function ( array $def, $attr, $val, $id = '', $placeholder = '' ) {
	$id_attr = $id ? ' id="' . esc_attr( $id ) . '"' : '';
	$ph      = '' !== (string) $placeholder ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '';
	switch ( $def['type'] ) {
		case 'textarea':
			printf( '<textarea%s class="field" rows="3" %s%s>%s</textarea>', $id_attr, $attr, $ph, esc_textarea( $val ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts.
			break;
		case 'code':
			printf( '<textarea%s class="field mono ltr" rows="3" %s%s spellcheck="false">%s</textarea>', $id_attr, $attr, $ph, esc_textarea( $val ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			break;
		case 'select':
			printf( '<select%s class="field" %s>', $id_attr, $attr ); // phpcs:ignore WordPress.Security.EscapeOutput
			foreach ( (array) $def['options'] as $k => $label ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( (string) $val, (string) $k, false ), esc_html( $label ) );
			}
			echo '</select>';
			break;
		case 'image':
			printf(
				'<div class="opt-image" data-image><span class="opt-thumb">%s</span><input%s class="field ltr" %s value="%s"%s><button type="button" class="btn btn-light btn-sm" data-image-pick>%sانتخاب</button></div>',
				$val ? '<img src="' . esc_url( $val ) . '" alt="">' : aim_icon( 'image-2-line' ), // phpcs:ignore WordPress.Security.EscapeOutput
				$id_attr, $attr, esc_attr( $val ), $ph, aim_icon( 'image-2-line' ) // phpcs:ignore WordPress.Security.EscapeOutput
			);
			break;
		default:
			printf( '<input%s class="field%s" %s value="%s"%s>', $id_attr, 'url' === $def['type'] ? ' ltr' : '', $attr, esc_attr( $val ), $ph ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
};
$row_html = function ( array $field, array $row ) use ( $control ) {
	echo '<div class="opt-row" data-row>';
	foreach ( $field['fields'] as $sub => $def ) {
		$def = AIM_Theme_Options::sub( $def );
		echo '<label class="opt-cell' . ( in_array( $def['type'], [ 'code', 'textarea' ], true ) ? ' is-wide' : '' ) . '"><span>' . esc_html( $def['label'] ) . '</span>';
		$control( $def, 'data-sub="' . esc_attr( $sub ) . '"', (string) ( $row[ $sub ] ?? '' ) );
		echo '</label>';
	}
	echo '<div class="opt-tools">';
	echo '<button type="button" class="btn btn-ghost btn-icon btn-sm" data-row-up aria-label="بالا">' . aim_icon( 'arrow-down-s-line' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<button type="button" class="btn btn-ghost btn-icon btn-sm" data-row-remove aria-label="حذف">' . aim_icon( 'delete-bin-6-line' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</div></div>';
};
?>
<div class="notice inline"><?php echo aim_icon( 'information-line' ); // phpcs:ignore ?><p>متن صفحه‌هایی که با المنتور رایگان ساخته نمی‌شوند (صفحهٔ محصول، فروشگاه، سبد خرید، پرداخت و…)، منوی سایت، فوتر و نمادهای اعتماد اینجا ویرایش می‌شوند. صفحهٔ اصلی و سربرگ را از <a href="<?php echo esc_url( aim_panel_url( 'admin', 'settings' ) . '#look' ); ?>">تنظیمات فروشگاه ← ظاهر سایت</a> با المنتور ویرایش کنید. کادر خالی = متن پیش‌فرض.</p></div>

<form data-theme-options data-tabs-root>
	<nav class="seg-tabs" data-tabs role="tablist">
		<?php foreach ( $schema['groups'] as $gkey => $group ) : ?>
			<button type="button" class="seg-tab<?php echo $gkey === $first ? ' is-active' : ''; ?>" data-tab="<?php echo esc_attr( $gkey ); ?>" role="tab" aria-selected="<?php echo $gkey === $first ? 'true' : 'false'; ?>"><?php echo aim_icon( $group['icon'] ?? 'settings-3-line' ); // phpcs:ignore ?><?php echo esc_html( $group['title'] ); ?></button>
		<?php endforeach; ?>
	</nav>

	<?php foreach ( $schema['groups'] as $gkey => $group ) : ?>
		<section class="card" data-tab-panel="<?php echo esc_attr( $gkey ); ?>" <?php echo $gkey === $first ? '' : 'hidden'; ?>>
			<div class="card-head"><h2><?php echo esc_html( $group['title'] ); ?></h2></div>
			<div class="card-body form-grid">
				<?php foreach ( $group['fields'] as $key => $field ) : ?>
					<?php $id = 'to-' . $key; ?>
					<?php if ( 'list' === $field['type'] ) : ?>
						<div class="form-row span-2">
							<span class="form-label"><?php echo esc_html( $field['label'] ); ?></span>
							<?php if ( ! empty( $field['help'] ) ) : ?><p class="form-help"><?php echo esc_html( $field['help'] ); ?></p><?php endif; ?>
							<div class="opt-list" data-list="<?php echo esc_attr( $key ); ?>">
								<?php foreach ( (array) $value( $key, $field ) as $row ) { $row_html( $field, (array) $row ); } ?>
							</div>
							<template data-row-template="<?php echo esc_attr( $key ); ?>"><?php $row_html( $field, [] ); ?></template>
							<button type="button" class="btn btn-light btn-sm" data-row-add="<?php echo esc_attr( $key ); ?>"><?php echo aim_icon( 'add-line' ); // phpcs:ignore ?>افزودن ردیف</button>
						</div>
					<?php elseif ( 'heading' === $field['type'] ) : ?>
						<h3 class="opt-heading span-2"><?php echo esc_html( $field['label'] ); ?></h3>
					<?php else : ?>
						<div class="form-row<?php echo in_array( $field['type'], [ 'textarea', 'code', 'image' ], true ) ? ' span-2' : ''; ?>">
							<label class="form-label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
							<?php $control( $field, 'data-opt="' . esc_attr( $key ) . '"', (string) ( $values[ $key ] ?? '' ), $id, is_scalar( $field['default'] ) ? $field['default'] : '' ); ?>
							<?php if ( ! empty( $field['help'] ) ) : ?><p class="form-help"><?php echo esc_html( $field['help'] ); ?></p><?php endif; ?>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endforeach; ?>

	<div class="save-bar">
		<button class="btn btn-primary btn-lg" type="submit"><?php echo aim_icon( 'save-3-line' ); // phpcs:ignore ?>ذخیرهٔ تنظیمات قالب</button>
	</div>
</form>
