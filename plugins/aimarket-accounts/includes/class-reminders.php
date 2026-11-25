<?php
defined( 'ABSPATH' ) || exit;

/**
 * Daily renewal reminders for accounts with an expiry date. Each configured
 * day (default 7, 3, 1 before expiry) is a bit in `reminded`, so a customer
 * gets each reminder once even if cron runs several times a day.
 */
class AIM_Reminders {

	const HOOK = 'aim_daily_reminders';

	public static function init() {
		add_action( self::HOOK, [ __CLASS__, 'run' ] );
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			self::schedule();
		}
	}

	public static function schedule() {
		wp_schedule_event( strtotime( 'tomorrow 10:00', current_time( 'timestamp' ) ) - (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ), 'daily', self::HOOK );
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	public static function days() {
		$days = array_map( 'absint', explode( ',', str_replace( '،', ',', aim_en_digits( (string) AIM_Settings::get( 'reminder_days' ) ) ) ) );
		$days = array_values( array_unique( array_filter( $days ) ) );
		rsort( $days );
		return array_slice( $days, 0, 8 ); // One bit each in a tinyint.
	}

	public static function run() {
		global $wpdb;
		$table = AIM_Inventory::table();
		$days  = self::days();
		if ( ! $days ) {
			return;
		}
		$max  = max( $days );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table WHERE status = 'assigned' AND expires_at IS NOT NULL AND expires_at > %s AND expires_at <= %s",
			gmdate( 'Y-m-d H:i:s' ),
			gmdate( 'Y-m-d H:i:s', time() + ( $max + 1 ) * DAY_IN_SECONDS )
		) );

		foreach ( $rows as $row ) {
			$left = (int) ceil( ( strtotime( $row->expires_at . ' UTC' ) - time() ) / DAY_IN_SECONDS );
			// Smallest threshold that has been reached decides which reminder this is.
			foreach ( array_reverse( $days, true ) as $i => $d ) {
				if ( $left > $d ) {
					continue;
				}
				$bit = 1 << $i;
				if ( ! ( (int) $row->reminded & $bit ) ) {
					self::notify( $row, $left );
					// Mark this and every larger threshold as sent so we never send an older reminder late.
					$mask = 0;
					foreach ( $days as $j => $dd ) {
						if ( $dd >= $d ) {
							$mask |= 1 << $j;
						}
					}
					$wpdb->update( $table, [ 'reminded' => (int) $row->reminded | $mask ], [ 'id' => $row->id ] );
					break;
				}
			}
		}
	}

	private static function notify( $row, $days_left ) {
		$phone = aim_user_phone( (int) $row->user_id );
		$name  = AIM_Inventory::product_label( (int) $row->product_id, (int) $row->variation_id );
		if ( $phone ) {
			AIM_SMS::send_pattern( $phone, 'reminder', [
				'service' => wp_strip_all_tags( $name ),
				'days'    => (string) max( 0, $days_left ),
			] );
		}
		$user = get_userdata( (int) $row->user_id );
		if ( $user && is_email( $user->user_email ) ) {
			aim_mail(
				$user->user_email,
				sprintf( 'اشتراک %s تا %d روز دیگر تمام می‌شود', wp_strip_all_tags( $name ), max( 0, $days_left ) ),
				'یادآوری تمدید اشتراک',
				sprintf( "اشتراک %s شما تا %d روز دیگر تمام می‌شود.\nبرای تمدید وارد حسابتان شوید:\n%s", wp_strip_all_tags( $name ), max( 0, $days_left ), aim_panel_url( 'user', 'accounts' ) )
			);
		}
	}
}
