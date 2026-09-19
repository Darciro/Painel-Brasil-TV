<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the theme's bundled logo as a fallback for the Site Logo block
 * when no custom logo has been set in Site Identity, so the header never
 * falls back to a plain site title link.
 *
 * @param string $block_content The block's rendered HTML.
 * @param array  $block         The parsed block, including its attributes.
 *
 * @return string The block's rendered HTML, replaced with the theme's
 *                default logo when no custom logo is set.
 */
function pbtv_theme_default_site_logo( string $block_content, array $block ): string {
	if ( has_custom_logo() ) {
		return $block_content;
	}

	$width   = isset( $block['attrs']['width'] ) ? absint( $block['attrs']['width'] ) : 120;
	$classes = 'wp-block-site-logo';

	if ( ! empty( $block['attrs']['className'] ) ) {
		$classes .= ' ' . sanitize_html_class( $block['attrs']['className'] );
	}

	return sprintf(
		'<div class="%1$s"><a href="%2$s" class="custom-logo-link" rel="home"><img width="%3$d" src="%4$s" alt="%5$s" class="custom-logo" /></a></div>',
		esc_attr( $classes ),
		esc_url( home_url( '/' ) ),
		$width,
		esc_url( get_theme_file_uri( 'assets/images/pbtv-logo.png' ) ),
		esc_attr( get_bloginfo( 'name' ) )
	);
}

add_filter( 'render_block_core/site-logo', 'pbtv_theme_default_site_logo', 10, 2 );
