<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$oembed = _wp_oembed_get_object();
$urls = array(
	'https://versedb.com/issue/18/sample-issue',
	'https://versedb.com/series/19/former-slug/',
	'https://versedb.com/title/20',
	'https://versedb.com/event/21/sample-event#details',
	'https://versedb.com/creator/22/sample-creator',
);
foreach ( $urls as $url ) {
	versedb_test_assert( 'https://versedb.com/oembed' === $oembed->get_provider( $url, array( 'discover' => false ) ), 'Public VerseDB links must use the registered provider without discovery.' );
}
foreach ( array(
	'https://versedb.com.evil.example/issue/18/sample',
	'https://versedb.com@evil.example/issue/18/sample',
	'https://evil.example/issue/18/sample',
	'https://versedb.com/issue/0/sample',
	'https://versedb.com/issue/18/sample/edit',
	'https://versedb.com/series/19/sample?source=blog',
	'https://versedb.com/series/19/edit',
	'https://versedb.com/character/20',
	'https://versedb.com/list/sample_reader/9/sample-list',
	'https://versedb.com/user/sample_reader',
	'https://versedb.com/my/collection',
	'https://versedb.com/shop/18/sample',
) as $url ) {
	versedb_test_assert( false === $oembed->get_provider( $url, array( 'discover' => false ) ), 'Unrelated hosts, private routes and unsupported pages must not use the VerseDB provider.' );
}

$embed_requests = array();
$embed_status = 200;
$embed_failure = '';
$embed_filter = function ( $pre, $args, $url ) use ( &$embed_requests, &$embed_status, &$embed_failure ) {
	if ( 'https://versedb.com/oembed' !== strtok( $url, '?' ) ) {
		return $pre;
	}
	$embed_requests[] = array( 'url' => $url, 'args' => $args );
	if ( 'network' === $embed_failure ) {
		return new WP_Error( 'http_request_failed', 'Timed out.' );
	}
	if ( 'malformed' === $embed_failure ) {
		return array( 'headers' => array(), 'body' => '{invalid', 'response' => array( 'code' => 200 ), 'cookies' => array() );
	}
	return array(
		'headers' => array( 'content-type' => 'application/json' ),
		'body' => wp_json_encode( 200 === $embed_status ? array( 'version' => '1.0', 'type' => 'rich', 'provider_name' => 'VerseDB', 'provider_url' => 'https://versedb.com', 'title' => 'Sample issue', 'width' => 480, 'height' => 320, 'html' => '<iframe title="Sample issue" src="https://versedb.com/embed/issue/18" width="480" height="320"></iframe>' ) : array( 'message' => 'Not Found' ) ),
		'response' => array( 'code' => $embed_status, 'message' => 200 === $embed_status ? 'OK' : 'Not Found' ),
		'cookies' => array(),
	);
};
add_filter( 'pre_http_request', $embed_filter, 20, 3 );
$card = wp_oembed_get( $urls[0], array( 'width' => 480, 'discover' => false ) );
versedb_test_assert( false !== strpos( (string) $card, 'https://versedb.com/embed/issue/18' ), 'WordPress must render a card returned by the provider.' );
parse_str( wp_parse_url( $embed_requests[0]['url'], PHP_URL_QUERY ), $embed_query );
versedb_test_assert( $urls[0] === $embed_query['url'] && 'json' === $embed_query['format'] && '480' === $embed_query['maxwidth'], 'WordPress must send the public link, format and requested width to the provider.' );
$proxy = new WP_REST_Request( 'GET', '/oembed/1.0/proxy' );
$proxy->set_param( 'url', $urls[0] );
$proxy->set_param( 'discover', false );
$proxy_response = rest_do_request( $proxy );
$proxy_data = (array) $proxy_response->get_data();
versedb_test_assert( 200 === $proxy_response->get_status() && false !== strpos( $proxy_data['html'], '/embed/issue/18' ), 'The editor oEmbed proxy must return the public card.' );
$previous_user = get_current_user_id();
wp_set_current_user( 0 );
$requests_before = count( $embed_requests );
$denied = rest_do_request( $proxy );
versedb_test_assert( 401 === $denied->get_status() && $requests_before === count( $embed_requests ), 'Anonymous visitors must not access the editor oEmbed proxy.' );
wp_set_current_user( $previous_user );
$connection_before_embed = get_option( 'versedb_connection' );
delete_option( 'versedb_connection' );
versedb_test_assert( false !== strpos( (string) wp_oembed_get( $urls[0], array( 'discover' => false ) ), '/embed/issue/18' ), 'Public link cards must work without a connected account.' );
versedb_store( 'versedb_connection', $connection_before_embed );
global $wp_embed;
$embed_post = wp_insert_post( array( 'post_title' => 'Embed test', 'post_content' => $urls[0], 'post_status' => 'publish' ) );
$previous_post_id = $wp_embed->post_ID;
$wp_embed->post_ID = $embed_post;
$autoembed = $wp_embed->autoembed( $urls[0] );
versedb_test_assert( false !== strpos( $autoembed, '/embed/issue/18' ), 'A standalone public link must auto-embed in classic content.' );
$embed_block = '<!-- wp:embed {"url":"' . $urls[0] . '","type":"rich","providerNameSlug":"versedb"} --><figure class="wp-block-embed"><div class="wp-block-embed__wrapper">' . "\n" . $urls[0] . "\n" . '</div></figure><!-- /wp:embed -->';
versedb_test_assert( false !== strpos( apply_filters( 'the_content', $embed_block ), '/embed/issue/18' ), 'Saved core Embed block markup must render the provider card.' );
$requests_before = count( $embed_requests );
$wp_embed->autoembed( $urls[0] );
versedb_test_assert( $requests_before === count( $embed_requests ), 'WordPress must reuse its cached card instead of requesting it on every render.' );
$wp_embed->post_ID = $previous_post_id;
wp_delete_post( $embed_post, true );
foreach ( $embed_requests as $request ) {
	versedb_test_assert( false === stripos( wp_json_encode( $request['args']['headers'] ), 'authorization' ), 'oEmbed must not send the connected account token.' );
}
$embed_status = 404;
versedb_test_assert( false === wp_oembed_get( $urls[0], array( 'discover' => false ) ), 'Unavailable or private cards must fail without rendering provider HTML.' );
$fallback_url = 'https://versedb.com/issue/21/unavailable';
$fallback = $wp_embed->autoembed( $fallback_url );
versedb_test_assert( false !== strpos( $fallback, $fallback_url ) && false === strpos( $fallback, '<iframe' ), 'An unavailable card must leave a usable public link.' );
$embed_status = 200;
foreach ( array( 'network', 'malformed' ) as $embed_failure ) {
	versedb_test_assert( false === wp_oembed_get( $urls[0], array( 'discover' => false ) ), 'Network failures and malformed provider responses must not render a card.' );
}
remove_filter( 'pre_http_request', $embed_filter, 20 );
