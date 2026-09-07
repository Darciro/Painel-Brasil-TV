<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers every custom block found under the theme's /blocks directory.
 *
 * Each block directory is expected to contain a block.json. When present,
 * a co-located index.js and style.css are registered as the editor script
 * and combined editor/front-end style, using the `pbtv-{block-name}` handle
 * convention, before the block itself is registered from its metadata.
 *
 * @return void
 */
function pbtv_register_blocks(): void {
	$blocks_dir = get_theme_file_path( 'blocks' );

	if ( ! is_dir( $blocks_dir ) ) {
		return;
	}

	$version = wp_get_theme()->get( 'Version' );

	foreach ( (array) glob( $blocks_dir . '/*', GLOB_ONLYDIR ) as $block_dir ) {
		if ( ! file_exists( $block_dir . '/block.json' ) ) {
			continue;
		}

		$block_name = basename( $block_dir );

		if ( file_exists( $block_dir . '/index.js' ) ) {
			wp_register_script(
				"pbtv-{$block_name}-editor",
				get_theme_file_uri( "blocks/{$block_name}/index.js" ),
				array(
					'wp-blocks',
					'wp-block-editor',
					'wp-components',
					'wp-element',
					'wp-i18n',
					'wp-server-side-render',
				),
				$version,
				true
			);
		}

		if ( file_exists( $block_dir . '/style.css' ) ) {
			wp_register_style(
				"pbtv-{$block_name}",
				get_theme_file_uri( "blocks/{$block_name}/style.css" ),
				array(),
				$version
			);
		}

		register_block_type( $block_dir );
	}
}

add_action( 'init', 'pbtv_register_blocks' );
