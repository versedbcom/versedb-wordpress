<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$server_before = $_SERVER;
$post_before = $_POST;
$request_before = $_REQUEST;
$admin_transport_origin = 'http://example.com';
$admin_transport_filter = function ( $url ) use ( &$admin_transport_origin ) {
	return preg_replace( '~^https?://[^/]+~', $admin_transport_origin, $url );
};
add_filter( 'admin_url', $admin_transport_filter );
$_SERVER['HTTPS'] = 'off';
$_SERVER['SERVER_PORT'] = '80';
ob_start();
versedb_settings_page();
$insecure_settings = ob_get_clean();
versedb_test_assert( false === strpos( $insecure_settings, 'name="token"' ) && false !== strpos( $insecure_settings, 'Enable HTTPS' ), 'A remote HTTP settings page must not offer token entry.' );

$die_status = 0;
$transport_die_handler = function () use ( &$die_status ) {
	return function ( $message, $title, $args ) use ( &$die_status ) {
		$die_status = $args['response'];
		throw new RuntimeException( 'Insecure token rejected.' );
	};
};
add_filter( 'wp_die_handler', $transport_die_handler );
$_REQUEST['_wpnonce'] = wp_create_nonce( 'versedb_save_token' );
$_POST['token'] = str_repeat( 'sample_token_', 4 );
$connection_before = get_option( 'versedb_connection' );
$http_before = $http_calls;
$rejected = false;
try { versedb_save_token(); } catch ( RuntimeException $exception ) { $rejected = 'Insecure token rejected.' === $exception->getMessage(); }
versedb_test_assert( $rejected && 403 === $die_status && $http_before === $http_calls && $connection_before === get_option( 'versedb_connection' ) && ! get_option( 'versedb_refresh_lock' ), 'Even a valid administrator nonce must not allow a remote HTTP token submission or change the connection.' );
remove_filter( 'wp_die_handler', $transport_die_handler );

$admin_transport_origin = 'https://example.com';
versedb_test_assert( ! versedb_secure_token_transport(), 'An HTTPS form action alone must not permit token entry on an HTTP page.' );
$_SERVER['HTTPS'] = 'on';
ob_start();
versedb_settings_page();
$secure_settings = ob_get_clean();
versedb_test_assert( false !== strpos( $secure_settings, 'name="token"' ) && false !== strpos( $secure_settings, 'action="https://example.com/wp-admin/admin-post.php"' ), 'HTTPS settings must offer a token form with an HTTPS action.' );
$transport_redirect = function () { throw new RuntimeException( 'Token action redirected.' ); };
add_filter( 'wp_redirect', $transport_redirect );
$http_before = $http_calls;
$redirected = false;
try { versedb_save_token(); } catch ( RuntimeException $exception ) { $redirected = 'Token action redirected.' === $exception->getMessage(); }
versedb_test_assert( $redirected && $http_calls === $http_before + 1 && versedb_token() === $_POST['token'] && ! get_option( 'versedb_refresh_lock' ), 'HTTPS token submissions must connect and release their lock.' );
remove_filter( 'wp_redirect', $transport_redirect );
$_SERVER['HTTPS'] = 'off';
foreach ( array( 'localhost', '127.0.0.1', '[::1]' ) as $loopback ) {
	$admin_transport_origin = 'http://' . $loopback;
	ob_start();
	versedb_settings_page();
	$local_settings = ob_get_clean();
	versedb_test_assert( versedb_secure_token_transport() && false !== strpos( $local_settings, 'name="token"' ), 'Local loopback development must retain token entry.' );
}
$admin_transport_origin = 'http://localhost.evil.example';
versedb_test_assert( ! versedb_secure_token_transport(), 'A hostname resembling localhost must not bypass HTTPS.' );
remove_filter( 'admin_url', $admin_transport_filter );
$_SERVER = $server_before;
$_POST = $post_before;
$_REQUEST = $request_before;

$account = get_option( 'versedb_account' );
$list_row = array( 'id' => 9, 'title' => 'Public list', 'is_private' => false, 'user' => array( 'id' => $account['id'] ) );
foreach ( array( null, 'draft', 'pending', 'archived' ) as $status ) {
	$candidate = $list_row;
	if ( null !== $status ) { $candidate['status'] = $status; }
	versedb_test_assert( array() === versedb_project_data( 'lists', array( $candidate ), $account ), 'Unknown or unpublished status must never reach the display projection.' );
}
$lists_query = versedb_descriptor( 'lists', array() );
$lists_key = md5( wp_json_encode( $lists_query ) );
versedb_store( 'versedb_data_' . $lists_key, array( 'updated' => time(), 'data' => array( array( 'id' => 11, 'name' => 'private-draft-marker', 'url' => 'https://versedb.com/list/sample_reader/11/draft' ) ) ) );
wp_set_current_user( 0 );
$http_before = $http_calls;
versedb_test_assert( '' === versedb_shortcode( 'lists', array() ) && $http_before === $http_calls && ! get_option( 'versedb_data_' . $lists_key ), 'Anonymous rendering must discard legacy unverified list caches without making HTTP requests.' );
wp_set_current_user( 1 );
versedb_store( 'versedb_data_' . $lists_key, array( 'updated' => time(), 'data' => array( array( 'id' => 11, 'name' => 'private-draft-marker' ) ) ) );
versedb_refresh();
$verified_cache = get_option( 'versedb_data_' . $lists_key );
$public_lists = versedb_shortcode( 'lists', array() );
versedb_test_assert( ! empty( $verified_cache['lists_verified'] ) && false !== strpos( $public_lists, 'Sample public list' ) && false === strpos( wp_json_encode( $verified_cache ) . $public_lists, 'private-draft-marker' ), 'Public lists must retain published cards and exclude drafts returned to a broad token.' );

$batch_details = 0;
$batch_filter = function ( $pre, $args, $url ) use ( &$batch_details, $account, $filter ) {
	$path = wp_parse_url( $url, PHP_URL_PATH );
	if ( '/api/v1/users/' . $account['id'] . '/lists' === $path ) {
		$cards = array();
		for ( $id = 20; $id < 27; ++$id ) { $cards[] = array( 'id' => $id, 'title' => 'Unverified card', 'is_private' => false, 'user' => array( 'id' => $account['id'] ) ); }
		return versedb_test_response( array( 'data' => $cards ) );
	}
	if ( preg_match( '~^/api/v1/lists/(2[0-6])$~', $path, $matches ) ) {
		++$batch_details;
		$id = (int) $matches[1];
		if ( 24 === $id ) { return versedb_test_response( array(), 404 ); }
		return versedb_test_response( array( 'data' => array( 'id' => $id, 'title' => 'Verified list ' . $id, 'is_private' => false, 'status' => 25 === $id ? 'draft' : 'published', 'user' => array( 'id' => $account['id'] ) ) ) );
	}
	return $filter( $pre, $args, $url );
};
// Replace the normal fixture temporarily so its unexpected-route check stays useful.
remove_filter( 'pre_http_request', $filter, 10 );
add_filter( 'pre_http_request', $batch_filter, 10, 3 );
$counts = array();
versedb_store( 'versedb_data_' . $lists_key, array( 'updated' => 0, 'data' => array(), 'lists_verified' => true ) );
for ( $batch = 0; $batch < 3; ++$batch ) {
	wp_clear_scheduled_hook( 'versedb_refresh' );
	versedb_refresh();
	$cache = get_option( 'versedb_data_' . $lists_key );
	$counts[] = count( $cache['data'] );
	versedb_test_assert( $batch_details <= 3 * ( $batch + 1 ), 'Each background batch must make at most three detail requests.' );
	if ( $batch < 2 ) {
		versedb_test_assert( 0 === $cache['updated'] && wp_next_scheduled( 'versedb_refresh' ), 'Incomplete list verification must schedule its next background batch.' );
	}
}
versedb_test_assert( array( 3, 4, 5 ) === $counts && 7 === $batch_details && ! get_option( 'versedb_lists_pending' ), 'List verification must resume through all candidates and exclude drafts and removed lists.' );
remove_filter( 'pre_http_request', $batch_filter, 10 );
add_filter( 'pre_http_request', $filter, 10, 3 );
versedb_store( 'versedb_lists_pending', array( 'ids' => array( 99 ) ) );
versedb_disconnect();
versedb_test_assert( ! get_option( 'versedb_lists_pending' ), 'Disconnect must remove any pending list verification.' );
versedb_test_assert( true === versedb_accept_token( str_repeat( 'sample_token_', 4 ) ), 'Security checks must leave a normal connection for the remaining integration tests.' );
