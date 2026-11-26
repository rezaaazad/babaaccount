<?php
/**
 * Theme setup: everything HooshMarket needs, installed and switched on for
 * the site owner.
 *
 *  - On activation, the bundled plugins (AIM Panel and its accounts module,
 *    shipped in inc/bundled/ by bin/build-bundled.sh) are installed if missing
 *    and activated right away.
 *  - The bundled plugins always follow the theme: installing or updating the
 *    theme brings its plugins to the versions in inc/bundled/versions.json
 *    with no separate plugin update. Triggered three ways: right after a
 *    theme package finishes installing/updating, on the next admin load,
 *    and by the «همگام‌سازی افزونه‌های همراه» button on the setup page.
 *  - Appearance → «راه‌اندازی هوش‌مارکت» (opened once after activation)
 *    shows each requirement and installs the rest with one button:
 *    WooCommerce and Elementor from wordpress.org, then the store settings
 *    for digital accounts (AIM_Setup::apply_preset) and the Elementor pages.
 *  - Until WooCommerce is active the storefront shows a «در حال راه‌اندازی»
 *    page instead of breaking (template-parts/setup-required.php).
 */
defined( 'ABSPATH' ) || exit;

const AIM_T_SETUP_PAGE     = 'aim-theme-setup';
const AIM_T_SETUP_REDIRECT = 'aim_theme_setup_redirect';

/** Requirements in install order (the accounts module needs WooCommerce first): [ file, label, text, zip | slug ]. */
function aim_t_requirements() {
        return [
                'core'        => [ 'file' => 'aimarket-core/aimarket-core.php', 'label' => 'AIM Panel — پنل مدیریت', 'text' => 'پنل مدیریت سایت، ورود پیامکی و تنظیمات.', 'zip' => 'aimarket-core.zip' ],
                'woocommerce' => [ 'file' => 'woocommerce/woocommerce.php', 'label' => 'ووکامرس', 'text' => 'فروشگاه، سبد خرید، پرداخت و سفارش‌ها.', 'slug' => 'woocommerce' ],
                'accounts'    => [ 'file' => 'aimarket-accounts/aimarket-accounts.php', 'label' => 'ماژول فروش اکانت', 'text' => 'انبار اکانت و تحویل خودکار بعد از پرداخت.', 'zip' => 'aimarket-accounts.zip' ],
                'reseller'    => [ 'file' => 'aimarket-reseller/aimarket-reseller.php', 'label' => 'همکاری در فروش', 'text' => 'کیف پول همکار، قیمت عمده و API خرید اکانت.', 'zip' => 'aimarket-reseller.zip' ],
                'elementor'   => [ 'file' => 'elementor/elementor.php', 'label' => 'المنتور', 'text' => 'ویرایش صفحهٔ اصلی، سربرگ و پابرگ با کشیدن و رها کردن.', 'slug' => 'elementor' ],
        ];
}

/**
 * Rocket mark of the setup screens (line drawing: outline follows the text
 * colour so it works on light and dark, flames in the accent orange).
 */
function aim_t_setup_rocket( $class = 'aimt-rocket' ) {
        return '<svg class="' . esc_attr( $class ) . '" viewBox="-200 -200 400 400" role="img" aria-label="راه‌اندازی">'
                . '<g transform="rotate(45)" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="15">'
                . '<path d="M0-150C52-98 62-12 42 68H-42C-62-12-52-98 0-150Z" stroke="currentColor"/>'
                . '<circle cy="-34" r="25" stroke="currentColor"/>'
                . '<path d="M-49 8C-84 22-104 50-106 84L-46 74" stroke="currentColor"/>'
                . '<path d="M49 8C84 22 104 50 106 84L46 74" stroke="currentColor"/>'
                . '<path d="M-22 96V146M0 96V178M22 96V146" stroke="#e8410f"/>'
                . '</g></svg>';
}

/** 'active' | 'installed' | 'missing' for each requirement. */
function aim_t_requirement_status() {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $out = [];
        foreach ( aim_t_requirements() as $key => $r ) {
                $out[ $key ] = is_plugin_active( $r['file'] ) ? 'active' : ( file_exists( WP_PLUGIN_DIR . '/' . $r['file'] ) ? 'installed' : 'missing' );
        }
        return $out;
}

/** Install one requirement (bundled zip or wordpress.org) and activate it. */
function aim_t_install_requirement( $key ) {
        $r = aim_t_requirements()[ $key ] ?? null;
        if ( ! $r ) {
                return new WP_Error( 'unknown', 'مورد نامعتبر.' );
        }
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        if ( ! file_exists( WP_PLUGIN_DIR . '/' . $r['file'] ) ) {
                $installed = aim_t_download_requirement( $r );
                if ( is_wp_error( $installed ) ) {
                        return $installed;
                }
        }
        if ( ! is_plugin_active( $r['file'] ) ) {
                $result = activate_plugin( $r['file'] );
                // A half-copied plugin folder cannot be activated: install it again over the old files, then retry.
                if ( is_wp_error( $result ) && 'no_plugin_header' === $result->get_error_code() ) {
                        $installed = aim_t_download_requirement( $r, true );
                        $result    = is_wp_error( $installed ) ? $installed : activate_plugin( $r['file'] );
                }
                if ( is_wp_error( $result ) ) {
                        return new WP_Error( 'activate', 'فعال‌سازی «' . $r['label'] . '» انجام نشد: ' . wp_strip_all_tags( $result->get_error_message() ) );
                }
        }
        // Our own setup screens replace the plugins' onboarding.
        delete_transient( '_wc_activation_redirect' );
        delete_transient( 'elementor_activation_redirect' );
        delete_transient( 'aim_setup_redirect' );
        return '«' . $r['label'] . '» نصب و فعال است.';
}

/** Copy a requirement into wp-content/plugins from its bundled zip or wordpress.org ($overwrite replaces broken files). */
function aim_t_download_requirement( array $r, $overwrite = false ) {
        if ( ! current_user_can( 'install_plugins' ) ) {
                return new WP_Error( 'cap', 'حساب شما اجازهٔ نصب افزونه ندارد.' );
        }
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        if ( ! empty( $r['zip'] ) ) {
                $package = get_template_directory() . '/inc/bundled/' . $r['zip'];
                if ( ! file_exists( $package ) ) {
                        return new WP_Error( 'missing', 'فایل «' . $r['zip'] . '» همراه قالب نیست؛ آن را از بستهٔ هوش‌مارکت دستی نصب کنید.' );
                }
        } else {
                $api = plugins_api( 'plugin_information', [ 'slug' => $r['slug'], 'fields' => [ 'sections' => false ] ] );
                if ( is_wp_error( $api ) ) {
                        return new WP_Error( 'api', 'اتصال به مخزن وردپرس برقرار نشد؛ «' . $r['label'] . '» را از افزونه‌ها ← افزودن دستی نصب کنید.' );
                }
                $package = $api->download_link;
        }
        $skin   = new WP_Ajax_Upgrader_Skin();
        $result = ( new Plugin_Upgrader( $skin ) )->install( $package, [ 'overwrite_package' => $overwrite ] );
        if ( true !== $result ) {
                $why = is_wp_error( $result ) ? $result->get_error_message() : (string) $skin->get_error_messages();
                return new WP_Error( 'install', 'نصب «' . $r['label'] . '» انجام نشد' . ( $why ? ': ' . wp_strip_all_tags( $why ) : '؛ احتمالاً هاست اجازهٔ نوشتن در پوشهٔ افزونه‌ها را نمی‌دهد.' ) );
        }
        return true;
}

/** Installed vs bundled version of every plugin shipped in inc/bundled/versions.json. */
function aim_t_bundled_status() {
        $file    = get_template_directory() . '/inc/bundled/versions.json';
        $shipped = file_exists( $file ) ? (array) json_decode( (string) file_get_contents( $file ), true ) : []; // phpcs:ignore WordPress.WP.AlternativeFunctions
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $out = [];
        foreach ( $shipped as $key => $version ) {
                $r = aim_t_requirements()[ $key ] ?? null;
                if ( ! $r ) {
                        continue;
                }
                $path      = WP_PLUGIN_DIR . '/' . $r['file'];
                $installed = file_exists( $path ) ? ( get_plugin_data( $path, false, false )['Version'] ?? '0' ) : null;
                $out[ $key ] = [
                        'label'     => $r['label'],
                        'shipped'   => (string) $version,
                        'installed' => $installed, // null = نصب نشده؛ صفحهٔ راه‌اندازی نصبش می‌کند.
                        'state'     => null === $installed ? 'missing' : ( version_compare( (string) $version, (string) $installed, '>' ) ? 'stale' : 'ok' ),
                ];
        }
        return $out;
}

/**
 * Bring the installed plugins up to the versions shipped with this theme
 * (inc/bundled/versions.json, written by bin/build-bundled.sh). A plugin that is
 * already newer (uploaded by hand) is left alone; one that is not installed
 * yet is skipped — the setup page installs it. Used by the admin_init check,
 * the theme-update hook and the setup-page button; never downgrades.
 */
function aim_t_bundled_sync() {
	global $wpdb;
	if ( ! current_user_can( 'update_plugins' ) ) {
		return [ 'done' => [], 'failed' => true ];
	}
	$lock = 'aim_bundle_' . md5( $wpdb->prefix );
	if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) ) {
		return [ 'done' => [], 'failed' => true ];
	}
	try {
		return aim_t_bundled_sync_locked();
	} finally {
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
	}
}

function aim_t_bundled_sync_locked() {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $done   = [];
        $failed = false;
        foreach ( aim_t_bundled_status() as $key => $s ) {
                if ( 'stale' !== $s['state'] ) {
                        continue;
                }
                $r      = aim_t_requirements()[ $key ];
                $result = aim_t_download_requirement( $r, true );
                $failed = $failed || is_wp_error( $result );
                $done[] = is_wp_error( $result ) ? '«' . $r['label'] . '»: ' . $result->get_error_message() : '«' . $r['label'] . '» از ' . $s['installed'] . ' به ' . $s['shipped'] . ' به‌روز شد.';
        }
        return [ 'done' => $done, 'failed' => $failed ];
}

/**
 * The theme is newer than the last sync (or its bundled versions.json
 * changed): bring the bundled plugins along. The marker option stores a
 * fingerprint of the theme version AND the versions.json contents, so a new
 * theme package — or just a new plugin zip inside the same theme version —
 * triggers exactly one sync. A failed sync is retried at most once an hour
 * until it succeeds.
 */
add_action( 'admin_init', function () {
	global $pagenow;
	if ( ! in_array( $pagenow, [ 'update-core.php', 'update.php', 'themes.php' ], true ) ) {
		return;
	}
        $file  = get_template_directory() . '/inc/bundled/versions.json';
        $raw   = file_exists( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
        $mark  = wp_get_theme( get_template() )->get( 'Version' ) . '|' . md5( $raw );
        if ( get_option( 'aim_theme_bundled_checked' ) === $mark || get_transient( 'aim_theme_bundled_retry' ) || ! current_user_can( 'update_plugins' ) || wp_doing_ajax() ) {
                return;
        }
        set_transient( 'aim_theme_bundled_retry', 1, HOUR_IN_SECONDS ); // also stops two admin tabs updating at once
        $report = aim_t_bundled_sync();
        if ( $report['done'] ) {
                set_transient( 'aim_theme_bundled_updated', [ 'messages' => $report['done'], 'failed' => $report['failed'] ], HOUR_IN_SECONDS );
        }
        if ( ! $report['failed'] ) {
                update_option( 'aim_theme_bundled_checked', $mark, false );
                delete_transient( 'aim_theme_bundled_retry' );
        }
}, 1 );

/** A theme package just finished installing or updating: sync the bundled plugins on the very next admin load, even if the version stayed the same. */
add_action( 'upgrader_process_complete', function ( $upgrader, $hook_extra ) {
        if ( ! empty( $hook_extra['type'] ) && 'theme' === $hook_extra['type'] ) {
                delete_option( 'aim_theme_bundled_checked' );
                delete_transient( 'aim_theme_bundled_retry' );
        }
}, 10, 2 );

add_action( 'admin_notices', function () {
        $done = get_transient( 'aim_theme_bundled_updated' );
        if ( $done && current_user_can( 'update_plugins' ) ) {
                delete_transient( 'aim_theme_bundled_updated' );
                $failed = ! empty( $done['failed'] );
                printf(
                        '<div class="notice notice-%s is-dismissible"><p><b>%s</b> %s</p></div>',
                        $failed ? 'warning' : 'success',
                        $failed ? 'به‌روزرسانی هوش‌مارکت کامل نشد؛ افزونه‌های همراه هم‌نسخهٔ قالب نشدند.' : 'به‌روزرسانی هوش‌مارکت انجام شد؛ افزونه‌های همراه هم‌نسخهٔ قالب شدند.',
                        esc_html( implode( ' ', (array) ( $done['messages'] ?? $done ) ) )
                );
        }
} );

// On activation: bring the bundled plugins in at once, then open the setup page.
add_action( 'after_switch_theme', function () {
        if ( ! current_user_can( 'activate_plugins' ) ) {
                return;
        }
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $log = [];
        // The accounts module needs WooCommerce; without it the setup page installs both.
        foreach ( is_plugin_active( 'woocommerce/woocommerce.php' ) ? [ 'core', 'accounts', 'reseller' ] : [ 'core' ] as $key ) {
                $r = aim_t_install_requirement( $key );
                if ( is_wp_error( $r ) ) {
                        $log[ $key ] = $r->get_error_message();
                }
        }
        update_option( 'aim_theme_setup_log', $log, false ); // Errors, shown on the setup page next to the requirement.
        if ( ! get_option( 'aim_theme_setup_done' ) ) {
                set_transient( AIM_T_SETUP_REDIRECT, 1, 120 );
        }
}, 5 );

add_action( 'admin_init', function () {
        if ( get_transient( AIM_T_SETUP_REDIRECT ) && ! wp_doing_ajax() && current_user_can( 'activate_plugins' ) ) {
                delete_transient( AIM_T_SETUP_REDIRECT );
                wp_safe_redirect( admin_url( 'themes.php?page=' . AIM_T_SETUP_PAGE ) );
                exit;
        }
} );

add_action( 'admin_menu', function () {
        add_theme_page( 'راه‌اندازی هوش‌مارکت', 'راه‌اندازی هوش‌مارکت', 'activate_plugins', AIM_T_SETUP_PAGE, function () {
                get_template_part( 'template-parts/setup-page' );
        } );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
        if ( 'appearance_page_' . AIM_T_SETUP_PAGE !== $hook ) {
                return;
        }
        $uri = get_template_directory_uri();
        $dir = get_template_directory();
	wp_enqueue_style( 'aim-font', $uri . '/assets/css/fonts.css', [], filemtime( $dir . '/assets/css/fonts.css' ) );
        wp_enqueue_style( 'aim-icons', $uri . '/assets/css/icons.css', [], filemtime( $dir . '/assets/css/icons.css' ) );
        wp_enqueue_style( 'aim-theme-setup', $uri . '/assets/css/setup.css', [], filemtime( $dir . '/assets/css/setup.css' ) );
        wp_enqueue_script( 'aim-theme-setup', $uri . '/assets/js/setup.js', [], filemtime( $dir . '/assets/js/setup.js' ), true );
        wp_localize_script( 'aim-theme-setup', 'AIMSETUP', [ 'ajax' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'aim_theme_setup' ) ] );
} );

// Remind on other admin screens until the requirements are in place.
add_action( 'admin_notices', function () {
        $screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->id, [ 'dashboard', 'appearance_page_' . AIM_T_SETUP_PAGE ], true ) ) {
		return;
	}
        if ( ! current_user_can( 'activate_plugins' ) || ( $screen && 'appearance_page_' . AIM_T_SETUP_PAGE === $screen->id ) || ( isset( $_GET['page'] ) && 'aimarket' === $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
                return;
        }
	$status = aim_t_requirement_status();
	if ( in_array( 'missing', $status, true ) || in_array( 'installed', $status, true ) ) {
                printf( '<div class="notice notice-warning"><p>قالب هوش‌مارکت هنوز کامل راه‌اندازی نشده است. <a class="button button-primary" href="%s">راه‌اندازی خودکار</a></p></div>', esc_url( admin_url( 'themes.php?page=' . AIM_T_SETUP_PAGE ) ) );
        }
} );

/**
 * admin-ajax «aim_theme_setup»: one step per request, so downloads cannot time out.
 * Steps: core, woocommerce, accounts, elementor (install + activate), configure, content.
 */
add_action( 'wp_ajax_aim_theme_setup', function () {
        check_ajax_referer( 'aim_theme_setup' );
        if ( ! current_user_can( 'activate_plugins' ) ) {
                wp_send_json_error( [ 'message' => 'دسترسی ندارید.' ], 403 );
        }
        $step = sanitize_key( wp_unslash( $_POST['step'] ?? '' ) );
        if ( isset( aim_t_requirements()[ $step ] ) ) {
                $result = aim_t_install_requirement( $step );
        } elseif ( 'bundled-sync' === $step ) {
                // One-click «همگام‌سازی افزونه‌های همراه» from the setup page: same sync as the automatic one, run on demand.
                if ( ! current_user_can( 'update_plugins' ) ) {
                        $result = new WP_Error( 'cap', 'حساب شما اجازهٔ به‌روزرسانی افزونه ندارد.' );
                } else {
                        $report  = aim_t_bundled_sync();
                        $missing = [];
                        foreach ( aim_t_bundled_status() as $s ) {
                                if ( 'missing' === $s['state'] ) {
                                        $missing[] = '«' . $s['label'] . '»';
                                }
                        }
                        $result = $report['done'] ? implode( ' ', $report['done'] ) : ( $missing
                                ? implode( '، ', $missing ) . ' هنوز نصب نشده است؛ «نصب و راه‌اندازی خودکار» آن را نصب می‌کند.'
                                : 'افزونه‌های همراه از قبل هم‌نسخهٔ قالب بودند.' );
                }
        } elseif ( 'configure' === $step ) {
                if ( ! class_exists( 'AIM_Setup' ) ) {
                        $result = new WP_Error( 'core', 'پنل مدیریت فعال نیست.' );
                } elseif ( ! class_exists( 'WooCommerce' ) ) {
                        $result = new WP_Error( 'woo', 'اول ووکامرس باید نصب و فعال شود؛ بعد «تلاش دوباره» را بزنید.' );
                } else {
                        $result = implode( ' ', AIM_Setup::apply_preset( 'digital' ) );
                }
        } elseif ( 'content' === $step ) {
                $result = function_exists( 'aim_t_elementor_install' ) && did_action( 'elementor/loaded' ) ? ( aim_t_elementor_install() ? 'صفحهٔ اصلی، تماس با ما، سربرگ و پابرگ با المنتور ساخته شد.' : 'صفحه‌ها آماده است.' ) : 'المنتور فعال نیست؛ صفحهٔ اصلی با طرح پیش‌فرض قالب نمایش داده می‌شود.';
                // Only a configured store counts as set up; otherwise the page keeps offering the setup.
                if ( class_exists( 'WooCommerce' ) && class_exists( 'AIM_Setup' ) && AIM_Setup::done() ) {
                        update_option( 'aim_theme_setup_done', time() );
                }
        } else {
                $result = new WP_Error( 'step', 'مرحلهٔ نامعتبر.' );
        }
        is_wp_error( $result ) ? wp_send_json_error( [ 'message' => $result->get_error_message() ] ) : wp_send_json_success( [ 'message' => $result ] );
} );

// The storefront needs WooCommerce: show a friendly «در حال راه‌اندازی» page meanwhile.
add_action( 'template_redirect', function () {
        if ( class_exists( 'WooCommerce' ) || is_admin() || get_query_var( 'aim_page' ) ) {
                return; // The panel's own pages (/login/, /panel/user/) work without the store.
        }
        status_header( 503 );
        header( 'Retry-After: 3600' );
        get_template_part( 'template-parts/setup-required' );
        exit;
}, 0 );
