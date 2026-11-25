<?php
defined( 'ABSPATH' ) || exit;

/**
 * Product editor tab «نمایش در فروشگاه»: a short tagline, a gold label for
 * the product and a gold label per variation (plan). Themes read the meta:
 * _aim_subtitle, _aim_badge, and _aim_plan_badge on variations.
 */
class AIM_Product_Display {

	/** Product facts printed in the order contract (AIM_Contract): meta key => [ label, placeholder ]. */
	const CONTRACT_FIELDS = [
		'_aim_c_activation' => [ 'روش فعال‌سازی', 'مثال: روی ایمیل خود خریدار' ],
		'_aim_c_delivery'   => [ 'زمان تحویل', 'مثال: کمتر از ۳ ساعت' ],
		'_aim_c_guarantee'  => [ 'ضمانت', 'مثال: تا پایان اشتراک؛ تعویض در صورت مسدود شدن' ],
		'_aim_c_prereq'     => [ 'پیش‌نیاز', 'مثال: هنگام فعال‌سازی در حساب خود وارد باشید' ],
		'_aim_c_limits'     => [ 'محدودیت مهم', 'مثال: سقف استفاده را سازندهٔ سرویس تعیین می‌کند' ],
	];

	public static function init() {
		add_filter( 'woocommerce_product_data_tabs', [ __CLASS__, 'tab' ] );
		add_action( 'woocommerce_product_data_panels', [ __CLASS__, 'panel' ] );
		add_action( 'woocommerce_admin_process_product_object', [ __CLASS__, 'save' ] );
		add_action( 'woocommerce_variation_options_pricing', [ __CLASS__, 'variation_field' ], 20, 3 );
		add_action( 'woocommerce_save_product_variation', [ __CLASS__, 'save_variation' ], 10, 2 );
	}

	public static function tab( $tabs ) {
		$tabs['aim_display'] = [
			'label'    => 'نمایش در فروشگاه',
			'target'   => 'aim_display_data',
			'priority' => 14,
		];
		return $tabs;
	}

	public static function panel() {
		echo '<div id="aim_display_data" class="panel woocommerce_options_panel">';
		woocommerce_wp_text_input( [
			'id'          => '_aim_subtitle',
			'label'       => 'کاربرد کوتاه',
			'placeholder' => 'مثال: گفت‌وگو و نوشتن',
			'desc_tip'    => true,
			'description' => 'زیر نام محصول در کارت‌های فروشگاه نمایش داده می‌شود.',
		] );
		woocommerce_wp_text_input( [
			'id'                => '_aim_badge',
			'label'             => 'برچسب طلایی محصول',
			'placeholder'       => 'مثال: محبوب',
			'desc_tip'          => true,
			'description'       => 'روی کارت محصول و بالای صفحهٔ محصول با رنگ طلایی نمایش داده می‌شود. خالی = بدون برچسب.',
			'custom_attributes' => [ 'list' => 'aim-badge-suggestions', 'maxlength' => 24 ],
		] );
		self::suggestions_list();
		echo '<p class="form-field"><strong>مشخصات در قرارداد سفارش</strong><br><span class="description">در قرارداد این محصول (مادهٔ مشخصات خدمت و ضمانت) نوشته می‌شود. خالی = آن ردیف در قرارداد نمی‌آید.</span></p>';
		foreach ( self::CONTRACT_FIELDS as $key => [ $label, $ph ] ) {
			woocommerce_wp_text_input( [ 'id' => $key, 'label' => $label, 'placeholder' => $ph ] );
		}
		echo '</div>';
	}

	public static function save( WC_Product $product ) {
		foreach ( array_merge( [ '_aim_subtitle', '_aim_badge' ], array_keys( self::CONTRACT_FIELDS ) ) as $key ) {
			$value = sanitize_text_field( wp_unslash( $_POST[ $key ] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- WooCommerce verifies the product save nonce.
			// The badge has a 24-character limit in the field (like the plan badge); enforce it on save too.
			$product->update_meta_data( $key, '_aim_badge' === $key ? mb_substr( $value, 0, 24 ) : $value );
		}
	}

	public static function variation_field( $loop, $variation_data, $variation ) {
		self::suggestions_list();
		woocommerce_wp_text_input( [
			'id'                => "_aim_plan_badge_{$loop}",
			'name'              => "_aim_plan_badge[{$loop}]",
			'value'             => get_post_meta( $variation->ID, '_aim_plan_badge', true ),
			'label'             => 'برچسب طلایی این پلن',
			'wrapper_class'     => 'form-row form-row-last',
			'placeholder'       => 'مثال: پیشنهادی',
			'custom_attributes' => [ 'list' => 'aim-badge-suggestions', 'maxlength' => 24 ],
		] );
	}

	public static function save_variation( $variation_id, $loop ) {
		// phpcs:ignore WordPress.Security.NonceVerification -- WooCommerce verifies the variations save nonce.
		if ( isset( $_POST['_aim_plan_badge'][ $loop ] ) ) {
			update_post_meta( $variation_id, '_aim_plan_badge', mb_substr( sanitize_text_field( wp_unslash( $_POST['_aim_plan_badge'][ $loop ] ) ), 0, 24 ) ); // phpcs:ignore
		}
	}

	/** <datalist> with suggested label texts, shared by the product and variation fields. */
	public static function suggestions_list() {
		static $printed = false;
		if ( $printed ) {
			return;
		}
		$printed = true;
		$items   = function_exists( 'aim_t_badge_suggestions' ) ? aim_t_badge_suggestions() : [ 'پیشنهادی', 'محبوب', 'پرفروش', 'به‌صرفه‌ترین', 'جدید', 'ویژه' ];
		echo '<datalist id="aim-badge-suggestions">';
		foreach ( $items as $t ) {
			echo '<option value="' . esc_attr( $t ) . '">';
		}
		echo '</datalist>';
	}
}
