<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$series_projection = versedb_entity_projection( $fixture_issue );
versedb_test_assert( 'Absolute Green Arrow (2026)' === $series_projection['series_name'] && 'Absolute Green Arrow (2026) #1' === $series_projection['name'], 'Series names must include the API start year independently of issue numbers.' );
$yearless = $fixture_issue;
unset( $yearless['series']['start_year'] );
versedb_test_assert( 'Absolute Green Arrow' === versedb_entity_projection( $yearless )['series_name'], 'Missing series years must not be invented from issue release dates.' );
$without_number = $fixture_issue;
unset( $without_number['issue_number'] );
versedb_test_assert( 'Absolute Green Arrow (2026)' === versedb_entity_projection( $without_number )['name'], 'The series must still display when the issue number is missing.' );
$http_before_template = $http_calls;
$template = '<!-- wp:versedb/collection {"customLayout":true,"enableLinks":false} --><!-- wp:group {"layout":{"type":"default"}} --><div class="wp-block-group"><!-- wp:versedb/field {"field":"series_name","style":{"color":{"text":"#b02030"},"typography":{"fontSize":"24px"},"spacing":{"padding":{"top":"8px"}}},"textAlign":"center"} /--><!-- wp:versedb/field {"field":"cover","imageWidth":150} /--></div><!-- /wp:group --><!-- /wp:versedb/collection -->';
$custom = do_blocks( $template );
versedb_test_assert( false !== strpos( $custom, 'Absolute Green Arrow (2026)' ) && false === strpos( wp_strip_all_tags( $custom ), '#1' ), 'A cover-and-series template must omit issue numbers and titles.' );
versedb_test_assert( false !== strpos( $custom, '#b02030' ) && false !== strpos( $custom, '24px' ) && false !== strpos( $custom, 'padding-top:8px' ) && false !== strpos( $custom, 'text-align:center' ), 'Nested fields must retain native color, typography, spacing and alignment styles.' );
versedb_test_assert( strpos( $custom, 'Absolute Green Arrow (2026)' ) < strpos( $custom, '<img' ) && false !== strpos( $custom, '--versedb-field-image-width:150px' ), 'Saved field order and image sizing must affect public rendering.' );
versedb_test_assert( false === strpos( $custom, '<a ' ) && false !== strpos( $custom, 'versedb-field__image--blurred' ), 'Custom templates must honor link and NSFW settings.' );
$swapped = str_replace( '"enableLinks":false', '"enableLinks":true,"showNsfw":true', $template );
$custom_links = do_blocks( $swapped );
versedb_test_assert( false !== strpos( $custom_links, 'https://versedb.com/series/31/absolute-green-arrow' ) && false === strpos( $custom_links, 'versedb-field__image--blurred' ), 'Series field links must use series URLs and NSFW override must work.' );
$collection_key = md5( wp_json_encode( versedb_descriptor( 'collection', WP_Block_Type_Registry::get_instance()->get_registered( 'versedb/collection' )->prepare_attributes_for_render( array() ) ) ) );
$collection_cache = get_option( 'versedb_data_' . $collection_key );
$two_items = $collection_cache;
$two_items['data'][] = array_merge( $two_items['data'][0], array( 'series_name' => 'Second series (2025)', 'name' => 'Second series (2025) #2', 'id' => 99 ) );
versedb_store( 'versedb_data_' . $collection_key, $two_items );
$repeated = do_blocks( $template );
versedb_test_assert( false !== strpos( $repeated, 'Second series (2025)' ) && 2 === substr_count( $repeated, '<img' ), 'The template must repeat with a separate record context for every comic.' );
versedb_store( 'versedb_data_' . $collection_key, $collection_cache );
$profile_template = '<!-- wp:versedb/profile {"customLayout":true,"enableLinks":false} --><!-- wp:versedb/field {"field":"bio"} /--><!-- wp:versedb/field {"field":"username"} /--><!-- /wp:versedb/profile -->';
$profile_output = do_blocks( $profile_template );
versedb_test_assert( strpos( $profile_output, 'A comic reader.' ) < strpos( $profile_output, 'sample_reader' ) && false === strpos( $profile_output, '<img' ), 'Profile fields must be independently removable and reorderable.' );
$orphan = do_blocks( '<!-- wp:versedb/field {"field":"username"} /-->' );
versedb_test_assert( '' === $orphan, 'An orphan field cannot access account data without a display context.' );
$private_field = str_replace( '"field":"series_name"', '"field":"notes"', $template );
versedb_test_assert( false === strpos( do_blocks( $private_field ), 'private-' ), 'Custom field names must never expose arbitrary cached properties.' );
foreach ( array( 'reading-goal', 'reading-stats', 'reading-calendar', 'lists', 'list', 'wishlist', 'pull-list', 'reading', 'currently-reading' ) as $template_type ) {
	$body = '';
	foreach ( versedb_template_fields( $template_type ) as $field ) {
		$body .= '<!-- wp:versedb/field {"field":"' . $field . '"} /-->';
	}
	$markup = do_blocks( '<!-- wp:versedb/' . $template_type . ' {"customLayout":true,"listId":9,"year":2026} -->' . $body . '<!-- /wp:versedb/' . $template_type . ' -->' );
	versedb_test_assert( false !== strpos( $markup, 'versedb-field' ) && false === strpos( $markup, 'private-' ), 'All display types must render safe custom templates: ' . $template_type );
}
versedb_test_assert( $http_before_template === $http_calls, 'Custom rendering must never synchronously contact VerseDB.' );
$rest_request = new WP_REST_Request( 'POST', '/versedb/v1/editor-preview' );
$rest_request->set_param( 'type', 'collection' );
$rest_request->set_param( 'attributes', array( 'enableLinks' => false ) );
wp_set_current_user( 0 );
$denied_preview = rest_get_server()->dispatch( $rest_request );
versedb_test_assert( 401 === $denied_preview->get_status(), 'Anonymous visitors must not access the editor preview endpoint.' );
wp_set_current_user( 1 );
$preview_response = rest_get_server()->dispatch( $rest_request );
$preview_json = wp_json_encode( $preview_response->get_data() );
versedb_test_assert( 200 === $preview_response->get_status() && false !== strpos( $preview_json, 'Absolute Green Arrow (2026)' ) && false === strpos( $preview_json, 'private-' ) && false === strpos( $preview_json, $token ), 'Editor previews must return only public display fields, never credentials or private payloads.' );
versedb_test_assert( $http_before_template === $http_calls, 'Editor preview reads must use cached data only.' );
$rest_request->set_param( 'type', 'unknown' );
versedb_test_assert( 400 === rest_get_server()->dispatch( $rest_request )->get_status(), 'Preview endpoints must allowlist display types.' );

$old_cache = $collection_cache;
foreach ( $old_cache['data'] as &$old_item ) {
	unset( $old_item['series_name'], $old_item['series_url'], $old_item['issue_number'], $old_item['issue_title'] );
}
unset( $old_item );
versedb_store( 'versedb_data_' . $collection_key, $old_cache );
wp_clear_scheduled_hook( 'versedb_refresh' );
$legacy_markup = versedb_shortcode( 'collection', array() );
versedb_test_assert( false !== strpos( $legacy_markup, 'Absolute Green Arrow' ) && wp_next_scheduled( 'versedb_refresh' ), 'Older cache schemas must keep displaying while scheduling a field refresh.' );
versedb_store( 'versedb_data_' . $collection_key, $collection_cache );

$unified_template = str_replace( '"customLayout":true', '"layoutVersion":1', $template );
versedb_test_assert( do_blocks( $unified_template ) === do_blocks( $template ), 'The unified layout must render fields without a custom-mode flag.' );
$empty_template = do_blocks( '<!-- wp:versedb/collection {"layoutVersion":1} /-->' );
versedb_test_assert( false === strpos( $empty_template, '<img' ) && false === strpos( $empty_template, 'Absolute Green Arrow' ), 'Removing all fields must not restore the standard template.' );

$rest_request->set_param( 'type', 'collection' );
versedb_store( 'versedb_data_' . $collection_key, $two_items );
$preview_data = rest_get_server()->dispatch( $rest_request )->get_data();
versedb_test_assert( 2 === count( $preview_data['items'] ) && false !== strpos( $preview_data['items'][1]['series_name'], 'Second series (2025)' ), 'The editor must preview every selected cached item with its own fields.' );
$rest_request->set_param( 'attributes', array( 'limit' => 1 ) );
$limited_preview = rest_get_server()->dispatch( $rest_request )->get_data();
versedb_test_assert( 1 === count( $limited_preview['items'] ), 'The preview must honor the selected item limit.' );
versedb_store( 'versedb_data_' . $collection_key, $collection_cache );

$heading_template = str_replace( '"customLayout":true', '"customLayout":true,"title":"Styled collection","headingStyle":{"fontSize":32,"fontWeight":"400","fontStyle":"italic","color":"#123456","textAlign":"right"}', $template );
$heading_output = do_blocks( $heading_template );
versedb_test_assert( false !== strpos( $heading_output, 'style="font-size:32px;font-weight:400;font-style:italic;color:#123456;text-align:right;">Styled collection</h2>' ), 'Heading styles must persist separately from item field styles.' );
$stat_output = do_blocks( '<!-- wp:versedb/reading-stats {"layoutVersion":1,"year":2026} --><!-- wp:versedb/field {"field":"read_count","style":{"typography":{"fontSize":"36px","fontWeight":"400"}},"labelStyle":{"fontSize":12,"color":"#123456","textTransform":"uppercase"}} /--><!-- /wp:versedb/reading-stats -->' );
versedb_test_assert( false !== strpos( $stat_output, '--versedb-label-font-size:12px;' ) && false !== strpos( $stat_output, '--versedb-label-color:#123456;' ) && false !== strpos( $stat_output, '36px' ) && false !== strpos( $stat_output, '--versedb-field-image-fit:cover; font-size:' ), 'Statistic labels and values must retain independent text styles.' );
$unsafe_style = versedb_text_style( array( 'fontFamily' => 'serif; background:url(https://example.com)', 'color' => 'red;position:fixed', 'fontSize' => array( 12 ), 'textAlign' => 'right;display:none', 'position' => 'fixed' ) );
versedb_test_assert( '' === $unsafe_style, 'Typography controls must reject arbitrary CSS, URLs and malformed attribute values.' );
$previous_preferences = get_option( 'versedb_preferences' );
update_option( 'versedb_preferences', array( 'credit' => true ) );
versedb_test_assert( false !== strpos( versedb_credit( array( 'fontSize' => 15, 'fontStyle' => 'italic' ) ), 'font-size:15px;font-style:italic;' ), 'Opt-in credit must support its own text style.' );
update_option( 'versedb_preferences', array( 'credit' => false ) );
versedb_test_assert( '' === versedb_credit( array( 'fontSize' => 15 ) ), 'Styling credit must not enable credit without consent.' );
if ( false === $previous_preferences ) {
	delete_option( 'versedb_preferences' );
} else {
	versedb_store( 'versedb_preferences', $previous_preferences );
}
