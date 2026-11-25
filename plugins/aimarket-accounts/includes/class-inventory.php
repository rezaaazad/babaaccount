<?php
defined( 'ABSPATH' ) || exit;

/**
 * Account inventory. Each row is one sellable account (credentials stored
 * encrypted). For products with automatic delivery the WooCommerce stock
 * quantity is kept equal to the number of available rows, so "out of stock"
 * on the storefront always matches what can actually be delivered.
 */
class AIM_Inventory {

	const STATUSES = [
		'available' => [ 'آماده تحویل', 'success' ],
		'assigned'  => [ 'تحویل‌شده', 'info' ],
		'replaced'  => [ 'تعویض‌شده', 'warning' ],
		'disabled'  => [ 'غیرفعال', 'danger' ],
	];

	public static function init() {
		// Keep stock honest if WooCommerce changes it on its own (e.g. order cancel restock).
		add_action( 'woocommerce_product_set_stock', [ __CLASS__, 'resync_after_wc_change' ] );
		add_action( 'woocommerce_variation_set_stock', [ __CLASS__, 'resync_after_wc_change' ] );
	}

	public static function table() {
		return AIM_Install::table( 'accounts' );
	}

	/** Split a pasted block into one entry per account. Blank line or "---" separates multi-line entries. */
	public static function parse_bulk( $text, $mode = 'line' ) {
		$text = str_replace( [ "\r\n", "\r" ], "\n", trim( (string) $text ) );
		if ( '' === $text ) {
			return [];
		}
		if ( 'block' === $mode ) {
			// A "---" line (also at the very start/end or repeated) works like a blank line.
			$text  = preg_replace( '/^[ \t]*-{3,}[ \t]*$/m', '', $text );
			$parts = preg_split( '/\n\s*\n/', $text );
		} else {
			$parts = explode( "\n", $text );
		}
		return array_values( array_filter( array_map( 'trim', $parts ), 'strlen' ) );
	}

	/** Drop entries repeated in $secrets or already stored for this product/plan, so one account is never sold twice. */
	public static function without_duplicates( $product_id, $variation_id, array $secrets ) {
		global $wpdb;
		$seen   = [];
		$stored = $wpdb->get_col( $wpdb->prepare( 'SELECT secret FROM ' . self::table() . ' WHERE product_id = %d AND variation_id = %d', $product_id, $variation_id ) );
		foreach ( $stored as $s ) {
			$seen[ AIM_Crypto::decrypt( $s ) ] = true;
		}
		$out = [];
		foreach ( $secrets as $secret ) {
			if ( ! isset( $seen[ $secret ] ) ) {
				$seen[ $secret ] = true;
				$out[]           = $secret;
			}
		}
		return $out;
	}

	/** @return int Number of rows inserted. */
	public static function add( $product_id, $variation_id, array $secrets, $note = '' ) {
		global $wpdb;
		$product_id   = (int) $product_id;
		$variation_id = (int) $variation_id;
		$duration     = self::duration_for( $product_id, $variation_id );
		$now          = current_time( 'mysql', true );
		$added        = 0;

		foreach ( $secrets as $secret ) {
			$ok = $wpdb->insert( self::table(), [
				'product_id'    => $product_id,
				'variation_id'  => $variation_id,
				'secret'        => AIM_Crypto::encrypt( $secret ),
				'note'          => mb_substr( sanitize_text_field( $note ), 0, 250 ),
				'status'        => 'available',
				'duration_days' => $duration,
				'created_by'    => get_current_user_id(),
				'created_at'    => $now,
			] );
			$added += $ok ? 1 : 0;
		}
		if ( $added ) {
			self::sync_stock( $product_id, $variation_id );
			AIM_Activity::log( 'accounts_added', sprintf( 'افزودن %s اکانت به «%s»', aim_fa_digits( $added ), self::product_label( $product_id, $variation_id ) ), 'product', $variation_id ?: $product_id );
		}
		return $added;
	}

	public static function available_count( $product_id, $variation_id = 0 ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . self::table() . " WHERE product_id = %d AND variation_id = %d AND status = 'available'",
			$product_id,
			$variation_id
		) );
	}

	/** Set WooCommerce stock to the number of available accounts. */
	public static function sync_stock( $product_id, $variation_id = 0 ) {
		$target = $variation_id ? wc_get_product( $variation_id ) : wc_get_product( $product_id );
		// Variable products keep stock on their variations; never put a stock count on the parent.
		if ( ! $target || 'auto' !== self::delivery_mode( $product_id ) || $target->is_type( 'variable' ) ) {
			return;
		}
		$count = self::available_count( $product_id, $variation_id );
		self::$syncing = true;
		$target->set_manage_stock( true );
		$target->set_stock_quantity( $count );
		$target->set_backorders( 'no' );
		$target->save();
		self::$syncing = false;
	}

	private static $syncing = false;

	public static function resync_after_wc_change( $product ) {
		if ( self::$syncing || ! $product ) {
			return;
		}
		$parent = $product->get_parent_id();
		$pid    = $parent ?: $product->get_id();
		if ( 'auto' === self::delivery_mode( $pid ) ) {
			self::sync_stock( $pid, $parent ? $product->get_id() : 0 );
		}
	}

	public static function is_account_product( $product_id ) {
		return 'yes' === get_post_meta( $product_id, '_aim_account', true );
	}

	/** auto = deliver from inventory, manual = admin activates on the customer's own email. */
	public static function delivery_mode( $product_id ) {
		if ( ! self::is_account_product( $product_id ) ) {
			return '';
		}
		return get_post_meta( $product_id, '_aim_delivery', true ) === 'manual' ? 'manual' : 'auto';
	}

	public static function duration_for( $product_id, $variation_id = 0 ) {
		$days = $variation_id ? (int) get_post_meta( $variation_id, '_aim_duration_days', true ) : 0;
		return $days ?: (int) get_post_meta( $product_id, '_aim_duration_days', true );
	}

	/** All sellable targets: [ product_id, variation_id, label ] for account products. */
	public static function targets() {
		$ids = get_posts( [
			'post_type'      => 'product',
			'post_status'    => [ 'publish', 'draft', 'private' ],
			'meta_key'       => '_aim_account',
			'meta_value'     => 'yes',
			'fields'         => 'ids',
			'posts_per_page' => -1,
		] );
		$out = [];
		foreach ( $ids as $pid ) {
			$p = wc_get_product( $pid );
			if ( ! $p ) {
				continue;
			}
			if ( $p->is_type( 'variable' ) ) {
				foreach ( $p->get_children() as $vid ) {
					$out[] = [ 'product_id' => $pid, 'variation_id' => $vid, 'label' => self::product_label( $pid, $vid ), 'mode' => self::delivery_mode( $pid ) ];
				}
			} else {
				$out[] = [ 'product_id' => $pid, 'variation_id' => 0, 'label' => $p->get_name(), 'mode' => self::delivery_mode( $pid ) ];
			}
		}
		return $out;
	}

	public static function product_label( $product_id, $variation_id = 0 ) {
		$p = wc_get_product( $variation_id ?: $product_id );
		if ( ! $p ) {
			return '#' . ( $variation_id ?: $product_id );
		}
		if ( $variation_id ) {
			$parent = wc_get_product( $product_id );
			$attrs  = wc_get_formatted_variation( $p, true, false, false );
			return ( $parent ? $parent->get_name() : '' ) . ( $attrs ? ' — ' . $attrs : '' );
		}
		return $p->get_name();
	}

	/** Per-target stock summary for the inventory overview. */
	public static function summary() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT product_id, variation_id, status, COUNT(*) c FROM ' . self::table() . ' GROUP BY product_id, variation_id, status' );
		$map  = [];
		foreach ( $rows as $r ) {
			$map[ $r->product_id . ':' . $r->variation_id ][ $r->status ] = (int) $r->c;
		}
		$out = [];
		foreach ( self::targets() as $t ) {
			$k     = $t['product_id'] . ':' . $t['variation_id'];
			$out[] = $t + [
				'available' => $map[ $k ]['available'] ?? 0,
				'assigned'  => $map[ $k ]['assigned'] ?? 0,
				'total'     => array_sum( $map[ $k ] ?? [] ),
			];
		}
		return $out;
	}

	public static function low_stock_products() {
		$threshold = (int) AIM_Settings::get( 'low_stock_threshold' );
		return array_values( array_filter( self::summary(), fn( $s ) => 'auto' === $s['mode'] && $s['available'] <= $threshold ) );
	}

	public static function query( $args = [] ) {
		global $wpdb;
		$args   = wp_parse_args( $args, [ 'status' => '', 'target' => '', 'search' => '', 'user_id' => 0, 'per_page' => 20, 'page' => 1 ] );
		$where  = [ '1=1' ];
		$params = [];
		if ( $args['status'] && isset( self::STATUSES[ $args['status'] ] ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}
		if ( $args['target'] && preg_match( '/^(\d+):(\d+)$/', $args['target'], $m ) ) {
			$where[]  = 'product_id = %d AND variation_id = %d';
			$params[] = (int) $m[1];
			$params[] = (int) $m[2];
		}
		if ( $args['user_id'] ) {
			$where[]  = 'user_id = %d';
			$params[] = (int) $args['user_id'];
		}
		if ( '' !== $args['search'] ) {
			$s   = trim( $args['search'] );
			$num = ltrim( aim_en_digits( $s ), '#' ); // "#۹۲" as shown in the list.
			if ( '' !== $num && ctype_digit( $num ) ) {
				$where[]  = '(id = %d OR order_id = %d)';
				$params[] = (int) $num;
				$params[] = (int) $num;
			} else {
				$where[]  = 'note LIKE %s';
				$params[] = '%' . $wpdb->esc_like( $s ) . '%';
			}
		}
		$sql_where = implode( ' AND ', $where );
		$table     = self::table();
		$count_sql = "SELECT COUNT(*) FROM $table WHERE $sql_where";
		$total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );
		$offset    = ( max( 1, (int) $args['page'] ) - 1 ) * (int) $args['per_page'];
		$rows      = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table WHERE $sql_where ORDER BY id DESC LIMIT %d OFFSET %d",
			array_merge( $params, [ (int) $args['per_page'], $offset ] )
		) );
		return [ 'rows' => $rows, 'total' => $total ];
	}

	public static function status_counts( $target = '' ) {
		global $wpdb;
		$sql    = 'SELECT status, COUNT(*) c FROM ' . self::table();
		$params = [];
		if ( $target && preg_match( '/^(\d+):(\d+)$/', $target, $m ) ) {
			$sql   .= ' WHERE product_id = %d AND variation_id = %d';
			$params = [ (int) $m[1], (int) $m[2] ];
		}
		$sql  .= ' GROUP BY status';
		$rows  = $wpdb->get_results( $params ? $wpdb->prepare( $sql, $params ) : $sql );
		$out   = array_fill_keys( array_keys( self::STATUSES ), 0 );
		foreach ( $rows as $r ) {
			$out[ $r->status ] = (int) $r->c;
		}
		$out[''] = array_sum( $out );
		return $out;
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) );
	}

	public static function secret( $row ) {
		return AIM_Crypto::decrypt( $row->secret );
	}

	public static function set_status( $id, $status ) {
		global $wpdb;
		$row = self::get( $id );
		if ( ! $row || ! isset( self::STATUSES[ $status ] ) ) {
			return false;
		}
		$wpdb->update( self::table(), [ 'status' => $status ], [ 'id' => $id ] );
		self::sync_stock( (int) $row->product_id, (int) $row->variation_id );
		return true;
	}

	/** Only accounts that were never delivered can be deleted; delivered ones stay for the customer's record. */
	public static function delete( $id ) {
		global $wpdb;
		$row = self::get( $id );
		if ( ! $row || 'assigned' === $row->status ) {
			return false;
		}
		$wpdb->delete( self::table(), [ 'id' => $id ] );
		self::sync_stock( (int) $row->product_id, (int) $row->variation_id );
		return true;
	}

	public static function status_pill( $status ) {
		[ $label, $tone ] = self::STATUSES[ $status ] ?? [ $status, '' ];
		return '<span class="pill pill-' . esc_attr( $tone ) . '">' . esc_html( $label ) . '</span>';
	}
}
