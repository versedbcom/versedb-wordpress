<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function versedb_profile_boolean( $value, $default = true ) {
	$parsed = filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
	return null === $parsed ? $default : $parsed;
}

function versedb_render_profile( $attributes = array() ) {
	$profile = get_option( 'versedb_profile_cache', array() );
	if ( ! get_transient( 'versedb_account_fresh' ) ) {
		versedb_queue_refresh();
	}
	if ( ! is_array( $profile ) || empty( $profile['username'] ) || ! is_string( $profile['username'] ) ) {
		return versedb_empty_display();
	}

	$enable_links = versedb_profile_boolean( isset( $attributes['enableLinks'] ) ? $attributes['enableLinks'] : true );
	$show_avatar = versedb_profile_boolean( isset( $attributes['showAvatar'] ) ? $attributes['showAvatar'] : true );
	$show_bio    = versedb_profile_boolean( isset( $attributes['showBio'] ) ? $attributes['showBio'] : true );
	$avatar      = isset( $profile['avatar'] ) && is_string( $profile['avatar'] ) ? esc_url( $profile['avatar'], array( 'https' ) ) : '';
	$bio         = isset( $profile['bio'] ) && is_string( $profile['bio'] ) ? $profile['bio'] : '';

	$size = versedb_int( isset( $attributes['avatarSize'] ) ? $attributes['avatarSize'] : 80, 80, 32, 160 );
	$shape = isset( $attributes['avatarShape'] ) && 'square' === $attributes['avatarShape'] ? 'square' : 'circle';
	$radius = 'square' === $shape ? versedb_int( isset( $attributes['avatarRadius'] ) ? $attributes['avatarRadius'] : 0, 0, 0, 80 ) . 'px' : '50%';
	ob_start();
	?>
	<div <?php echo get_block_wrapper_attributes( array( 'class' => 'versedb-profile', 'style' => '--versedb-avatar-size:' . $size . 'px;--versedb-avatar-radius:' . $radius ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress escapes wrapper attributes. ?>>
		<?php if ( ! empty( $attributes['showBanner'] ) && ! empty( $profile['banner_url'] ) ) : ?><img class="versedb-profile__banner" src="<?php echo esc_url( $profile['banner_url'] ); ?>" alt="" loading="lazy" /><?php endif; ?>
		<?php if ( $show_avatar && $avatar ) : ?>
			<img class="versedb-profile__avatar" src="<?php echo esc_url( $avatar ); ?>" alt="" width="<?php echo esc_attr( $size ); ?>" height="<?php echo esc_attr( $size ); ?>" loading="lazy" decoding="async" />
		<?php endif; ?>
		<div class="versedb-profile__details">
			<p class="versedb-profile__username"><?php if ( $enable_links ) : ?><a href="<?php echo esc_url( 'https://versedb.com/user/' . rawurlencode( strtolower( $profile['username'] ) ) ); ?>"><?php echo esc_html( $profile['username'] ); ?></a><?php else : ?><?php echo esc_html( $profile['username'] ); ?><?php endif; ?></p>
			<?php if ( $show_bio && '' !== $bio ) : ?>
				<p class="versedb-profile__bio"><?php echo esc_html( $bio ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $attributes['showStats'] ) ) : ?>
				<?php // translators: 1: member level, 2: XP total, 3: contribution count. ?>
				<p><?php echo esc_html( sprintf( __( 'Level %1$s · %2$s XP · %3$s contributions', 'versedb' ), number_format_i18n( isset( $profile['level'] ) ? $profile['level'] : 0 ), number_format_i18n( isset( $profile['xp'] ) ? $profile['xp'] : 0 ), number_format_i18n( isset( $profile['contributions_count'] ) ? $profile['contributions_count'] : 0 ) ) ); ?></p><?php endif; ?>
		</div>
		<?php echo versedb_credit(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static escaped markup. ?>
	</div>
	<?php
	return ob_get_clean();
}

function versedb_profile_shortcode( $attributes = array() ) {
	return versedb_shortcode( 'profile', $attributes );
}
