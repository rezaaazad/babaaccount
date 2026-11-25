<?php
/**
 * WooCommerce error notices in the store style.
 *
 * @var array $notices
 */
defined( 'ABSPATH' ) || exit;
if ( ! $notices ) {
	return;
}
?>
<div class="sf-notices" role="<?php echo 'error' === 'error' ? 'alert' : 'status'; ?>">
	<?php foreach ( $notices as $notice ) : ?>
		<div class="sf-notice is-err"<?php echo wc_get_notice_data_attr( $notice ); // phpcs:ignore ?>>
			<i class="ic-error-warning-fill" aria-hidden="true"></i>
			<div><?php echo wc_kses_notice( $notice['notice'] ); ?></div>
		</div>
	<?php endforeach; ?>
</div>
