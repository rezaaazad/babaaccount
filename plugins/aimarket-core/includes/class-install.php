<?php
defined( 'ABSPATH' ) || exit;

class AIM_Install {

	const DB_VERSION = '3';

	public static function activate() {
		self::create_tables();
		set_transient( AIM_Setup::REDIRECT, 1, 60 ); // Open the setup wizard on the next admin page.
		// Rebuilt on the next page load, once WooCommerce has registered its endpoints too (AIM_Router::maybe_flush).
		update_option( AIM_Router::FLUSH_OPTION, 1 );
		update_option( 'aim_db_version', self::DB_VERSION );
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}

	public static function maybe_upgrade() {
		if ( get_option( 'aim_db_version' ) !== self::DB_VERSION ) {
			global $wpdb;
			$table = self::table( 'alerts' );
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table ) {
				// Keep the oldest pending subscription; sent history is untouched.
				$wpdb->query( $wpdb->prepare( "DELETE newer FROM %i newer INNER JOIN %i older ON newer.product_id = older.product_id AND newer.user_id = older.user_id AND newer.type = older.type AND newer.id > older.id WHERE newer.status = 'pending' AND older.status = 'pending'", $table, $table ) );
			}
			self::create_tables();
			update_option( 'aim_db_version', self::DB_VERSION );
		}
	}

	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'aim_' . $name;
	}

	private static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		dbDelta( 'CREATE TABLE ' . self::table( 'otp' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			phone varchar(20) NOT NULL,
			code_hash varchar(255) NOT NULL,
			attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
			ip varchar(45) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY phone (phone)
		) $charset;" );

		dbDelta( 'CREATE TABLE ' . self::table( 'activity' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			role varchar(20) NOT NULL DEFAULT '',
			action varchar(50) NOT NULL,
			object_type varchar(30) NOT NULL DEFAULT '',
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			message text NOT NULL,
			ip varchar(45) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY created_at (created_at)
		) $charset;" );

		dbDelta( 'CREATE TABLE ' . self::table( 'alerts' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			product_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			phone varchar(20) NOT NULL DEFAULT '',
			type varchar(10) NOT NULL DEFAULT 'stock',
			status varchar(10) NOT NULL DEFAULT 'pending',
			pending_slot tinyint GENERATED ALWAYS AS (CASE WHEN status = 'pending' THEN 1 ELSE NULL END) STORED,
			created_at datetime NOT NULL,
			sent_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY product_pending (product_id,type,status),
			UNIQUE KEY unique_pending (product_id,user_id,type,pending_slot),
			KEY user_id (user_id)
		) $charset;" );
	}
}
