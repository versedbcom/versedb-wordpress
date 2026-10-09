<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
require_once __DIR__ . '/includes/storage.php';
versedb_disconnect();
delete_option( 'versedb_preferences' );
delete_option( 'versedb_enabled_blocks' );
delete_option( 'versedb_refresh_lock' );
