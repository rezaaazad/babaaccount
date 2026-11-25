<?php
defined( 'ABSPATH' ) || exit;

/** Activity log shown on the admin dashboard and the "recent activity" page. */
class AIM_Activity {

	public static function log( $action, $message, $object_type = '', $object_id = 0, $user_id = null ) {
		global $wpdb;
		$user_id = null === $user_id ? get_current_user_id() : (int) $user_id;
		$wpdb->insert( AIM_Install::table( 'activity' ), [
			'user_id'     => $user_id,
			'role'        => self::role_of( $user_id ),
			'action'      => $action,
			'object_type' => $object_type,
			'object_id'   => (int) $object_id,
			'message'     => $message,
			'ip'          => aim_client_ip(),
			'created_at'  => current_time( 'mysql', true ),
		] );
	}

	public static function role_of( $user_id ) {
		if ( ! $user_id ) {
			return 'system';
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return 'system';
		}
		if ( user_can( $user, 'manage_options' ) ) {
			return 'owner';
		}
		if ( user_can( $user, 'manage_woocommerce' ) ) {
			return 'manager';
		}
		return 'customer';
	}

	public static function role_label( $role ) {
		return [
			'owner'    => 'مدیر کل',
			'manager'  => 'مدیر فروشگاه',
			'customer' => 'کاربر',
			'system'   => 'سیستم',
		][ $role ] ?? $role;
	}

	public static function query( $args = [] ) {
		global $wpdb;
		$args   = wp_parse_args( $args, [ 'role' => '', 'per_page' => 20, 'page' => 1 ] );
		$table  = AIM_Install::table( 'activity' );
		$where  = '1=1';
		$params = [];
		if ( $args['role'] ) {
			$where   .= ' AND role = %s';
			$params[] = $args['role'];
		}
		$total  = (int) $wpdb->get_var( $params ? $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE $where", $params ) : "SELECT COUNT(*) FROM $table" );
		$offset = ( max( 1, (int) $args['page'] ) - 1 ) * (int) $args['per_page'];
		$rows   = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table WHERE $where ORDER BY id DESC LIMIT %d OFFSET %d",
			array_merge( $params, [ (int) $args['per_page'], $offset ] )
		) );
		return [ 'rows' => $rows, 'total' => $total ];
	}
}
