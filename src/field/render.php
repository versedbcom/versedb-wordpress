<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
echo versedb_render_field( $attributes, $block ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared renderer escapes display fields.
