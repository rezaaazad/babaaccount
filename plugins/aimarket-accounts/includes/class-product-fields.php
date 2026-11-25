<?php
defined( 'ABSPATH' ) || exit;

/** «اکانت و تحویل» tab on the WooCommerce product editor (simple and variable products). */
class AIM_Product_Fields {

	public static function init() {
		add_filter( 'woocommerce_product_data_tabs', [ __CLASS__, 'tab' ] );
		add_action( 'woocommerce_product_data_panels', [ __CLASS__, 'panel' ] );
		add_action( 'woocommerce_admin_process_product_object', [ __CLASS__, 'save' ] );
		add_action( 'woocommerce_variation_options_pricing', [ __CLASS__, 'variation_field' ], 10, 3 );
		add_action( 'woocommerce_save_product_variation', [ __CLASS__, 'save_variation' ], 10, 2 );
	}

	public static function tab( $tabs ) {
		$tabs['aim_account'] = [
			'label'    => 'اکانت و تحویل',
			'target'   => 'aim_account_data',
			'priority' => 15,
		];
		return $tabs;
	}

	public static function panel() {
		global $post;
		echo '<div id="aim_account_data" class="panel woocommerce_options_panel">';
		woocommerce_wp_checkbox( [
			'id'          => '_aim_account',
			'label'       => 'محصول اکانت است',
			'description' => 'با فعال شدن، محصول مجازی می‌شود و پس از پرداخت تحویل داده می‌شود.',
		] );
		woocommerce_wp_select( [
			'id'          => '_aim_delivery',
			'label'       => 'روش تحویل',
			'options'     => [
				'auto'   => 'خودکار از انبار اکانت',
				'manual' => 'دستی (فعال‌سازی روی ایمیل مشتری)',
			],
			'desc_tip'    => true,
			'description' => 'در حالت خودکار، موجودی محصول همیشه برابر تعداد اکانت‌های آماده است.',
		] );
		woocommerce_wp_text_input( [
			'id'                => '_aim_duration_days',
			'label'             => 'مدت اعتبار (روز)',
			'type'              => 'number',
			'custom_attributes' => [ 'min' => 0, 'step' => 1 ],
			'placeholder'       => 'مثال: ۳۰',
			'desc_tip'          => true,
			'description'       => 'برای محصول متغیر می‌توانید برای هر متغیر مدت جدا تعیین کنید. صفر یعنی بدون انقضا.',
		] );
		woocommerce_wp_text_input( [
			'id'          => '_aim_type_label',
			'label'       => 'نوع اکانت',
			'placeholder' => 'مثال: اختصاصی / اشتراکی / روی ایمیل شما',
		] );
		woocommerce_wp_text_input( [
			'id'          => '_aim_delivery_label',
			'label'       => 'متن زمان تحویل',
			'placeholder' => 'خالی = «آنی» برای تحویل خودکار',
		] );
		if ( $post && AIM_Inventory::is_account_product( $post->ID ) ) {
			printf(
				'<p class="form-field"><a class="button" href="%s" target="_top">مدیریت انبار این محصول در پنل</a></p>',
				esc_url( aim_panel_url( 'admin', 'accounts' ) )
			);
		}
		echo '</div>';
	}

	public static function save( WC_Product $product ) {
		$is = isset( $_POST['_aim_account'] ) ? 'yes' : 'no'; // phpcs:ignore WordPress.Security.NonceVerification -- WooCommerce verifies the product save nonce.
		$product->update_meta_data( '_aim_account', $is );
		$product->update_meta_data( '_aim_delivery', ( $_POST['_aim_delivery'] ?? 'auto' ) === 'manual' ? 'manual' : 'auto' ); // phpcs:ignore
		$product->update_meta_data( '_aim_duration_days', absint( $_POST['_aim_duration_days'] ?? 0 ) ); // phpcs:ignore
		foreach ( [ '_aim_type_label', '_aim_delivery_label' ] as $key ) {
			$product->update_meta_data( $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ?? '' ) ) ); // phpcs:ignore
		}
		if ( 'yes' === $is ) {
			$product->set_virtual( true );
			$product->set_sold_individually( false );
		}
	}

	public static function variation_field( $loop, $variation_data, $variation ) {
		woocommerce_wp_text_input( [
			'id'            => "_aim_duration_days_{$loop}",
			'name'          => "_aim_duration_days[{$loop}]",
			'value'         => get_post_meta( $variation->ID, '_aim_duration_days', true ),
			'label'         => 'مدت اعتبار (روز)',
			'type'          => 'number',
			'wrapper_class' => 'form-row form-row-first',
			'placeholder'   => 'از محصول اصلی',
		] );
	}

	public static function save_variation( $variation_id, $loop ) {
		// phpcs:ignore WordPress.Security.NonceVerification -- WooCommerce verifies the variations save nonce.
		if ( isset( $_POST['_aim_duration_days'][ $loop ] ) ) {
			update_post_meta( $variation_id, '_aim_duration_days', absint( $_POST['_aim_duration_days'][ $loop ] ) ); // phpcs:ignore
		}
	}
}
