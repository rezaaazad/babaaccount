<?php
defined( 'ABSPATH' ) || exit;

/**
 * Order contract («قرارداد سفارش»).
 *
 * Before paying, the buyer reads a draft built from the cart and their details
 * (POST /contract/preview) and ticks the consent box; checkout refuses the
 * order without it. When the order is created, the final contract — with the
 * order number, acceptance time, IP, browser, the versions of the contract,
 * consent text and purchase rules, and a SHA-256 fingerprint — is rendered
 * once and frozen in the order meta (META). Later edits of the text never
 * change contracts already accepted.
 *
 * The text is edited in panel → «قرارداد سفارش» (option OPTION) as numbered
 * articles. A line [parties], [specs], [payment] or [guarantee] inside an
 * article inserts the generated block; {seller}, {site}, {website} and
 * {buyer} are replaced in the text.
 *
 * Themes may print the consent box where they like with
 * do_action( 'aim_checkout_consent' ) after add_theme_support( 'aim-contract' );
 * otherwise it appears above WooCommerce's place-order button.
 */
class AIM_Contract {

	const OPTION          = 'aim_contract';
	const META            = '_aim_contract';
	const CONSENT_VERSION = 'consent-v1';
	const HASH_SLOT       = '{{contract-sha256}}';

	public static function init() {
		add_action( 'rest_api_init', function () {
			register_rest_route( AIM_Admin_API::NS, '/contract/preview', [
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'rest_preview' ],
				'permission_callback' => '__return_true', // reads only the visitor's own cart
			] );
			register_rest_route( AIM_Admin_API::NS, '/contract/settings', [
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'rest_save' ],
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
			] );
		} );
		// Contracts already accepted stay viewable even if the feature is later switched off.
		add_action( 'template_redirect', [ __CLASS__, 'print_view' ] );
		add_action( 'aim_admin_order_after_items', [ __CLASS__, 'order_card' ] );
		if ( ! self::get( 'enabled' ) ) {
			return;
		}
		add_action( 'wp', function () {
			if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url( 'order-received' ) ) {
				return;
			}
			if ( ! current_theme_supports( 'aim-contract' ) ) {
				add_action( 'woocommerce_review_order_before_submit', [ __CLASS__, 'consent_box' ] );
			}
			add_action( 'wp_enqueue_scripts', [ __CLASS__, 'assets' ] );
			add_action( 'wp_footer', [ __CLASS__, 'modal' ] );
		} );
		add_action( 'aim_checkout_consent', [ __CLASS__, 'consent_box' ] );
		add_action( 'woocommerce_checkout_process', [ __CLASS__, 'validate' ] );
		add_action( 'woocommerce_checkout_order_created', [ __CLASS__, 'freeze' ] );
	}

	/* ---------------------------------------------------------------- settings */

	public static function defaults() {
		$site = get_bloginfo( 'name' );
		return [
			'enabled'       => 1,
			'version'       => 'CONTRACT-' . wp_date( 'Y' ) . '-v1',
			'title'         => 'قرارداد خرید و فعال‌سازی خدمات دیجیتال',
			'seller_legal'  => $site,
			'seller_brand'  => $site,
			'national_id'   => '',
			'reg_no'        => '',
			'phone'         => '',
			'email'         => get_option( 'admin_email' ),
			'address'       => '',
			'postal'        => '',
			'logo'          => '',
			'rules_url'     => '',
			'consent_text'  => 'قرارداد این سفارش را خوانده‌ام و می‌پذیرم.',
			'consent_help'  => 'مشخصات پلن انتخابی و قوانین خرید بخشی از همین قرارداد است.',
			'articles'      => self::default_articles(),
			'rules'         => self::default_rules(),
		];
	}

	/** Sample text — must be reviewed by the shop's own legal adviser before use. */
	private static function default_articles() {
		return [
			[ 'title' => 'طرفین قرارداد', 'body' => "این قرارداد بر پایهٔ مادهٔ ۱۰ قانون مدنی و قانون تجارت الکترونیکی، میان {seller} («فروشنده») و خریدار با مشخصات زیر («خریدار») از طریق وب‌سایت {website} منعقد شده و برای هر دو طرف لازم‌الاجراست.\n[parties]" ],
			[ 'title' => 'تعاریف', 'body' => "«سرویس اصلی»: سرویس یا اشتراک دیجیتالی که شرکت سازندهٔ آن ارائه می‌کند و قوانینش را همان شرکت تعیین می‌کند.\n«خدمت»: خرید، ثبت و فعال‌سازی سرویس اصلی برای خریدار توسط فروشنده، مطابق مادهٔ ۴.\n«تحویل»: فعال شدن سرویس روی حسابی که خریدار معرفی کرده، یا قرار گرفتن اطلاعات اکانت در حساب کاربری خریدار." ],
			[ 'title' => 'موضوع قرارداد', 'body' => 'موضوع قرارداد، ارائهٔ خدمت توصیف‌شده در مادهٔ ۴ در برابر مبلغ مادهٔ ۵ است. فروشنده واسطهٔ خرید و فعال‌سازی است و مالک یا سازندهٔ سرویس اصلی نیست.' ],
			[ 'title' => 'مشخصات خدمت', 'body' => "مشخصات زیر عیناً همان است که خریدار پیش از پرداخت در صفحهٔ محصول و سبد خرید دیده و انتخاب کرده است.\n[specs]" ],
			[ 'title' => 'مبلغ قرارداد و نحوهٔ پرداخت', 'body' => "[payment]\nمبلغ قرارداد همان مبلغی است که خریدار پیش از پرداخت دیده و تأیید کرده و پس از پرداخت با تغییر نرخ ارز یا قیمت سرویس تغییر نمی‌کند. پرداخت فقط از درگاه‌های فعال سایت انجام می‌شود و اطلاعات کارت بانکی نزد فروشنده ثبت نمی‌شود." ],
			[ 'title' => 'تحویل و بررسی', 'body' => 'فروشنده پس از تأیید پرداخت، خدمت را در زمان درج‌شده در مادهٔ ۴ تحویل می‌دهد. خریدار باید ظرف ۲۴ ساعت پس از تحویل، درستی فعال‌سازی را بررسی و هر ایرادی را از طریق تیکت اعلام کند.' ],
			[ 'title' => 'تعهدات خریدار', 'body' => 'خریدار اقرار می‌کند اطلاعات واردشده درست و متعلق به خود اوست، پیش‌نیازهای مادهٔ ۴ را رعایت می‌کند و از سرویس فقط طبق قوانین شرکت سازنده و قوانین کشور استفاده می‌کند. اشتراک‌گذاری اکانت اختصاصی یا تغییر تنظیمات امنیتی آن خارج از راهنمای فروشنده، ضمانت را از بین می‌برد.' ],
			[ 'title' => 'سرویس اصلی و حدود مسئولیت', 'body' => 'قطعی، کندی، تغییر قیمت، تغییر امکانات یا توقف سرویس اصلی از سوی شرکت سازنده، خارج از اختیار فروشنده است. مسئولیت فروشنده محدود به تعهدات همین قرارداد و حداکثر به میزان مبلغ پرداختی همین سفارش است.' ],
			[ 'title' => 'ضمانت', 'body' => "[guarantee]\nضمانت فقط در محدودهٔ بالا و تا پایان مدت اشتراک معتبر است و با نقض تعهدات مادهٔ ۷ ساقط می‌شود." ],
			[ 'title' => 'انصراف و بازگشت وجه', 'body' => 'پیش از شروع فعال‌سازی، خریدار می‌تواند سفارش را لغو کند و وجه پس از کسر کارمزدهای قطعی بازگردانده می‌شود. پس از تحویل، به دلیل ماهیت دیجیتال خدمت، بازگشت وجه فقط وقتی ممکن است که ایراد از سمت فروشنده باشد و رفع نشود.' ],
			[ 'title' => 'قوهٔ قاهره', 'body' => 'رویدادهای خارج از اختیار طرفین، از جمله تحریم، قطع اینترنت، تصمیم‌های حاکمیتی یا اختلال سراسری سرویس اصلی، تا زمان رفع، تعهدات مرتبط را معلق می‌کند.' ],
			[ 'title' => 'حریم خصوصی', 'body' => 'فروشنده اطلاعات خریدار را فقط برای انجام همین سفارش، پشتیبانی و الزامات قانونی نگه می‌دارد و جز به حکم مرجع قانونی در اختیار دیگران قرار نمی‌دهد. رمز عبور حساب‌های شخصی خریدار مگر برای فعال‌سازی و با درخواست خود او دریافت نمی‌شود.' ],
			[ 'title' => 'انعقاد الکترونیکی و ابلاغ', 'body' => 'این قرارداد «داده‌پیام» است و با تیک پذیرش و ثبت سفارش منعقد می‌شود. زمان، نشانی IP، مرورگر، نسخهٔ متن و اثر انگشت دیجیتال آن ثبت و نگه داشته می‌شود. پیامک و ایمیل به شماره و نشانی ثبت‌شده در این قرارداد، ابلاغ محسوب می‌شود.' ],
			[ 'title' => 'رسیدگی به شکایات', 'body' => 'شکایت از طریق تیکت حساب کاربری یا راه‌های تماس فروشنده ثبت و حداکثر ظرف ۷۲ ساعت کاری پاسخ داده می‌شود. در صورت حل نشدن، مرجع رسیدگی، مراجع صالح قضایی جمهوری اسلامی ایران است.' ],
			[ 'title' => 'مقررات پایانی', 'body' => 'ترتیب تقدم اسناد: ۱) مشخصات مادهٔ ۴؛ ۲) این قرارداد؛ ۳) قوانین خرید سایت در زمان ثبت سفارش (پیوست). بی‌اعتباری یک بند به اعتبار بقیه لطمه نمی‌زند. تغییرات بعدی متن قرارداد یا قوانین سایت بر این سفارش اثری ندارد. نسخهٔ الکترونیکی این قرارداد در حساب کاربری خریدار قابل مشاهده و چاپ است و حکم نسخهٔ واحد را دارد.' ],
		];
	}

	private static function default_rules() {
		return "شرایط هر پلن (نوع اکانت، روش فعال‌سازی، زمان تحویل و ضمانت) در صفحهٔ همان محصول آمده و مالک سفارش است.\nقیمت معتبر همان قیمتی است که هنگام پرداخت می‌بینید.\nاکانت اختصاصی فقط برای استفادهٔ خریدار است و اشتراک‌گذاری آن ضمانت را از بین می‌برد.\nپاسخ‌گویی پشتیبانی از طریق تیکت حساب کاربری انجام می‌شود.";
	}

	public static function all() {
		static $all = null;
		return $all ??= array_replace( self::defaults(), (array) get_option( self::OPTION, [] ) );
	}

	public static function get( $key ) {
		return self::all()[ $key ] ?? null;
	}

	public static function rest_save( WP_REST_Request $req ) {
		$in   = (array) $req->get_param( 'values' );
		$def  = self::defaults();
		$save = [];
		foreach ( $def as $key => $default ) {
			if ( ! array_key_exists( $key, $in ) ) {
				continue;
			}
			switch ( $key ) {
				case 'enabled':
					$save[ $key ] = empty( $in[ $key ] ) ? 0 : 1;
					break;
				case 'articles':
					$save[ $key ] = [];
					foreach ( (array) $in[ $key ] as $row ) {
						if ( ! is_array( $row ) ) {
							continue;
						}
						$title = sanitize_text_field( (string) ( $row['title'] ?? '' ) );
						$body  = sanitize_textarea_field( (string) ( $row['body'] ?? '' ) );
						if ( '' !== $title || '' !== $body ) {
							$save[ $key ][] = [ 'title' => $title, 'body' => $body ];
						}
					}
					break;
				case 'rules':
				case 'address':
					$save[ $key ] = sanitize_textarea_field( (string) $in[ $key ] );
					break;
				case 'logo':
				case 'rules_url':
					$save[ $key ] = esc_url_raw( (string) $in[ $key ] );
					break;
				case 'email':
					$save[ $key ] = sanitize_email( (string) $in[ $key ] );
					break;
				default:
					$save[ $key ] = sanitize_text_field( (string) $in[ $key ] );
			}
		}
		if ( isset( $save['articles'] ) && ! $save['articles'] ) {
			return new WP_REST_Response( [ 'ok' => false, 'message' => 'قرارداد دست‌کم یک ماده لازم دارد.' ], 400 );
		}
		// Every accepted contract records the version and shows the title: neither may be blank.
		foreach ( [ 'version' => 'نسخهٔ متن قرارداد را وارد کنید.', 'title' => 'عنوان قرارداد را وارد کنید.' ] as $key => $message ) {
			if ( isset( $save[ $key ] ) && '' === $save[ $key ] ) {
				return new WP_REST_Response( [ 'ok' => false, 'message' => $message ], 400 );
			}
		}
		if ( isset( $save['email'] ) && '' === $save['email'] && '' !== trim( (string) $in['email'] ) ) {
			return new WP_REST_Response( [ 'ok' => false, 'message' => 'ایمیل فروشنده معتبر نیست.' ], 400 );
		}
		update_option( self::OPTION, array_replace( (array) get_option( self::OPTION, [] ), $save ), false );
		AIM_Activity::log( 'contract_saved', 'ذخیرهٔ متن قرارداد سفارش', 'settings' );
		return rest_ensure_response( [ 'ok' => true, 'message' => 'قرارداد سفارش ذخیره شد. سفارش‌های قبلی همان متنی را که پذیرفته‌اند نگه می‌دارند.' ] );
	}

	/** "rules-xxxxxxxxxxxx": changes whenever the purchase rules text changes. */
	public static function rules_version() {
		return 'rules-' . substr( md5( (string) self::get( 'rules' ) ), 0, 12 );
	}

	/* ------------------------------------------------------------------- data */

	/** What the contract says about one product line. */
	private static function item( WC_Product $product, $qty, $total ) {
		$base = $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : $product;
		$plan = $product->is_type( 'variation' ) ? wc_get_formatted_variation( $product, true, false, false ) : '';
		$mode = (string) $base->get_meta( '_aim_delivery' );
		$days = (int) ( $product->get_meta( '_aim_duration_days' ) ?: $base->get_meta( '_aim_duration_days' ) );
		$meta = fn( $key ) => trim( (string) ( $product->get_meta( $key ) ?: $base->get_meta( $key ) ) );
		$specs = [
			'plan'        => [ 'پلن', trim( $base->get_name() . ( $plan ? ' — ' . $plan : '' ) ) ],
			'duration'    => [ 'مدت', $days ? aim_fa_digits( $days ) . ' روز' : '' ],
			'ownership'   => [ 'نوع اکانت', $meta( '_aim_type_label' ) ],
			'activation'  => [ 'فعال‌سازی', $meta( '_aim_c_activation' ) ?: ( 'manual' === $mode ? 'روی ایمیل خریدار' : ( 'auto' === $mode ? 'تحویل اطلاعات اکانت در حساب کاربری' : '' ) ) ],
			'delivery'    => [ 'زمان تحویل', $meta( '_aim_c_delivery' ) ?: $meta( '_aim_delivery_label' ) ?: ( 'auto' === $mode ? 'آنی، پس از پرداخت' : ( 'manual' === $mode ? 'تا ۲۴ ساعت پس از پرداخت' : '' ) ) ],
			'guarantee'   => [ 'ضمانت', $meta( '_aim_c_guarantee' ) ],
			'prerequisite' => [ 'پیش‌نیاز', $meta( '_aim_c_prereq' ) ],
			'limits'      => [ 'محدودیت مهم', $meta( '_aim_c_limits' ) ],
		];
		$specs = apply_filters( 'aim_contract_item_specs', $specs, $product, $base );
		return [
			'name'  => $base->get_name(),
			'plan'  => $plan,
			'qty'   => (int) $qty,
			'total' => (float) $total,
			'specs' => array_filter( $specs, fn( $s ) => '' !== (string) $s[1] ),
		];
	}

	private static function seller() {
		$s = self::all();
		return [
			'legal'       => $s['seller_legal'] ?: get_bloginfo( 'name' ),
			'brand'       => $s['seller_brand'] ?: get_bloginfo( 'name' ),
			'national_id' => $s['national_id'],
			'reg_no'      => $s['reg_no'],
			'website'     => wp_parse_url( home_url(), PHP_URL_HOST ),
			'phone'       => $s['phone'] ?: AIM_Settings::get( 'support_phone' ),
			'email'       => $s['email'],
			'address'     => trim( $s['address'] . ( $s['postal'] ? ' — کد پستی ' . $s['postal'] : '' ) ),
			'logo'        => $s['logo'] ?: (string) apply_filters( 'aim_brand_logo_url', '' ),
		];
	}

	/** Contract data from the current cart and the buyer fields typed on checkout. */
	public static function from_cart( array $buyer ) {
		$cart  = WC()->cart;
		$items = [];
		foreach ( $cart ? $cart->get_cart() : [] as $line ) {
			if ( ! empty( $line['data'] ) && $line['data'] instanceof WC_Product ) {
				$items[] = self::item( $line['data'], $line['quantity'], (float) $line['line_total'] + (float) $line['line_tax'] );
			}
		}
		$user = wp_get_current_user();
		return [
			'status'   => 'draft',
			'number'   => '',
			'date'     => time(),
			'seller'   => self::seller(),
			'buyer'    => [
				'name'    => trim( ( $buyer['first_name'] ?? '' ) . ' ' . ( $buyer['last_name'] ?? '' ) ) ?: $user->display_name,
				'phone'   => $buyer['phone'] ?? '',
				'email'   => $buyer['email'] ?? '',
				'user_id' => $user->ID,
				'ip'      => aim_client_ip(),
			],
			'items'    => $items,
			'subtotal' => $cart ? (float) $cart->get_subtotal() : 0,
			'discount' => $cart ? (float) $cart->get_discount_total() : 0,
			'total'    => $cart ? (float) $cart->get_total( 'edit' ) : 0,
		];
	}

	public static function from_order( WC_Order $order ) {
		$items = [];
		foreach ( $order->get_items() as $line ) {
			$product = $line->get_product();
			if ( $product ) {
				$items[] = self::item( $product, $line->get_quantity(), (float) $line->get_total() + (float) $line->get_total_tax() );
			}
		}
		return [
			'status'   => 'final',
			'number'   => $order->get_order_number(),
			'date'     => $order->get_date_created() ? $order->get_date_created()->getTimestamp() : time(),
			'seller'   => self::seller(),
			'buyer'    => [
				'name'    => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) ?: ( $order->get_user() ? $order->get_user()->display_name : '' ),
				'phone'   => $order->get_billing_phone(),
				'email'   => $order->get_billing_email(),
				'user_id' => $order->get_customer_id(),
				'ip'      => $order->get_customer_ip_address() ?: aim_client_ip(),
			],
			'items'    => $items,
			'subtotal' => (float) $order->get_subtotal(),
			'discount' => (float) $order->get_discount_total(),
			'total'    => (float) $order->get_total(),
		];
	}

	/* ---------------------------------------------------------------- render */

	/** The contract document (HTML). $accept: acceptance record for the final copy. */
	public static function render( array $data, array $accept = [] ) {
		$s    = self::all();
		$text = function ( $str ) use ( $data ) {
			return strtr( (string) $str, [
				'{seller}'  => $data['seller']['legal'] . ( $data['seller']['brand'] && $data['seller']['brand'] !== $data['seller']['legal'] ? ' (' . $data['seller']['brand'] . ')' : '' ),
				'{site}'    => get_bloginfo( 'name' ),
				'{website}' => $data['seller']['website'],
				'{buyer}'   => $data['buyer']['name'],
			] );
		};
		ob_start();
		aim_template( 'contract/document', [
			'data'     => $data,
			'accept'   => $accept,
			's'        => $s,
			'text'     => $text,
			'versions' => [ 'contract' => $s['version'], 'consent' => self::CONSENT_VERSION, 'rules' => self::rules_version() ],
		] );
		return trim( ob_get_clean() );
	}

	/** Per-item summary cards shown above the draft in the checkout window. */
	public static function summary_html( array $data ) {
		ob_start();
		aim_template( 'contract/summary', [ 'data' => $data ] );
		return ob_get_clean();
	}

	/* -------------------------------------------------------------- checkout */

	public static function assets() {
		wp_enqueue_style( 'aim-contract', AIM_URL . 'assets/css/contract.css', [], AIM_VERSION . '.' . filemtime( AIM_DIR . 'assets/css/contract.css' ) );
		wp_enqueue_script( 'aim-contract', AIM_URL . 'assets/js/contract.js', [], AIM_VERSION . '.' . filemtime( AIM_DIR . 'assets/js/contract.js' ), true );
		wp_localize_script( 'aim-contract', 'AIMC', [
			'rest'  => esc_url_raw( rest_url( AIM_Admin_API::NS . '/contract/preview' ) ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'css'   => AIM_URL . 'assets/css/contract.css?ver=' . AIM_VERSION . '.' . filemtime( AIM_DIR . 'assets/css/contract.css' ),
		] );
	}

	public static function rules_url() {
		return self::get( 'rules_url' ) ?: aim_terms_url();
	}

	public static function consent_box() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		aim_template( 'contract/consent', [ 's' => self::all(), 'rules' => self::rules_url() ] );
	}

	public static function modal() {
		aim_template( 'contract/modal' );
	}

	public static function rest_preview( WP_REST_Request $req ) {
		if ( ! WC()->cart ) {
			wc_load_cart();
		}
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return new WP_REST_Response( [ 'ok' => false, 'message' => 'سبد خرید خالی است.' ], 400 );
		}
		$buyer = array_map( fn( $v ) => sanitize_text_field( (string) $v ), (array) $req->get_param( 'buyer' ) );
		$data  = self::from_cart( $buyer );
		return rest_ensure_response( [
			'ok'      => true,
			'summary' => self::summary_html( $data ),
			'html'    => self::render( $data ),
		] );
	}

	public static function validate() {
		if ( empty( $_POST['aim_contract_accept'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- WooCommerce verified the checkout nonce.
			wc_add_notice( 'برای ثبت سفارش، قرارداد سفارش را بخوانید و بپذیرید.', 'error', [ 'id' => 'aim_contract_accept' ] );
		}
	}

	/** Render the final contract once, with the order number, and keep it with the order. */
	public static function freeze( WC_Order $order ) {
		if ( $order->get_meta( self::META ) ) {
			return;
		}
		$accept = [
			'time'    => time(),
			'ip'      => aim_client_ip(),
			'agent'   => substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ), 0, 255 ),
			'method'  => 'تیک پذیرش قرارداد و ثبت سفارش',
		];
		// The fingerprint is printed inside the document: render with a placeholder, hash that,
		// then put the hash in. To verify a stored copy, swap the hash back for HASH_SLOT and SHA-256 it.
		$draft          = self::render( self::from_order( $order ), $accept + [ 'hash' => self::HASH_SLOT ] );
		$accept['hash'] = hash( 'sha256', $draft );
		$html           = str_replace( self::HASH_SLOT, $accept['hash'], $draft );
		$order->update_meta_data( self::META, [
			'html'     => $html,
			'hash'     => $accept['hash'],
			'time'     => $accept['time'],
			'ip'       => $accept['ip'],
			'agent'    => $accept['agent'],
			'versions' => [ 'contract' => self::get( 'version' ), 'consent' => self::CONSENT_VERSION, 'rules' => self::rules_version() ],
		] );
		$order->save();
		$order->add_order_note( 'قرارداد سفارش پذیرفته و ثبت شد (نسخهٔ ' . self::get( 'version' ) . '، اثر انگشت ' . substr( $accept['hash'], 0, 16 ) . '…).' );
	}

	/* ------------------------------------------------------------------ view */

	/** True when a stored copy still matches its fingerprint (nothing in it was changed). */
	public static function verify( array $contract ) {
		return hash_equals( $contract['hash'], hash( 'sha256', str_replace( $contract['hash'], self::HASH_SLOT, $contract['html'] ) ) );
	}

	/** Stored contract of an order, or null. */
	public static function of( WC_Order $order ) {
		$c = $order->get_meta( self::META );
		return is_array( $c ) && ! empty( $c['html'] ) ? $c : null;
	}

	public static function can_view( WC_Order $order ) {
		return current_user_can( 'manage_woocommerce' ) || ( get_current_user_id() && (int) $order->get_customer_id() === get_current_user_id() );
	}

	public static function url( WC_Order $order ) {
		return add_query_arg( [ 'aim_contract' => $order->get_id() ], home_url( '/' ) );
	}

	/** Contract card on the panel's order page. */
	public static function order_card( WC_Order $order ) {
		$c = self::of( $order );
		echo '<div class="contract-card">' . aim_icon( 'file-list-3-line' ); // phpcs:ignore WordPress.Security.EscapeOutput
		if ( $c ) {
			printf(
				'<div><b>قرارداد سفارش پذیرفته شده</b><small>%s — IP <span class="ltr">%s</span> — نسخهٔ <span class="ltr">%s</span> — اثر انگشت <code class="ltr">%s…</code></small></div><a class="btn btn-light btn-sm" href="%s" target="_blank" rel="noopener">مشاهده و چاپ</a>',
				esc_html( aim_jdate( $c['time'], true ) ),
				esc_html( $c['ip'] ),
				esc_html( $c['versions']['contract'] ?? '' ),
				esc_html( substr( $c['hash'], 0, 12 ) ),
				esc_url( self::url( $order ) )
			);
		} else {
			echo '<div><b>قرارداد ثبت نشده</b><small>این سفارش پیش از فعال شدن قرارداد سفارش یا از پیشخوان ثبت شده است.</small></div>';
		}
		echo '</div>';
	}

	/** ?aim_contract=ORDER_ID → the frozen contract as a printable page (owner or shop manager only). */
	public static function print_view() {
		if ( empty( $_GET['aim_contract'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$order = wc_get_order( absint( $_GET['aim_contract'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( aim_login_url( home_url( add_query_arg( [] ) ) ) );
			exit;
		}
		$contract = $order instanceof WC_Order ? self::of( $order ) : null; // not a refund
		if ( ! $contract || ! self::can_view( $order ) ) {
			wp_die( 'قراردادی برای نمایش پیدا نشد.', 'قرارداد سفارش', [ 'response' => 404 ] );
		}
		nocache_headers();
		aim_template( 'contract/print', [ 'order' => $order, 'contract' => $contract ] );
		exit;
	}
}
