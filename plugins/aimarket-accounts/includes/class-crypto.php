<?php
defined( 'ABSPATH' ) || exit;

/**
 * Encrypts account credentials at rest with AES-256-GCM.
 *
 * The key comes from AIM_SECRET_KEY in wp-config.php (set it from panel →
 * تنظیمات فروشگاه → ورود و امنیت, see AIM_Secret_Key), or else from the
 * site's salts. After AIM_SECRET_KEY is added, values written with the salts
 * key still decrypt, and migrate() re-encrypts them with the new key once.
 */
class AIM_Crypto {

	/** Option holding a hash of the key the stored values were last migrated to. */
	const MIGRATED_OPTION = 'aim_key_migrated';

	private static function key( $material = null ) {
		$material ??= defined( 'AIM_SECRET_KEY' ) ? AIM_SECRET_KEY : self::salts();
		return hash( 'sha256', 'aim-accounts|' . $material, true );
	}

	private static function salts() {
		return wp_salt( 'auth' ) . wp_salt( 'secure_auth' );
	}

	public static function encrypt( $plain ) {
		$iv     = random_bytes( 12 );
		$tag    = '';
		$cipher = openssl_encrypt( (string) $plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag );
		return 'v1:' . base64_encode( $iv . $tag . $cipher );
	}

	public static function decrypt( $stored ) {
		$plain = self::open( $stored, self::key() );
		// Written before AIM_SECRET_KEY existed: still readable with the salts key.
		if ( null === $plain && defined( 'AIM_SECRET_KEY' ) ) {
			$plain = self::open( $stored, self::key( self::salts() ) );
		}
		return $plain ?? '';
	}

	/** Plain text, or null when $stored is not ours or the key does not fit. */
	private static function open( $stored, $key ) {
		if ( ! str_starts_with( (string) $stored, 'v1:' ) ) {
			return null;
		}
		$raw = base64_decode( substr( $stored, 3 ), true );
		if ( false === $raw || strlen( $raw ) < 28 ) {
			return null;
		}
		$plain = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ) );
		return false === $plain ? null : $plain;
	}

	/**
	 * Re-encrypt stored accounts with AIM_SECRET_KEY, once per key.
	 * Runs on admin requests; values that already use the key are left alone.
	 */
	public static function migrate() {
		if ( ! defined( 'AIM_SECRET_KEY' ) ) {
			return;
		}
		$marker = hash( 'sha256', 'aim-key-check|' . AIM_SECRET_KEY );
		if ( get_option( self::MIGRATED_OPTION ) === $marker ) {
			return;
		}
		global $wpdb;
		$table   = AIM_Install::table( 'accounts' );
		$current = self::key();
		$old     = self::key( self::salts() );
		$moved   = 0;
		$progress = get_option( 'aim_key_migration_progress', [] );
		$cursor = ( $progress['marker'] ?? '' ) === $marker ? (int) $progress['cursor'] : 0;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, secret FROM $table WHERE id > %d ORDER BY id LIMIT 200", $cursor ) );
		if ( null === $rows ) {
			return;
		}
		foreach ( $rows as $row ) {
			$cursor = (int) $row->id;
			if ( null !== self::open( $row->secret, $current ) ) {
				continue;
			}
			$plain = self::open( $row->secret, $old );
			if ( null !== $plain ) {
				$wpdb->update( $table, [ 'secret' => self::encrypt( $plain ) ], [ 'id' => $row->id, 'secret' => $row->secret ] );
				$moved++;
			}
		}
		if ( count( $rows ) < 200 ) {
			update_option( self::MIGRATED_OPTION, $marker, false );
			delete_option( 'aim_key_migration_progress' );
		} else {
			update_option( 'aim_key_migration_progress', [ 'marker' => $marker, 'cursor' => $cursor ], false );
		}
		if ( $moved ) {
			AIM_Activity::log( 'key_migrated', sprintf( 'اطلاعات %s اکانت با کلید رمزنگاری جدید دوباره رمز شد', aim_fa_digits( $moved ) ), 'settings' );
		}
	}
}
