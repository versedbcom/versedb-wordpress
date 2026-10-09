<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function versedb_settings_url() {
	return admin_url( 'options-general.php?page=versedb' );
}

function versedb_admin_redirect( $notice ) {
	wp_safe_redirect( add_query_arg( 'versedb_notice', $notice, versedb_settings_url() ) );
	exit;
}

function versedb_admin_guard( $action ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You cannot manage this connection.', 'versedb' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'versedb_' . $action );
}

function versedb_secure_token_transport() {
	$url = admin_url( 'admin-post.php' );
	$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
	$host = wp_parse_url( $url, PHP_URL_HOST );
	return ( is_ssl() && 'https' === $scheme ) || ( 'http' === $scheme && in_array( $host, array( 'localhost', '127.0.0.1', '[::1]' ), true ) );
}

function versedb_accept_token( $token, $expires = '' ) {
	if ( ! is_string( $token ) || ! preg_match( '/^[A-Za-z0-9|_-]{20,512}$/', $token ) ) {
		return new WP_Error( 'invalid_token', __( 'Enter a valid VerseDB personal access token.', 'versedb' ) );
	}
	$response = versedb_api( 'user', array(), $token );
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	$user = $response['data'];
	if ( empty( $user['id'] ) || ! versedb_int( $user['id'] ) || empty( $user['username'] ) || ! is_string( $user['username'] ) || ! isset( $user['is_pro'] ) || ! is_bool( $user['is_pro'] ) ) {
		return new WP_Error( 'invalid_account', __( 'The token did not resolve to a VerseDB account.', 'versedb' ) );
	}
	try {
		$secret = versedb_encrypt_token( $token );
	} catch ( Throwable $exception ) {
		return new WP_Error( 'storage_failed', __( 'The connection could not be stored securely.', 'versedb' ) );
	}
	versedb_clear_data();
	$connection = array( 'secret' => $secret, 'expires' => $expires, 'generation' => wp_generate_uuid4() );
	versedb_store( 'versedb_connection', $connection );
	versedb_store( 'versedb_account', array( 'id' => versedb_int( $user['id'] ), 'is_pro' => (bool) $user['is_pro'] ) );
	versedb_store( 'versedb_profile_cache', versedb_profile_projection( $user ) );
		versedb_store( 'versedb_account_updated', time() );
	versedb_store( 'versedb_status', 'connected' );
	set_transient( 'versedb_account_fresh', 1, HOUR_IN_SECONDS );
	versedb_ensure_schedule();
	return true;
}

function versedb_save_token() {
	versedb_admin_guard( 'save_token' );
	if ( ! versedb_secure_token_transport() ) {
		wp_die( esc_html__( 'Enable HTTPS before entering a VerseDB token.', 'versedb' ), '', array( 'response' => 403 ) );
	}
	$lock = versedb_acquire_lock();
	if ( ! $lock ) {
		versedb_admin_redirect( 'busy' );
	}
	try {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- versedb_admin_guard verifies the capability and action nonce above.
		$token = isset( $_POST['token'] ) && is_string( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$result = versedb_accept_token( $token );
	} finally {
		versedb_release_lock( $lock );
	}
	versedb_admin_redirect( is_wp_error( $result ) ? 'connection_failed' : 'connected' );
}
add_action( 'admin_post_versedb_save_token', 'versedb_save_token' );

function versedb_oauth_start() {
	versedb_admin_guard( 'connect' );
	$callback = admin_url( 'admin-post.php?action=versedb_callback' );
	$host = wp_parse_url( $callback, PHP_URL_HOST );
	$scheme = wp_parse_url( $callback, PHP_URL_SCHEME );
	if ( 'https' !== $scheme && ! ( 'http' === $scheme && in_array( $host, array( 'localhost', '127.0.0.1', '[::1]' ), true ) ) ) {
		versedb_admin_redirect( 'https_required' );
	}
	$state = bin2hex( random_bytes( 32 ) );
	$verifier = rtrim( strtr( base64_encode( random_bytes( 48 ) ), '+/', '-_' ), '=' );
	versedb_store( 'versedb_pending_connect', array( 'state' => $state, 'verifier' => $verifier, 'user' => get_current_user_id(), 'callback' => $callback, 'expires' => time() + 600 ) );
	$url = add_query_arg(
		array(
			'redirect_uri' => $callback,
			'state' => $state,
			'code_challenge' => rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' ),
			'code_challenge_method' => 'S256',
			'client_name' => 'WordPress',
			'site_name' => substr( wp_strip_all_tags( get_bloginfo( 'name' ) ), 0, 100 ),
			'scope' => 'read:public read:showcase',
		),
		'https://versedb.com/connect'
	);
	wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Fixed VerseDB consent origin.
	exit;
}
add_action( 'admin_post_versedb_connect', 'versedb_oauth_start' );

function versedb_claim_state( $state, $user_id, $callback ) {
	$pending = get_option( 'versedb_pending_connect', array() );
	if ( ! is_string( $state ) || empty( $pending['state'] ) || empty( $pending['verifier'] ) || empty( $pending['expires'] ) || $pending['expires'] < time() || $pending['user'] !== $user_id || $pending['callback'] !== $callback || ! hash_equals( $pending['state'], $state ) ) {
		return false;
	}
	delete_option( 'versedb_pending_connect' );
	return $pending;
}

function versedb_exchange_code( $code, $pending ) {
	if ( ! is_string( $code ) || ! preg_match( '/^[A-Za-z0-9]{1,200}$/', $code ) ) {
		return new WP_Error( 'invalid_code', __( 'The connection code is invalid.', 'versedb' ) );
	}
	$grant = versedb_api( 'connect/token', array( 'grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $pending['callback'], 'code_verifier' => $pending['verifier'] ), '', 'POST' );
	if ( is_wp_error( $grant ) ) {
		return $grant;
	}
	if ( ! isset( $grant['access_token'], $grant['abilities'], $grant['expires_at'] ) || ! is_array( $grant['abilities'] ) || ! in_array( 'read:showcase', $grant['abilities'], true ) || ! in_array( 'read:public', $grant['abilities'], true ) || ! is_string( $grant['expires_at'] ) || strtotime( $grant['expires_at'] ) <= time() ) {
		return new WP_Error( 'invalid_grant', __( 'VerseDB returned an invalid connection grant.', 'versedb' ) );
	}
	return versedb_accept_token( $grant['access_token'], $grant['expires_at'] );
}

function versedb_oauth_callback() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You cannot manage this connection.', 'versedb' ), '', array( 'response' => 403 ) );
	}
	$lock = versedb_acquire_lock();
	if ( ! $lock ) {
		versedb_admin_redirect( 'busy' );
	}
	$notice = 'connection_failed';
	try {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Single-use state bound to this administrator and exact callback replaces a WordPress nonce for the OAuth response.
		$state = isset( $_GET['state'] ) && is_string( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$code = isset( $_GET['code'] ) && is_string( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		$pending = versedb_claim_state( $state, get_current_user_id(), admin_url( 'admin-post.php?action=versedb_callback' ) );
		if ( ! $pending ) {
			$notice = 'invalid_state';
		} elseif ( isset( $_GET['error'] ) ) {
			$notice = 'denied';
		} elseif ( $code ) {
			$result = versedb_exchange_code( $code, $pending );
			$notice = is_wp_error( $result ) ? 'connection_failed' : 'connected';
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	} finally {
		versedb_release_lock( $lock );
	}
	versedb_admin_redirect( $notice );
}
add_action( 'admin_post_versedb_callback', 'versedb_oauth_callback' );

function versedb_admin_disconnect() {
	versedb_admin_guard( 'disconnect' );
	$lock = versedb_acquire_lock();
	if ( ! $lock ) {
		versedb_admin_redirect( 'busy' );
	}
	try {
		versedb_disconnect();
	} finally {
		versedb_release_lock( $lock );
	}
	versedb_admin_redirect( 'disconnected' );
}
add_action( 'admin_post_versedb_disconnect', 'versedb_admin_disconnect' );

function versedb_admin_refresh() {
	versedb_admin_guard( 'refresh' );
	if ( 'reconnect' === get_option( 'versedb_status' ) ) {
		versedb_admin_redirect( 'reconnect' );
	}
	delete_option( 'versedb_lists_pending' );
	foreach ( get_option( 'versedb_queries', array() ) as $key => $query ) {
		$data = get_option( 'versedb_data_' . $key, array() );
		if ( isset( $data['data'] ) ) {
			$data['updated'] = 0;
			versedb_store( 'versedb_data_' . $key, $data );
		}
	}
	delete_transient( 'versedb_account_fresh' );
	versedb_queue_refresh();
	versedb_admin_redirect( 'queued' );
}
add_action( 'admin_post_versedb_refresh', 'versedb_admin_refresh' );

function versedb_admin_clear_cache() {
	versedb_admin_guard( 'clear_cache' );
	if ( ! versedb_clear_cache() ) {
		versedb_admin_redirect( 'busy' );
	}
	versedb_admin_redirect( 'cache_cleared' );
}
add_action( 'admin_post_versedb_clear_cache', 'versedb_admin_clear_cache' );
