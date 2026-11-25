<?php
/**
 * Site footer data. Everything comes from panel → «تنظیمات قالب» → «فوتر» and
 * «نمادهای اعتماد» (groups in inc/options.php); the built-in footer and the
 * Elementor footer widget both render template-parts/sections/footer.php
 * with aim_t_footer_args().
 *
 * Link lines are "text | url"; urls may use the tokens of aim_t_footer_tokens().
 * A line "[products]" or "[categories]" expands to the best sellers or the
 * product categories.
 */
defined( 'ABSPATH' ) || exit;

/** {token} → URL or text, for footer links and texts. */
function aim_t_footer_tokens() {
	static $tokens = null;
	if ( null !== $tokens ) {
		return $tokens;
	}
	$panel  = fn( $sec ) => function_exists( 'aim_panel_url' ) ? aim_panel_url( 'user', $sec ) : home_url( '/' );
	$blog   = (int) get_option( 'page_for_posts' );
	$tokens = [
		'{site}'    => get_bloginfo( 'name' ),
		'{year}'    => aim_t_copyright( '{year}' ),
		'{home}'    => home_url( '/' ),
		'{shop}'    => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ),
		'{account}' => aim_theme_account_url(),
		'{orders}'  => $panel( 'orders' ),
		'{tickets}' => $panel( 'tickets' ),
		'{contact}' => aim_t_contact_url(),
		'{steps}'   => home_url( '/#steps' ),
		'{faq}'     => home_url( '/#faq' ),
		'{blog}'    => $blog ? get_permalink( $blog ) : home_url( '/blog/' ),
		'{privacy}' => get_privacy_policy_url() ?: home_url( '/privacy/' ),
	];
	return $tokens;
}

function aim_t_footer_fill( $text ) {
	return strtr( (string) $text, aim_t_footer_tokens() );
}

/** "text | url" lines → [ [ text, url ] ]. */
function aim_t_footer_links( $lines ) {
	$links = [];
	foreach ( preg_split( '/\R/u', (string) $lines ) as $line ) {
		$line = trim( $line );
		if ( '[products]' === $line ) {
			$links = array_merge( $links, aim_t_footer_product_links( 6 ) );
		} elseif ( '[categories]' === $line ) {
			$links = array_merge( $links, aim_t_footer_category_links( 6 ) );
		} elseif ( '' !== $line ) {
			[ $text, $url ] = array_map( 'trim', explode( '|', $line, 2 ) + [ 1 => '' ] );
			if ( '' === $text ) {
				continue; // "| url" without a text would print an empty link.
			}
			$links[] = [ 'text' => aim_t_footer_fill( $text ), 'url' => $url ? aim_t_footer_fill( $url ) : '' ];
		}
	}
	return $links;
}

/** Everything the footer template needs. */
function aim_t_footer_args() {
	$s     = class_exists( 'AIM_Settings' ) ? AIM_Settings::all() : [];
	$phone = aim_t_opt( 'f_phone' ) ?: ( $s['support_phone'] ?? '' );
	// "@support", "support" or a pasted link "https://t.me/support" → "support".
	$tg    = trim( preg_replace( '#^(https?://)?(www\.)?(t|telegram)\.me/#i', '', trim( (string) aim_t_opt( 'f_telegram' ) ) ), '@/ ' );
	$on    = fn( $key ) => 'off' !== aim_t_opt( $key );
	return [
		'brand'      => get_bloginfo( 'name' ),
		'brand_sub'  => aim_t_opt( 'f_brand_sub' ),
		'logo'       => aim_t_opt( 'f_logo' ),
		'ticket'     => [ 'label' => aim_t_opt( 'f_ticket_label' ), 'text' => aim_t_opt( 'f_ticket_text' ), 'url' => aim_t_footer_fill( aim_t_opt( 'f_ticket_url' ) ?: '{tickets}' ) ],
		'telegram'   => $tg ? [ 'label' => aim_t_opt( 'f_telegram_label' ), 'id' => '@' . ltrim( $tg, '@' ), 'url' => 'https://t.me/' . ltrim( $tg, '@' ) ] : null,
		'phone'      => $phone ? [ 'label' => aim_t_opt( 'f_phone_label' ), 'text' => aim_t_fa( $phone ), 'url' => 'tel:' . preg_replace( '/[^\d+]/', '', aim_en_digits_safe( $phone ) ) ] : null,
		'email'      => aim_t_opt( 'f_email' ) ?: ( $s['support_email'] ?? '' ),
		'features'   => array_map( fn( $f ) => [ 'icon' => $f['icon'] ?: 'shield-check-line', 'title' => aim_t_footer_fill( $f['title'] ), 'text' => aim_t_footer_fill( $f['text'] ) ], (array) aim_t_opt( 'f_features' ) ),
		'columns'    => array_values( array_filter( array_map( fn( $c ) => [ 'title' => aim_t_footer_fill( $c['title'] ), 'links' => aim_t_footer_links( $c['links'] ) ], (array) aim_t_opt( 'f_columns' ) ), fn( $c ) => $c['links'] ) ),
		'news'       => $on( 'f_news' ) ? [ 'title' => aim_t_opt( 'f_news_title' ), 'text' => aim_t_footer_fill( aim_t_opt( 'f_news_text' ) ), 'placeholder' => aim_t_opt( 'f_news_placeholder' ), 'button' => aim_t_opt( 'f_news_button' ) ] : null,
		'social_t'   => aim_t_footer_fill( aim_t_opt( 'f_social_title' ) ),
		'socials'    => array_values( array_map( fn( $r ) => [ 'url' => aim_t_footer_fill( $r['url'] ) ] + $r, array_filter( (array) aim_t_opt( 'f_socials' ), fn( $r ) => ! empty( $r['url'] ) ) ) ),
		'about'      => aim_t_footer_fill( aim_t_opt( 'f_about' ) ),
		'badges'     => array_values( array_map( fn( $b ) => [ 'url' => aim_t_footer_fill( $b['url'] ?? '' ) ] + $b, array_filter( (array) aim_t_opt( 'f_badges' ), fn( $b ) => ! empty( $b['image'] ) || ! empty( $b['code'] ) ) ) ),
		'copyright'  => aim_t_copyright( aim_t_opt( 'f_copyright' ) ),
		'backtop'    => $on( 'f_backtop' ),
	];
}

function aim_en_digits_safe( $s ) {
	return function_exists( 'aim_en_digits' ) ? aim_en_digits( $s ) : $s;
}

/** Social networks offered in the panel: key => [ label, svg path data (24×24, stroke) ]. */
function aim_t_socials() {
	return [
		'instagram' => [ 'اینستاگرام', '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.3" cy="6.7" r=".6" fill="currentColor"/>' ],
		'telegram'  => [ 'تلگرام', '<path d="M21 4 3 11l6 2.2M21 4l-3 16-9-6.8M21 4 9 13.2V19l3-3.4"/>' ],
		'whatsapp'  => [ 'واتساپ', '<path d="M4 20l1.3-4A8 8 0 1 1 8.4 19z"/><path d="M9 9.5c.4 2 1.9 3.6 4 4.3l1-1.1 1.8.8"/>' ],
		'linkedin'  => [ 'لینکدین', '<rect x="3" y="3" width="18" height="18" rx="4"/><path d="M8 10.5V16M8 7.8v.1M11.5 16v-5.5M11.5 13c0-1.6 1-2.5 2.2-2.5S16 11.3 16 13v3"/>' ],
		'youtube'   => [ 'یوتیوب', '<rect x="2.5" y="5.5" width="19" height="13" rx="4"/><path d="m10 9.3 4.6 2.7-4.6 2.7z"/>' ],
		'aparat'    => [ 'آپارات', '<circle cx="12" cy="12" r="8.5"/><circle cx="9" cy="8.6" r="1.6"/><circle cx="15.4" cy="9" r="1.6"/><circle cx="8.6" cy="15" r="1.6"/><circle cx="15" cy="15.4" r="1.6"/>' ],
		'x'         => [ 'ایکس', '<path d="M4 4l16 16M20 4 4 20"/>' ],
		'eitaa'     => [ 'ایتا', '<path d="M5 18.5V7a3 3 0 0 1 3-3h8a3 3 0 0 1 3 3v6a3 3 0 0 1-3 3H8.5z"/><path d="M9 9.5h6M9 12.5h4"/>' ],
		'bale'      => [ 'بله', '<circle cx="12" cy="12" r="8.5"/><path d="m8.5 12.2 2.4 2.4 4.6-5"/>' ],
		'link'      => [ 'لینک دیگر', '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>' ],
	];
}

function aim_t_social_icon( $net ) {
	$all = aim_t_socials();
	$def = $all[ $net ] ?? $all['link'];
	return '<svg class="sf-social-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $def[1] . '</svg>';
}

/** Icons offered for footer feature rows: icon name => label. */
function aim_t_footer_icon_options() {
	return [
		'flashlight-line'         => 'رعد (تحویل سریع)',
		'shield-check-line'       => 'سپر (ضمانت)',
		'customer-service-2-line' => 'هدست (پشتیبانی)',
		'file-list-3-line'        => 'فاکتور (پیگیری سفارش)',
		'refresh-line'            => 'چرخش (تعویض / بازگشت)',
		'lock-2-line'             => 'قفل (امنیت)',
		'wallet-3-line'           => 'کیف پول',
		'bank-card-line'          => 'کارت بانکی',
		'time-line'               => 'ساعت',
		'vip-crown-2-line'        => 'تاج (ویژه)',
		'rocket-2-line'           => 'موشک',
		'sparkling-2-line'        => 'ستاره‌ها',
		'chat-3-line'             => 'گفتگو',
		'heart-3-line'            => 'قلب',
		'smartphone-line'         => 'موبایل',
	];
}
