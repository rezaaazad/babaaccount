<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! get_option( 'aim_delete_data_on_uninstall', false ) ) {
	return;
}
global $wpdb;
foreach ( [ 'otp', 'activity', 'alerts' ] as $name ) {
	$table = $wpdb->prefix . 'aim_' . $name;
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
}
foreach ( [ 'aim_settings', 'aim_db_version', 'aim_sms_log', 'aim_delete_data_on_uninstall' ] as $option ) {
	delete_option( $option );
}
