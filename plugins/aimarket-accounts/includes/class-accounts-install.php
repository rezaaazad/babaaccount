<?php
defined( 'ABSPATH' ) || exit;

/** Accounts table ({prefix}aim_accounts) and the daily renewal-reminder job. */
class AIM_Accounts_Install {

	const DB_VERSION = '1';
	const OPTION     = 'aima_db_version';

	public static function activate() {
		self::create_tables();
		AIM_Reminders::schedule();
		update_option( self::OPTION, self::DB_VERSION );
	}

	public static function deactivate() {
		AIM_Reminders::unschedule();
	}

	public static function maybe_upgrade() {
		if ( get_option( self::OPTION ) !== self::DB_VERSION ) {
			self::create_tables();
			update_option( self::OPTION, self::DB_VERSION );
		}
	}

	private static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		dbDelta( 'CREATE TABLE ' . AIM_Install::table( 'accounts' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			product_id bigint(20) unsigned NOT NULL,
			variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
			secret longtext NOT NULL,
			note varchar(255) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'available',
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			order_item_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			duration_days int(11) unsigned NOT NULL DEFAULT 0,
			reminded tinyint(3) unsigned NOT NULL DEFAULT 0,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			assigned_at datetime DEFAULT NULL,
			expires_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY product_status (product_id,variation_id,status),
			KEY order_id (order_id),
			KEY user_id (user_id),
			KEY expires_at (expires_at)
		) $charset;" );
	}
}
