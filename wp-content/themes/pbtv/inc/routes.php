<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register custom video route.
 */
function pbtv_register_video_route(): void {

	/*
	 * Register the query variable.
	 *
	 * This allows WordPress to understand:
	 *
	 * ?pbtv_video_id=ABC123
	 */
	add_rewrite_tag(
		'%pbtv_video_id%',
		'([^&]+)'
	);

	/*
	 * /videos/{youtube-id}/
	 */
	add_rewrite_rule(
		'^videos/([^/]+)/?$',
		'index.php?pbtv_video_id=$matches[1]',
		'top'
	);
}

add_action(
	'init',
	'pbtv_register_video_route'
);


/**
 * Load our custom template.
 *
 * Priority 99 is intentional so this runs after
 * WordPress has resolved the normal Block Theme template.
 */
function pbtv_video_template_include( string $template ): string {

	$video_id = get_query_var( 'pbtv_video_id' );

	if ( ! $video_id ) {
		return $template;
	}

	$video_template = get_theme_file_path(
		'route-templates/video.php'
	);

	if ( file_exists( $video_template ) ) {
		return $video_template;
	}

	return $template;
}

add_filter(
	'template_include',
	'pbtv_video_template_include',
	99
);


/**
 * Prevent canonical redirects for our custom route.
 */
function pbtv_video_disable_canonical(
	$redirect_url,
	$requested_url
) {

	if ( get_query_var( 'pbtv_video_id' ) ) {
		return false;
	}

	return $redirect_url;
}

add_filter(
	'redirect_canonical',
	'pbtv_video_disable_canonical',
	10,
	2
);