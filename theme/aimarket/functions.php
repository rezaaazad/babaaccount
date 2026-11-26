<?php
defined( 'ABSPATH' ) || exit;

require_once get_template_directory() . '/inc/components.php';
require_once get_template_directory() . '/inc/cache.php';
require_once get_template_directory() . '/inc/art.php';
require_once get_template_directory() . '/inc/minicart.php';
require_once get_template_directory() . '/inc/sections.php';
require_once get_template_directory() . '/inc/brand.php';
require_once get_template_directory() . '/inc/menu.php';
require_once get_template_directory() . '/inc/footer.php';
require_once get_template_directory() . '/inc/archive.php';
require_once get_template_directory() . '/inc/options.php';
require_once get_template_directory() . '/inc/setup.php';
require_once get_template_directory() . '/inc/hardening.php';
require_once get_template_directory() . '/inc/seo.php';
require_once get_template_directory() . '/inc/elementor/loader.php';

// Footer "get discount codes" signup: stores unique phone numbers in an option.
add_action( 'rest_api_init', function () {
	register_rest_route( 'aimarket/v1', '/newsletter', [
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $req ) {
			if ( $req->get_param( 'website' ) ) {
				return new WP_REST_Response( [ 'ok' => false ], 400 );
			}
			$phone = function_exists( 'aim_normalize_phone' ) ? aim_normalize_phone( $req->get_param( 'phone' ) ) : '';
			if ( ! $phone ) {
				return new WP_REST_Response( [ 'ok' => false, 'message' => 'شمارهٔ موبایل معتبر نیست.' ], 400 );
			}
			$list = get_option( 'aim_newsletter', [] );
			$ip = function_exists( 'aim_client_ip' ) ? aim_client_ip() : sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
			$key = 'aim_nl_' . md5( $ip );
			$number_key = 'aim_nl_phone_' . md5( $phone );
			if ( (int) get_transient( $key ) >= 5 || get_transient( $number_key ) || ( ! isset( $list[ $phone ] ) && count( $list ) >= 20000 ) ) {
				return new WP_REST_Response( [ 'ok' => false, 'message' => 'لطفاً کمی بعد دوباره تلاش کنید.' ], 429 );
			}
			set_transient( $key, (int) get_transient( $key ) + 1, HOUR_IN_SECONDS );
			set_transient( $number_key, 1, HOUR_IN_SECONDS );
			if ( ! isset( $list[ $phone ] ) ) {
				$list[ $phone ] = time();
				update_option( 'aim_newsletter', $list, false );
			}
			return [ 'ok' => true, 'message' => 'ثبت شد! تخفیف‌های بعدی را برایت پیامک می‌کنیم.' ];
		},
	] );
} );

add_action( 'after_setup_theme', function () {
	load_theme_textdomain( 'aimarket', get_template_directory() . '/languages' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'responsive-embeds' );
	$GLOBALS['content_width'] = 1200;
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'custom-logo', [ 'height' => 64, 'width' => 220, 'flex-width' => true ] );
	add_theme_support( 'woocommerce' );
	add_theme_support( 'html5', [ 'search-form', 'gallery', 'caption', 'style', 'script', 'comment-form', 'comment-list' ] );
	register_nav_menus( [
		'header' => 'منوی هدر',
		'footer' => 'منوی فوتر',
	] );
} );

add_action( 'wp_enqueue_scripts', function () {
	$dir = get_template_directory();
	$uri = get_template_directory_uri();
	wp_enqueue_style( 'aim-font', $uri . '/assets/css/fonts.css', [], filemtime( $dir . '/assets/css/fonts.css' ) );
	wp_enqueue_style( 'aim-icons', $uri . '/assets/css/icons.css', [], filemtime( $dir . '/assets/css/icons.css' ) );
	if ( defined( 'AIM_URL' ) && defined( 'AIM_DIR' ) && defined( 'AIM_VERSION' ) ) {
		wp_enqueue_style( 'aim-panel', AIM_URL . 'assets/css/panel.css', [], AIM_VERSION . '.' . filemtime( AIM_DIR . 'assets/css/panel.css' ) );
	}
	wp_enqueue_style( 'aim-theme', $uri . '/assets/css/theme.css', [], filemtime( $dir . '/assets/css/theme.css' ) );
	wp_enqueue_script( 'aim-theme', $uri . '/assets/js/theme.js', [], filemtime( $dir . '/assets/js/theme.js' ), true );
	wp_localize_script( 'aim-theme', 'AIMT', [
		'rest'     => esc_url_raw( rest_url( 'aimarket/v1/' ) ),
		'nonce'    => '',
		'nonceUrl' => admin_url( 'admin-ajax.php?action=aim_theme_nonce' ),
		'noDrawer' => ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) ? 1 : 0,
	] );
} );

function aim_theme_nonce() {
	nocache_headers();
	header( 'Cache-Control: no-store, private' );
	header( 'Vary: Cookie', false );
	wp_send_json_success( [ 'nonce' => wp_create_nonce( 'wp_rest' ) ] );
}
add_action( 'wp_ajax_aim_theme_nonce', 'aim_theme_nonce' );
add_action( 'wp_ajax_nopriv_aim_theme_nonce', 'aim_theme_nonce' );

// Apply saved colour mode before paint.
add_action( 'wp_head', function () {
	echo function_exists( 'aim_color_mode_script' ) ? aim_color_mode_script() : "<script>try{var m=localStorage.getItem('aim-theme');if(m)document.documentElement.dataset.theme=m;}catch(e){}</script>\n"; // phpcs:ignore
}, 1 );

add_filter( 'body_class', fn( $c ) => array_merge( $c, [ 'aim' ] ) );
add_filter( 'elementor/frontend/print_google_fonts', '__return_false' );
add_action( 'wp_head', static function () {
	foreach ( [ 'Regular', 'Bold' ] as $weight ) {
		echo '<link rel="preload" as="font" type="font/woff2" crossorigin href="' . esc_url( get_template_directory_uri() . '/assets/fonts/Vazirmatn-' . $weight . '.woff2' ) . '">' . "\n";
	}
}, 2 );

// Shop: 3 columns, 24 per page; no sidebar.
add_filter( 'loop_shop_columns', fn() => 3 );
add_filter( 'loop_shop_per_page', fn() => 24 );

// Archive filters: ?instock=1 and ?onsale=1 (price range and sorting are handled by WooCommerce).
add_action( 'woocommerce_product_query', function ( WP_Query $q ) {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	if ( ! empty( $_GET['instock'] ) ) {
		$meta   = (array) $q->get( 'meta_query' );
		$meta[] = [ 'key' => '_stock_status', 'value' => 'instock' ];
		$q->set( 'meta_query', $meta );
	}
	if ( ! empty( $_GET['onsale'] ) ) {
		$ids = wc_get_product_ids_on_sale();
		$q->set( 'post__in', $ids ? array_map( 'absint', $ids ) : [ 0 ] );
	}
	// phpcs:enable
} );

// "Buy now" on the product page skips the cart and goes straight to checkout.
add_filter( 'woocommerce_add_to_cart_redirect', function ( $url ) {
	return ! empty( $_POST['aim_buy_now'] ) ? wc_get_checkout_url() : $url; // phpcs:ignore WordPress.Security.NonceVerification
} );

// Checkout fields: the shop sells AI subscriptions — nothing ships, so the
// buyer form stays as short as four fields: name, family name, mobile (the
// login username) and an optional email. Runs late (30) so it wins over the
// accounts module and any other checkout-fields filter, no matter what is in
// the cart; if a non-account product sneaks in, the address fields never come
// back and the form keeps the shape of the «اطلاعات خریدار» sample.
add_filter( 'woocommerce_checkout_fields', function ( $fields ) {
	$user = wp_get_current_user();

	// Drop every billing field except the four buyer-identity ones.
	$keep = [ 'billing_first_name' => true, 'billing_last_name' => true, 'billing_phone' => true, 'billing_email' => true ];
	foreach ( (array) ( $fields['billing'] ?? [] ) as $key => $one ) {
		if ( empty( $keep[ $key ] ) ) {
			unset( $fields['billing'][ $key ] );
		}
	}
	// Subscriptions are delivered in the customer's panel: no shipping section.
	$fields['shipping'] = [];
	unset( $fields['order']['order_comments'] ); // notes are off anyway; keep the form short.

	// [ label, placeholder, row class, hint under the box ]
	$map = [
		'billing_first_name' => [ 'نام', 'مثلاً سارا', 'form-row-first', '' ],
		'billing_last_name'  => [ 'نام خانوادگی', 'مثلاً محمدی', 'form-row-last', '' ],
		'billing_phone'      => [ 'شمارهٔ موبایل', '۰۹۱۲۳۴۵۶۷۸۹', 'form-row-first', 'قابل استفاده به‌عنوان نام کاربری برای ورود به سایت.' ],
		'billing_email'      => [ 'ایمیل (اختیاری)', 'example@gmail.com', 'form-row-last', 'اختیاری؛ لینک و رسید خرید برایتان ارسال می‌شود.' ],
	];
	foreach ( $map as $key => [ $label, $ph, $cls, $desc ] ) {
		if ( ! isset( $fields['billing'][ $key ] ) ) {
			continue;
		}
		$fields['billing'][ $key ]['label']       = $label;
		$fields['billing'][ $key ]['placeholder'] = $ph;
		$fields['billing'][ $key ]['class']       = [ $cls ];
		$fields['billing'][ $key ]['description'] = $desc;
	}
	if ( isset( $fields['billing']['billing_phone'] ) ) {
		$fields['billing']['billing_phone']['required'] = true;
	}
	if ( isset( $fields['billing']['billing_email'] ) ) {
		$fields['billing']['billing_email']['required'] = false;
	}

	// Prefill the identity fields from the signed-in account.
	if ( $user->exists() && WC()->customer ) {
		$defaults = [
			'billing_first_name' => $user->first_name ?: $user->display_name,
			'billing_last_name'  => $user->last_name,
			'billing_phone'      => function_exists( 'aim_user_phone' ) ? aim_user_phone( $user->ID ) : '',
			'billing_email'      => $user->user_email,
		];
		$current = [
			'billing_first_name' => WC()->customer->get_billing_first_name(),
			'billing_last_name'  => WC()->customer->get_billing_last_name(),
			'billing_phone'      => WC()->customer->get_billing_phone(),
			'billing_email'      => WC()->customer->get_billing_email(),
		];
		foreach ( $defaults as $key => $value ) {
			if ( $value && isset( $fields['billing'][ $key ] ) && ! $current[ $key ] ) {
				$fields['billing'][ $key ]['default'] = $value;
			}
		}
	}
	return $fields;
}, 30 );

add_filter( 'woocommerce_order_button_text', fn() => aim_t_opt( 'co_place_order' ) );
// The coupon box lives inside the order summary (woocommerce/checkout/review-order.php).
remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
// Order contract (aimarket-core AIM_Contract): the box is placed in the buyer card by our checkout template.
add_theme_support( 'aim-contract' );
add_filter( 'woocommerce_enable_order_notes_field', '__return_false' );

// The order-received page renders its own summary (woocommerce/checkout/thankyou.php).
remove_action( 'woocommerce_thankyou', 'woocommerce_order_details_table', 10 );

// The WordPress toolbar on the storefront is for store staff only; customers never see it.
add_filter( 'show_admin_bar', fn( $show ) => $show && current_user_can( 'manage_woocommerce' ) );

// Light header on inner pages, like the reference; the homepage keeps the dark one.
add_filter( 'body_class', fn( $c ) => is_front_page() ? $c : array_merge( $c, [ 'sf-inner' ] ) );
// The homepage's new desktop hero has light and dark looks; its header follows (theme.css «Hero, desktop»).
add_filter( 'body_class', fn( $c ) => is_front_page() ? array_merge( $c, [ 'sf-front' ] ) : $c );
remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );

// Persian "Add to cart" label for account products.
add_filter( 'woocommerce_product_add_to_cart_text', function ( $text, $product ) {
	return $product->is_in_stock() ? ( $product->is_type( 'variable' ) ? 'انتخاب پلن' : 'خرید اکانت' ) : 'ناموجود';
}, 10, 2 );

// Instant delivery badge on product cards and single pages.
add_action( 'woocommerce_before_shop_loop_item_title', 'aim_theme_delivery_badge', 15 );
add_action( 'woocommerce_single_product_summary', 'aim_theme_delivery_badge', 6 );
function aim_theme_delivery_badge() {
	global $product;
	if ( ! $product || ! class_exists( 'AIM_Inventory' ) ) {
		return;
	}
	$mode = AIM_Inventory::delivery_mode( $product->get_id() );
	if ( 'auto' === $mode ) {
		echo '<span class="pill pill-success aim-badge"><i class="ic-flashlight-line"></i>تحویل فوری</span>';
	} elseif ( 'manual' === $mode ) {
		echo '<span class="pill pill-info aim-badge"><i class="ic-mail-check-line"></i>فعال‌سازی روی ایمیل شما</span>';
	}
}

function aim_theme_account_url() {
	if ( ! is_user_logged_in() ) {
		return function_exists( 'aim_login_url' ) ? aim_login_url() : wp_login_url();
	}
	return function_exists( 'aim_panel_url' ) ? aim_panel_url( 'user' ) : wc_get_page_permalink( 'myaccount' );
}

// Keep the header cart count fresh after AJAX add-to-cart.
add_filter( 'woocommerce_add_to_cart_fragments', function ( $fragments ) {
	$n = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
	$fragments['span.aim-cart-count'] = '<span class="sh-badge aim-cart-count">' . esc_html( aim_t_fa( $n ) ) . '</span>';
	return $fragments;
} );


/* ---------------------------------------------------------------------------
 * Default WordPress screens, restyled.
 * ------------------------------------------------------------------------- */

// wp-login.php (admin sign-in, lost/reset password): store branding instead of the WordPress logo.
add_action( 'login_enqueue_scripts', function () {
	wp_enqueue_style( 'aim-font', get_template_directory_uri() . '/assets/css/fonts.css', [], null );
	wp_enqueue_style( 'aim-login', get_template_directory_uri() . '/assets/css/login.css', [], filemtime( get_template_directory() . '/assets/css/login.css' ) );
} );
add_filter( 'login_headerurl', fn() => home_url( '/' ) );
add_filter( 'login_headertext', fn() => get_bloginfo( 'name' ) );
add_filter( 'login_display_language_dropdown', '__return_false' );
add_action( 'login_footer', function () {
	if ( function_exists( 'aim_login_url' ) ) {
		echo '<p class="aim-login-alt">مشتری هستید؟ <a href="' . esc_url( aim_login_url() ) . '">ورود با شمارهٔ موبایل</a></p>';
	}
} );

// Password-protected posts and pages.
add_filter( 'the_password_form', function () {
	$id = 'pwbox-' . get_the_ID();
	return '<form class="pg-pass" action="' . esc_url( site_url( 'wp-login.php?action=postpass', 'login_post' ) ) . '" method="post">'
		. '<span class="co-empty-ico"><i class="ic-lock-2-line"></i></span>'
		. '<h2>این محتوا محافظت‌شده است</h2><p>برای دیدن آن، رمز را وارد کن.</p>'
		. '<div class="co-coupon-row"><label class="visually-hidden" for="' . esc_attr( $id ) . '">رمز</label>'
		. '<input name="post_password" id="' . esc_attr( $id ) . '" type="password" placeholder="رمز" required>'
		. '<button type="submit">مشاهده</button></div></form>';
} );
add_filter( 'protected_title_format', fn() => '%s' );

// Blog: on activation, give the journal its own page (front page stays the custom homepage).
add_action( 'after_switch_theme', function () {
	if ( 'page' === get_option( 'show_on_front' ) && get_option( 'page_for_posts' ) ) {
		return;
	}
	$home = get_option( 'page_on_front' ) ?: wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'خانه', 'post_name' => 'home' ] );
	$blog = get_option( 'page_for_posts' ) ?: wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'مجله', 'post_name' => 'blog' ] );
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $home );
	update_option( 'page_for_posts', $blog );
} );

// WooCommerce emails: store colours, right-to-left, Persian font stack.
add_filter( 'woocommerce_email_styles', function ( $css ) {
	return $css . '
		#wrapper { background-color: #f4f8ff !important; direction: rtl; }
		#template_container { border: 1px solid #e8eef5 !important; border-radius: 18px !important; box-shadow: 0 10px 30px -18px rgba(30,87,160,.35) !important; overflow: hidden; }
		#template_header { background: #1d4ed8 !important; border-radius: 18px 18px 0 0 !important; }
		#template_header h1, #template_header h1 a { color: #ffffff !important; font-family: Vazirmatn, Tahoma, Arial, sans-serif !important; text-align: right; }
		#body_content, #body_content_inner, #body_content td, #body_content p, #body_content li, h2, h3, .td { font-family: Vazirmatn, Tahoma, Arial, sans-serif !important; text-align: right !important; direction: rtl; color: #151c26; }
		h2, h3 { color: #1d4ed8 !important; }
		a { color: #1d5fe0 !important; }
		.td { border-color: #e8eef5 !important; }
		#template_footer td, #credit { font-family: Vazirmatn, Tahoma, Arial, sans-serif !important; color: #8b97ab !important; text-align: center; }
	';
} );
foreach ( [ 'woocommerce_email_base_color' => '#1d4ed8', 'woocommerce_email_background_color' => '#f4f8ff', 'woocommerce_email_text_color' => '#151c26', 'woocommerce_email_body_background_color' => '#ffffff' ] as $opt => $val ) {
	add_filter( 'default_option_' . $opt, fn() => $val );
}
add_filter( 'woocommerce_email_footer_text', fn() => get_bloginfo( 'name' ) . ' — فروشگاه اکانت‌های هوش مصنوعی' );


// Browser-tab titles for WooCommerce pages, independent of how the pages were named at install time.
add_filter( 'document_title_parts', function ( $parts ) {
	if ( function_exists( 'is_shop' ) && is_shop() && ! is_search() ) {
		$parts['title'] = 'فروشگاه';
	} elseif ( function_exists( 'is_cart' ) && is_cart() ) {
		$parts['title'] = 'سبد خرید';
	} elseif ( function_exists( 'is_checkout' ) && is_checkout() ) {
		$parts['title'] = is_wc_endpoint_url( 'order-received' ) ? 'سفارش ثبت شد' : ( is_wc_endpoint_url( 'order-pay' ) ? 'پرداخت سفارش' : 'اطلاعات و پرداخت' );
	}
	return $parts;
} );

// The empty-cart template has its own message; drop WooCommerce's duplicate notice.
add_action( 'init', function () {
	remove_action( 'woocommerce_cart_is_empty', 'wc_empty_cart_message', 10 );
} );

// "Added to cart" notice in the store's own words, with buttons to the cart and checkout.
add_filter( 'wc_add_to_cart_message_html', function ( $message, $products ) {
	$names = [];
	foreach ( (array) $products as $id => $qty ) {
		$p = wc_get_product( $id );
		if ( $p ) {
			$parent  = $p->get_parent_id() ? wc_get_product( $p->get_parent_id() ) : $p;
			$plan    = $p->is_type( 'variation' ) ? wc_get_formatted_variation( $p, true, false, false ) : '';
			$names[] = '«' . esc_html( $parent->get_name() . ( $plan ? ' — ' . $plan : '' ) ) . '»' . ( $qty > 1 ? ' × ' . esc_html( aim_t_fa( $qty ) ) : '' );
		}
	}
	$text = $names ? implode( '، ', $names ) . ' به سبد خرید اضافه شد.' : 'به سبد خرید اضافه شد.';
	return '<span>' . $text . '</span>'
		. '<a href="' . esc_url( wc_get_checkout_url() ) . '" class="button wc-forward">ادامه و پرداخت<i class="ic-arrow-left-line"></i></a>'
		. '<a href="' . esc_url( wc_get_cart_url() ) . '" class="sf-notice-link">مشاهدهٔ سبد</a>';
}, 10, 2 );
