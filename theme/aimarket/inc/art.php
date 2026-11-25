<?php
/**
 * Original SVG artwork: product tiles (used when a product has no image) and
 * section illustrations. Colours come from CSS custom properties, so the art
 * follows the theme colours and dark mode.
 */
defined( 'ABSPATH' ) || exit;

/** Use-case glyphs, drawn on a 64×64 grid, white on the tile. */
function aim_t_glyph( $kind ) {
	$g = [
		// Speech bubble with three dots.
		'text'     => '<path d="M14 18a8 8 0 0 1 8-8h20a8 8 0 0 1 8 8v14a8 8 0 0 1-8 8H30l-9 8v-8h1a8 8 0 0 1-8-8z" fill="#fff"/><circle cx="24" cy="25" r="3" fill="var(--t2)"/><circle cx="32" cy="25" r="3" fill="var(--t2)"/><circle cx="40" cy="25" r="3" fill="var(--t2)"/>',
		// Picture frame: sun + mountains.
		'image'    => '<rect x="11" y="14" width="42" height="34" rx="7" fill="#fff"/><circle cx="23" cy="25" r="4.5" fill="#fbbf24"/><path d="M14 45l12-12 8 8 6-6 10 10z" fill="var(--t2)"/>',
		// Play button on a screen + film perforations.
		'video'    => '<rect x="10" y="15" width="44" height="32" rx="8" fill="#fff"/><path d="M28 24.5v13l11-6.5z" fill="var(--t2)"/><rect x="14" y="50" width="8" height="3" rx="1.5" fill="#fff" opacity=".7"/><rect x="28" y="50" width="8" height="3" rx="1.5" fill="#fff" opacity=".7"/><rect x="42" y="50" width="8" height="3" rx="1.5" fill="#fff" opacity=".7"/>',
		// Code brackets.
		'code'     => '<rect x="10" y="14" width="44" height="36" rx="8" fill="#fff"/><path d="M26 25l-6 7 6 7M38 25l6 7-6 7M34 23l-4 18" fill="none" stroke="var(--t2)" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"/>',
		// Magnifier over a document.
		'research' => '<rect x="12" y="10" width="30" height="40" rx="6" fill="#fff" opacity=".85"/><rect x="17" y="17" width="18" height="3" rx="1.5" fill="var(--t2)" opacity=".5"/><rect x="17" y="24" width="14" height="3" rx="1.5" fill="var(--t2)" opacity=".5"/><circle cx="38" cy="36" r="10" fill="#fff" stroke="var(--t2)" stroke-width="3.5"/><path d="M45 43l7 7" stroke="#fff" stroke-width="5" stroke-linecap="round"/>',
		// Four-point sparkle.
		'daily'    => '<path d="M32 8c2 12 6 16 18 18-12 2-16 6-18 18-2-12-6-16-18-18 12-2 16-6 18-18z" fill="#fff"/><path d="M49 40c.9 5 2.6 6.7 7.5 7.5-4.9.9-6.6 2.6-7.5 7.5-.9-4.9-2.6-6.6-7.5-7.5 4.9-.8 6.6-2.5 7.5-7.5z" fill="#fff" opacity=".8"/>',
	];
	return $g[ $kind ] ?? $g['daily'];
}

/** Which glyph a product gets: first matching use-case category, else "daily". */
function aim_t_product_kind( WC_Product $p ) {
	$id    = $p->get_parent_id() ?: $p->get_id();
	$slugs = wp_get_post_terms( $id, 'product_cat', [ 'fields' => 'slugs' ] );
	foreach ( [ 'video', 'image', 'code', 'text', 'research', 'daily' ] as $k ) {
		if ( ! is_wp_error( $slugs ) && in_array( $k, $slugs, true ) ) {
			return $k;
		}
	}
	return 'daily';
}

/**
 * Product tile palette: one of eight harmonious gradients, chosen from the
 * product name so each service keeps the same colours everywhere.
 */
function aim_t_palette( $name ) {
	$pal = [
		[ '#3b82f6', '#1d4ed8' ], // blue
		[ '#22d3ee', '#0e7490' ], // cyan
		[ '#34d399', '#047857' ], // emerald
		[ '#818cf8', '#4338ca' ], // indigo
		[ '#f59e0b', '#c2410c' ], // amber
		[ '#fb7185', '#be123c' ], // rose
		[ '#2dd4bf', '#0f766e' ], // teal
		[ '#60a5fa', '#0c4a6e' ], // sky → deep
	];
	return $pal[ hexdec( substr( md5( strtolower( trim( $name ) ) ), 0, 4 ) ) % count( $pal ) ];
}

/**
 * Product artwork tile (square). Initials of the service sit in the corner so
 * tiles stay recognisable at small sizes.
 */
function aim_t_art( WC_Product $p, $class = 'pc-logo' ) {
	$base     = $p->get_parent_id() ? wc_get_product( $p->get_parent_id() ) : $p;
	$name     = trim( preg_replace( '/^(اکانت|اشتراک)\s+/u', '', $base->get_name() ) );
	[ $c1, $c2 ] = aim_t_palette( $name );
	$kind     = aim_t_product_kind( $base );
	$id       = 'g' . substr( md5( $name . $class . wp_rand() ), 0, 8 );
	$initials = mb_strtoupper( mb_substr( preg_replace( '/[^\p{L}\p{N}]/u', '', $name ), 0, 2 ) );
	return sprintf(
		'<svg class="%1$s is-art" viewBox="0 0 64 64" role="img" aria-label="%2$s" style="--t1:%3$s;--t2:%4$s">'
		. '<defs><linearGradient id="%5$s" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="%3$s"/><stop offset="1" stop-color="%4$s"/></linearGradient></defs>'
		. '<rect width="64" height="64" rx="16" fill="url(#%5$s)"/>'
		. '<circle cx="56" cy="6" r="18" fill="#fff" opacity=".12"/><circle cx="4" cy="62" r="14" fill="#000" opacity=".08"/>'
		. '<g transform="translate(6 4) scale(.82)">%6$s</g>'
		. '<text x="58" y="58" text-anchor="end" font-family="system-ui,sans-serif" font-size="9" font-weight="800" fill="#fff" opacity=".9">%7$s</text>'
		. '</svg>',
		esc_attr( $class ),
		esc_attr( $base->get_name() ),
		esc_attr( $c1 ),
		esc_attr( $c2 ),
		esc_attr( $id ),
		aim_t_glyph( $kind ),
		esc_html( $initials )
	);
}

/**
 * Section illustrations (wide, 320×200). Colours: --v-500/--v-100/--v-900 from the theme.
 */
function aim_t_illus( $name, $class = '' ) {
	$cls  = trim( 'illus ' . $class );
	static $n = 0;
	$u    = 'ilg' . ( ++$n );
	// Gradients per SVG (ids must be unique on the page); colours come from CSS on the stops.
	$grad = fn( $id, $a, $b ) => sprintf( '<linearGradient id="%s-%s" x1="0" y1="0" x2="1" y2="1"><stop offset="0" class="%s"/><stop offset="1" class="%s"/></linearGradient>', $u, $id, $a, $b );
	$defs = '<defs>' . $grad( 'a', 'il-s-a1', 'il-s-a2' ) . $grad( 'g', 'il-s-g1', 'il-s-g2' ) . $grad( 'k', 'il-s-k1', 'il-s-k2' ) . '</defs>';
	$fill = fn( $id ) => sprintf( ' style="fill:url(#%s-%s)"', $u, $id );
	$open = '<svg class="' . esc_attr( $cls ) . '" viewBox="0 0 320 200" aria-hidden="true" focusable="false">' . $defs;
	$bg   = '<rect x="10" y="14" width="300" height="172" rx="28" class="il-bg"/><circle cx="276" cy="40" r="46" class="il-soft"/><circle cx="40" cy="170" r="34" class="il-soft"/>';
	$card = fn( $x, $y, $w, $h, $r = 14, $cls2 = 'il-card' ) => sprintf( '<rect x="%d" y="%d" width="%d" height="%d" rx="%d" class="%s"/>', $x, $y, $w, $h, $r, $cls2 );
	$line = fn( $x, $y, $w, $cls2 = 'il-line' ) => sprintf( '<rect x="%d" y="%d" width="%d" height="7" rx="3.5" class="%s"/>', $x, $y, $w, $cls2 );

	switch ( $name ) {
		case 'text': // Chat window with a typed answer.
			$svg = $card( 50, 40, 220, 120 ) . $card( 66, 56, 120, 26, 12, 'il-accent' ) . $line( 78, 66, 90, 'il-on' )
				. $card( 132, 92, 122, 52, 12, 'il-tint' ) . $line( 144, 104, 98 ) . $line( 144, 116, 80 ) . $line( 144, 128, 60 )
				. '<path d="M248 60l4 10 10 4-10 4-4 10-4-10-10-4 10-4z" class="il-pop"/>';
			break;
		case 'image': // Canvas with generated picture and brush.
			$svg = $card( 56, 34, 168, 132 ) . $card( 70, 48, 140, 90, 12, 'il-tint' ) . '<circle cx="100" cy="76" r="12" class="il-sun"/><path d="M74 134l42-42 26 26 20-20 46 36z" class="il-accent"/>'
				. '<rect x="232" y="56" width="16" height="78" rx="8" transform="rotate(25 240 95)" class="il-dark"/><circle cx="252" cy="136" r="10" class="il-accent"/>';
			break;
		case 'video': // Timeline + player.
			$svg = $card( 46, 36, 228, 128 ) . $card( 60, 50, 200, 76, 12, 'il-dark' ) . '<path d="M150 72v32l26-16z" class="il-on"/>'
				. $card( 60, 136, 200, 14, 7, 'il-tint' ) . $card( 60, 136, 120, 14, 7, 'il-accent' ) . '<circle cx="180" cy="143" r="9" class="il-on il-ring"/>';
			break;
		case 'code': // Editor with code lines.
			$svg = $card( 46, 34, 228, 132 ) . '<circle cx="64" cy="50" r="4" class="il-red"/><circle cx="76" cy="50" r="4" class="il-sun"/><circle cx="88" cy="50" r="4" class="il-green"/>'
				. $line( 66, 70, 60, 'il-accent' ) . $line( 132, 70, 40 ) . $line( 82, 86, 90 ) . $line( 82, 102, 120, 'il-accent' ) . $line( 82, 118, 70 ) . $line( 66, 134, 40, 'il-accent' )
				. $card( 200, 96, 58, 50, 12, 'il-dark' ) . '<path d="M218 112l-8 9 8 9M240 112l8 9-8 9" fill="none" class="il-stroke-on"/>';
			break;
		case 'research': // Chart + magnifier.
			$svg = $card( 50, 36, 200, 128 ) . $card( 70, 120, 22, 28, 6, 'il-tint' ) . $card( 100, 98, 22, 50, 6, 'il-accent' ) . $card( 130, 80, 22, 68, 6, 'il-tint' ) . $card( 160, 62, 22, 86, 6, 'il-accent' )
				. '<circle cx="226" cy="84" r="30" class="il-on il-ring-thick"/><path d="M248 106l22 22" class="il-stroke-dark"/>';
			break;
		case 'daily': // Checklist + sparkle.
			$svg = $card( 70, 30, 180, 140 ) . '<circle cx="96" cy="62" r="9" class="il-accent"/>' . $line( 114, 58, 100 ) . '<circle cx="96" cy="94" r="9" class="il-accent"/>' . $line( 114, 90, 80 )
				. '<circle cx="96" cy="126" r="9" class="il-tint"/>' . $line( 114, 122, 110 ) . '<path d="M262 120c3 18 9 24 27 27-18 3-24 9-27 27-3-18-9-24-27-27 18-3 24-9 27-27z" class="il-pop"/>';
			break;
		case 'step-plan': // Stack of plan cards, one selected.
			$svg = $card( 90, 42, 160, 40, 12 ) . $card( 80, 90, 160, 40, 12, 'il-sel' ) . '<circle cx="104" cy="110" r="8" class="il-accent"/>' . $line( 120, 106, 80 ) . $card( 90, 138, 160, 30, 12 );
			break;
		case 'step-login': // Phone with a mail code, plus a verified badge.
			$svg = '<ellipse cx="160" cy="182" rx="70" ry="5" class="il-shadow"/>'
				. '<rect x="116" y="22" width="88" height="156" rx="22" class="il-ink"/><rect x="123" y="31" width="74" height="138" rx="16" class="il-card"/><rect x="146" y="37" width="28" height="6" rx="3" class="il-tint"/>'
				. '<rect x="140" y="56" width="40" height="28" rx="7"' . $fill( 'a' ) . '/><path d="M141 60l19 13 19-13" class="il-stroke-on il-thin"/>'
				. '<rect x="133" y="98" width="12" height="17" rx="4" class="il-accent"/><rect x="148" y="98" width="12" height="17" rx="4" class="il-accent"/><rect x="163" y="98" width="12" height="17" rx="4" class="il-tint"/><rect x="178" y="98" width="12" height="17" rx="4" class="il-tint"/>'
				. '<rect x="133" y="128" width="57" height="15" rx="7.5"' . $fill( 'a' ) . '/><rect x="146" y="150" width="30" height="5" rx="2.5" class="il-tint"/>'
				. '<circle cx="226" cy="70" r="20"' . $fill( 'k' ) . ' class="il-lift"/><path d="M217 70l6 6 12-13" class="il-stroke-on il-thin"/>'
				. '<g transform="rotate(-12 86 120)"><rect x="60" y="102" width="52" height="36" rx="8" class="il-card"/><path d="M62 106l24 16 24-16" class="il-stroke-accent il-thin"/></g>'
				. '<path d="M250 128c2 10 5 13 15 15-10 2-13 5-15 15-2-10-5-13-15-15 10-2 13-5 15-15z" class="il-pop"/>';
			break;
		case 'step-pay': // Bank card with a gold chip, protected by a shield.
			$svg = '<ellipse cx="170" cy="176" rx="96" ry="6" class="il-shadow"/>'
				. '<rect x="76" y="46" width="160" height="100" rx="16" transform="rotate(-8 156 96)" class="il-tint"/>'
				. '<rect x="88" y="60" width="164" height="102" rx="16"' . $fill( 'a' ) . '/>'
				. '<rect x="88" y="80" width="164" height="14" class="il-band"/>'
				. '<rect x="104" y="106" width="30" height="22" rx="5"' . $fill( 'g' ) . '/><path d="M104 117h30M119 106v22" class="il-chip"/>'
				. '<rect x="104" y="140" width="72" height="7" rx="3.5" class="il-on il-o8"/><circle cx="226" cy="138" r="10" class="il-on il-o5"/><circle cx="212" cy="138" r="10" class="il-on il-o3"/>'
				. '<path d="M252 88l30 11v22c0 22-13 35-30 42-17-7-30-20-30-42V99z"' . $fill( 'k' ) . ' class="il-lift"/><path d="M239 124l9 9 16-18" class="il-stroke-on"/>'
				. '<path d="M70 52c2 10 5 13 15 15-10 2-13 5-15 15-2-10-5-13-15-15 10-2 13-5 15-15z" class="il-pop"/>';
			break;
		case 'step-receive': // Account details rising out of an open box, with a gold key.
			$svg = '<ellipse cx="160" cy="180" rx="86" ry="5" class="il-shadow"/>'
				. '<path d="M92 112h136v44a12 12 0 0 1-12 12H104a12 12 0 0 1-12-12z" class="il-tint"/>'
				. '<g class="il-lift"><rect x="112" y="34" width="96" height="92" rx="14" class="il-card"/>'
				. '<circle cx="134" cy="56" r="10"' . $fill( 'a' ) . '/><rect x="150" y="50" width="44" height="6" rx="3" class="il-line"/><rect x="150" y="60" width="28" height="5" rx="2.5" class="il-line"/>'
				. '<rect x="124" y="76" width="72" height="14" rx="7" class="il-tint"/><rect x="132" y="81" width="34" height="4" rx="2" class="il-accent"/>'
				. '<rect x="124" y="96" width="72" height="14" rx="7" class="il-tint"/><circle cx="134" cy="103" r="2.6" class="il-accent"/><circle cx="142" cy="103" r="2.6" class="il-accent"/><circle cx="150" cy="103" r="2.6" class="il-accent"/><circle cx="158" cy="103" r="2.6" class="il-accent"/><circle cx="166" cy="103" r="2.6" class="il-accent"/></g>'
				. '<path d="M92 120l68 30 68-30v36a12 12 0 0 1-12 12H104a12 12 0 0 1-12-12z"' . $fill( 'a' ) . '/>'
				. '<g transform="rotate(-28 244 66)"' . $fill( 'g' ) . '><circle cx="244" cy="66" r="15"/><rect x="255" y="62" width="34" height="8" rx="4"/><rect x="279" y="66" width="6" height="12" rx="2"/><rect x="269" y="66" width="5" height="9" rx="2"/></g><circle cx="244" cy="66" r="5.5" class="il-card"/>'
				. '<path d="M82 60c2 10 5 13 15 15-10 2-13 5-15 15-2-10-5-13-15-15 10-2 13-5 15-15z" class="il-pop"/>';
			break;
		case 'step-launch': // Rocket taking off from a cloud.
			$svg = '<circle cx="128" cy="170" r="16" class="il-tint"/><circle cx="160" cy="174" r="21" class="il-tint"/><circle cx="194" cy="170" r="16" class="il-tint"/>'
				. '<path d="M147 126h26c0 18-6 30-13 42-7-12-13-24-13-42z"' . $fill( 'g' ) . '/>'
				. '<path d="M136 96l-24 32 28-5zM184 96l24 32-28-5z"' . $fill( 'a' ) . '/>'
				. '<path d="M160 24c25 16 35 45 31 80l-4 26h-54l-4-26c-4-35 6-64 31-80z" class="il-card il-lift"/>'
				. '<path d="M160 24c10 6 18 15 23 26h-46c5-11 13-20 23-26z"' . $fill( 'a' ) . '/>'
				. '<circle cx="160" cy="82" r="15"' . $fill( 'a' ) . '/><circle cx="160" cy="82" r="8" class="il-on il-o3"/><circle cx="155" cy="77" r="3.5" class="il-on il-o8"/>'
				. '<rect x="150" y="108" width="20" height="6" rx="3" class="il-tint"/>'
				. '<path d="M96 50c2 10 5 13 15 15-10 2-13 5-15 15-2-10-5-13-15-15 10-2 13-5 15-15zM236 70c1.5 8 4 10.5 12 12-8 1.5-10.5 4-12 12-1.5-8-4-10.5-12-12 8-1.5 10.5-4 12-12z" class="il-pop"/>'
				. '<circle cx="226" cy="40" r="4" class="il-accent il-o5"/><circle cx="84" cy="118" r="3" class="il-accent il-o5"/>';
			break;
		case 'gift':
			$svg = $card( 92, 84, 136, 86, 14, 'il-on' ) . $card( 84, 64, 152, 30, 10, 'il-on' ) . '<rect x="152" y="64" width="16" height="106" class="il-sun"/>'
				. '<path d="M160 64c-10-26-40-26-34-6 4 10 22 8 34 6zM160 64c10-26 40-26 34-6-4 10-22 8-34 6z" class="il-sun"/>';
			break;
		case 'faq':
			$svg = $card( 50, 40, 150, 80, 20 ) . '<text x="125" y="96" text-anchor="middle" class="il-q">؟</text>' . $card( 130, 104, 140, 62, 20, 'il-accent' ) . $line( 150, 124, 100, 'il-on' ) . $line( 150, 140, 70, 'il-on' );
			break;
		case 'cart': // Empty cart waiting for a first item.
			$svg = '<ellipse cx="172" cy="182" rx="84" ry="5" class="il-shadow"/>'
				. '<path d="M72 54h20c4 0 7 2 8 6l3 12" class="il-stroke-ink"/>'
				. '<path d="M102 72h142a8 8 0 0 1 7.7 10.2l-15.4 54a10 10 0 0 1-9.6 7.3H126a10 10 0 0 1-9.7-7.6z"' . $fill( 'a' ) . ' class="il-lift"/>'
				. '<path d="M150 86v44M178 86v44M206 86v44M116 108h124" class="il-slat"/>'
				. '<path d="M120 146l-6 10h120" class="il-stroke-ink"/>'
				. '<circle cx="132" cy="166" r="11" class="il-ink"/><circle cx="132" cy="166" r="4" class="il-bg-dot"/><circle cx="222" cy="166" r="11" class="il-ink"/><circle cx="222" cy="166" r="4" class="il-bg-dot"/>'
				. '<rect x="150" y="22" width="54" height="40" rx="11" class="il-dash"/><path d="M177 33v18M168 42h18" class="il-stroke-accent il-thin"/>'
				. '<path d="M258 36c2 12 6 16 18 18-12 2-16 6-18 18-2-12-6-16-18-18 12-2 16-6 18-18z" class="il-pop"/><circle cx="96" cy="96" r="4" class="il-accent il-o5"/><circle cx="270" cy="110" r="3" class="il-accent il-o5"/>';
			break;
		case 'success':
			$svg = '<circle cx="160" cy="100" r="56" class="il-green-soft"/><circle cx="160" cy="100" r="40" class="il-green"/><path d="M142 100l12 12 24-26" fill="none" class="il-stroke-on"/>'
				. '<path d="M236 54c2 12 6 16 18 18-12 2-16 6-18 18-2-12-6-16-18-18 12-2 16-6 18-18zM82 128c1.5 8 4 10.5 12 12-8 1.5-10.5 4-12 12-1.5-8-4-10.5-12-12 8-1.5 10.5-4 12-12z" class="il-pop"/>';
			break;
		default:
			$svg = '';
	}
	return $open . $bg . $svg . '</svg>';
}
