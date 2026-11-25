<?php
defined( 'ABSPATH' ) || exit;

/**
 * Panel → «تنظیمات قالب»: edits the texts of pages Elementor (free) cannot
 * build. The active theme describes the fields with the filter
 * 'aim_theme_options_schema' → [ 'option' => name, 'groups' => [ key => [ title, icon, fields ] ] ];
 * this class renders the form (templates/admin/theme-options.php) and saves
 * the values into that option.
 *
 *   POST /theme-options { values: { key: string | [ { sub: string } ] } }
 */
class AIM_Theme_Options {

	public static function init() {
		add_action( 'rest_api_init', function () {
			register_rest_route( AIM_Admin_API::NS, '/theme-options', [
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'rest_save' ],
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
			] );
		} );
	}

	/** The theme's schema, or [] when the theme does not offer options. */
	public static function schema() {
		$schema = apply_filters( 'aim_theme_options_schema', [] );
		return ! empty( $schema['option'] ) && ! empty( $schema['groups'] ) ? $schema : [];
	}

	public static function values() {
		$schema = self::schema();
		return $schema ? (array) get_option( $schema['option'], [] ) : [];
	}

	public static function rest_save( WP_REST_Request $req ) {
		$schema = self::schema();
		if ( ! $schema ) {
			return new WP_REST_Response( [ 'ok' => false, 'message' => 'قالب فعال تنظیمات قابل ویرایش ندارد.' ], 400 );
		}
		$input  = (array) $req->get_param( 'values' );
		$values = self::values();
		foreach ( $schema['groups'] as $group ) {
			foreach ( $group['fields'] as $key => $field ) {
				if ( 'heading' === $field['type'] || ! array_key_exists( $key, $input ) ) {
					continue;
				}
				$values[ $key ] = self::sanitize( $field, $input[ $key ] );
			}
		}
		update_option( $schema['option'], $values, true );
		AIM_Activity::log( 'theme_options_saved', 'ذخیرهٔ تنظیمات قالب', 'settings' );
		return rest_ensure_response( [ 'ok' => true, 'message' => 'تنظیمات قالب ذخیره شد.' ] );
	}

	/**
	 * Field / sub-field type. Sub-fields are "label" (a text box) or [ 'label' => …, 'type' => … ].
	 * Types: text, textarea, url, image (URL, picked from the media library), code (HTML such as
	 * trust-seal snippets; kept as typed for users who may post unfiltered HTML), select (options).
	 */
	public static function sub( $sub ) {
		return is_array( $sub ) ? $sub + [ 'type' => 'text' ] : [ 'label' => (string) $sub, 'type' => 'text' ];
	}

	/** List → rows of known sub-fields, empty rows dropped; other types by self::clean(). */
	private static function sanitize( array $field, $value ) {
		if ( 'list' !== $field['type'] ) {
			return self::clean( $field, $value );
		}
		$rows = [];
		foreach ( (array) $value as $row ) {
			$clean = [];
			foreach ( $field['fields'] as $sub => $def ) {
				$clean[ $sub ] = self::clean( self::sub( $def ), $row[ $sub ] ?? '' );
			}
			if ( implode( '', $clean ) !== '' ) {
				$rows[] = $clean;
			}
		}
		return $rows;
	}

	private static function clean( array $field, $value ) {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		switch ( $field['type'] ) {
			case 'textarea':
				return sanitize_textarea_field( $value );
			case 'url':
			case 'image':
				// Relative links (/contact/, #faq) and footer tokens ({tickets}) are allowed in menus and buttons.
				return preg_match( '/^[\/#{]/', $value ) ? sanitize_text_field( $value ) : esc_url_raw( $value );
			case 'code':
				if ( current_user_can( 'unfiltered_html' ) ) {
					return $value;
				}
				// wp_kses_post() keeps the text inside <script>/<style>; drop those blocks whole.
				return wp_kses_post( preg_replace( '#<(script|style)\b[^>]*>.*?</\1\s*>#is', '', $value ) );
			case 'select':
				return array_key_exists( $value, (array) ( $field['options'] ?? [] ) ) ? $value : '';
			default:
				return sanitize_text_field( $value );
		}
	}
}
