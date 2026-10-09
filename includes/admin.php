<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function versedb_admin_menu() {
	add_options_page( __( 'VerseDB', 'versedb' ), __( 'VerseDB', 'versedb' ), 'manage_options', 'versedb', 'versedb_settings_page' );
}
add_action( 'admin_menu', 'versedb_admin_menu' );

function versedb_admin_styles( $hook ) {
	if ( 'settings_page_versedb' !== $hook ) {
		return;
	}
	wp_enqueue_script( 'versedb-admin', plugins_url( 'admin.js', __FILE__ ), array(), (string) filemtime( __DIR__ . '/admin.js' ), true );
	wp_enqueue_style( 'versedb-admin', plugins_url( 'admin.css', __FILE__ ), array(), (string) filemtime( __DIR__ . '/admin.css' ) );
}
add_action( 'admin_enqueue_scripts', 'versedb_admin_styles' );


function versedb_register_settings() {
	register_setting( 'versedb_blocks', 'versedb_enabled_blocks', array( 'type' => 'array', 'sanitize_callback' => 'versedb_sanitize_enabled_blocks', 'default' => array_keys( versedb_block_catalog() ) ) );
	register_setting( 'versedb', 'versedb_preferences', array( 'type' => 'array', 'sanitize_callback' => 'versedb_sanitize_preferences', 'default' => array( 'credit' => false ) ) );
}
add_action( 'admin_init', 'versedb_register_settings' );

function versedb_sanitize_preferences( $input ) {
	return array( 'credit' => is_array( $input ) && ! empty( $input['credit'] ) );
}

function versedb_action_button( $action, $label, $primary = false ) {
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="versedb-action">
		<input type="hidden" name="action" value="<?php echo esc_attr( 'versedb_' . $action ); ?>" />
		<?php wp_nonce_field( 'versedb_' . $action ); ?>
		<?php submit_button( $label, $primary ? 'primary' : 'secondary', 'submit', false ); ?>
	</form>
	<?php
}

function versedb_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$connection = get_option( 'versedb_connection', array() );
	$profile = get_option( 'versedb_profile_cache', array() );
	$account = get_option( 'versedb_account', array() );
	$preferences = get_option( 'versedb_preferences', array( 'credit' => false ) );
	$notices = array(
		'connected' => __( 'VerseDB is connected. Add a VerseDB block to a page or widget area.', 'versedb' ),
		'disconnected' => __( 'VerseDB has been disconnected and its cached data removed.', 'versedb' ),
		'cache_cleared' => __( 'Cached display data has been cleared. Your account connection and preferences are unchanged.', 'versedb' ),
		'queued' => __( 'A background refresh has been scheduled. Cached content remains available.', 'versedb' ),
		'connection_failed' => __( 'Connection failed. Check your token and its read:public and read:showcase permissions, then try again.', 'versedb' ),
		'invalid_state' => __( 'This connection request is invalid or expired. Start Connect to VerseDB again.', 'versedb' ),
		'denied' => __( 'Connection was cancelled. Your existing connection is unchanged.', 'versedb' ),
		'https_required' => __( 'Connect to VerseDB requires HTTPS. Use a pasted token for local development, or enable HTTPS.', 'versedb' ),
		'busy' => __( 'A refresh is running. Please try this action again shortly.', 'versedb' ),
		'reconnect' => __( 'Reconnect your VerseDB account before refreshing.', 'versedb' ),
	);
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Selects escaped informational text only; performs no action.
	$notice = isset( $_GET['versedb_notice'] ) && is_string( $_GET['versedb_notice'] ) ? sanitize_key( wp_unslash( $_GET['versedb_notice'] ) ) : '';
	?>
	<div class="wrap versedb-settings">
		<h1 class="versedb-brand"><img src="<?php echo esc_url( plugins_url( 'logo-full.png', __FILE__ ) ); ?>" alt="<?php esc_attr_e( 'VerseDB', 'versedb' ); ?>" width="960" height="469" /></h1>
		<p class="versedb-intro"><?php esc_html_e( 'Your comics, at home on your website.', 'versedb' ); ?></p>
		<?php if ( isset( $notices[ $notice ] ) ) : ?><div class="notice notice-info is-dismissible"><p><?php echo esc_html( $notices[ $notice ] ); ?></p></div><?php endif; ?>
		<div class="versedb-settings-layout">
		<div class="versedb-settings-main">
		<section class="versedb-settings-card" aria-labelledby="versedb-connection-heading">
		<div class="versedb-card-heading">
		<h2 id="versedb-connection-heading"><?php esc_html_e( 'Account connection', 'versedb' ); ?></h2>
		<span class="versedb-status <?php echo $connection ? ( 'connected' === get_option( 'versedb_status' ) ? 'versedb-status-connected' : 'versedb-status-warning' ) : ''; ?>"><?php echo $connection ? ( 'connected' === get_option( 'versedb_status' ) ? esc_html__( 'Connected', 'versedb' ) : esc_html__( 'Needs attention', 'versedb' ) ) : esc_html__( 'Not connected', 'versedb' ); ?></span>
		</div>
		<?php if ( $connection ) : ?>
			<p class="versedb-account-name"><?php echo esc_html( isset( $profile['username'] ) ? $profile['username'] : '' ); ?> — <?php echo ! empty( $account['is_pro'] ) ? esc_html__( 'VerseDB Pro', 'versedb' ) : esc_html__( 'VerseDB Free', 'versedb' ); ?></p>
			<p><?php echo 'connected' === get_option( 'versedb_status' ) ? esc_html__( 'Connected. Data refreshes hourly in the background.', 'versedb' ) : esc_html__( 'Refresh needs attention. Cached content remains available.', 'versedb' ); ?></p>
			<?php if ( get_option( 'versedb_query_errors' ) ) : ?><p><?php esc_html_e( 'VerseDB denied access to one or more displays. Check token permissions and Pro eligibility.', 'versedb' ); ?></p><?php endif; ?>
			<?php // translators: %s: token expiration date.
			$expiry_text = ! empty( $connection['expires'] ) ? sprintf( __( 'Token expires: %s', 'versedb' ), wp_date( get_option( 'date_format' ), strtotime( $connection['expires'] ) ) ) : __( 'Token expiry is unknown for pasted tokens. Check My Apps on VerseDB.', 'versedb' ); ?>
			<p><?php echo esc_html( $expiry_text ); ?></p>

		<?php else : ?>
			<p><?php esc_html_e( 'Connect your account to display your comics on this site. Only the blocks you add publish data.', 'versedb' ); ?></p>
		<?php endif; ?>
		<div class="versedb-actions">
			<?php if ( $connection ) : ?>
				<?php versedb_action_button( 'refresh', __( 'Refresh now', 'versedb' ) ); ?>
				<?php versedb_action_button( 'disconnect', __( 'Disconnect', 'versedb' ) ); ?>
			<?php endif; ?>
			<?php versedb_action_button( 'connect', $connection ? __( 'Reconnect to VerseDB', 'versedb' ) : __( 'Connect to VerseDB', 'versedb' ), true ); ?>
		</div>
		<details class="versedb-token-settings">
			<summary><?php esc_html_e( 'Use a personal access token instead', 'versedb' ); ?></summary>
			<p><?php esc_html_e( 'Create a token with read:public and read:showcase permissions in My Apps. Existing read:user tokens also work, but grant broader access.', 'versedb' ); ?> <a href="https://versedb.com/my/apps" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open My Apps', 'versedb' ); ?></a></p>
			<?php if ( versedb_secure_token_transport() ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="versedb_save_token" />
				<?php wp_nonce_field( 'versedb_save_token' ); ?>
				<p><label for="versedb-token"><?php esc_html_e( 'Personal access token', 'versedb' ); ?></label><br />
				<input class="regular-text" id="versedb-token" name="token" type="password" autocomplete="new-password" required /></p>
				<?php submit_button( __( 'Test and save connection', 'versedb' ) ); ?>
			</form>
			<?php else : ?>
				<p><?php esc_html_e( 'Enable HTTPS before entering a VerseDB token. HTTP is allowed only on localhost.', 'versedb' ); ?></p>
			<?php endif; ?>
		</details>
		</section>
		<?php versedb_cache_card(); ?>
		<?php versedb_block_manager(); ?>

		<?php if ( empty( $account['is_pro'] ) ) : ?>
		<section class="versedb-settings-card versedb-pro-card" aria-labelledby="versedb-pro-heading">
		<h2 id="versedb-pro-heading"><?php esc_html_e( 'More ways to share with VerseDB Pro', 'versedb' ); ?></h2>
		<p><?php esc_html_e( 'Add more reading insights and collection filters to your website with a VerseDB Pro account.', 'versedb' ); ?></p>
		<ul>
			<li><?php esc_html_e( 'Reading Stats: show your yearly reading statistics.', 'versedb' ); ?></li>
			<li><?php esc_html_e( 'Reading Calendar: display your reading activity as a heatmap.', 'versedb' ); ?></li>
			<li><?php esc_html_e( 'Collection filters: signed copies, grade range, grading company, genre, creator and character.', 'versedb' ); ?></li>
		</ul>
		<p><a class="button button-primary" href="https://versedb.com/pro"><?php esc_html_e( 'Explore VerseDB Pro', 'versedb' ); ?></a></p>
		<p class="description"><?php esc_html_e( 'Already upgraded? Use Refresh now to update your connected account. No separate plugin subscription is needed.', 'versedb' ); ?></p>
		</section>
		<?php endif; ?>
		</div>
		<div class="versedb-settings-sidebar">

		<aside class="versedb-settings-card versedb-settings-help" aria-labelledby="versedb-help-heading">
		<h2 id="versedb-help-heading"><?php esc_html_e( 'Getting started', 'versedb' ); ?></h2>
		<ol>
			<li><?php esc_html_e( 'Connect your VerseDB account.', 'versedb' ); ?></li>
			<li><?php esc_html_e( 'Open a page, post or widget area.', 'versedb' ); ?></li>
			<li><?php esc_html_e( 'Search for VerseDB in the block inserter and choose a display.', 'versedb' ); ?></li>
		</ol>
		<h3><?php esc_html_e( 'Need a hand?', 'versedb' ); ?></h3>
		<p><a href="https://versedb.com/support/apps/wordpress"><?php esc_html_e( 'WordPress', 'versedb' ); ?></a></p>
		<p><a href="mailto:hello@versedb.com">hello@versedb.com</a></p>
		<p class="versedb-disconnect-help"><?php esc_html_e( 'Disconnect removes the local token, display cache and scheduled refreshes. To revoke the token on VerseDB, delete it in My Apps.', 'versedb' ); ?></p>
		</aside>
		<section class="versedb-settings-card" aria-labelledby="versedb-credit-heading">
		<h2 id="versedb-credit-heading"><?php esc_html_e( 'Display credit', 'versedb' ); ?></h2>
		<form method="post" action="options.php">
			<?php settings_fields( 'versedb' ); ?>
			<input type="hidden" name="versedb_preferences[credit]" value="0" />
			<label class="versedb-toggle"><input role="switch" type="checkbox" name="versedb_preferences[credit]" value="1" <?php checked( ! empty( $preferences['credit'] ) ); ?> /> <span><?php esc_html_e( 'Show a “Powered by VerseDB” link below displays', 'versedb' ); ?></span></label>
			<?php submit_button( __( 'Save preferences', 'versedb' ) ); ?>
		</form>
		</section>
		</div>
		</div>
	</div>
	<?php
}

function versedb_connection_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$connection = get_option( 'versedb_connection', array() );
	if ( ! $connection ) {
		return;
	}
	$expiry = ! empty( $connection['expires'] ) ? strtotime( $connection['expires'] ) : false;
	if ( 'reconnect' === get_option( 'versedb_status' ) || ( $expiry && $expiry < time() + 14 * DAY_IN_SECONDS ) ) {
		?>
		<div class="notice notice-warning is-dismissible"><p><?php esc_html_e( 'Your VerseDB token is expiring or needs reconnection. Cached displays remain available.', 'versedb' ); ?> <a href="<?php echo esc_url( versedb_settings_url() ); ?>"><?php esc_html_e( 'Reconnect VerseDB', 'versedb' ); ?></a></p></div>
		<?php
	}
}
add_action( 'admin_notices', 'versedb_connection_notice' );

function versedb_cache_card() {
	$queries = get_option( 'versedb_queries', array() );
	$errors = get_option( 'versedb_query_errors', array() );
	$counts = array( 'fresh' => 0, 'stale' => 0, 'empty' => 0, 'failed' => 0 );
	foreach ( $queries as $key => $query ) {
		if ( ! versedb_block_enabled( $query['type'] ) ) {
			continue;
		}
		$cache = get_option( 'versedb_data_' . $key, array() );
		if ( ! empty( $errors[ $key ] ) ) {
			++$counts['failed'];
		} elseif ( ! isset( $cache['data'] ) ) {
			++$counts['empty'];
		} elseif ( ! empty( $cache['updated'] ) && $cache['updated'] > time() - HOUR_IN_SECONDS ) {
			++$counts['fresh'];
		} else {
			++$counts['stale'];
		}
	}
	$connected = (bool) get_option( 'versedb_connection' );
	$status = get_option( 'versedb_status' );
	$lock = get_option( 'versedb_refresh_lock', array() );
	$retry = (int) get_option( 'versedb_next_request', 0 );
	$queued = wp_next_scheduled( 'versedb_refresh' );
	$hourly = wp_next_scheduled( 'versedb_hourly' );
	$next = $queued && $hourly ? min( $queued, $hourly ) : ( $queued ? $queued : $hourly );
	$last = (int) get_option( 'versedb_account_updated', 0 );
	$label = __( 'Up to date', 'versedb' );
	if ( ! $connected ) {
		$label = __( 'Not connected', 'versedb' );
	} elseif ( 'reconnect' === $status ) {
		$label = __( 'Reconnect required', 'versedb' );
		$next = false;
	} elseif ( ! empty( $lock['expires'] ) && $lock['expires'] > time() ) {
		$label = __( 'Refreshing', 'versedb' );
	} elseif ( $retry > time() ) {
		$label = __( 'Waiting to retry', 'versedb' );
		$next = $next ? max( $next, $retry ) : false;
	} elseif ( $counts['failed'] ) {
		$label = __( 'Some displays need attention', 'versedb' );
	} elseif ( $queued ) {
		$label = __( 'Refresh queued', 'versedb' );
	} elseif ( $counts['stale'] || $counts['empty'] || ! get_transient( 'versedb_account_fresh' ) ) {
		$label = __( 'Refresh needed', 'versedb' );
	}
	$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
	?>
	<section class="versedb-settings-card" aria-labelledby="versedb-cache-heading">
		<div class="versedb-card-heading">
			<h2 id="versedb-cache-heading"><?php esc_html_e( 'Cache status', 'versedb' ); ?></h2>
			<span class="versedb-status"><?php echo esc_html( $label ); ?></span>
		</div>
		<dl class="versedb-cache-details">
			<dt><?php esc_html_e( 'Account last refreshed', 'versedb' ); ?></dt>
			<dd><?php echo esc_html( $last ? wp_date( $format, $last ) : __( 'Not recorded yet', 'versedb' ) ); ?></dd>
			<dt><?php esc_html_e( 'Next scheduled refresh', 'versedb' ); ?></dt>
			<dd><?php echo esc_html( $next ? wp_date( $format, $next ) : __( 'Not scheduled', 'versedb' ) ); ?></dd>
			<?php foreach ( array( 'fresh' => __( 'Up-to-date displays', 'versedb' ), 'stale' => __( 'Displays awaiting refresh', 'versedb' ), 'empty' => __( 'Displays not cached yet', 'versedb' ), 'failed' => __( 'Displays with access errors', 'versedb' ) ) as $key => $title ) : ?>
				<dt><?php echo esc_html( $title ); ?></dt><dd><?php echo esc_html( number_format_i18n( $counts[ $key ] ) ); ?></dd>
			<?php endforeach; ?>
		</dl>
		<p><?php esc_html_e( 'Display counts cover enabled block queries. Different filters can create separate cached displays. Data is stored in this WordPress site’s database.', 'versedb' ); ?></p>
		<p class="description"><?php esc_html_e( 'Refreshes run through WordPress cron and may be delayed on quiet sites. Reload this page to see the latest status.', 'versedb' ); ?></p>
		<p><?php esc_html_e( 'Clearing the cache temporarily removes public displays until fresh data is available. Your account stays connected. Active displays are queued for refresh; reconnect first if your token has expired.', 'versedb' ); ?></p>
		<?php versedb_action_button( 'clear_cache', __( 'Clear cache', 'versedb' ) ); ?>
	</section>
	<?php
}
