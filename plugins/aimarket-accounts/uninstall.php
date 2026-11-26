<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! get_option( 'aima_delete_data_on_uninstall', false ) ) {
	return;
}
global $wpdb;
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'aim_accounts' ) );
foreach ( [ 'aima_db_version', 'aim_key_migrated', 'aim_key_migration_progress', 'aima_delete_data_on_uninstall' ] as $option ) {
	delete_option( $option );
}
