<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function versedb_register_oembed_provider() {
	// Matches the website provider, which rejects query strings.
	wp_oembed_add_provider( '~^https://versedb\.com/(?:title|series|issue|event|creator)/[1-9][0-9]*(?:/(?!edit(?:[/?#]|$))[^/?#]+)?/?(?:#.*)?$~', 'https://versedb.com/oembed', true );
}
add_action( 'init', 'versedb_register_oembed_provider' );
