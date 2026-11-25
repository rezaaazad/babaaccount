<?php
/**
 * Elementor integration (works with the free Elementor).
 *
 *  - Registers the «هوش‌مارکت» widget category and one widget per site section
 *    (inc/elementor/widgets/), plus the theme's icons as an icon library.
 *  - Header / footer: an Elementor template (Templates → «سربرگ/پابرگ
 *    هوش‌مارکت») replaces the built-in one; see aim_t_elementor_part().
 *  - aim_t_elementor_install() creates the Elementor homepage, the «تماس با ما» page and the
 *    header/footer templates with the current content, so everything is
 *    editable from the first visit.
 *
 * Without Elementor the theme keeps working with its built-in sections.
 */
defined( 'ABSPATH' ) || exit;

const AIM_EL_WIDGETS = [
	'hero'     => 'AIM_El_Hero',
	'products' => 'AIM_El_Products',
	'offer'    => 'AIM_El_Offer',
	'finder'   => 'AIM_El_Finder',
	'steps'    => 'AIM_El_Steps',
	'faq'      => 'AIM_El_Faq',
	'posts'    => 'AIM_El_Posts',
	'trust'    => 'AIM_El_Trust',
	'contact'  => 'AIM_El_Contact',
	'header'   => 'AIM_El_Header',
	'footer'   => 'AIM_El_Footer',
];

/** Whether Elementor is loaded and $post_id is built with it. */
function aim_t_is_elementor_page( $post_id ) {
	return did_action( 'elementor/loaded' ) && $post_id && \Elementor\Plugin::$instance->documents->get( $post_id )?->is_built_with_elementor();
}

/**
 * Print the Elementor template chosen for the header or footer.
 * Returns false (nothing printed) when there is none, so the caller shows the built-in part.
 */
function aim_t_elementor_part( $location ) {
	if ( ! did_action( 'elementor/loaded' ) ) {
		return false;
	}
	$id = aim_t_elementor_part_id( $location );
	if ( ! $id ) {
		return false;
	}
	// Inside the editor of that very template the preview already shows it.
	if ( isset( $_GET['elementor-preview'] ) && (int) $_GET['elementor-preview'] === $id ) { // phpcs:ignore WordPress.Security.NonceVerification
		return true;
	}
	$html = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $id, true );
	if ( '' === trim( (string) $html ) ) {
		return false;
	}
	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- rendered by Elementor.
	return true;
}

/** Template id for "header" / "footer": the one chosen in the panel, else the one the theme created. */
function aim_t_elementor_part_id( $location ) {
	$chosen = class_exists( 'AIM_Settings' ) ? (int) AIM_Settings::get( 'el_' . $location ) : 0;
	if ( -1 === $chosen ) {
		return 0; // "Built-in" chosen explicitly.
	}
	if ( $chosen && 'publish' === get_post_status( $chosen ) ) {
		return $chosen;
	}
	$found = get_posts( [ 'post_type' => 'elementor_library', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_aim_location', 'meta_value' => $location ] ); // phpcs:ignore WordPress.DB.SlowDBQuery
	return (int) ( $found[0] ?? 0 );
}

add_action( 'elementor/elements/categories_registered', function ( $manager ) {
	$manager->add_category( 'aimarket', [ 'title' => 'هوش‌مارکت', 'icon' => 'eicon-site-identity' ] );
} );

add_action( 'elementor/widgets/register', function ( $manager ) {
	require_once __DIR__ . '/class-widget.php';
	foreach ( AIM_EL_WIDGETS as $file => $class ) {
		require_once __DIR__ . '/widgets/' . $file . '.php';
		$manager->register( new $class() );
	}
} );

// The theme's icons in Elementor's icon picker (next to Font Awesome and SVG upload).
add_filter( 'elementor/icons_manager/additional_tabs', function ( $tabs ) {
	$css = get_template_directory_uri() . '/assets/css/icons.css';
	$ver = (string) filemtime( get_template_directory() . '/assets/css/icons.css' );
	$tabs['aim-icons'] = [
		'name'          => 'aim-icons',
		'label'         => 'آیکن‌های قالب',
		'url'           => $css,
		'enqueue'       => [ $css ],
		'prefix'        => 'ic-',
		'displayPrefix' => '',
		'labelIcon'     => 'ic-sparkling-2-fill',
		'ver'           => $ver,
		'fetchJson'     => get_template_directory_uri() . '/assets/icons/icons.json',
		'native'        => false,
	];
	return $tabs;
} );

// Theme sections are full-width blocks with their own spacing: no gap between them.
add_action( 'wp_enqueue_scripts', function () {
	wp_add_inline_style( 'aim-theme', '.elementor-widget[class*="elementor-widget-aim-"]{margin-block-end:0}' );
}, 20 );

// ---------------------------------------------------------------------------
// Installer
// ---------------------------------------------------------------------------

/**
 * Create the Elementor homepage and header/footer templates (once; pass
 * $force to rebuild the homepage from the built-in content).
 *
 * @return int Homepage id, or 0 without Elementor.
 */
function aim_t_elementor_install( $force = false ) {
	if ( ! did_action( 'elementor/loaded' ) ) {
		return 0;
	}
	$home = (int) get_option( 'aim_el_home' );
	if ( $force || ! $home || 'publish' !== get_post_status( $home ) ) {
		$home = aim_t_elementor_create( 'page', 'صفحهٔ اصلی', [
			[ 'aim-hero', [] ],
			[ 'aim-products', [] ],
			[ 'aim-offer', [] ],
			[ 'aim-finder', [] ],
			[ 'aim-products', [ 'anchor' => 'newest', 'title_before' => 'جدیدترین', 'title_highlight' => 'سرویس‌ها', 'text' => 'سرویس‌هایی که تازه به فروشگاه اضافه شده‌اند.', 'source' => 'newest', 'flag' => 'new' ] ],
			[ 'aim-steps', [] ],
			[ 'aim-faq', [] ],
			[ 'aim-posts', [] ],
		] );
		update_option( 'aim_el_home', $home );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $home );
	}
	$contact = (int) get_option( 'aim_el_contact' );
	if ( ! $contact || ! get_post_status( $contact ) ) {
		$contact = aim_t_elementor_create( 'page', 'تماس با ما', [ [ 'aim-contact', [] ], [ 'aim-faq', [ 'tint' => '' ] ] ] );
		wp_update_post( [ 'ID' => $contact, 'post_name' => 'contact' ] );
		update_option( 'aim_el_contact', $contact );
	}
	foreach ( [ 'header' => 'سربرگ هوش‌مارکت', 'footer' => 'پابرگ هوش‌مارکت' ] as $location => $title ) {
		$existing = get_posts( [ 'post_type' => 'elementor_library', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_aim_location', 'meta_value' => $location ] ); // phpcs:ignore WordPress.DB.SlowDBQuery
		if ( ! $existing ) {
			$id = aim_t_elementor_create( 'section', $title, [ [ 'aim-' . $location, [] ] ] );
			update_post_meta( $id, '_aim_location', $location );
		}
	}
	return $home;
}

/**
 * A page (type "page") or library template (type "section") made of full-width
 * blocks, one per widget: [ [ widgetType, settings ], … ].
 */
function aim_t_elementor_create( $type, $title, array $widgets ) {
	$id = wp_insert_post( [
		'post_type'   => 'page' === $type ? 'page' : 'elementor_library',
		'post_status' => 'publish',
		'post_title'  => $title,
	] );
	$data = array_map( fn( $w ) => aim_t_elementor_block( $w[0], $w[1] ), $widgets );
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $id, '_elementor_template_type', 'page' === $type ? 'wp-page' : 'section' );
	update_post_meta( $id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '' );
	update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
	if ( 'page' === $type ) {
		update_post_meta( $id, '_wp_page_template', 'elementor_header_footer' );
	} else {
		wp_set_object_terms( $id, 'section', 'elementor_library_type' );
	}
	return $id;
}

/** One full-width, gap-less container (or section/column on sites without containers) holding a widget. */
function aim_t_elementor_block( $widget, array $settings ) {
	$rand   = fn() => substr( md5( uniqid( '', true ) ), 0, 7 );
	$zero   = [ 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ];
	$el     = [ 'id' => $rand(), 'elType' => 'widget', 'widgetType' => $widget, 'settings' => (object) ( $settings + aim_t_elementor_defaults( $widget ) ), 'elements' => [] ];
	$flex   = \Elementor\Plugin::$instance->experiments->is_feature_active( 'container' );
	if ( $flex ) {
		return [ 'id' => $rand(), 'elType' => 'container', 'settings' => [ 'content_width' => 'full', 'padding' => $zero, 'flex_gap' => [ 'unit' => 'px', 'size' => 0, 'column' => '0', 'row' => '0' ] ], 'elements' => [ $el ], 'isInner' => false ];
	}
	return [
		'id'       => $rand(),
		'elType'   => 'section',
		'settings' => [ 'layout' => 'full_width', 'gap' => 'no', 'padding' => $zero ],
		'elements' => [ [ 'id' => $rand(), 'elType' => 'column', 'settings' => [ '_column_size' => 100, 'padding' => $zero ], 'elements' => [ $el ], 'isInner' => false ] ],
		'isInner'  => false,
	];
}

/**
 * A widget's own control defaults, ready to save. Elementor fills simple
 * controls from their defaults at render time but not repeaters (lists), so a
 * widget created in code must carry them explicitly.
 */
function aim_t_elementor_defaults( $widget ) {
	$type = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $widget );
	if ( ! $type ) {
		return [];
	}
	$out = [];
	foreach ( $type->get_controls() as $name => $control ) {
		if ( '_' === $name[0] || ! array_key_exists( 'default', $control ) || in_array( $control['type'], [ 'section', 'tab', 'tabs' ], true ) ) {
			continue;
		}
		$value = $control['default'];
		if ( 'repeater' === $control['type'] ) {
			$value = array_map( fn( $row ) => $row + [ '_id' => substr( md5( uniqid( '', true ) ), 0, 7 ) ], (array) $value );
		}
		$out[ $name ] = $value;
	}
	return $out;
}

// Build once Elementor is active (after the theme is switched on, or when Elementor is installed later).
add_action( 'elementor/init', function () {
	if ( ! get_option( 'aim_el_installed' ) && current_user_can( 'manage_options' ) ) {
		update_option( 'aim_el_installed', 1 );
		add_action( 'init', fn() => aim_t_elementor_install(), 20 );
	}
} );

/**
 * One-time fix for headers built before 1.8.0: the installer saved the site name (and the old
 * "AI ACCOUNTS" subtitle) into the header widget, so renaming the site never reached the header.
 * Clear those copies so the widget falls back to the live values (text typed in Elementor later still wins).
 */
add_action( 'admin_init', function () {
	if ( get_option( 'aim_t_header_brand_unpinned_2' ) || ! did_action( 'elementor/loaded' ) ) {
		return;
	}
	update_option( 'aim_t_header_brand_unpinned_2', 1 );
	global $wpdb;
	$ids   = $wpdb->get_col( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_data' AND meta_value LIKE '%aim-header%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$strip = function ( array $els ) use ( &$strip ) {
		foreach ( $els as &$el ) {
			if ( ( $el['widgetType'] ?? '' ) === 'aim-header' ) {
				unset( $el['settings']['brand'] );
				if ( 'AI ACCOUNTS' === ( $el['settings']['brand_sub'] ?? '' ) ) {
					unset( $el['settings']['brand_sub'] ); // the old default; the logo's "HOOSH MARKET" applies
				}
			}
			if ( ! empty( $el['elements'] ) ) {
				$el['elements'] = $strip( $el['elements'] );
			}
		}
		return $els;
	};
	foreach ( $ids as $id ) {
		$data = json_decode( (string) get_post_meta( $id, '_elementor_data', true ), true );
		if ( is_array( $data ) ) {
			update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $strip( $data ) ) ) );
		}
	}
	if ( $ids && isset( \Elementor\Plugin::$instance->files_manager ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}
} );
