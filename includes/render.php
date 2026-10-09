<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function versedb_empty_display( $message = '' ) {
	if ( wp_is_serving_rest_request() && current_user_can( 'edit_posts' ) ) {
		if ( ! $message ) {
			$message = get_option( 'versedb_connection' ) ? __( 'No items are cached for this display yet. A background refresh has been scheduled. Use Refresh preview after it finishes.', 'versedb' ) : __( 'Connect your account in Settings → VerseDB to display your comics.', 'versedb' );
		}
		return '<div ' . get_block_wrapper_attributes( array( 'class' => 'versedb-empty' ) ) . '><p>' . esc_html( $message ) . '</p></div>';
	}
	return '';
}

function versedb_credit( $style = array() ) {
	$preferences = get_option( 'versedb_preferences', array( 'credit' => false ) );
	$style = versedb_text_style( $style );
	return ! empty( $preferences['credit'] ) ? '<p class="versedb-credit"' . ( $style ? ' style="' . esc_attr( $style ) . '"' : '' ) . '><a href="https://versedb.com">' . esc_html__( 'Powered by VerseDB', 'versedb' ) . '</a></p>' : '';
}

function versedb_render_display( $type, $attributes = array(), $block = null ) {
	if ( ! versedb_block_enabled( $type ) ) {
		return '';
	}
	if ( $block && ( ! empty( $attributes['layoutVersion'] ) || ! empty( $attributes['customLayout'] ) ) ) {
		return versedb_render_template( $type, $attributes, $block );
	}
	if ( 'profile' === $type ) {
		return versedb_render_profile( $attributes );
	}
	$account = get_option( 'versedb_account', array() );
	if ( in_array( $type, array( 'reading-stats', 'reading-calendar' ), true ) && empty( $account['is_pro'] ) ) {
		return versedb_empty_display( __( 'This display requires an active VerseDB Pro account.', 'versedb' ) );
	}
	$data = versedb_request_data( $type, $attributes );
	if ( ! $data ) {
		if ( 'list' === $type && empty( $attributes['listId'] ) ) {
			return versedb_empty_display( __( 'Choose one of your public lists using its list ID.', 'versedb' ) );
		}
		$key = md5( wp_json_encode( versedb_descriptor( $type, $attributes ) ) );
		$cached = get_option( 'versedb_data_' . $key, array() );
		if ( ! empty( $cached['updated'] ) ) {
			return versedb_empty_display( __( 'No public items match this display.', 'versedb' ) );
		}
		return versedb_empty_display();
	}
	$columns = versedb_int( isset( $attributes['columns'] ) ? $attributes['columns'] : 4, 4, 1, 6 );
	$mobile_columns = versedb_int( isset( $attributes['mobileColumns'] ) ? $attributes['mobileColumns'] : 2, 2, 1, min( 3, $columns ) );
	$gap = versedb_int( isset( $attributes['gap'] ) ? $attributes['gap'] : 16, 16, 0, 64 );
	$cover_size = versedb_int( isset( $attributes['coverSize'] ) ? $attributes['coverSize'] : 80, 80, 40, 200 );
	$layout = isset( $attributes['layout'] ) && 'list' === $attributes['layout'] ? 'list' : 'grid';
	$title = isset( $attributes['title'] ) ? versedb_text( $attributes['title'] ) : '';
	$style = '--versedb-columns:' . $columns . ';--versedb-mobile-columns:' . $mobile_columns . ';--versedb-gap:' . $gap . 'px;--versedb-cover-size:' . $cover_size . 'px';
	$cell_size = versedb_int( isset( $attributes['cellSize'] ) ? $attributes['cellSize'] : 12, 12, 8, 24 );
	$cell_gap = versedb_int( isset( $attributes['cellGap'] ) ? $attributes['cellGap'] : 3, 3, 0, 8 );
	$style .= ';--versedb-cell-size:' . $cell_size . 'px;--versedb-cell-gap:' . $cell_gap . 'px';
	$enable_links = versedb_profile_boolean( isset( $attributes['enableLinks'] ) ? $attributes['enableLinks'] : true );
	$show_progress = versedb_profile_boolean( isset( $attributes['showProgress'] ) ? $attributes['showProgress'] : true );
	ob_start();
	?>
	<section <?php echo get_block_wrapper_attributes( array( 'class' => 'versedb-display', 'style' => $style ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by WordPress. ?>>
		<?php if ( $title ) : ?><h2 class="versedb-display__title"><?php echo esc_html( $title ); ?></h2><?php endif; ?>
		<?php if ( 'reading-goal' === $type ) : ?>
			<?php $target = $data['target']; $reads = $data['read_count']; ?>
			<?php // translators: 1: comics read, 2: target comics, 3: calendar year. ?>
			<p class="versedb-goal__count"><?php echo esc_html( sprintf( __( '%1$s of %2$s comics read in %3$s', 'versedb' ), number_format_i18n( $reads ), number_format_i18n( $target ), $data['year'] ) ); ?></p>
			<?php if ( $show_progress && $target > 0 ) : ?><progress class="versedb-goal__progress" max="<?php echo esc_attr( $target ); ?>" value="<?php echo esc_attr( min( $reads, $target ) ); ?>" aria-label="<?php esc_attr_e( 'Reading goal progress', 'versedb' ); ?>"><?php echo esc_html( min( 100, $data['percent'] ) . '%' ); ?></progress><?php endif; ?>
		<?php elseif ( 'reading-stats' === $type ) : ?>
			<dl class="versedb-stats">
				<?php foreach ( array( 'read_count' => __( 'Comics read', 'versedb' ), 'active_days' => __( 'Active days', 'versedb' ), 'current_streak' => __( 'Current streak', 'versedb' ), 'longest_streak' => __( 'Longest streak', 'versedb' ), 'this_week' => __( 'This week', 'versedb' ), 'this_month' => __( 'This month', 'versedb' ) ) as $key => $label ) : ?>
					<?php $visibility = array( 'read_count' => 'showReadCount', 'active_days' => 'showActiveDays', 'current_streak' => 'showCurrentStreak', 'longest_streak' => 'showLongestStreak', 'this_week' => 'showThisWeek', 'this_month' => 'showThisMonth' );
					if ( ! versedb_profile_boolean( isset( $attributes[ $visibility[ $key ] ] ) ? $attributes[ $visibility[ $key ] ] : true ) ) { continue; } ?>
					<div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( number_format_i18n( $data[ $key ] ) ); ?></dd></div>
				<?php endforeach; ?>
			</dl>
		<?php elseif ( 'reading-calendar' === $type ) : ?>
			<?php // translators: %s: calendar year. ?>
			<?php if ( versedb_profile_boolean( isset( $attributes['showYear'] ) ? $attributes['showYear'] : true ) ) : ?><p><?php echo esc_html( sprintf( __( 'Reading activity in %s', 'versedb' ), $data['year'] ) ); ?></p><?php endif; ?>
			<ul class="versedb-calendar" tabindex="0" aria-label="<?php esc_attr_e( 'Daily reading activity', 'versedb' ); ?>">
				<?php foreach ( $data['days'] as $day ) : ?>
					<?php // translators: 1: localized date, 2: number of comics read.
					$label = sprintf( _n( '%1$s: %2$s comic read', '%1$s: %2$s comics read', $day['count'], 'versedb' ), wp_date( get_option( 'date_format' ), strtotime( $day['date'] . 'T12:00:00Z' ), new DateTimeZone( 'UTC' ) ), number_format_i18n( $day['count'] ) ); ?>
					<li class="versedb-calendar__day versedb-calendar__day--<?php echo esc_attr( min( 4, $day['count'] ) ); ?>" title="<?php echo esc_attr( $label ); ?>"><span class="versedb-calendar__label"><?php echo esc_html( $label ); ?></span></li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<ul class="versedb-items versedb-items--<?php echo esc_attr( $layout ); ?>">
				<?php foreach ( array_slice( $data, 0, versedb_int( isset( $attributes['limit'] ) ? $attributes['limit'] : 12, 12, 1, 100 ) ) as $item ) : ?>
					<li class="versedb-item">
						<?php if ( versedb_profile_boolean( isset( $attributes['showCovers'] ) ? $attributes['showCovers'] : true ) && ! empty( $item['image'] ) ) : ?>
							<?php $blur = ! empty( $item['is_nsfw'] ) && ! versedb_profile_boolean( isset( $attributes['showNsfw'] ) ? $attributes['showNsfw'] : false, false );
							// translators: %s: comic title.
							$cover_label = $blur ? sprintf( __( '%s — explicit cover blurred', 'versedb' ), $item['name'] ) : $item['name']; ?>
							<?php if ( $enable_links ) : ?>
							<a class="versedb-item__cover<?php echo $blur ? ' versedb-item__cover--blurred' : ''; ?>" href="<?php echo esc_url( $item['url'] ); ?>" aria-label="<?php echo esc_attr( $cover_label ); ?>"><img src="<?php echo esc_url( $item['image'] ); ?>" alt="" loading="lazy" decoding="async" /></a>
							<?php else : ?>
							<span class="versedb-item__cover<?php echo $blur ? ' versedb-item__cover--blurred' : ''; ?>"><img src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $cover_label ); ?>" loading="lazy" decoding="async" /></span>
							<?php endif; ?>
						<?php endif; ?>
						<div class="versedb-item__details">
							<?php if ( $enable_links ) : ?><a class="versedb-item__name" href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['name'] ); ?></a><?php else : ?><span class="versedb-item__name"><?php echo esc_html( $item['name'] ); ?></span><?php endif; ?>
							<?php if ( 'currently-reading' === $type ) : ?>
								<?php // translators: %s: percentage of the comic read. ?>
								<p><?php echo esc_html( sprintf( __( '%s%% read', 'versedb' ), number_format_i18n( $item['percent'] ) ) ); ?></p>
								<?php if ( $show_progress ) : ?><progress max="100" value="<?php echo esc_attr( $item['percent'] ); ?>" aria-label="<?php esc_attr_e( 'Comic reading progress', 'versedb' ); ?>"><?php echo esc_html( $item['percent'] . '%' ); ?></progress><?php endif; ?>
							<?php endif; ?>
							<?php if ( ! empty( $attributes['showPublisher'] ) && ! empty( $item['publisher'] ) ) : ?><p><?php echo esc_html( $item['publisher'] ); ?></p><?php endif; ?>
							<?php if ( ! empty( $attributes['showDates'] ) && ! empty( $item['date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $item['date'] ) ) : ?><p><time datetime="<?php echo esc_attr( $item['date'] ); ?>"><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $item['date'] . 'T12:00:00Z' ), new DateTimeZone( 'UTC' ) ) ); ?></time></p><?php endif; ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php echo versedb_credit(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static escaped markup. ?>
	</section>
	<?php
	return ob_get_clean();
}

function versedb_shortcode( $type, $attributes ) {
	$attributes = is_array( $attributes ) ? $attributes : array();
	$mapping = array( 'enable_links' => 'enableLinks', 'show_avatar' => 'showAvatar', 'show_bio' => 'showBio', 'show_banner' => 'showBanner', 'show_stats' => 'showStats', 'avatar_size' => 'avatarSize', 'avatar_shape' => 'avatarShape', 'avatar_radius' => 'avatarRadius', 'show_covers' => 'showCovers', 'show_nsfw' => 'showNsfw', 'show_publisher' => 'showPublisher', 'show_dates' => 'showDates', 'mobile_columns' => 'mobileColumns', 'show_progress' => 'showProgress', 'show_read_count' => 'showReadCount', 'show_active_days' => 'showActiveDays', 'show_current_streak' => 'showCurrentStreak', 'show_longest_streak' => 'showLongestStreak', 'show_this_week' => 'showThisWeek', 'show_this_month' => 'showThisMonth', 'show_year' => 'showYear', 'cell_size' => 'cellSize', 'cell_gap' => 'cellGap', 'cover_size' => 'coverSize', 'list_id' => 'listId' );
	foreach ( $mapping as $from => $to ) {
		if ( isset( $attributes[ $from ] ) ) {
			$attributes[ $to ] = $attributes[ $from ];
			unset( $attributes[ $from ] );
		}
	}
	$block_type = WP_Block_Type_Registry::get_instance()->get_registered( 'versedb/' . $type );
	if ( ! $block_type ) {
		return '';
	}
	$clean = array();
	foreach ( $block_type->get_attributes() as $name => $schema ) {
		if ( ! isset( $attributes[ $name ] ) ) {
			continue;
		}
		if ( 'boolean' === $schema['type'] ) {
			$clean[ $name ] = versedb_profile_boolean( $attributes[ $name ], isset( $schema['default'] ) ? $schema['default'] : false );
		} elseif ( 'number' === $schema['type'] ) {
			$clean[ $name ] = versedb_int( $attributes[ $name ] );
		} elseif ( 'string' === $schema['type'] ) {
			$clean[ $name ] = versedb_text( $attributes[ $name ] );
		}
	}
	return render_block( array( 'blockName' => 'versedb/' . $type, 'attrs' => $clean, 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) );
}
