<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
function versedb_test_response( $data, $status = 200, $headers = array() ) {
	return array( 'headers' => $headers, 'body' => wp_json_encode( $data ), 'response' => array( 'code' => $status, 'message' => '' ), 'cookies' => array() );
}

$http_calls = 0;
$failure = 0;
$pro = true;
$private_list = false;
$grant_valid = true;
$grant_expired = false;
$exchange_request = array();
$fixture_user = array( 'id' => 123, 'username' => 'sample_reader', 'bio' => 'A comic reader.', 'avatar' => 'https://placehold.co/160x160/png?text=Reader', 'is_pro' => true, 'level' => 4, 'xp' => 120, 'contributions_count' => 7, 'email' => 'private-email-marker', 'birth_date' => 'private-birth-marker', 'formatted_location' => 'private-location-marker' );
$fixture_issue = array( 'id' => 17, 'slug' => 'sample-comic', 'name' => 'Sample comic <script>alert(1)</script>', 'cover_url' => 'https://placehold.co/300x450/png?text=Sample+Comic', 'image_url' => 'https://placehold.co/300x450/png?text=Sample+Comic', 'is_nsfw' => true, 'publisher' => 'Sample publisher', 'release_date' => '2026-09-01' );
$fixture_issue['series'] = array( 'id' => 31, 'name' => 'Absolute Green Arrow', 'start_year' => 2026, 'slug' => 'absolute-green-arrow', 'publisher_name' => 'Sample publisher' );
$fixture_issue['issue_number'] = '1';
$fixture_goal = array( 'year' => 2026, 'target' => 24, 'read_count' => 12, 'percent' => 50, 'remaining' => 12, 'email_updates' => 'private-email-updates-marker' );
$fixture_stats = array( 'progress' => $fixture_goal, 'active_days' => 10, 'longest_streak' => 4, 'current_streak' => 2, 'this_week' => 3, 'this_month' => 6, 'days' => array( array( 'date' => '2026-09-01', 'count' => 2 ) ) );
$fixture_stats['days'] = array();
for ( $day = 0; $day < 365; ++$day ) {
	$fixture_stats['days'][] = array( 'date' => gmdate( 'Y-m-d', strtotime( '2026-01-01' ) + $day * DAY_IN_SECONDS ), 'count' => 0 === $day % 11 ? 1 + $day % 4 : 0 );
}
$filter = function ( $pre, $args, $url ) use ( &$http_calls, &$failure, &$pro, &$private_list, &$grant_valid, &$grant_expired, &$exchange_request, $fixture_user, $fixture_issue, $fixture_goal, $fixture_stats ) {
	if ( false === strpos( $url, 'https://versedb.com/api/v1/' ) ) {
		return $pre;
	}
	++$http_calls;
	if ( $failure ) {
		return versedb_test_response( array( 'message' => 'failure' ), $failure, array( 'retry-after' => '300' ) );
	}
	$path = wp_parse_url( $url, PHP_URL_PATH );
	if ( '/api/v1/connect/token' === $path ) {
		$exchange_request = $args;
		return versedb_test_response( array( 'access_token' => str_repeat( 'sample_token_', 4 ), 'abilities' => $grant_valid ? array( 'read:public', 'read:showcase' ) : array( 'read:public' ), 'expires_at' => gmdate( 'c', time() + ( $grant_expired ? -DAY_IN_SECONDS : DAY_IN_SECONDS ) ) ) );
	}
	if ( '/api/v1/user' === $path ) {
		$user = $fixture_user;
		$user['is_pro'] = $pro;
		return versedb_test_response( array( 'data' => $user ) );
	}
	if ( '/api/v1/user/collections' === $path ) {
		$copies = array( array( 'id' => 1, 'is_public' => true, 'collectable' => $fixture_issue, 'price_paid' => 'private-price-marker', 'notes' => 'private-notes-marker', 'storage_location' => 'private-storage-marker', 'loans' => 'private-loans-marker', 'estimated_value' => 'private-value-marker' ), array( 'id' => 2, 'is_public' => false, 'collectable' => array_merge( $fixture_issue, array( 'name' => 'private-copy-marker' ) ) ), array( 'id' => 3, 'is_public' => 'false', 'collectable' => array_merge( $fixture_issue, array( 'name' => 'private-malformed-marker' ) ) ) );
		if ( defined( 'VERSEDB_TEST_PREVIEW' ) ) {
			foreach ( array( 'Moonlight', 'The Explorer', 'City Stories', 'Wild Horizons', 'Starlight' ) as $index => $name ) {
				$copies[] = array( 'is_public' => true, 'collectable' => array_merge( $fixture_issue, array( 'id' => 20 + $index, 'name' => $name . ' #1', 'is_nsfw' => false, 'image_url' => 'https://placehold.co/300x450/253447/ffffff/png?text=' . rawurlencode( $name ) ) ) );
			}
		}
		return versedb_test_response( array( 'data' => $copies ) );
	}
	if ( '/api/v1/user/wishlist' === $path ) {
		return versedb_test_response( array( 'data' => array( array( 'entity' => $fixture_issue, 'entity_type' => 'issues', 'note' => 'private-note-marker' ) ) ) );
	}
	if ( '/api/v1/user/pull-list' === $path ) {
		return versedb_test_response( array( 'data' => array( array( 'series' => array_merge( $fixture_issue, array( 'name' => 'Sample series' ) ), 'personal_notes' => 'private-pull-marker', 'next_issue' => $fixture_issue ) ) ) );
	}
	if ( '/api/v1/user/read-status' === $path ) {
		return versedb_test_response( array( 'data' => array( array( 'readable' => $fixture_issue ) ) ) );
	}
	if ( '/api/v1/user/reading/in-progress' === $path ) {
		return versedb_test_response( array( 'data' => array( array( 'issue' => $fixture_issue, 'percent' => 50, 'last_page' => 'private-resume-marker' ) ) ) );
	}
	if ( '/api/v1/user/reading-goal' === $path ) {
		return versedb_test_response( array( 'data' => $fixture_goal ) );
	}
	if ( '/api/v1/user/reading-stats/year' === $path ) {
		return versedb_test_response( array( 'data' => $fixture_stats ) );
	}
	$list = array( 'id' => 9, 'title' => 'Sample public list', 'slug' => 'sample-list', 'is_private' => $private_list, 'status' => 'published', 'user' => array( 'id' => 123 ), 'items' => array( array( 'entity_type' => 'issues', 'entity' => $fixture_issue, 'note' => 'private-list-note-marker' ) ) );
	if ( '/api/v1/users/123/lists' === $path ) {
		$card = $list;
		unset( $card['status'], $card['items'] );
		return versedb_test_response( array( 'data' => array( $card, array_merge( $card, array( 'id' => 10, 'title' => 'private-list-marker', 'is_private' => true ) ), array_merge( $card, array( 'id' => 11, 'title' => 'private-draft-marker' ) ) ) ) );
	}
	if ( '/api/v1/lists/9' === $path ) {
		return versedb_test_response( array( 'data' => $list ) );
	}
	if ( '/api/v1/lists/11' === $path ) {
		return versedb_test_response( array( 'data' => array_merge( $list, array( 'id' => 11, 'title' => 'private-draft-marker', 'status' => 'draft' ) ) ) );
	}
	throw new RuntimeException( 'Unexpected API route: ' . $path );
};
add_filter( 'pre_http_request', $filter, 10, 3 );
