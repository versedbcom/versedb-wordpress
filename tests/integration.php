<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}
global $wpdb;

function versedb_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

require __DIR__ . '/fixtures.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
wp_set_current_user( 1 );
$die_handler = function () {
	return function () { throw new RuntimeException( 'Admin action rejected.' ); };
};
add_filter( 'wp_die_handler', $die_handler );
$request_before = $_REQUEST;
foreach ( array( array( 0, '' ), array( 1, 'invalid-nonce' ) ) as $attempt ) {
	wp_set_current_user( $attempt[0] );
	$_REQUEST['_wpnonce'] = $attempt[1];
	$rejected = false;
	try { versedb_admin_clear_cache(); } catch ( RuntimeException $exception ) { $rejected = true; }
	versedb_test_assert( $rejected, 'Admin mutations must reject missing capability or invalid nonce.' );
}
wp_set_current_user( 1 );
$_REQUEST['_wpnonce'] = wp_create_nonce( 'versedb_disconnect' );
versedb_admin_guard( 'disconnect' );
$_REQUEST = $request_before;
remove_filter( 'wp_die_handler', $die_handler );
versedb_disconnect();
$token = str_repeat( 'sample_token_', 4 );
versedb_test_assert( true === versedb_accept_token( $token ), 'A valid token must connect.' );
versedb_test_assert( $token === versedb_token(), 'Encrypted credentials must decrypt.' );
$connection = get_option( 'versedb_connection' );
versedb_test_assert( false === strpos( $connection['secret'], $token ), 'Stored credentials must not contain the plaintext token.' );
versedb_test_assert( ! isset( wp_load_alloptions()['versedb_connection'] ), 'Credentials must not be autoloaded.' );
ob_start();
versedb_settings_page();
$settings_markup = ob_get_clean();
versedb_editor_settings();
$editor_settings = implode( '', (array) wp_scripts()->get_data( 'wp-blocks', 'after' ) );
versedb_test_assert( false === strpos( $settings_markup . $editor_settings, $token ) && false === strpos( $settings_markup . $editor_settings, $connection['secret'] ), 'Settings and editor scripts must not expose stored credentials.' );
$corrupt = $connection;
$corrupt['secret'] = 'broken';
versedb_store( 'versedb_connection', $corrupt );
versedb_test_assert( '' === versedb_token(), 'Corrupt ciphertext must fail closed.' );
versedb_store( 'versedb_connection', $connection );
$pending = array( 'state' => 'sample-state', 'verifier' => str_repeat( 'a', 48 ), 'user' => 1, 'callback' => 'https://example.com/callback', 'expires' => time() + 60 );
versedb_store( 'versedb_pending_connect', $pending );
versedb_test_assert( false === versedb_claim_state( 'wrong', 1, $pending['callback'] ), 'Wrong state must be rejected.' );
versedb_test_assert( false === versedb_claim_state( 'sample-state', 2, $pending['callback'] ), 'State must be bound to the admin.' );
versedb_test_assert( false === versedb_claim_state( 'sample-state', 1, 'https://other.example/callback' ), 'State must be bound to the exact callback.' );
versedb_test_assert( $pending === versedb_claim_state( 'sample-state', 1, $pending['callback'] ), 'Valid state must be claimable.' );
versedb_test_assert( false === versedb_claim_state( 'sample-state', 1, $pending['callback'] ), 'State must be single-use.' );
$pending['expires'] = time() - 1;
versedb_store( 'versedb_pending_connect', $pending );
versedb_test_assert( false === versedb_claim_state( 'sample-state', 1, $pending['callback'] ), 'Expired state must be rejected.' );
$http_before = $http_calls;
versedb_test_assert( is_wp_error( versedb_exchange_code( '<script>', $pending ) ) && $http_before === $http_calls, 'Invalid authorization codes must not be exchanged.' );
versedb_test_assert( true === versedb_exchange_code( 'sampleAuthorizationCode', $pending ), 'A valid OAuth grant must connect.' );
versedb_test_assert( 'POST' === $exchange_request['method'] && $pending['callback'] === $exchange_request['body']['redirect_uri'] && $pending['verifier'] === $exchange_request['body']['code_verifier'] && ! isset( $exchange_request['headers']['Authorization'] ), 'Exchange must send the exact callback and PKCE verifier without a bearer token.' );
$saved_connection = get_option( 'versedb_connection' );
ob_start();
versedb_connection_notice();
$expiry_notice = ob_get_clean();
versedb_test_assert( ! empty( $saved_connection['expires'] ) && false !== strpos( $expiry_notice, 'expiring' ), 'A known expiry within two weeks must warn the administrator.' );
$grant_expired = true;
versedb_test_assert( is_wp_error( versedb_exchange_code( 'sampleAuthorizationCode', $pending ) ) && $saved_connection === get_option( 'versedb_connection' ), 'Expired token grants must be rejected without replacing the connection.' );
$grant_expired = false;
$grant_valid = false;
versedb_test_assert( is_wp_error( versedb_exchange_code( 'sampleAuthorizationCode', $pending ) ) && $saved_connection === get_option( 'versedb_connection' ), 'Missing required scopes must preserve the existing connection.' );
$grant_valid = true;
require __DIR__ . '/oembed.php';
require __DIR__ . '/security.php';
$unnamed_issue = versedb_entity_projection( array( 'id' => 18, 'name' => null, 'issue_number' => '2', 'series' => array( 'name' => 'Example series', 'publisher_name' => 'Example publisher' ) ) );
versedb_test_assert( 'Example series #2' === $unnamed_issue['name'] && 'Example publisher' === $unnamed_issue['publisher'], 'Ordinary issues without individual names must display their series and issue number.' );
$series = versedb_entity_projection( array( 'id' => 19, 'name' => 'Example series', 'publisher_name' => 'Example publisher' ), 'series' );
versedb_test_assert( 'Example publisher' === $series['publisher'], 'Series publisher labels must use the API field.' );
$ordered = versedb_descriptor( 'collection', array( 'sort_by' => 'title' ) );
versedb_test_assert( 'title' === $ordered['params']['sort_by'], 'Title ordering must use the supported API sort field.' );

$types = array( 'profile', 'collection', 'wishlist', 'pull-list', 'reading', 'currently-reading', 'reading-goal', 'lists', 'list', 'reading-stats', 'reading-calendar' );
$http_before = $http_calls;
foreach ( $types as $type ) {
	$registered = WP_Block_Type_Registry::get_instance()->get_registered( 'versedb/' . $type );
	versedb_test_assert( $registered && in_array( 'versedb-display', $registered->style_handles, true ) && wp_style_is( 'versedb-display', 'registered' ), 'Every block must register its packaged stylesheet.' );
	versedb_shortcode( $type, array( 'list_id' => 9, 'year' => 2026 ) );
}
versedb_test_assert( $http_before === $http_calls, 'Rendering an uncached block must not make HTTP requests.' );
for ( $batch = 0; $batch < 4; ++$batch ) {
	versedb_refresh();
}
$output = '';
foreach ( $types as $type ) {
	$output .= versedb_shortcode( $type, array( 'list_id' => 9, 'year' => 2026 ) );
	$block = '<!-- wp:versedb/' . $type . ' {"listId":9,"year":2026} /-->';
	ob_start();
	$widget = new WP_Widget_Block();
	$widget->widget( array( 'before_widget' => '<aside>', 'after_widget' => '</aside>' ), array( 'content' => $block ) );
	$widget_output = ob_get_clean();
	versedb_test_assert( false !== strpos( $widget_output, versedb_shortcode( $type, array( 'list_id' => 9, 'year' => 2026 ) ) ), 'Block widget output must match the shared shortcode renderer.' );
}
versedb_test_assert( false !== strpos( versedb_shortcode( 'profile', array() ), '--versedb-avatar-radius:50%' ), 'Existing profiles must keep circular avatars.' );
versedb_test_assert( false !== strpos( versedb_shortcode( 'profile', array( 'avatar_shape' => 'square', 'avatar_radius' => 12 ) ), '--versedb-avatar-radius:12px' ), 'Square avatar shortcode settings must reach the shared renderer.' );
versedb_test_assert( false !== strpos( versedb_shortcode( 'profile', array( 'avatar_shape' => 'square', 'avatar_radius' => -5 ) ), '--versedb-avatar-radius:0px' ), 'Avatar radius cannot be negative.' );
versedb_test_assert( false !== strpos( versedb_shortcode( 'profile', array( 'avatar_shape' => 'square', 'avatar_radius' => 999 ) ), '--versedb-avatar-radius:80px' ), 'Avatar radius must be bounded.' );
versedb_test_assert( false !== strpos( versedb_shortcode( 'profile', array( 'avatar_shape' => 'invalid', 'avatar_radius' => 12 ) ), '--versedb-avatar-radius:50%' ), 'Invalid avatar shapes must fall back to a circle.' );
foreach ( array( 'profile', 'collection', 'wishlist', 'pull-list', 'currently-reading', 'reading', 'lists', 'list' ) as $link_type ) {
	$link_settings = array( 'list_id' => 9, 'year' => 2026 );
	$linked = versedb_shortcode( $link_type, $link_settings );
	$unlinked = versedb_shortcode( $link_type, array_merge( $link_settings, array( 'enable_links' => 'false' ) ) );
	versedb_test_assert( false !== strpos( $linked, '<a ' ) && false === strpos( $unlinked, '<a ' ), 'Content links must default on and turn off for ' . $link_type );
	versedb_test_assert( wp_strip_all_tags( $linked ) === wp_strip_all_tags( $unlinked ), 'Disabling links must preserve display text for ' . $link_type );
	versedb_test_assert( substr_count( $linked, '<img ' ) === substr_count( $unlinked, '<img ' ), 'Disabling links must preserve covers for ' . $link_type );
}
versedb_test_assert( false !== strpos( versedb_shortcode( 'collection', array( 'enable_links' => 'false' ) ), 'cover--blurred' ), 'Unlinked covers must retain NSFW blur.' );
require __DIR__ . '/templates.php';
versedb_test_assert( false !== strpos( $output, 'sample_reader' ) && false !== strpos( $output, 'Sample series' ) && false !== strpos( $output, 'Sample public list' ), 'The connected displays must render cached content.' );
versedb_test_assert( false !== strpos( $output, 'versedb-calendar__day' ) && false !== strpos( $output, '<progress' ) && false !== strpos( $output, 'versedb-stats' ), 'Reading displays must render.' );
versedb_test_assert( false !== strpos( versedb_shortcode( 'currently-reading', array() ), '50% read' ), 'Currently Reading must render the safe cached percentage.' );
versedb_test_assert( false === strpos( $output, 'private-' ), 'Private payload fields and private rows must not appear in public output.' );
versedb_test_assert( false === strpos( $output, '<script>' ), 'Entity names must not inject markup.' );
versedb_test_assert( false !== strpos( $output, 'versedb-item__cover--blurred' ), 'Explicit covers must be blurred by default.' );
versedb_test_assert( false === strpos( versedb_shortcode( 'collection', array( 'show_nsfw' => 'true' ) ), 'cover--blurred' ), 'An owner can opt out of explicit cover blur.' );
versedb_test_assert( false === strpos( $output, 'Powered by' ), 'Credit links must be off by default.' );
versedb_store( 'versedb_preferences', array( 'credit' => true ) );
versedb_test_assert( false !== strpos( versedb_shortcode( 'profile', array() ), 'Powered by' ), 'Enabled credits must render.' );
versedb_store( 'versedb_preferences', array( 'credit' => false ) );
versedb_test_assert( '' === versedb_credit(), 'An explicit disabled credit preference must be respected.' );
$filtered = versedb_descriptor( 'collection', array( 'for_sale' => '0', 'for_trade' => '1', 'genre_id' => '12', 'creator_id' => '34', 'character_id' => '56' ) );
versedb_test_assert( '0' === $filtered['params']['for_sale'] && '1' === $filtered['params']['for_trade'] && '12' === $filtered['params']['genre_id'] && '34' === $filtered['params']['creator_id'] && '56' === $filtered['params']['character_id'], 'Collection availability and Pro entity filters must reach the query, including negative availability.' );
$item_types = array( 'collection', 'wishlist', 'pull-list', 'reading', 'currently-reading', 'lists', 'list' );
foreach ( $item_types as $type ) {
	$settings = array( 'list_id' => 9, 'layout' => 'list', 'columns' => 5, 'mobile_columns' => 1, 'gap' => 0, 'cover_size' => 120, 'limit' => 1, 'show_covers' => 'false', 'title' => 'Custom heading' );
	$markup = versedb_shortcode( $type, $settings );
	versedb_test_assert( false !== strpos( $markup, 'versedb-items--list' ) && false !== strpos( $markup, 'Custom heading' ) && false !== strpos( $markup, '--versedb-mobile-columns:1' ) && false !== strpos( $markup, '--versedb-gap:0px' ) && false !== strpos( $markup, '--versedb-cover-size:120px' ), 'Every item display must honor its layout, heading and spacing settings.' );
	versedb_test_assert( false === strpos( $markup, '<img' ) && 1 === substr_count( $markup, '<li ' ), 'Item count and hidden covers must apply to every item display.' );
}
$markup = versedb_shortcode( 'collection', array( 'show_publisher' => 'true', 'show_dates' => 'true', 'columns' => 1, 'mobile_columns' => 3 ) );
versedb_test_assert( false !== strpos( $markup, 'Sample publisher' ) && false !== strpos( $markup, '<time' ) && false !== strpos( $markup, '--versedb-mobile-columns:1' ), 'Metadata toggles must render and mobile columns cannot exceed desktop columns.' );
$markup = versedb_shortcode( 'profile', array( 'show_avatar' => 'false', 'show_bio' => 'false', 'show_stats' => 'true' ) );
versedb_test_assert( false === strpos( $markup, '<img' ) && false === strpos( $markup, 'versedb-profile__bio' ) && false !== strpos( $markup, 'XP' ), 'Profile visibility controls must affect rendered output.' );
foreach ( array( 'currently-reading', 'reading-goal' ) as $type ) {
	$markup = versedb_shortcode( $type, array( 'year' => 2026, 'show_progress' => 'false' ) );
	versedb_test_assert( false === strpos( $markup, '<progress' ) && false !== strpos( $markup, 'read' ), 'Hiding a progress bar must preserve the reading count or percentage.' );
}
$metric_labels = array( 'show_read_count' => 'Comics read', 'show_active_days' => 'Active days', 'show_current_streak' => 'Current streak', 'show_longest_streak' => 'Longest streak', 'show_this_week' => 'This week', 'show_this_month' => 'This month' );
foreach ( $metric_labels as $setting => $label ) {
	$markup = versedb_shortcode( 'reading-stats', array( 'year' => 2026, $setting => 'false', 'columns' => 2, 'mobile_columns' => 1, 'gap' => 24 ) );
	versedb_test_assert( false === strpos( $markup, '<dt>' . $label . '</dt>' ) && 5 === substr_count( $markup, '<dt>' ) && false !== strpos( $markup, '--versedb-columns:2' ) && false !== strpos( $markup, '--versedb-gap:24px' ), 'Each statistic must be independently hideable without removing other metrics.' );
}
$markup = versedb_shortcode( 'reading-calendar', array( 'year' => 2026, 'show_year' => 'false', 'cell_size' => 20, 'cell_gap' => 0 ) );
versedb_test_assert( false === strpos( $markup, 'Reading activity in' ) && false !== strpos( $markup, '--versedb-cell-size:20px' ) && false !== strpos( $markup, '--versedb-cell-gap:0px' ) && false !== strpos( $markup, 'Daily reading activity' ), 'Calendar controls must apply while preserving accessible activity labels.' );
$markup = versedb_shortcode( 'reading-calendar', array( 'year' => 2026, 'cell_size' => 999, 'cell_gap' => -1 ) );
versedb_test_assert( false !== strpos( $markup, '--versedb-cell-size:24px' ) && false !== strpos( $markup, '--versedb-cell-gap:0px' ), 'Calendar dimensions must remain within supported bounds.' );

$http_before = $http_calls;
delete_transient( 'versedb_account_fresh' );
foreach ( $types as $type ) {
	versedb_shortcode( $type, array( 'list_id' => 9, 'year' => 2026 ) );
}
versedb_test_assert( $http_before === $http_calls, 'Stale public rendering must not make HTTP requests.' );
versedb_test_assert( (bool) wp_next_scheduled( 'versedb_refresh' ), 'Stale rendering must schedule background work.' );

$lock = versedb_acquire_lock();
$http_before = $http_calls;
versedb_refresh();
versedb_test_assert( $http_before === $http_calls, 'A competing refresh must not make requests.' );
versedb_release_lock( $lock );
$expired_lock = array( 'owner' => 'expired', 'expires' => time() - 1 );
versedb_store( 'versedb_refresh_lock', $expired_lock );
$replacement_lock = versedb_acquire_lock();
versedb_test_assert( (bool) $replacement_lock, 'Expired refresh locks must be recoverable.' );
versedb_release_lock( $expired_lock );
versedb_test_assert( $replacement_lock === get_option( 'versedb_refresh_lock' ), 'A previous owner must not release a replacement lock.' );
versedb_release_lock( $replacement_lock );
$failure = 401;
versedb_refresh();
versedb_test_assert( 'reconnect' === get_option( 'versedb_status' ), 'A 401 must ask the admin to reconnect.' );
versedb_test_assert( false !== strpos( versedb_shortcode( 'profile', array() ), 'sample_reader' ), 'A 401 must preserve the last good cache.' );
$failure = 429;
versedb_refresh();
versedb_test_assert( get_option( 'versedb_next_request' ) >= time() + 290, 'Retry-After must be honored.' );
$http_before = $http_calls;
versedb_refresh();
versedb_test_assert( $http_before === $http_calls, 'Backoff must prevent additional requests.' );
delete_option( 'versedb_next_request' );
$failure = 503;
versedb_refresh();
versedb_test_assert( false !== strpos( versedb_shortcode( 'profile', array() ), 'sample_reader' ), 'An outage must preserve cached content.' );
delete_option( 'versedb_next_request' );
$failure = 0;
$pro = false;
versedb_refresh();
wp_set_current_user( 0 );
versedb_test_assert( '' === versedb_shortcode( 'reading-stats', array( 'year' => 2026 ) ), 'A confirmed Pro lapse must remove public Pro output.' );
versedb_test_assert( '' === versedb_shortcode( 'reading-calendar', array( 'year' => 2026 ) ), 'A confirmed Pro lapse must remove the calendar.' );
versedb_test_assert( '' === do_blocks( '<!-- wp:versedb/reading-stats {"customLayout":true,"year":2026} --><!-- wp:versedb/field {"field":"read_count"} /--><!-- /wp:versedb/reading-stats -->' ), 'Pro lapse must also suppress custom templates.' );
$allowed = versedb_allowed_blocks( true );
versedb_test_assert( ! in_array( 'versedb/reading-stats', $allowed, true ), 'Pro blocks must be hidden from the free-account inserter.' );
$descriptor = versedb_descriptor( 'collection', array( 'sort_by' => 'price_paid', 'is_signed' => '1', 'grade_min' => '8', 'genre_id' => '12', 'creator_id' => '34', 'character_id' => '56', 'for_trade' => '0' ) );
versedb_test_assert( 'date_added' === $descriptor['params']['sort_by'] && ! array_intersect( array( 'is_signed', 'genre_id', 'creator_id', 'character_id' ), array_keys( $descriptor['params'] ) ) && '0' === $descriptor['params']['for_trade'], 'Private ordering and unavailable Pro filters must be removed.' );
$private_list = true;
$result = versedb_fetch_query( array( 'type' => 'list', 'params' => array( 'id' => 9 ) ), get_option( 'versedb_account' ) );
versedb_test_assert( array() === $result, 'A list becoming private must not be publishable.' );

$enabled_before = versedb_enabled_blocks();
versedb_test_assert( 11 === count( $enabled_before ), 'All blocks must be enabled by default.' );
versedb_test_assert( array( 'profile' ) === versedb_sanitize_enabled_blocks( array( 'profile', 'unknown', 'profile', '', array( 'collection' ) ) ), 'Block choices must be allowlisted and deduplicated.' );
versedb_store( 'versedb_enabled_blocks', array( 'profile' ) );
foreach ( array_keys( versedb_block_catalog() ) as $type ) {
	unregister_block_type( 'versedb/' . $type );
}
versedb_register_blocks();
$registered = WP_Block_Type_Registry::get_instance();
versedb_test_assert( $registered->is_registered( 'versedb/profile' ) && ! $registered->is_registered( 'versedb/collection' ), 'Only enabled blocks may register editor assets.' );
versedb_test_assert( '' === do_blocks( '<!-- wp:versedb/collection {"customLayout":true} --><!-- wp:paragraph --><p>Disabled template text</p><!-- /wp:paragraph --><!-- /wp:versedb/collection -->' ), 'Disabled custom blocks must suppress saved static child content as well.' );
$queries_before = get_option( 'versedb_queries' );
versedb_test_assert( '' === do_shortcode( '[versedb_collection]' ) && '' === do_blocks( '<!-- wp:versedb/collection /-->' ), 'Disabled blocks and shortcodes must render nothing.' );
versedb_request_data( 'collection', array( 'limit' => 73 ) );
versedb_test_assert( $queries_before === get_option( 'versedb_queries' ), 'Disabled displays must not queue new queries.' );
$failure = 0;
delete_option( 'versedb_next_request' );
foreach ( $queries_before as $key => $query ) {
	delete_option( 'versedb_data_' . $key );
}
$http_before = $http_calls;
versedb_refresh();
versedb_test_assert( $http_calls === $http_before + 1, 'Profile-only refresh must make just the account request, skipping disabled display queries.' );
versedb_store( 'versedb_enabled_blocks', array() );
unregister_block_type( 'versedb/profile' );
versedb_register_blocks();
versedb_test_assert( ! $registered->is_registered( 'versedb/profile' ) && '' === do_shortcode( '[versedb_profile]' ), 'Disabling all must remain empty rather than reverting to defaults.' );
versedb_store( 'versedb_enabled_blocks', $enabled_before );
versedb_register_blocks();
versedb_test_assert( $registered->is_registered( 'versedb/collection' ) && false !== strpos( versedb_shortcode( 'profile', array() ), 'sample_reader' ), 'Re-enabling must restore registration and saved displays.' );

$cache_connection = get_option( 'versedb_connection' );
$cache_preferences = get_option( 'versedb_preferences' );
$cache_account = get_option( 'versedb_account' );
$cache_queries = get_option( 'versedb_queries' );
$cache_lock = versedb_acquire_lock();
versedb_test_assert( false === versedb_clear_cache() && get_option( 'versedb_profile_cache' ), 'Clear cache must refuse while another refresh holds the lock.' );
versedb_release_lock( $cache_lock );
$retry_at = time() + 600;
versedb_store( 'versedb_next_request', $retry_at );
wp_clear_scheduled_hook( 'versedb_refresh' );
versedb_test_assert( true === versedb_clear_cache(), 'Clear cache must complete when unlocked.' );
versedb_test_assert( $cache_connection === get_option( 'versedb_connection' ) && $cache_account === get_option( 'versedb_account' ) && $cache_preferences === get_option( 'versedb_preferences' ), 'Clearing must preserve credentials, account eligibility and preferences.' );
versedb_test_assert( $cache_queries === get_option( 'versedb_queries' ) && ! get_option( 'versedb_profile_cache' ) && ! get_option( 'versedb_account_updated' ), 'Clearing must retain active query definitions and remove profile data and freshness timestamps.' );
foreach ( $cache_queries as $key => $query ) {
	versedb_test_assert( false === get_option( 'versedb_data_' . $key ), 'Clear cache must delete each cached display.' );
}
versedb_test_assert( $retry_at === get_option( 'versedb_next_request' ) && wp_next_scheduled( 'versedb_refresh' ) >= $retry_at, 'Clearing must respect API backoff when scheduling fresh data.' );
delete_option( 'versedb_next_request' );
versedb_refresh();
versedb_test_assert( get_option( 'versedb_account_updated' ) && get_option( 'versedb_profile_cache' ), 'Successful refresh must rebuild profile cache and record its time.' );

$keys = array_keys( get_option( 'versedb_queries' ) );
versedb_disconnect();
versedb_test_assert( '' === versedb_token() && ! get_option( 'versedb_profile_cache' ), 'Disconnect must clear credentials and profile data.' );
foreach ( $keys as $key ) {
	versedb_test_assert( false === get_option( 'versedb_data_' . $key ), 'Disconnect must clear display cache.' );
}
versedb_test_assert( ! wp_next_scheduled( 'versedb_refresh' ) && ! wp_next_scheduled( 'versedb_hourly' ), 'Disconnect must clear scheduled work.' );
versedb_test_assert( true === versedb_accept_token( $token ), 'The plugin must reconnect after disconnect.' );
versedb_shortcode( 'collection', array() );
versedb_refresh();
versedb_store( 'versedb_preferences', array( 'credit' => true ) );
versedb_acquire_lock();
uninstall_plugin( 'versedb/versedb.php' );
$remaining = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'versedb_' ) . '%' ) );
versedb_test_assert( array() === $remaining && ! wp_next_scheduled( 'versedb_hourly' ), 'Uninstall must remove all plugin settings, credentials, caches, locks and scheduled work.' );
remove_filter( 'pre_http_request', $filter, 10 );
echo 'All integration checks passed. WordPress ' . get_bloginfo( 'version' ) . ', PHP ' . PHP_VERSION . ". Synthetic HTTP responses.\n";
