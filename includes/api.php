<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function versedb_text( $value ) {
	return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
}

function versedb_int( $value, $default = 0, $min = 0, $max = 1000000000 ) {
	return is_numeric( $value ) ? max( $min, min( $max, (int) $value ) ) : $default;
}

function versedb_safe_url( $value ) {
	return is_string( $value ) ? esc_url_raw( $value, array( 'https' ) ) : '';
}

function versedb_profile_projection( $raw ) {
	$profile = array();
	foreach ( array( 'username', 'bio', 'level_name' ) as $field ) {
		$profile[ $field ] = isset( $raw[ $field ] ) && is_scalar( $raw[ $field ] ) ? ( 'bio' === $field ? sanitize_textarea_field( (string) $raw[ $field ] ) : versedb_text( $raw[ $field ] ) ) : '';
	}
	foreach ( array( 'level', 'xp', 'contributions_count' ) as $field ) {
		$profile[ $field ] = versedb_int( isset( $raw[ $field ] ) ? $raw[ $field ] : 0 );
	}
	$profile['avatar'] = versedb_safe_url( isset( $raw['avatar'] ) ? $raw['avatar'] : '' );
	$profile['banner_url'] = versedb_safe_url( isset( $raw['banner_url'] ) ? $raw['banner_url'] : '' );
	return $profile;
}

function versedb_entity_projection( $raw, $type = 'issues' ) {
	if ( ! is_array( $raw ) || empty( $raw['id'] ) || ! is_string( $type ) ) {
		return array();
	}
	$paths = array( 'issues' => 'issue', 'series' => 'series', 'characters' => 'character', 'creators' => 'creator', 'teams' => 'teams', 'story_arcs' => 'story-arc', 'lists' => 'list' );
	if ( ! isset( $paths[ $type ] ) ) {
		return array();
	}
	if ( 'lists' === $type ) {
		$profile = get_option( 'versedb_profile_cache', array() );
		$owner = isset( $raw['user']['username'] ) ? versedb_text( $raw['user']['username'] ) : ( isset( $profile['username'] ) ? $profile['username'] : '' );
		$paths['lists'] = 'list/' . rawurlencode( strtolower( $owner ) );
	}
	$image = isset( $raw['image_url'] ) ? $raw['image_url'] : ( isset( $raw['images']['cover_md'] ) ? $raw['images']['cover_md'] : ( isset( $raw['cover_url'] ) ? $raw['cover_url'] : '' ) );
	$name = versedb_text( isset( $raw['name'] ) ? $raw['name'] : ( isset( $raw['title'] ) ? $raw['title'] : '' ) );
	$series = 'series' === $type ? $raw : ( isset( $raw['series'] ) && is_array( $raw['series'] ) ? $raw['series'] : array() );
	$series_name = versedb_text( isset( $series['name'] ) ? $series['name'] : '' );
	$series_year = versedb_int( isset( $series['start_year'] ) ? $series['start_year'] : 0, 0, 0, 9999 );
	$series_label = $series_name . ( $series_name && $series_year ? ' (' . $series_year . ')' : '' );
	$issue_number = 'issues' === $type ? versedb_text( isset( $raw['issue_number'] ) ? $raw['issue_number'] : '' ) : '';
	$issue_title = $name;
	if ( $series_label ) {
		$name = $series_label . ( '' !== $issue_number ? ' #' . $issue_number : '' );
	}
	if ( '' === $name ) {
		$name = __( 'Untitled item', 'versedb' );
	}
	return array(
		'id' => versedb_int( $raw['id'] ),
		'name' => $name,
		'series_name' => $series_label,
		'issue_number' => $issue_number,
		'issue_title' => $issue_title,
		'series_url' => ! empty( $series['id'] ) ? 'https://versedb.com/series/' . versedb_int( $series['id'] ) . '/' . rawurlencode( isset( $series['slug'] ) ? versedb_text( $series['slug'] ) : '' ) : '',
		'url' => 'https://versedb.com/' . $paths[ $type ] . '/' . versedb_int( $raw['id'] ) . '/' . rawurlencode( isset( $raw['slug'] ) ? versedb_text( $raw['slug'] ) : '' ),
		'image' => versedb_safe_url( $image ),
		'is_nsfw' => ! empty( $raw['is_nsfw'] ),
		'publisher' => versedb_text( isset( $raw['publisher'] ) && is_scalar( $raw['publisher'] ) ? $raw['publisher'] : ( isset( $raw['publisher_name'] ) ? $raw['publisher_name'] : ( isset( $raw['series']['publisher_name'] ) ? $raw['series']['publisher_name'] : '' ) ) ),
		'date' => versedb_text( isset( $raw['release_date'] ) ? $raw['release_date'] : '' ),
	);
}

function versedb_cache_has_series_fields( $cache ) {
	return empty( $cache['data'][0] ) || array_key_exists( 'series_name', $cache['data'][0] );
}

function versedb_descriptor( $type, $attributes ) {
	if ( 'reading-calendar' === $type ) {
		$type = 'reading-stats';
	}
	$params = array();
	if ( 'collection' === $type ) {
		foreach ( array( 'status', 'format', 'condition', 'graded', 'for_sale', 'for_trade', 'publisher_id', 'series_id', 'read_status', 'review', 'search', 'sort_by', 'sort_order' ) as $field ) {
			if ( isset( $attributes[ $field ] ) && '' !== $attributes[ $field ] ) {
				$params[ $field ] = substr( versedb_text( $attributes[ $field ] ), 0, 100 );
			}
		}
		$account = get_option( 'versedb_account', array() );
		if ( isset( $params['sort_by'] ) && ! in_array( $params['sort_by'], array( 'date_added', 'title', 'release_date' ), true ) ) {
			$params['sort_by'] = 'date_added';
		}
		if ( ! empty( $account['is_pro'] ) ) {
			foreach ( array( 'is_signed', 'grade_min', 'grade_max', 'grading_company', 'genre_id', 'creator_id', 'character_id' ) as $field ) {
				if ( isset( $attributes[ $field ] ) && '' !== $attributes[ $field ] ) {
					$params[ $field ] = substr( versedb_text( $attributes[ $field ] ), 0, 50 );
				}
			}
		}
	}
	if ( in_array( $type, array( 'reading-goal', 'reading-stats', 'reading-calendar' ), true ) ) {
		$params['year'] = versedb_int( isset( $attributes['year'] ) ? $attributes['year'] : 0, 0, 0, (int) gmdate( 'Y' ) );
		if ( ! $params['year'] ) {
			$params['year'] = (int) gmdate( 'Y' );
		}
		$params['year'] = max( 1900, $params['year'] );
	}
	if ( 'list' === $type ) {
		$params['id'] = versedb_int( isset( $attributes['listId'] ) ? $attributes['listId'] : 0 );
	}
	ksort( $params );
	return array( 'type' => $type, 'params' => $params );
}

function versedb_api( $path, $params = array(), $token = null, $method = 'GET' ) {
	if ( null === $token ) {
		$token = versedb_token();
	}
	$url = 'https://versedb.com/api/v1/' . ltrim( $path, '/' );
	$args = array( 'method' => $method, 'timeout' => 6, 'redirection' => 0, 'limit_response_size' => 2097152, 'headers' => array( 'Accept' => 'application/json' ) );
	if ( $token ) {
		$args['headers']['Authorization'] = 'Bearer ' . $token;
	}
	if ( 'GET' === $method ) {
		$url = add_query_arg( $params, $url );
	} else {
		$args['body'] = $params;
	}
	$response = wp_safe_remote_request( $url, $args );
	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'unavailable', __( 'VerseDB could not be reached. Cached content is still available.', 'versedb' ) );
	}
	$status = wp_remote_retrieve_response_code( $response );
	if ( 200 !== $status ) {
		$retry = wp_remote_retrieve_header( $response, 'retry-after' );
		$wait = is_numeric( $retry ) ? (int) $retry : ( is_string( $retry ) ? max( 0, (int) strtotime( $retry ) - time() ) : 0 );
		return new WP_Error( 'http_' . $status, __( 'VerseDB could not refresh this connection.', 'versedb' ), array( 'status' => $status, 'retry' => max( 60, min( DAY_IN_SECONDS, $wait ) ) ) );
	}
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	return is_array( $data ) && isset( $data['data'] ) && is_array( $data['data'] ) ? $data : ( 'POST' === $method && is_array( $data ) ? $data : new WP_Error( 'invalid_response', __( 'VerseDB returned an unexpected response.', 'versedb' ) ) );
}

function versedb_project_data( $type, $raw, $account ) {
	if ( in_array( $type, array( 'reading-goal', 'reading-stats', 'reading-calendar' ), true ) ) {
		$result = array();
		$progress = 'reading-goal' === $type ? $raw : ( isset( $raw['progress'] ) ? $raw['progress'] : array() );
		foreach ( array( 'year', 'target', 'read_count', 'percent', 'remaining' ) as $field ) {
			$result[ $field ] = versedb_int( isset( $progress[ $field ] ) ? $progress[ $field ] : 0 );
		}
		if ( 'reading-goal' !== $type ) {
			foreach ( array( 'active_days', 'longest_streak', 'current_streak', 'this_week', 'this_month' ) as $field ) {
				$result[ $field ] = versedb_int( isset( $raw[ $field ] ) ? $raw[ $field ] : 0 );
			}
			$result['days'] = array();
			foreach ( isset( $raw['days'] ) && is_array( $raw['days'] ) ? array_slice( $raw['days'], 0, 366 ) : array() as $day ) {
				if ( is_array( $day ) && isset( $day['date'] ) && is_string( $day['date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day['date'] ) ) {
					$result['days'][] = array( 'date' => $day['date'], 'count' => versedb_int( isset( $day['count'] ) ? $day['count'] : 0 ) );
				}
			}
		}
		return $result;
	}
	$items = array();
	foreach ( array_slice( $raw, 0, 100 ) as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		if ( 'collection' === $type && ( ! isset( $row['is_public'] ) || true !== $row['is_public'] ) ) {
			continue;
		}
		if ( 'lists' === $type ) {
			if ( ! array_key_exists( 'is_private', $row ) || false !== $row['is_private'] || 'published' !== ( isset( $row['status'] ) ? $row['status'] : '' ) || empty( $row['user']['id'] ) || (int) $row['user']['id'] !== (int) $account['id'] ) {
				continue;
			}
			$item = versedb_entity_projection( $row, 'lists' );
		} elseif ( 'pull-list' === $type ) {
			$entity = isset( $row['series'] ) ? $row['series'] : ( isset( $row['pullable'] ) ? $row['pullable'] : array() );
			$item = versedb_entity_projection( $entity, 'series' );
			if ( ! empty( $row['next_issue']['release_date'] ) ) {
				$item['date'] = versedb_text( $row['next_issue']['release_date'] );
			}
		} else {
			$field = 'collection' === $type ? 'collectable' : ( 'currently-reading' === $type ? 'issue' : ( 'reading' === $type ? 'readable' : 'entity' ) );
			$item = versedb_entity_projection( isset( $row[ $field ] ) ? $row[ $field ] : array(), isset( $row['entity_type'] ) ? $row['entity_type'] : 'issues' );
			if ( ! empty( $row['variant']['cover_image_url'] ) ) {
				$item['image'] = versedb_safe_url( $row['variant']['cover_image_url'] );
			}
		}
		if ( 'currently-reading' === $type ) {
			$item['percent'] = versedb_int( isset( $row['percent'] ) ? $row['percent'] : 0, 0, 0, 100 );
		}
		if ( ! empty( $item['id'] ) ) {
			$items[] = $item;
		}
	}
	return $items;
}

function versedb_fetch_public_lists( $account ) {
	$connection = get_option( 'versedb_connection' );
	$generation = isset( $connection['generation'] ) ? $connection['generation'] : '';
	$batch = get_option( 'versedb_lists_pending', array() );
	if ( empty( $batch ) || $batch['generation'] !== $generation || $batch['account'] !== $account['id'] ) {
		$response = versedb_api( 'users/' . $account['id'] . '/lists', array( 'limit' => 100 ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$ids = array();
		foreach ( array_slice( $response['data'], 0, 100 ) as $row ) {
			if ( is_array( $row ) && isset( $row['is_private'], $row['user']['id'], $row['id'] ) && false === $row['is_private'] && (int) $row['user']['id'] === (int) $account['id'] && versedb_int( $row['id'] ) ) {
				$ids[] = versedb_int( $row['id'] );
			}
		}
		$batch = array( 'generation' => $generation, 'account' => $account['id'], 'ids' => array_values( array_unique( $ids ) ), 'data' => array() );
	}
	// List cards omit status, so verify publication through details in bounded cron batches.
	foreach ( array_slice( $batch['ids'], 0, 3 ) as $id ) {
		$response = versedb_api( 'lists/' . $id );
		if ( is_wp_error( $response ) && ! in_array( $response->get_error_code(), array( 'http_403', 'http_404' ), true ) ) {
			versedb_store( 'versedb_lists_pending', $batch );
			return $response;
		}
		if ( ! is_wp_error( $response ) && isset( $response['data']['id'] ) && $id === versedb_int( $response['data']['id'] ) ) {
			$batch['data'] = array_merge( $batch['data'], versedb_project_data( 'lists', array( $response['data'] ), $account ) );
		}
		array_shift( $batch['ids'] );
	}
	if ( $batch['ids'] ) {
		versedb_store( 'versedb_lists_pending', $batch );
	} else {
		delete_option( 'versedb_lists_pending' );
	}
	return $batch['data'];
}

function versedb_fetch_query( $query, $account ) {
	$type = $query['type'];
	$params = $query['params'];
	if ( 'lists' === $type ) {
		return versedb_fetch_public_lists( $account );
	}
	$paths = array( 'collection' => 'user/collections', 'wishlist' => 'user/wishlist', 'pull-list' => 'user/pull-list', 'reading' => 'user/read-status', 'currently-reading' => 'user/reading/in-progress', 'reading-goal' => 'user/reading-goal', 'reading-stats' => 'user/reading-stats/year', 'reading-calendar' => 'user/reading-stats/year' );
	if ( 'list' === $type ) {
		if ( empty( $params['id'] ) ) {
			return array();
		}
		$response = versedb_api( 'lists/' . $params['id'] );
		if ( is_wp_error( $response ) ) {
			return in_array( $response->get_error_code(), array( 'http_403', 'http_404' ), true ) ? array() : $response;
		}
		$list = $response['data'];
		if ( ! isset( $list['is_private'] ) || false !== $list['is_private'] || 'published' !== ( isset( $list['status'] ) ? $list['status'] : '' ) || empty( $list['user']['id'] ) || (int) $list['user']['id'] !== (int) $account['id'] ) {
			return array();
		}
		return versedb_project_data( 'list', isset( $list['items'] ) ? $list['items'] : array(), $account );
	}
	if ( ! isset( $paths[ $type ] ) ) {
		return array();
	}
	if ( in_array( $type, array( 'reading-stats', 'reading-calendar' ), true ) && empty( $account['is_pro'] ) ) {
		return array();
	}
	if ( ! in_array( $type, array( 'reading-goal', 'reading-stats', 'reading-calendar' ), true ) ) {
		$params['per_page'] = 100;
		$params['limit'] = 'currently-reading' === $type ? 50 : 100;
	}
	if ( empty( $account['is_pro'] ) ) {
		foreach ( array( 'is_signed', 'grade_min', 'grade_max', 'grading_company', 'genre_id', 'creator_id', 'character_id' ) as $field ) {
			unset( $params[ $field ] );
		}
	}
	$response = versedb_api( $paths[ $type ], $params );
	return is_wp_error( $response ) ? $response : versedb_project_data( $type, $response['data'], $account );
}

function versedb_handle_error( $error ) {
	$data = $error->get_error_data();
	$status = is_array( $data ) && isset( $data['status'] ) ? $data['status'] : 0;
	if ( 401 === $status ) {
		versedb_store( 'versedb_status', 'reconnect' );
		return;
	}
	$attempt = min( 6, (int) get_option( 'versedb_retry_count', 0 ) + 1 );
	$delay = 429 === $status ? $data['retry'] : min( HOUR_IN_SECONDS, 60 * pow( 2, $attempt ) );
	versedb_store( 'versedb_status', 'unavailable' );
	versedb_store( 'versedb_retry_count', $attempt );
	versedb_store( 'versedb_next_request', time() + $delay );
	versedb_queue_refresh( $delay );
}

function versedb_refresh() {
	if ( (int) get_option( 'versedb_next_request', 0 ) > time() || ! get_option( 'versedb_connection' ) ) {
		return;
	}
	$lock = versedb_acquire_lock();
	if ( ! $lock ) {
		versedb_queue_refresh( 60 );
		return;
	}
	try {
		$connection = get_option( 'versedb_connection' );
		$token = versedb_token();
		if ( ! $token ) {
			versedb_store( 'versedb_status', 'reconnect' );
			return;
		}
		$response = versedb_api( 'user', array(), $token );
		if ( is_wp_error( $response ) ) {
			versedb_handle_error( $response );
			return;
		}
		$user = $response['data'];
		if ( empty( $user['id'] ) || ! versedb_int( $user['id'] ) || empty( $user['username'] ) || ! is_string( $user['username'] ) || ! isset( $user['is_pro'] ) || ! is_bool( $user['is_pro'] ) ) {
			versedb_handle_error( new WP_Error( 'invalid_response', 'Invalid account.' ) );
			return;
		}
		if ( get_option( 'versedb_connection' ) !== $connection ) {
			return;
		}
		$account = array( 'id' => versedb_int( $user['id'] ), 'is_pro' => (bool) $user['is_pro'] );
		versedb_store( 'versedb_account', $account );
		versedb_store( 'versedb_profile_cache', versedb_profile_projection( $user ) );
		versedb_store( 'versedb_account_updated', time() );
		set_transient( 'versedb_account_fresh', 1, HOUR_IN_SECONDS );
		$count = 0;
		$pending = false;
		$query_errors = get_option( 'versedb_query_errors', array() );
		foreach ( get_option( 'versedb_queries', array() ) as $key => $query ) {
			if ( ! versedb_block_enabled( $query['type'] ) ) {
				continue;
			}
			$cached = get_option( 'versedb_data_' . $key, array() );
			if ( versedb_cache_has_series_fields( $cached ) && ! empty( $cached['updated'] ) && $cached['updated'] > time() - HOUR_IN_SECONDS && ( 'lists' !== $query['type'] || ! empty( $cached['lists_verified'] ) ) ) {
				continue;
			}
			if ( $count >= 3 ) {
				$pending = true;
				break;
			}
			$result = versedb_fetch_query( $query, $account );
			++$count;
			if ( get_option( 'versedb_connection' ) !== $connection ) {
				return;
			}
			if ( is_wp_error( $result ) ) {
				if ( 'http_403' === $result->get_error_code() ) {
					$query_errors[ $key ] = true;
					versedb_store( 'versedb_query_errors', $query_errors );
					versedb_store( 'versedb_data_' . $key, array( 'updated' => time(), 'data' => array(), 'lists_verified' => 'lists' === $query['type'] ) );
					continue;
				}
				versedb_handle_error( $result );
				return;
			}
			unset( $query_errors[ $key ] );
			versedb_store( 'versedb_query_errors', $query_errors );
			$cache = array( 'updated' => time(), 'data' => $result );
			if ( 'lists' === $query['type'] ) {
				$cache['lists_verified'] = true;
				if ( get_option( 'versedb_lists_pending' ) ) {
					$cache['updated'] = 0;
					$pending = true;
				}
			}
			versedb_store( 'versedb_data_' . $key, $cache );
		}
		delete_option( 'versedb_next_request' );
		delete_option( 'versedb_retry_count' );
		versedb_store( 'versedb_status', $query_errors ? 'partial' : 'connected' );
		if ( $pending ) {
			versedb_queue_refresh( 10 );
		}
	} finally {
		versedb_release_lock( $lock );
	}
}
add_action( 'versedb_refresh', 'versedb_refresh' );
add_action( 'versedb_hourly', 'versedb_refresh' );
