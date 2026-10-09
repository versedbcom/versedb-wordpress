<?php
/**
 * Plugin Name: VerseDB
 * Description: Display your VerseDB profile and comics with WordPress blocks.
 * Version: 1.0.0
 * Requires at least: 6.8
 * Requires PHP: 7.4
 * Author: VerseDB
 * Author URI: https://versedb.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: versedb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

foreach ( array( 'blocks', 'storage', 'api', 'connect', 'admin', 'profile', 'render', 'template', 'oembed' ) as $versedb_module ) {
	require_once __DIR__ . '/includes/' . $versedb_module . '.php';
}

function versedb_register_blocks() {
	$build = __DIR__ . '/build';
	if ( ! file_exists( $build . '/blocks-manifest.php' ) ) {
		return;
	}
	if ( file_exists( $build . '/shared/style.css' ) ) {
		wp_register_style( 'versedb-display', plugins_url( 'build/shared/style.css', __FILE__ ), array(), (string) filemtime( $build . '/shared/style.css' ) );
		wp_style_add_data( 'versedb-display', 'rtl', 'replace' );
	}

	wp_register_block_metadata_collection( $build, $build . '/blocks-manifest.php' );
	foreach ( array_keys( versedb_block_catalog() ) as $type ) {
		if ( versedb_block_enabled( $type ) ) {
			register_block_type( $build . '/' . $type );
		}
		add_shortcode( 'versedb_' . str_replace( '-', '_', $type ), function ( $attributes = array() ) use ( $type ) {
			return versedb_shortcode( $type, $attributes );
		} );
	}
	if ( versedb_enabled_blocks() && ! WP_Block_Type_Registry::get_instance()->is_registered( 'versedb/field' ) ) {
		register_block_type( $build . '/field' );
	}
	if ( ! versedb_enabled_blocks() && WP_Block_Type_Registry::get_instance()->is_registered( 'versedb/field' ) ) {
		unregister_block_type( 'versedb/field' );
	}
	versedb_ensure_schedule();
}
add_action( 'init', 'versedb_register_blocks' );

function versedb_build_notice() {
	if ( ! current_user_can( 'activate_plugins' ) || file_exists( __DIR__ . '/build/blocks-manifest.php' ) ) {
		return;
	}
	?>
	<div class="notice notice-error"><p><?php esc_html_e( 'VerseDB block assets are missing. Install a complete plugin package.', 'versedb' ); ?></p></div>
	<?php
}
add_action( 'admin_notices', 'versedb_build_notice' );

function versedb_editor_settings() {
	$account = get_option( 'versedb_account', array() );
	wp_add_inline_script( 'wp-blocks', 'window.verseDBSettings = ' . wp_json_encode( array( 'isPro' => ! empty( $account['is_pro'] ), 'settingsUrl' => versedb_settings_url() ) ) . ';', 'after' );
}
add_action( 'enqueue_block_editor_assets', 'versedb_editor_settings' );

function versedb_allowed_blocks( $allowed ) {
	$account = get_option( 'versedb_account', array() );
	if ( ! empty( $account['is_pro'] ) || false === $allowed ) {
		return $allowed;
	}
	$names = true === $allowed ? array_keys( WP_Block_Type_Registry::get_instance()->get_all_registered() ) : $allowed;
	return array_values( array_diff( $names, array( 'versedb/reading-stats', 'versedb/reading-calendar' ) ) );
}
add_filter( 'allowed_block_types_all', 'versedb_allowed_blocks' );

function versedb_deactivate() {
	wp_clear_scheduled_hook( 'versedb_refresh' );
	wp_clear_scheduled_hook( 'versedb_hourly' );
}
register_deactivation_hook( __FILE__, 'versedb_deactivate' );
