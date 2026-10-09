<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function versedb_store( $name, $value ) {
	update_option( $name, $value, false );
}

function versedb_crypto() {
	if ( ! class_exists( 'ParagonIE_Sodium_Compat' ) ) {
		require_once ABSPATH . WPINC . '/sodium_compat/autoload.php';
	}
}

function versedb_encrypt_token( $token ) {
	versedb_crypto();
	$nonce = random_bytes( 24 );
	$key   = hash( 'sha256', wp_salt( 'auth' ) . wp_salt( 'secure_auth' ), true );
	return base64_encode( $nonce . ParagonIE_Sodium_Compat::crypto_secretbox( $token, $nonce, $key ) );
}

function versedb_token() {
	$connection = get_option( 'versedb_connection', array() );
	if ( empty( $connection['secret'] ) || ! is_string( $connection['secret'] ) ) {
		return '';
	}
	try {
		versedb_crypto();
		$payload = base64_decode( $connection['secret'], true );
		if ( false === $payload || strlen( $payload ) < 41 ) {
			return '';
		}
		$key = hash( 'sha256', wp_salt( 'auth' ) . wp_salt( 'secure_auth' ), true );
		$token = ParagonIE_Sodium_Compat::crypto_secretbox_open( substr( $payload, 24 ), substr( $payload, 0, 24 ), $key );
		return is_string( $token ) ? $token : '';
	} catch ( Throwable $exception ) {
		return '';
	}
}

function versedb_release_lock( $lock ) {
	global $wpdb;
	$wpdb->delete( $wpdb->options, array( 'option_name' => 'versedb_refresh_lock', 'option_value' => maybe_serialize( $lock ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Atomic owner comparison prevents deleting a replacement lock.
	wp_cache_delete( 'versedb_refresh_lock', 'options' );
}

function versedb_acquire_lock() {
	$lock = array( 'owner' => wp_generate_uuid4(), 'expires' => time() + 120 );
	if ( add_option( 'versedb_refresh_lock', $lock, '', false ) ) {
		return $lock;
	}
	$previous = get_option( 'versedb_refresh_lock' );
	if ( is_array( $previous ) && isset( $previous['expires'] ) && $previous['expires'] < time() ) {
		versedb_release_lock( $previous );
		if ( add_option( 'versedb_refresh_lock', $lock, '', false ) ) {
			return $lock;
		}
	}
	return false;
}

function versedb_clear_data() {
	foreach ( get_option( 'versedb_queries', array() ) as $key => $query ) {
		delete_option( 'versedb_data_' . $key );
	}
	foreach ( array( 'versedb_account_updated', 'versedb_profile_cache', 'versedb_queries', 'versedb_query_errors', 'versedb_account', 'versedb_status', 'versedb_next_request', 'versedb_retry_count', 'versedb_lists_pending' ) as $name ) {
		delete_option( $name );
	}
	delete_transient( 'versedb_account_fresh' );
	wp_clear_scheduled_hook( 'versedb_refresh' );
	wp_clear_scheduled_hook( 'versedb_hourly' );
}

function versedb_disconnect() {
	delete_option( 'versedb_connection' );
	versedb_clear_data();
	delete_option( 'versedb_pending_connect' );
}

function versedb_queue_refresh( $delay = 5 ) {
	if ( ! get_option( 'versedb_connection' ) || 'reconnect' === get_option( 'versedb_status' ) ) {
		return;
	}
	$when = max( time() + $delay, (int) get_option( 'versedb_next_request', 0 ) );
	if ( ! wp_next_scheduled( 'versedb_refresh' ) ) {
		wp_schedule_single_event( $when, 'versedb_refresh' );
	}
}

function versedb_ensure_schedule() {
	if ( get_option( 'versedb_connection' ) && ! wp_next_scheduled( 'versedb_hourly' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'versedb_hourly' );
	}
}

function versedb_request_data( $type, $attributes = array() ) {
	if ( ! versedb_block_enabled( $type ) ) {
		return array();
	}
	$descriptor = versedb_descriptor( $type, $attributes );
	$key        = md5( wp_json_encode( $descriptor ) );
	$queries    = get_option( 'versedb_queries', array() );
	if ( get_option( 'versedb_connection' ) && ! isset( $queries[ $key ] ) ) {
		if ( count( $queries ) >= 100 ) {
			$oldest = key( $queries );
			unset( $queries[ $oldest ] );
			delete_option( 'versedb_data_' . $oldest );
		}
		$queries[ $key ] = $descriptor;
		versedb_store( 'versedb_queries', $queries );
	}
	$data = get_option( 'versedb_data_' . $key, array() );
	if ( 'lists' === $type && empty( $data['lists_verified'] ) ) {
		delete_option( 'versedb_data_' . $key );
		$data = array();
	}
	if ( ! versedb_cache_has_series_fields( $data ) || empty( $data['updated'] ) || $data['updated'] < time() - HOUR_IN_SECONDS ) {
		versedb_queue_refresh();
	}
	return isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : array();
}

function versedb_clear_cache() {
	$lock = versedb_acquire_lock();
	if ( ! $lock ) {
		return false;
	}
	try {
		$queries = get_option( 'versedb_queries', array() );
		foreach ( $queries as $key => $query ) {
			delete_option( 'versedb_data_' . $key );
			if ( ! versedb_block_enabled( $query['type'] ) ) {
				unset( $queries[ $key ] );
			}
		}
		versedb_store( 'versedb_queries', $queries );
		foreach ( array( 'versedb_profile_cache', 'versedb_account_updated', 'versedb_query_errors', 'versedb_lists_pending' ) as $name ) {
			delete_option( $name );
		}
		delete_transient( 'versedb_account_fresh' );
		versedb_queue_refresh();
		versedb_ensure_schedule();
	} finally {
		versedb_release_lock( $lock );
	}
	return true;
}
