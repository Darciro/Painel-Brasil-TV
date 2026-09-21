<?php

/**
 * Script module dependencies for view.js, read by register_block_type()
 * via the block's `viewScriptModule` field. Without this file WordPress
 * has no way to know view.js imports `@wordpress/interactivity`, so the
 * browser's import map would be missing that entry and the module import
 * would fail.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'dependencies' => array( '@wordpress/interactivity' ),
	'version'      => wp_get_theme()->get( 'Version' ),
);
