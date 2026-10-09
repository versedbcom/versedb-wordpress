<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function versedb_block_catalog() {
	return array(
		'profile' => array( __( 'Profile', 'versedb' ), 'admin-users', __( 'Your profile, biography and account statistics.', 'versedb' ) ),
		'collection' => array( __( 'Collection', 'versedb' ), 'book', __( 'Public comics from your collection.', 'versedb' ) ),
		'wishlist' => array( __( 'Wishlist', 'versedb' ), 'heart', __( 'Comics you want to collect.', 'versedb' ) ),
		'pull-list' => array( __( 'Pull List', 'versedb' ), 'list-view', __( 'Series on your pull list.', 'versedb' ) ),
		'currently-reading' => array( __( 'Currently Reading', 'versedb' ), 'book-alt', __( 'Unfinished comics and reading progress.', 'versedb' ) ),
		'reading' => array( __( 'Recently Read', 'versedb' ), 'yes-alt', __( 'Your recorded comic reads.', 'versedb' ) ),
		'reading-goal' => array( __( 'Reading Goal', 'versedb' ), 'flag', __( 'Your annual reading target and progress.', 'versedb' ) ),
		'lists' => array( __( 'Public Lists', 'versedb' ), 'screenoptions', __( 'Your published public lists.', 'versedb' ) ),
		'list' => array( __( 'List', 'versedb' ), 'editor-ul', __( 'Comics from one of your public lists.', 'versedb' ) ),
		'reading-stats' => array( __( 'Reading Stats', 'versedb' ), 'chart-bar', __( 'Yearly reading statistics and streaks.', 'versedb' ) ),
		'reading-calendar' => array( __( 'Reading Calendar', 'versedb' ), 'calendar-alt', __( 'Your reading activity as a heatmap.', 'versedb' ) ),
	);
}

function versedb_enabled_blocks() {
	return get_option( 'versedb_enabled_blocks', array_keys( versedb_block_catalog() ) );
}

function versedb_block_enabled( $type ) {
	return in_array( $type, (array) versedb_enabled_blocks(), true );
}

function versedb_sanitize_enabled_blocks( $input ) {
	return is_array( $input ) ? array_values( array_intersect( array_keys( versedb_block_catalog() ), array_filter( $input, 'is_string' ) ) ) : array();
}

function versedb_block_manager() {
	?>
	<section class="versedb-settings-card versedb-block-manager" aria-labelledby="versedb-blocks-heading">
		<h2 id="versedb-blocks-heading"><?php esc_html_e( 'Blocks', 'versedb' ); ?></h2>
		<p><?php esc_html_e( 'Enable only the displays you use. Disabled blocks and their shortcodes stop displaying, and their editor scripts and display refreshes are skipped. Saved page content stays intact; re-enable a block to restore it before editing pages that use it.', 'versedb' ); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields( 'versedb_blocks' ); ?>
			<input type="hidden" name="versedb_enabled_blocks[]" value="" />
			<div class="versedb-block-tools" hidden>
				<label class="screen-reader-text" for="versedb-block-search"><?php esc_html_e( 'Search blocks', 'versedb' ); ?></label>
				<input type="search" id="versedb-block-search" placeholder="<?php esc_attr_e( 'Search blocks…', 'versedb' ); ?>" />
				<button type="button" class="button" data-versedb-enable="true"><?php esc_html_e( 'Enable all', 'versedb' ); ?></button>
				<button type="button" class="button" data-versedb-enable="false"><?php esc_html_e( 'Disable all', 'versedb' ); ?></button>
			</div>
			<div class="versedb-block-grid">
				<?php foreach ( versedb_block_catalog() as $type => $details ) : ?>
					<div class="versedb-block-card">
						<label class="versedb-toggle">
							<span class="dashicons dashicons-<?php echo esc_attr( $details[1] ); ?>" aria-hidden="true"></span>
							<input type="checkbox" role="switch" name="versedb_enabled_blocks[]" value="<?php echo esc_attr( $type ); ?>" <?php checked( versedb_block_enabled( $type ) ); ?> />
							<span class="versedb-block-name"><?php echo esc_html( $details[0] ); ?><?php if ( in_array( $type, array( 'reading-stats', 'reading-calendar' ), true ) ) : ?> <span class="versedb-pro-label"><?php esc_html_e( 'Pro', 'versedb' ); ?></span><?php endif; ?></span>
						</label>
						<p><?php echo esc_html( $details[2] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
			<p class="versedb-block-empty" hidden><?php esc_html_e( 'No blocks match your search.', 'versedb' ); ?></p>
			<p class="description"><?php esc_html_e( 'Blocks marked Pro also require a connected VerseDB Pro account.', 'versedb' ); ?></p>
			<?php submit_button( __( 'Save blocks', 'versedb' ) ); ?>
		</form>
	</section>
	<?php
}

function versedb_block_categories( $categories ) {
	foreach ( $categories as $category ) {
		if ( 'versedb' === $category['slug'] ) {
			return $categories;
		}
	}
	$categories[] = array( 'slug' => 'versedb', 'title' => __( 'VerseDB', 'versedb' ), 'icon' => 'book' );
	return $categories;
}
add_filter( 'block_categories_all', 'versedb_block_categories' );
