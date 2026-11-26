<?php
defined( 'ABSPATH' ) || exit;

/**
 * Normalize an Iranian mobile number to 09xxxxxxxxx, or return '' if invalid.
 * Accepts Persian/Arabic digits, +98, 0098 and 9xxxxxxxxx forms.
 */
function aim_normalize_phone( $phone ) {
	if ( ! is_scalar( $phone ) ) {
		return ''; // e.g. an array from a crafted REST body.
	}
	$phone = aim_en_digits( (string) $phone );
	$phone = preg_replace( '/\D+/', '', $phone );
	if ( str_starts_with( $phone, '0098' ) ) {
		$phone = '0' . substr( $phone, 4 );
	} elseif ( str_starts_with( $phone, '98' ) && strlen( $phone ) === 12 ) {
		$phone = '0' . substr( $phone, 2 );
	} elseif ( str_starts_with( $phone, '9' ) && strlen( $phone ) === 10 ) {
		$phone = '0' . $phone;
	}
	return preg_match( '/^09\d{9}$/', $phone ) ? $phone : '';
}

function aim_en_digits( $str ) {
	return strtr( (string) $str, [
		'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
		'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
	] );
}

function aim_fa_digits( $str ) {
	return strtr( (string) $str, [ '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ] );
}

/** Gregorian to Jalali (algorithm from jdf, public domain). */
function aim_gregorian_to_jalali( $gy, $gm, $gd ) {
	$g_d_m = [ 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 ];
	$gy2   = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
	$days  = 355666 + ( 365 * $gy ) + intdiv( $gy2 + 3, 4 ) - intdiv( $gy2 + 99, 100 ) + intdiv( $gy2 + 399, 400 ) + $gd + $g_d_m[ $gm - 1 ];
	$jy    = -1595 + ( 33 * intdiv( $days, 12053 ) );
	$days %= 12053;
	$jy   += 4 * intdiv( $days, 1461 );
	$days %= 1461;
	if ( $days > 365 ) {
		$jy  += intdiv( $days - 1, 365 );
		$days = ( $days - 1 ) % 365;
	}
	if ( $days < 186 ) {
		$jm = 1 + intdiv( $days, 31 );
		$jd = 1 + ( $days % 31 );
	} else {
		$jm = 7 + intdiv( $days - 186, 30 );
		$jd = 1 + ( ( $days - 186 ) % 30 );
	}
	return [ $jy, $jm, $jd ];
}

/** Format a timestamp or GMT MySQL datetime as a Jalali date in the site timezone. */
function aim_jdate( $time, $with_time = false ) {
	if ( ! $time || '0000-00-00 00:00:00' === $time ) {
		return '—';
	}
	$ts = is_numeric( $time ) ? (int) $time : strtotime( $time . ' UTC' );
	$dt = ( new DateTime( '@' . $ts ) )->setTimezone( wp_timezone() );
	[ $y, $m, $d ] = aim_gregorian_to_jalali( (int) $dt->format( 'Y' ), (int) $dt->format( 'n' ), (int) $dt->format( 'j' ) );
	$out = sprintf( '%04d/%02d/%02d', $y, $m, $d );
	if ( $with_time ) {
		$out .= ' - ' . $dt->format( 'H:i' );
	}
	return aim_fa_digits( $out );
}

function aim_price( $amount ) {
	return aim_fa_digits( number_format( (float) $amount ) ) . ' <small>' . esc_html( aim_currency_label() ) . '</small>';
}

/** «تومان» or «ریال», from the WooCommerce currency (IRT / IRR); تومان without WooCommerce. */
function aim_currency_label() {
	return function_exists( 'get_woocommerce_currency' ) && 'IRR' === get_woocommerce_currency() ? 'ریال' : 'تومان';
}

function aim_number( $n ) {
	return aim_fa_digits( number_format( (float) $n ) );
}

/** Load a plugin template with variables. */
/**
 * File for a panel template ("admin/orders", "user/dashboard"…). Modules add
 * their own pages with the 'aim_template_file' filter.
 */
function aim_template_path( $name ) {
	return (string) apply_filters( 'aim_template_file', AIM_DIR . 'templates/' . $name . '.php', $name );
}

function aim_template( $name, $vars = [] ) {
	$file = aim_template_path( $name );
	if ( ! file_exists( $file ) ) {
		return;
	}
	extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract
	include $file;
}

function aim_panel_url( $panel = 'admin', $section = '', $args = [] ) {
	// The store admin panel lives in wp-admin (AIM_WP_Admin); only the customer panel is on the front end.
	if ( 'admin' === $panel ) {
		return AIM_WP_Admin::url( $section, $args );
	}
	$url = home_url( '/panel/' . $panel . '/' . ( $section ? $section . '/' : '' ) );
	return $args ? add_query_arg( $args, $url ) : $url;
}

function aim_login_url( $redirect = '' ) {
	$url = home_url( '/login/' );
	return $redirect ? add_query_arg( 'redirect_to', rawurlencode( $redirect ), $url ) : $url;
}

function aim_user_phone( $user_id ) {
	return (string) get_user_meta( $user_id, 'aim_phone', true );
}

function aim_get_user_by_phone( $phone ) {
	$users = get_users( [
		'meta_key'   => 'aim_phone',
		'meta_value' => $phone,
		'number'     => 1,
		'fields'     => 'ids',
	] );
	return $users ? (int) $users[0] : 0;
}

function aim_client_ip() {
	$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	if ( ! filter_var( $remote, FILTER_VALIDATE_IP ) ) {
		return '0.0.0.0';
	}
	$trusted = (array) apply_filters( 'aim_trusted_proxies', defined( 'AIM_TRUSTED_PROXIES' ) ? AIM_TRUSTED_PROXIES : [ '127.0.0.0/8', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '::1/128', 'fc00::/7' ] );
	$is_trusted = static function ( $ip ) use ( $trusted ) {
		$packed = inet_pton( $ip );
		foreach ( $trusted as $range ) {
			$parts = explode( '/', (string) $range, 2 );
			if ( ! filter_var( $parts[0], FILTER_VALIDATE_IP ) ) {
				continue;
			}
			$net = inet_pton( $parts[0] );
			$bits = isset( $parts[1] ) ? (int) $parts[1] : strlen( $net ) * 8;
			if ( strlen( $packed ) !== strlen( $net ) || $bits < 0 || $bits > strlen( $net ) * 8 ) {
				continue;
			}
			$bytes = intdiv( $bits, 8 );
			$remaining = $bits % 8;
			if ( substr( $packed, 0, $bytes ) === substr( $net, 0, $bytes ) && ( ! $remaining || ( ( ord( $packed[ $bytes ] ) ^ ord( $net[ $bytes ] ) ) >> ( 8 - $remaining ) ) === 0 ) ) {
				return true;
			}
		}
		return false;
	};
	if ( ! $is_trusted( $remote ) ) {
		return $remote;
	}
	$header = 'HTTP_' . strtoupper( str_replace( '-', '_', (string) apply_filters( 'aim_client_ip_header', 'X-Forwarded-For' ) ) );
	$chain = isset( $_SERVER[ $header ] ) ? sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) : '';
	foreach ( array_reverse( explode( ',', $chain ) ) as $candidate ) {
		$candidate = trim( $candidate );
		if ( filter_var( $candidate, FILTER_VALIDATE_IP ) && ! $is_trusted( $candidate ) ) {
			return $candidate;
		}
	}
	return $remote;
}

/**
 * The brand mark in the panel, login page and customer area. Themes can return
 * their own logo (an <img class="brand-mark is-img">) through 'aim_brand_mark'.
 */
function aim_brand_mark() {
	return (string) apply_filters( 'aim_brand_mark', '<span class="brand-mark">' . aim_icon( 'sparkling-2-fill' ) . '</span>' );
}

function aim_icon( $name ) {
	return '<i class="ic-' . esc_attr( $name ) . '" aria-hidden="true"></i>';
}

/** Page title + breadcrumb + primary actions, shared by every panel page. */
function aim_page_head( $title, $actions = '', $panel = 'admin' ) {
	$root = 'admin' === $panel ? 'پنل مدیریت' : 'ناحیهٔ کاربری';
	echo '<div class="page-head"><div><h1>' . esc_html( $title ) . '</h1>';
	echo '<nav class="breadcrumb" aria-label="مسیر"><a href="' . esc_url( aim_panel_url( $panel ) ) . '">' . esc_html( $root ) . '</a>';
	echo aim_icon( 'arrow-left-s-line' ) . '<span>' . esc_html( $title ) . '</span></nav></div>';
	if ( $actions ) {
		echo '<div class="actions">' . $actions . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts by callers.
	}
	echo '</div>';
}

/** Pagination footer for panel tables. */
function aim_pagination( $total, $per_page, $page ) {
	$pages = (int) ceil( $total / max( 1, $per_page ) );
	echo '<div class="pagination"><span>' . esc_html( aim_fa_digits( number_format( $total ) ) ) . ' مورد</span>';
	if ( $pages > 1 ) {
		echo '<div class="pages">';
		$window = array_unique( array_filter( [ 1, $page - 1, $page, $page + 1, $pages ], fn( $p ) => $p >= 1 && $p <= $pages ) );
		sort( $window );
		$prev = 0;
		foreach ( $window as $p ) {
			if ( $prev && $p > $prev + 1 ) {
				echo '<span class="text-3">…</span>';
			}
			if ( $p === $page ) {
				echo '<span class="cur">' . esc_html( aim_fa_digits( $p ) ) . '</span>';
			} else {
				echo '<a href="' . esc_url( add_query_arg( 'paged', $p ) ) . '">' . esc_html( aim_fa_digits( $p ) ) . '</a>';
			}
			$prev = $p;
		}
		echo '</div>';
	}
	echo '</div>';
}

/** Status tabs that filter a list through a query arg. */
function aim_status_tabs( array $tabs, $current, $arg = 'status' ) {
	echo '<nav class="status-tabs" aria-label="فیلتر وضعیت">';
	foreach ( $tabs as $key => $tab ) {
		$url = remove_query_arg( 'paged', add_query_arg( $arg, $key ?: false ) );
		printf(
			'<a class="status-tab%s" href="%s">%s%s</a>',
			(string) $key === (string) $current ? ' is-active' : '',
			esc_url( $url ),
			esc_html( $tab['label'] ),
			isset( $tab['count'] ) ? '<span class="count">' . esc_html( aim_fa_digits( $tab['count'] ) ) . '</span>' : ''
		);
	}
	echo '</nav>';
}

function aim_empty( $icon, $title, $text = '', $action = '' ) {
	echo '<div class="empty">' . aim_icon( $icon ) . '<h3>' . esc_html( $title ) . '</h3>';
	if ( $text ) {
		echo '<p>' . esc_html( $text ) . '</p>';
	}
	echo $action . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
}

/** Simple SVG sparkline from a list of numbers. */
function aim_sparkline( array $values ) {
	$values = array_values( $values ) ?: [ 0 ];
	$n      = count( $values );
	$max    = max( $values ) ?: 1;
	$w      = 110;
	$h      = 44;
	$pts    = [];
	foreach ( $values as $i => $v ) {
		$x     = $n > 1 ? ( $w / ( $n - 1 ) ) * $i : $w / 2;
		$y     = $h - 4 - ( ( $h - 8 ) * ( $v / $max ) );
		$pts[] = round( $x, 1 ) . ',' . round( $y, 1 );
	}
	$line = 'M' . implode( ' L', $pts );
	$area = $line . " L$w,$h L0,$h Z";
	return '<svg class="spark" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" aria-hidden="true"><path class="area" d="' . esc_attr( $area ) . '"/><path class="line" d="' . esc_attr( $line ) . '"/></svg>';
}

function aim_order_status_pill( $status ) {
	$map = [
		'pending'    => [ 'در انتظار پرداخت', 'warning' ],
		'processing' => [ 'در حال پردازش', 'info' ],
		'on-hold'    => [ 'در انتظار بررسی', 'warning' ],
		'completed'  => [ 'تکمیل‌شده', 'success' ],
		'cancelled'  => [ 'لغوشده', 'danger' ],
		'refunded'   => [ 'مسترد شده', 'danger' ],
		'failed'     => [ 'ناموفق', 'danger' ],
	];
	[ $label, $tone ] = $map[ $status ] ?? [ wc_get_order_status_name( $status ), '' ];
	return '<span class="pill' . ( $tone ? ' pill-' . $tone : '' ) . '">' . esc_html( $label ) . '</span>';
}

/**
 * Send a store email. Uses WooCommerce's branded HTML layout when available,
 * so plugin emails look like the store's other emails; plain text otherwise.
 */
function aim_mail( $to, $subject, $heading, $text ) {
	if ( function_exists( 'WC' ) && WC()->mailer() ) {
		$mailer = WC()->mailer();
		$html   = wpautop( make_clickable( esc_html( $text ) ) );
		return $mailer->send( $to, $subject, $mailer->wrap_message( $heading, $html ), [ 'Content-Type: text/html; charset=UTF-8' ] );
	}
	return wp_mail( $to, $subject, $text );
}

/** Terms page: WooCommerce's terms page, else a page with slug "terms", else the privacy policy. '' if none. */
function aim_terms_url() {
	$id = function_exists( 'wc_terms_and_conditions_page_id' ) ? wc_terms_and_conditions_page_id() : 0;
	if ( $id && 'publish' === get_post_status( $id ) ) {
		return get_permalink( $id );
	}
	$page = get_page_by_path( 'terms' );
	if ( $page && 'publish' === $page->post_status ) {
		return get_permalink( $page );
	}
	return (string) get_privacy_policy_url();
}

/** Persian order status names (WooCommerce's own labels depend on its translation being installed). */
function aim_order_status_label( $status ) {
	$status = str_starts_with( (string) $status, 'wc-' ) ? substr( $status, 3 ) : (string) $status;
	$map    = [
		'pending'    => 'در انتظار پرداخت',
		'processing' => 'در حال پردازش',
		'on-hold'    => 'در انتظار بررسی',
		'completed'  => 'تکمیل‌شده',
		'cancelled'  => 'لغوشده',
		'refunded'   => 'مسترد شده',
		'failed'     => 'ناموفق',
	];
	return $map[ $status ] ?? wc_get_order_status_name( $status );
}

/** Order statuses shown in the panel (WooCommerce's internal checkout drafts are hidden). */
function aim_panel_order_statuses() {
	return array_values( array_diff( array_keys( wc_get_order_statuses() ), [ 'wc-checkout-draft' ] ) );
}

/**
 * Inline <head> script that sets the colour mode before first paint:
 * the visitor's own choice (saved by the toggle) wins, otherwise the store default
 * from Settings → General (light, dark, or "auto" = follow the device).
 */
/**
 * Inline <head> script that applies the saved light/dark mode before first paint.
 * The shop and the customer area share 'aim-theme'; the admin panel keeps its own
 * key ('aim-theme-admin'), so switching one side never changes the other.
 */
function aim_color_mode_script( $key = 'aim-theme' ) {
	$default = class_exists( 'AIM_Settings' ) ? (string) AIM_Settings::get( 'theme_mode' ) : 'light';
	$default = in_array( $default, [ 'light', 'dark', 'auto' ], true ) ? $default : 'light';
	return "<script>(function(){var m=null;try{m=localStorage.getItem(" . wp_json_encode( $key ) . ");}catch(e){}"
		. "if(!m){var d=" . wp_json_encode( $default ) . ";m=d==='auto'?(window.matchMedia&&matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'):d;}"
		. "document.documentElement.dataset.theme=m;})();</script>\n";
}

/**
 * Products and variations whose managed stock is at or below the "low stock"
 * threshold (settings), lowest first. Works for any WooCommerce store; the
 * accounts module keeps account products' stock equal to ready accounts.
 *
 * @return array[] [ id, label, available ]
 */
function aim_low_stock_items( $limit = 50 ) {
	static $cache = [];
	if ( isset( $cache[ $limit ] ) ) {
		return $cache[ $limit ];
	}
	if ( ! function_exists( 'wc_get_product' ) ) {
		return [];
	}
	global $wpdb;
	$threshold = (int) AIM_Settings::get( 'low_stock_threshold' );
	$rows      = $wpdb->get_results( $wpdb->prepare(
		"SELECT l.product_id, l.stock_quantity FROM {$wpdb->prefix}wc_product_meta_lookup l
		INNER JOIN {$wpdb->posts} p ON p.ID = l.product_id
		WHERE l.stock_quantity IS NOT NULL AND l.stock_quantity <= %d AND p.post_status = 'publish'
		ORDER BY l.stock_quantity ASC LIMIT %d",
		$threshold,
		$limit
	) );
	$items = [];
	foreach ( $rows as $row ) {
		$p = wc_get_product( $row->product_id );
		if ( ! $p || ! $p->managing_stock() ) {
			continue;
		}
		$parent = $p->get_parent_id() ? wc_get_product( $p->get_parent_id() ) : null;
		if ( $parent && 'publish' !== $parent->get_status() ) {
			continue;
		}
		$plan    = $parent ? wc_get_formatted_variation( $p, true, false, false ) : '';
		$items[] = [
			'id'        => $p->get_id(),
			'label'     => $parent ? $parent->get_name() . ( $plan ? ' — ' . $plan : '' ) : $p->get_name(),
			'available' => max( 0, (int) $row->stock_quantity ),
		];
	}
	return $cache[ $limit ] = $items;
}
