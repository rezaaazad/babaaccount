<?php
defined( 'ABSPATH' ) || exit;

/**
 * Bulk price management (panel → «مدیریت گروهی قیمت»): edit regular/sale
 * prices of simple products and plans (variations) in one table, or raise /
 * lower the selected rows by a percentage or a fixed amount.
 *
 *   POST /prices/save  { rows: [ { id, regular, sale } ] }
 *   POST /prices/bulk  { ids: [], field: regular|sale, op: increase|decrease|set|clear, mode: percent|fixed, value, round }
 *
 * increase/decrease change the field's current value (an empty sale price
 * starts from the regular price); set+fixed writes the amount; set+percent on
 * the sale price means "X% off the regular price"; clear removes the sale price.
 */
class AIM_Bulk_Prices {

	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'routes' ] );
	}

	public static function routes() {
		$perm = fn() => current_user_can( 'edit_products' );
		register_rest_route( AIM_Admin_API::NS, '/prices/save', [ 'methods' => 'POST', 'callback' => [ __CLASS__, 'save' ], 'permission_callback' => $perm ] );
		register_rest_route( AIM_Admin_API::NS, '/prices/bulk', [ 'methods' => 'POST', 'callback' => [ __CLASS__, 'bulk' ], 'permission_callback' => $perm ] );
	}

	/**
	 * Table rows: one per simple product, and one per plan of a variable product.
	 * Each: [ id, product, plan, regular, sale, stock, in_stock, edit_id ].
	 */
	public static function rows( $args ) {
		$rows = [];
		foreach ( wc_get_products( $args + [ 'type' => [ 'simple', 'variable' ] ] ) as $p ) {
			$items = $p->is_type( 'variable' ) ? array_filter( array_map( 'wc_get_product', $p->get_children() ) ) : [ $p ];
			foreach ( $items as $item ) {
				$rows[] = [
					'id'       => $item->get_id(),
					'product'  => $p->get_name(),
					'plan'     => $item->is_type( 'variation' ) ? wc_get_formatted_variation( $item, true, false, false ) : '',
					'regular'  => $item->get_regular_price( 'edit' ),
					'sale'     => $item->get_sale_price( 'edit' ),
					'stock'    => $item->managing_stock() ? (int) $item->get_stock_quantity() : null,
					'in_stock' => $item->is_in_stock(),
					'edit_id'  => $p->get_id(),
				];
			}
		}
		return $rows;
	}

	public static function save( WP_REST_Request $req ) {
		$rows = array_filter( (array) $req->get_param( 'rows' ), 'is_array' );
		// Validate every row first, so one bad row doesn't leave the table half saved.
		$todo = [];
		foreach ( $rows as $row ) {
			$item = self::editable( $row['id'] ?? 0 );
			if ( ! $item ) {
				continue;
			}
			$regular = self::amount( $row['regular'] ?? '' );
			$sale    = self::amount( $row['sale'] ?? '' );
			if ( null === $regular || null === $sale ) {
				return self::fail( sprintf( 'قیمت «%s» نامعتبر است؛ فقط عدد مثبت وارد کنید.', $item->get_name() ) );
			}
			if ( '' !== $sale && '' === $regular ) {
				return self::fail( sprintf( 'برای «%s» ابتدا قیمت عادی را وارد کنید.', $item->get_name() ) );
			}
			if ( '' !== $sale && (float) $sale >= (float) $regular ) {
				return self::fail( sprintf( 'قیمت ویژهٔ «%s» باید کمتر از قیمت عادی باشد.', $item->get_name() ) );
			}
			$todo[] = [ $item, $regular, $sale ];
		}
		foreach ( $todo as [ $item, $regular, $sale ] ) {
			$item->set_regular_price( $regular );
			$item->set_sale_price( $sale );
			$item->save();
		}
		$done = count( $todo );
		self::sync_parents( array_column( $rows, 'id' ) );
		if ( $done ) {
			AIM_Activity::log( 'prices', sprintf( 'ویرایش قیمت %s ردیف', aim_fa_digits( $done ) ) );
		}
		return rest_ensure_response( [ 'ok' => true, 'message' => $done ? sprintf( 'قیمت %s ردیف ذخیره شد.', aim_fa_digits( $done ) ) : 'ردیفی ذخیره نشد.' ] );
	}

	public static function bulk( WP_REST_Request $req ) {
		$ids   = array_map( 'absint', (array) $req->get_param( 'ids' ) );
		$field = 'sale' === $req->get_param( 'field' ) ? 'sale' : 'regular';
		$op    = (string) $req->get_param( 'op' );
		$mode  = 'fixed' === $req->get_param( 'mode' ) ? 'fixed' : 'percent';
		$value = self::amount( $req->get_param( 'value' ) ?? '' );
		$round = max( 0, (int) $req->get_param( 'round' ) );
		if ( ! $ids ) {
			return self::fail( 'ابتدا ردیف‌های موردنظر را انتخاب کنید.' );
		}
		if ( ! in_array( $op, [ 'increase', 'decrease', 'set', 'clear' ], true ) ) {
			return self::fail( 'عملیات نامعتبر است.' );
		}
		if ( 'clear' !== $op && '' === $value ) {
			return self::fail( 'مقدار را وارد کنید.' );
		}
		$value = (float) $value;
		if ( 'clear' !== $op && $value <= 0 ) {
			return self::fail( 'مقدار باید عددی بزرگ‌تر از صفر باشد.' );
		}
		if ( 'set' === $op && 'percent' === $mode && 'regular' === $field ) {
			return self::fail( 'برای قیمت عادی، «تنظیم روی» فقط با مبلغ ثابت ممکن است.' );
		}
		if ( 'percent' === $mode && in_array( $op, [ 'decrease', 'set' ], true ) && $value >= 100 ) {
			return self::fail( 'کاهش درصدی باید کمتر از ۱۰۰٪ باشد.' );
		}

		$done = 0;
		$skip = 0;
		foreach ( $ids as $id ) {
			$item = self::editable( $id );
			if ( ! $item ) {
				continue;
			}
			if ( 'clear' === $op ) {
				if ( '' === $item->get_sale_price( 'edit' ) ) {
					continue;
				}
				$item->set_sale_price( '' );
				$item->save();
				$done++;
				continue;
			}
			$regular = (float) $item->get_regular_price( 'edit' );
			$current = 'sale' === $field ? (float) ( $item->get_sale_price( 'edit' ) ?: $regular ) : $regular;
			$delta   = 'percent' === $mode ? $current * $value / 100 : $value;
			$new     = match ( $op ) {
				'increase' => $current + $delta,
				'decrease' => $current - $delta,
				// "Set" with a percentage on the sale price means "X% off the regular price".
				'set'      => 'percent' === $mode ? $regular * ( 100 - min( $value, 100 ) ) / 100 : $value,
			};
			if ( $round > 1 ) {
				$new = round( $new / $round ) * $round;
			}
			// Never make an item free by accident (a decrease larger than the price, or rounding down to zero).
			// A sale price must also stay below the regular price.
			if ( $new <= 0 || ( 'sale' === $field && $new >= $regular ) ) {
				$skip++;
				continue;
			}
			if ( 'sale' === $field ) {
				$item->set_sale_price( wc_format_decimal( $new ) );
			} else {
				$item->set_regular_price( wc_format_decimal( $new ) );
				if ( '' !== $item->get_sale_price( 'edit' ) && (float) $item->get_sale_price( 'edit' ) >= $new ) {
					$item->set_sale_price( '' );
				}
			}
			$item->save();
			$done++;
		}
		self::sync_parents( $ids );
		if ( $done ) {
			AIM_Activity::log( 'prices', sprintf( 'تغییر گروهی قیمت %s ردیف', aim_fa_digits( $done ) ) );
		}
		$message = $done ? sprintf( 'قیمت %s ردیف به‌روز شد.', aim_fa_digits( $done ) ) : 'ردیفی تغییر نکرد.';
		if ( $skip ) {
			$message .= sprintf( ' %s ردیف تغییر نکرد، چون قیمت جدید صفر می‌شد یا قیمت ویژه به قیمت عادی می‌رسید.', aim_fa_digits( $skip ) );
		}
		return rest_ensure_response( [ 'ok' => true, 'message' => $message ] );
	}

	/** A simple product or a variation the current user may edit. */
	private static function editable( $id ) {
		$item = wc_get_product( absint( $id ) );
		if ( ! $item || ! ( $item->is_type( 'simple' ) || $item->is_type( 'variation' ) ) ) {
			return null;
		}
		return current_user_can( 'edit_post', $item->get_parent_id() ?: $item->get_id() ) ? $item : null;
	}

	/** '' stays empty (no price); a non-negative number (Persian digits and separators allowed) becomes a decimal string; anything else is null. */
	private static function amount( $v ) {
		$v = trim( aim_en_digits( is_scalar( $v ) ? (string) $v : 'x' ) );
		$v = str_replace( [ ',', '٬', '،', ' ', "\u{00A0}", '٫' ], [ '', '', '', '', '', '.' ], $v );
		if ( '' === $v ) {
			return '';
		}
		return is_numeric( $v ) && (float) $v >= 0 ? wc_format_decimal( $v ) : null;
	}

	/** Variable parents cache their price range; refresh it after their plans change. */
	private static function sync_parents( array $ids ) {
		$parents = [];
		foreach ( $ids as $id ) {
			$parent = wp_get_post_parent_id( absint( $id ) );
			if ( $parent ) {
				$parents[ $parent ] = true;
			}
		}
		foreach ( array_keys( $parents ) as $parent ) {
			WC_Product_Variable::sync( $parent );
			wc_delete_product_transients( $parent );
		}
	}

	private static function fail( $message ) {
		return new WP_REST_Response( [ 'ok' => false, 'message' => $message ], 400 );
	}
}
