<?php
/**
 * Homepage sections.
 *
 * Every section has one template in template-parts/sections/{name}.php that takes
 * a plain array of settings. Two callers feed it:
 *  - the Elementor widgets (inc/elementor/), from the editor's controls;
 *  - front-page.php when the homepage is not built with Elementor, from
 *    aim_t_section_defaults() below.
 * The defaults are also the widgets' initial values, so a fresh widget looks
 * exactly like the built-in homepage.
 */
defined( 'ABSPATH' ) || exit;

/** Render one section template; without settings it shows the built-in content. */
function aim_t_section( $name, ?array $args = null ) {
	get_template_part( 'template-parts/sections/' . $name, null, $args ?? aim_t_section_defaults( $name ) );
}

/** "before <em>highlight</em> after" as safe HTML (the highlight is drawn in the accent colour). */
function aim_t_title( $before, $highlight = '', $after = '' ) {
	$html = esc_html( $before );
	if ( '' !== (string) $highlight ) {
		$html .= ' <em>' . esc_html( $highlight ) . '</em>';
	}
	if ( '' !== (string) $after ) {
		$html .= ' ' . esc_html( $after );
	}
	return trim( $html );
}

/** srcset with the theme's 800px copy when $url is one of the bundled hero artworks. */
function aim_t_hero_srcset( $url ) {
	$base = get_template_directory_uri() . '/assets/images/';
	if ( ! preg_match( '#^' . preg_quote( $base, '#' ) . '(hero-art-(light|dark))\.webp$#', (string) $url, $m ) ) {
		return '';
	}
	return sprintf( ' srcset="%s 800w, %s 1536w" sizes="(max-width: 991px) 92vw, 50vw"', esc_url( $base . $m[1] . '-800.webp' ), esc_url( $url ) );
}

/** Small logo for a hero search tag: the image of the first product whose name matches, else nothing. */
function aim_t_tag_logo( $text ) {
	static $cache = [];
	$key = mb_strtolower( trim( (string) $text ) );
	if ( '' === $key || ! function_exists( 'wc_get_products' ) ) {
		return '';
	}
	if ( ! isset( $cache[ $key ] ) ) {
		$found         = wc_get_products( [ 'status' => 'publish', 'limit' => 1, 's' => $text, 'orderby' => 'popularity', 'order' => 'DESC' ] );
		$cache[ $key ] = $found ? aim_t_logo( $found[0], 'tag-logo' ) : '';
	}
	return $cache[ $key ];
}

/** `<i class="ic-name">` for a Coolicons name (see assets/css/icons.css). */
function aim_t_icon( $name ) {
	return $name ? '<i class="ic-' . esc_attr( $name ) . '" aria-hidden="true"></i>' : '';
}

/**
 * Products for a section.
 *
 * @param string $source popular | newest | sale | rating | manual
 * @param int    $count  How many.
 * @param int[]  $ids    For "manual": product ids in display order.
 * @param string $cat    Optional product_cat slug to limit to.
 * @return WC_Product[]
 */
function aim_t_section_products( $source, $count = 8, array $ids = [], $cat = '' ) {
	$args = [ 'status' => 'publish', 'visibility' => 'catalog', 'limit' => max( 1, (int) $count ) ];
	if ( $cat ) {
		$args['category'] = [ $cat ];
	}
	switch ( $source ) {
		case 'manual':
			$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
			if ( ! $ids ) {
				return [];
			}
			$args['include'] = $ids;
			$args['orderby'] = 'include';
			$args['limit']   = count( $ids );
			break;
		case 'newest':
			$args += [ 'orderby' => 'date', 'order' => 'DESC' ];
			break;
		case 'sale':
			$args['include'] = wc_get_product_ids_on_sale() ?: [ 0 ];
			$args           += [ 'orderby' => 'popularity', 'order' => 'DESC' ];
			break;
		case 'rating':
			$args += [ 'orderby' => 'rating', 'order' => 'DESC' ];
			break;
		default:
			$args += [ 'orderby' => 'popularity', 'order' => 'DESC' ];
	}
	return wc_get_products( $args );
}

/** Settings each section starts with: the built-in homepage content (aim_t_home()). */
function aim_t_section_defaults( $name ) {
	$home = aim_t_home();
	$shop = wc_get_page_permalink( 'shop' );

	switch ( $name ) {
		case 'hero':
			return [
				'id'              => 'hero',
				'kicker'          => $home['hero_kicker'],
				'title_before'    => 'هوش مصنوعی‌های محبوب دنیا،',
				'title_highlight' => 'بدون دردسر',
				'title_after'     => 'در دستان تو',
				'text'            => $home['hero_text'],
				'search'          => true,
				'placeholder'     => 'مثلاً ChatGPT، Claude یا Midjourney',
				'button'          => 'جست‌وجوی محصول',
				'tags_label'      => 'جست‌وجوهای محبوب:',
				'tags'            => array_map( fn( $t ) => [ 'text' => $t, 'url' => '' ], $home['popular'] ),
				'hint_text'       => 'نمی‌دانی کدام را بخری؟ راهنمای انتخاب',
				'hint_url'        => '#finder',
				'scroll_text'     => 'پرفروش‌ترین‌ها',
				'scroll_url'      => '#best',
				// 3D artwork beside the text on desktop, one per colour mode (theme files unless replaced in Elementor).
				'art_light'       => get_template_directory_uri() . '/assets/images/hero-art-light.webp',
				'art_dark'        => get_template_directory_uri() . '/assets/images/hero-art-dark.webp',
				// Floating service chips around the hero: [ label, url, logo (HTML) ].
				'chips'           => array_map(
					fn( $p ) => [ 'label' => preg_replace( '/^(اکانت|اشتراک)\s+/u', '', $p->get_name() ), 'url' => $p->get_permalink(), 'logo' => aim_t_logo( $p, 'chip-logo' ) ],
					aim_t_section_products( 'popular', 6 )
				),
			];

		case 'products':
			return [
				'id'              => 'best',
				'kicker'          => '',
				'title_before'    => 'پرفروش‌ترین',
				'title_highlight' => 'اکانت‌ها',
				'title_after'     => '',
				'text'            => 'قیمت شروع هر سرویس؛ همهٔ پلن‌ها را در صفحهٔ محصول مقایسه کن.',
				'link_url'        => $shop,
				'link_text'       => 'همه محصولات',
				'layout'          => 'rail', // rail | grid
				'flag'            => 'rank', // rank | new | none
				'tint'            => true,
				'products'        => aim_t_section_products( 'popular', 8 ),
			];

		case 'offer':
			return [
				'id'         => 'offer',
				'art'        => aim_t_illus( 'gift' ),
				'kicker'     => 'هدیهٔ اولین خرید',
				'amount'     => $home['offer']['amount'],
				'title'      => $home['offer']['title'],
				'text'       => $home['offer']['text'],
				'code'       => $home['offer']['code'],
				'code_label' => 'کد تخفیف',
				'copy_text'  => 'کپی کد',
				'points'     => [ 'فقط برای اولین خرید', 'روی همهٔ سرویس‌ها', 'در صفحهٔ پرداخت وارد کن' ],
			];

		case 'finder':
			return [
				'id'              => 'finder',
				'title_before'    => 'هوش مصنوعی برای',
				'title_highlight' => 'چه کاری',
				'title_after'     => 'می‌خواهی؟',
				'text'            => 'کاربردت را انتخاب کن تا مناسب‌ترین سرویس‌ها را ببینی.',
				'ask'             => true,
				'ask_label'       => 'یا بنویس دقیقاً چه کاری می‌خواهی انجام بدهی',
				'ask_placeholder' => 'مثلاً ساخت ویدیوی تبلیغاتی',
				'cta'             => 'مشاهدهٔ پلن‌ها',
				// [ key, icon (HTML), label, title, text, art (HTML), products ].
				'uses'            => array_map(
					fn( $u ) => [
						'key'      => $u['key'],
						'icon'     => aim_t_icon( $u['icon'] ),
						'label'    => $u['label'],
						'title'    => $u['title'],
						'text'     => $u['text'],
						'art'      => aim_t_illus( $u['cat'], 'finder-art' ),
						'products' => term_exists( $u['cat'], 'product_cat' ) ? aim_t_section_products( 'popular', 4, [], $u['cat'] ) : aim_t_section_products( 'popular', 3 ),
					],
					$home['uses']
				),
			];

		case 'steps':
			$art = [ 'step-plan', 'step-login', 'step-pay', 'step-receive', 'step-launch' ];
			return [
				'id'              => 'steps',
				'kicker'          => 'مسیر خرید',
				'title_before'    => 'از انتخاب پلن تا',
				'title_highlight' => 'استفاده از اکانت',
				'title_after'     => '',
				'text'            => 'پنج قدم ساده؛ روی هر مرحله بزن.',
				'prev_text'       => 'قبلی',
				'next_text'       => 'مرحلهٔ بعد',
				'last_text'       => 'شروع خرید',
				'last_url'        => $shop,
				'steps'           => array_map(
					fn( $s, $i ) => [ 'label' => $s['label'], 'title' => $s['title'], 'text' => $s['text'], 'art' => aim_t_illus( $art[ $i ] ?? 'step-plan', 'step-art' ) ],
					$home['steps'],
					array_keys( $home['steps'] )
				),
			];

		case 'faq':
			return [
				'id'              => 'faq',
				'art'             => aim_t_illus( 'faq', 'faq-art' ),
				'title_before'    => 'پیش از خرید،',
				'title_highlight' => 'پاسخ سؤال‌هایت',
				'title_after'     => 'را ببین',
				'text'            => 'از زمان تحویل و تفاوت پلن‌ها تا پرداخت و ضمانت.',
				'tint'            => true,
				'open_first'      => true,
				'items'           => $home['faq'],
			];

		case 'posts':
			return [
				'id'              => 'journal',
				'kicker'          => 'مجله',
				'title_before'    => 'تازه‌ترین',
				'title_highlight' => 'مقاله‌ها و آموزش‌ها',
				'title_after'     => '',
				'text'            => 'راهنمای انتخاب، آموزش استفاده و خبرهای هوش مصنوعی.',
				'link_url'        => get_permalink( get_option( 'page_for_posts' ) ) ?: home_url( '/blog/' ),
				'link_text'       => 'همهٔ مقاله‌ها',
				'posts'           => get_posts( [ 'numberposts' => 4, 'post_status' => 'publish' ] ),
			];

		case 'header':
			return [
				'announce'      => ! empty( $home['announce']['text'] ),
				'announce_text' => $home['announce']['text'],
				'announce_cta'  => $home['announce']['cta'],
				'announce_url'  => $home['announce']['link'],
				'logo'          => has_custom_logo() ? wp_get_attachment_image( get_theme_mod( 'custom_logo' ), 'medium', false, [ 'alt' => get_bloginfo( 'name' ) ] ) : '',
				'brand'         => get_bloginfo( 'name' ),
				'brand_sub'     => 'HOOSH MARKET',
				'search'        => true,
				'placeholder'   => 'جست‌وجوی سرویس هوش مصنوعی یا اکانت…',
				'theme_toggle'  => true,
				'login'         => true,
				'login_in'      => 'حساب کاربری',
				'login_out'     => 'ورود | ثبت‌نام',
				'cart'          => true,
				'menu'          => aim_t_header_menu(),
				'track_text'    => 'پیگیری سفارش',
				'track_url'     => function_exists( 'aim_panel_url' ) ? aim_panel_url( 'user', 'orders' ) : '',
			];

		case 'footer':
			return aim_t_footer_args(); // inc/footer.php — edited in panel → تنظیمات قالب → فوتر

		case 'contact':
			$s = class_exists( 'AIM_Settings' ) ? AIM_Settings::all() : [];
			return [
				'id'              => 'contact',
				'kicker'          => 'تماس با ما',
				'title_before'    => 'هر سؤالی داری،',
				'title_highlight' => 'کنارت هستیم',
				'title_after'     => '',
				'text'            => 'پیش از خرید یا بعد از تحویل اکانت، از یکی از راه‌های زیر با پشتیبانی در ارتباط باش.',
				// [ icon (HTML), title, value, url ].
				'items'           => array_values( array_filter( [
					[ 'icon' => aim_t_icon( 'customer-service-2-line' ), 'title' => 'پشتیبانی', 'value' => 'تیکت از حساب کاربری', 'url' => function_exists( 'aim_panel_url' ) ? aim_panel_url( 'user', 'tickets' ) : '' ],
					! empty( $s['support_phone'] ) ? [ 'icon' => aim_t_icon( 'phone-line' ), 'title' => 'تلفن', 'value' => aim_t_fa( $s['support_phone'] ), 'url' => 'tel:' . preg_replace( '/[^\d+]/', '', $s['support_phone'] ) ] : null,
					! empty( $s['support_email'] ) ? [ 'icon' => aim_t_icon( 'mail-line' ), 'title' => 'ایمیل', 'value' => $s['support_email'], 'url' => 'mailto:' . $s['support_email'] ] : null,
					! empty( $s['response_time'] ) ? [ 'icon' => aim_t_icon( 'time-line' ), 'title' => 'ساعات پاسخگویی', 'value' => $s['response_time'], 'url' => '' ] : null,
				] ) ),
			];

		case 'trust':
			return [
				'items' => array_map( fn( $t ) => [ 'icon' => aim_t_icon( $t['icon'] ), 'title' => $t['title'], 'text' => $t['text'] ], $home['trust'] ),
			];
	}
	return [];
}

/** @return array[] [ text, url ] for the best-selling products. */
function aim_t_footer_product_links( $count ) {
	return array_map( fn( $p ) => [ 'text' => $p->get_name(), 'url' => $p->get_permalink() ], aim_t_section_products( 'popular', $count ) );
}

/** @return array[] [ text, url ] for product categories that have products. */
function aim_t_footer_category_links( $count ) {
	$cats = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => true, 'number' => $count, 'exclude' => [ (int) get_option( 'default_product_cat' ) ] ] );
	return is_wp_error( $cats ) ? [] : array_map( fn( $c ) => [ 'text' => $c->name, 'url' => get_term_link( $c ) ], $cats );
}

/** Copyright line with {year} (Jalali) and {site} filled in. */
function aim_t_copyright( $text ) {
	$year = function_exists( 'aim_gregorian_to_jalali' ) ? aim_gregorian_to_jalali( (int) wp_date( 'Y' ), (int) wp_date( 'n' ), (int) wp_date( 'j' ) )[0] : wp_date( 'Y' );
	return strtr( (string) $text, [ '{year}' => aim_t_fa( $year ), '{site}' => get_bloginfo( 'name' ) ] );
}

/** The contact page (made by the Elementor installer), else /contact/. */
function aim_t_contact_url() {
	$id = (int) get_option( 'aim_el_contact' );
	return $id && 'publish' === get_post_status( $id ) ? get_permalink( $id ) : home_url( '/contact/' );
}
