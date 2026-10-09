<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function versedb_template_fields( $type ) {
	if ( 'profile' === $type ) {
		return array( 'avatar', 'username', 'bio', 'banner', 'profile-stats' );
	}
	if ( 'reading-goal' === $type ) {
		return array( 'goal-summary', 'progress' );
	}
	if ( 'reading-stats' === $type ) {
		return array( 'read_count', 'active_days', 'current_streak', 'longest_streak', 'this_week', 'this_month' );
	}
	if ( 'reading-calendar' === $type ) {
		return array( 'year', 'calendar' );
	}
	$fields = array( 'cover', 'name' );
	if ( 'lists' !== $type ) {
		$fields = array_merge( $fields, array( 'series_name', 'issue_number', 'issue_title', 'publisher', 'date' ) );
	}
	if ( 'currently-reading' === $type ) {
		$fields = array_merge( $fields, array( 'percent', 'progress' ) );
	}
	return $fields;
}

function versedb_template_data( $type, $attributes ) {
	if ( ! versedb_block_enabled( $type ) ) {
		return array();
	}
	$account = get_option( 'versedb_account', array() );
	if ( in_array( $type, array( 'reading-stats', 'reading-calendar' ), true ) && empty( $account['is_pro'] ) ) {
		return array();
	}
	if ( 'profile' === $type ) {
		if ( ! get_transient( 'versedb_account_fresh' ) ) {
			versedb_queue_refresh();
		}
		$record = get_option( 'versedb_profile_cache', array() );
		return ! empty( $record['username'] ) ? array( $record ) : array();
	}
	$data = versedb_request_data( $type, $attributes );
	if ( ! $data ) {
		return array();
	}
	return in_array( $type, array( 'reading-goal', 'reading-stats', 'reading-calendar' ), true )
		? array( $data ) : array_slice( $data, 0, versedb_int( isset( $attributes['limit'] ) ? $attributes['limit'] : 12, 12, 1, 100 ) );
}

function versedb_field_content( $field, $record, $options, $type ) {
	if ( ! in_array( $field, versedb_template_fields( $type ), true ) ) {
		return '';
	}
	$link = versedb_profile_boolean( isset( $options['enableLinks'] ) ? $options['enableLinks'] : true );
	$url = isset( $record['url'] ) ? $record['url'] : '';
	if ( 'series_name' === $field && ! empty( $record['series_url'] ) ) {
		$url = $record['series_url'];
	}
	if ( 'username' === $field ) {
		$url = 'https://versedb.com/user/' . rawurlencode( strtolower( $record['username'] ) );
	}
	if ( in_array( $field, array( 'cover', 'avatar', 'banner' ), true ) ) {
		$key = 'cover' === $field ? 'image' : ( 'banner' === $field ? 'banner_url' : 'avatar' );
		if ( empty( $record[ $key ] ) ) {
			return '';
		}
		$blur = 'cover' === $field && ! empty( $record['is_nsfw'] ) && empty( $options['showNsfw'] );
		$label = isset( $record['name'] ) ? $record['name'] : '';
		if ( $blur ) {
			// translators: %s: comic title.
			$label = sprintf( __( '%s — explicit cover blurred', 'versedb' ), $label );
		}
		$html = '<img class="versedb-field__image' . ( $blur ? ' versedb-field__image--blurred' : '' ) . '" src="' . esc_url( $record[ $key ] ) . '" alt="' . esc_attr( $label ) . '" loading="lazy" decoding="async" />';
		return $link && $url && 'cover' === $field ? '<a href="' . esc_url( $url ) . '">' . $html . '</a>' : $html;
	}
	if ( 'progress' === $field ) {
		$percent = versedb_int( isset( $record['percent'] ) ? $record['percent'] : 0, 0, 0, 100 );
		return '<progress max="100" value="' . esc_attr( $percent ) . '" aria-label="' . esc_attr__( 'Reading progress', 'versedb' ) . '">' . esc_html( $percent . '%' ) . '</progress>';
	}
	if ( 'calendar' === $field ) {
		$html = '<ul class="versedb-calendar" tabindex="0" aria-label="' . esc_attr__( 'Daily reading activity', 'versedb' ) . '">';
		foreach ( isset( $record['days'] ) ? $record['days'] : array() as $day ) {
			// translators: 1: localized date, 2: number of comics read.
			$label = sprintf( _n( '%1$s: %2$s comic read', '%1$s: %2$s comics read', $day['count'], 'versedb' ), wp_date( get_option( 'date_format' ), strtotime( $day['date'] . 'T12:00:00Z' ), new DateTimeZone( 'UTC' ) ), number_format_i18n( $day['count'] ) );
			$html .= '<li class="versedb-calendar__day versedb-calendar__day--' . esc_attr( min( 4, $day['count'] ) ) . '" title="' . esc_attr( $label ) . '"><span class="versedb-calendar__label">' . esc_html( $label ) . '</span></li>';
		}
		return $html . '</ul>';
	}
	$stats = array( 'read_count' => __( 'Comics read', 'versedb' ), 'active_days' => __( 'Active days', 'versedb' ), 'current_streak' => __( 'Current streak', 'versedb' ), 'longest_streak' => __( 'Longest streak', 'versedb' ), 'this_week' => __( 'This week', 'versedb' ), 'this_month' => __( 'This month', 'versedb' ) );
	if ( isset( $stats[ $field ] ) ) {
		return '<span class="versedb-field__label">' . esc_html( $stats[ $field ] ) . '</span><strong>' . esc_html( number_format_i18n( isset( $record[ $field ] ) ? $record[ $field ] : 0 ) ) . '</strong>';
	}
	if ( 'goal-summary' === $field ) {
		// translators: 1: comics read, 2: target comics, 3: calendar year.
		return esc_html( sprintf( __( '%1$s of %2$s comics read in %3$s', 'versedb' ), number_format_i18n( $record['read_count'] ), number_format_i18n( $record['target'] ), $record['year'] ) );
	}
	if ( 'profile-stats' === $field ) {
		// translators: 1: member level, 2: XP total, 3: contribution count.
		return esc_html( sprintf( __( 'Level %1$s · %2$s XP · %3$s contributions', 'versedb' ), number_format_i18n( $record['level'] ), number_format_i18n( $record['xp'] ), number_format_i18n( $record['contributions_count'] ) ) );
	}
	if ( 'percent' === $field ) {
		// translators: %s: percentage of the comic read.
		return esc_html( sprintf( __( '%s%% read', 'versedb' ), number_format_i18n( $record['percent'] ) ) );
	}
	$text = isset( $record[ $field ] ) ? versedb_text( $record[ $field ] ) : '';
	if ( 'bio' === $field ) {
		return nl2br( esc_html( isset( $record['bio'] ) ? $record['bio'] : '' ) );
	}
	if ( 'date' === $field && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $text ) ) {
		return '<time datetime="' . esc_attr( $text ) . '">' . esc_html( wp_date( get_option( 'date_format' ), strtotime( $text . 'T12:00:00Z' ), new DateTimeZone( 'UTC' ) ) ) . '</time>';
	}
	if ( 'issue_number' === $field && '' !== $text ) {
		$text = '#' . $text;
	}
	$html = esc_html( $text );
	return $link && $url && $html && in_array( $field, array( 'name', 'series_name', 'issue_number', 'issue_title', 'username' ), true ) ? '<a href="' . esc_url( $url ) . '">' . $html . '</a>' : $html;
}

function versedb_text_style( $value, $prefix = '' ) {
	if ( ! is_array( $value ) ) {
		return '';
	}
	$rules = array(
		'fontSize' => array( 8, 144, 'px' ),
		'lineHeight' => array( 0.5, 4, '' ),
		'letterSpacing' => array( -5, 20, 'px' ),
	);
	$choices = array(
		'fontWeight' => array( '300', '400', '500', '600', '700', '800' ),
		'fontStyle' => array( 'normal', 'italic' ),
		'textTransform' => array( 'none', 'uppercase', 'lowercase', 'capitalize' ),
		'textDecoration' => array( 'none', 'underline', 'line-through' ),
		'textAlign' => array( 'left', 'center', 'right' ),
	);
	$style = '';
	foreach ( $value as $key => $item ) {
		if ( ! is_scalar( $item ) || '' === (string) $item ) {
			continue;
		}
		$item = (string) $item;
		if ( isset( $rules[ $key ] ) && is_numeric( $item ) ) {
			$rule = $rules[ $key ];
			$item = max( $rule[0], min( $rule[1], (float) $item ) ) . $rule[2];
		} elseif ( isset( $choices[ $key ] ) && in_array( $item, $choices[ $key ], true ) ) {
			// Enumerated typography values are safe CSS keywords.
		} elseif ( 'color' === $key && sanitize_hex_color( $item ) ) {
			$item = sanitize_hex_color( $item );
		} elseif ( 'fontFamily' === $key && preg_match( '/^[a-zA-Z0-9 ,\'"_-]{1,200}$/', $item ) ) {
			$item = trim( $item );
		} else {
			continue;
		}
		$property = strtolower( preg_replace( '/([A-Z])/', '-$1', $key ) );
		$style .= $prefix . $property . ':' . $item . ';';
	}
	return $style;
}

function versedb_render_field( $attributes, $block ) {
	if ( empty( $block->context['versedb/type'] ) || empty( $block->context['versedb/record'] ) ) {
		return '';
	}
	$field = isset( $attributes['field'] ) ? $attributes['field'] : 'name';
	$html = versedb_field_content( $field, $block->context['versedb/record'], $block->context['versedb/options'], $block->context['versedb/type'] );
	if ( '' === $html ) {
		return '';
	}
	$style = versedb_text_style( isset( $attributes['labelStyle'] ) ? $attributes['labelStyle'] : array(), '--versedb-label-' );
	if ( in_array( isset( $attributes['textAlign'] ) ? $attributes['textAlign'] : '', array( 'left', 'center', 'right' ), true ) ) {
		$style .= 'text-align:' . $attributes['textAlign'] . ';';
	}
	foreach ( array( 'imageWidth' => 'width', 'imageHeight' => 'height' ) as $key => $property ) {
		$value = versedb_int( isset( $attributes[ $key ] ) ? $attributes[ $key ] : 0, 0, 0, 1200 );
		if ( $value ) {
			$style .= '--versedb-field-image-' . $property . ':' . $value . 'px;';
		}
	}
	$style .= '--versedb-field-image-fit:' . ( isset( $attributes['imageFit'] ) && 'contain' === $attributes['imageFit'] ? 'contain' : 'cover' ) . ';';
	return '<div ' . get_block_wrapper_attributes( array( 'class' => 'versedb-field versedb-field--' . sanitize_html_class( $field ), 'style' => $style ) ) . '>' . $html . '</div>';
}

function versedb_prepare_template_block( $parsed, &$budget, $depth = 0 ) {
	$empty = array( 'blockName' => null, 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array( '' ) );
	if ( --$budget < 0 || $depth > 8 || ( 'versedb/field' !== $parsed['blockName'] && ( ! is_string( $parsed['blockName'] ) || 0 !== strpos( $parsed['blockName'], 'core/' ) ) ) ) {
		return $empty;
	}
	foreach ( $parsed['innerBlocks'] as $index => $child ) {
		$parsed['innerBlocks'][ $index ] = versedb_prepare_template_block( $child, $budget, $depth + 1 );
	}
	return $parsed;
}

function versedb_render_template_children( $blocks, $context ) {
	$html = '';
	$budget = 100;
	foreach ( array_slice( $blocks, 0, 100 ) as $parsed ) {
		$block = new WP_Block( versedb_prepare_template_block( $parsed, $budget ), $context );
		$html .= $block->render();
	}
	return $html;
}

function versedb_render_template( $type, $attributes, $block ) {
	$records = versedb_template_data( $type, $attributes );
	if ( ! $records ) {
		return versedb_empty_display();
	}
	$columns = versedb_int( isset( $attributes['columns'] ) ? $attributes['columns'] : 4, 4, 1, 6 );
	$mobile = versedb_int( isset( $attributes['mobileColumns'] ) ? $attributes['mobileColumns'] : 2, 2, 1, min( 3, $columns ) );
	$gap = versedb_int( isset( $attributes['gap'] ) ? $attributes['gap'] : 16, 16, 0, 64 );
	$style = '--versedb-columns:' . $columns . ';--versedb-mobile-columns:' . $mobile . ';--versedb-gap:' . $gap . 'px';
	$style .= ';--versedb-cell-size:' . versedb_int( isset( $attributes['cellSize'] ) ? $attributes['cellSize'] : 12, 12, 8, 24 ) . 'px;--versedb-cell-gap:' . versedb_int( isset( $attributes['cellGap'] ) ? $attributes['cellGap'] : 3, 3, 0, 8 ) . 'px;';
	$html = '<section ' . get_block_wrapper_attributes( array( 'class' => 'versedb-display versedb-custom-layout', 'style' => $style ) ) . '>';
	if ( ! empty( $attributes['title'] ) ) {
		$html .= '<h2 class="versedb-display__title" style="' . esc_attr( versedb_text_style( isset( $attributes['headingStyle'] ) ? $attributes['headingStyle'] : array() ) ) . '">' . esc_html( versedb_text( $attributes['title'] ) ) . '</h2>';
	}
	$single = in_array( $type, array( 'profile', 'reading-goal', 'reading-stats', 'reading-calendar' ), true );
	$layout = isset( $attributes['layout'] ) && 'list' === $attributes['layout'] ? 'list' : 'grid';
	if ( ! $single ) {
		$html .= '<ul class="versedb-items versedb-items--' . esc_attr( $layout ) . '">';
	}
	foreach ( $records as $record ) {
		$context = array( 'versedb/record' => $record, 'versedb/options' => $attributes, 'versedb/type' => $type );
		$children = versedb_render_template_children( $block->parsed_block['innerBlocks'], $context );
		$html .= $single ? $children : '<li class="versedb-template-item">' . $children . '</li>';
	}
	return $html . ( $single ? '' : '</ul>' ) . versedb_credit( isset( $attributes['creditStyle'] ) ? $attributes['creditStyle'] : array() ) . '</section>';
}

function versedb_editor_preview( $request ) {
	$type = $request->get_param( 'type' );
	$registry = WP_Block_Type_Registry::get_instance();
	$registered = is_string( $type ) && isset( versedb_block_catalog()[ $type ] ) ? $registry->get_registered( 'versedb/' . $type ) : null;
	if ( ! $registered || ! versedb_block_enabled( $type ) ) {
		return new WP_Error( 'versedb_invalid_display', __( 'This display is not available.', 'versedb' ), array( 'status' => 400 ) );
	}
	$attributes = $request->get_param( 'attributes' );
	$attributes = $registered->prepare_attributes_for_render( is_array( $attributes ) ? $attributes : array() );
	$records = versedb_template_data( $type, $attributes );
	$items = array();
	foreach ( $records as $record ) {
		$fields = array();
		foreach ( versedb_template_fields( $type ) as $field ) {
			$fields[ $field ] = versedb_field_content( $field, $record, $attributes, $type );
		}
		$items[] = $fields;
	}
	return rest_ensure_response( array( 'fields' => isset( $items[0] ) ? $items[0] : array(), 'items' => $items, 'hasData' => (bool) $records, 'credit' => $records ? versedb_credit( isset( $attributes['creditStyle'] ) ? $attributes['creditStyle'] : array() ) : '' ) );
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'versedb/v1', '/editor-preview', array(
		'methods' => 'POST',
		'callback' => 'versedb_editor_preview',
		'permission_callback' => function () { return current_user_can( 'edit_posts' ); },
	) );
} );


add_filter( 'render_block', function ( $html, $parsed ) {
	$name = isset( $parsed['blockName'] ) ? $parsed['blockName'] : '';
	$type = is_string( $name ) && 0 === strpos( $name, 'versedb/' ) ? substr( $name, 8 ) : '';
	return isset( versedb_block_catalog()[ $type ] ) && ! versedb_block_enabled( $type ) ? '' : $html;
}, 10, 2 );
