<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo versedb_render_display( 'collection', $attributes, $block ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared renderer escapes each field.
