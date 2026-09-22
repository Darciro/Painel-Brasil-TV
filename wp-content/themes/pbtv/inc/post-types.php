<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the "videos" custom post type.
 *
 * One post is created per YouTube video the first time its dynamic
 * route (/videos/{video_id}/) is visited, so the video only needs to
 * be resolved from YouTube once. The post slug always matches the
 * YouTube video ID.
 */
function pbtv_register_video_post_type(): void {
	register_post_type(
		'videos',
		array(
			'labels'              => array(
				'name'          => __( 'Videos', 'pbtv' ),
				'singular_name' => __( 'Video', 'pbtv' ),
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => true,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'capability_type'     => 'post',
			'supports'            => array( 'title', 'thumbnail', 'comments', 'custom-fields' ),
			'menu_icon'           => 'dashicons-video-alt3',
		)
	);
}
add_action( 'init', 'pbtv_register_video_post_type' );

/**
 * Register the "video_id" post meta used to identify a video that
 * has already been imported as a "videos" post.
 */
function pbtv_register_video_post_meta(): void {
	register_post_meta(
		'videos',
		'video_id',
		array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => function (): bool {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'init', 'pbtv_register_video_post_meta' );

/**
 * Preserve the exact-case video_id as the "videos" post slug.
 *
 * WordPress lowercases slugs by default via sanitize_title(), but
 * YouTube video IDs are case-sensitive, so the default behavior
 * would break the 1:1 mapping between video_id and post slug that
 * the video route depends on.
 *
 * @param array<string, mixed> $data    Sanitized post data.
 * @param array<string, mixed> $postarr Raw, unsanitized post data.
 *
 * @return array<string, mixed>
 */
function pbtv_preserve_video_post_slug( array $data, array $postarr ): array {
	if ( 'videos' !== $data['post_type'] || empty( $postarr['post_name'] ) ) {
		return $data;
	}

	$data['post_name'] = $postarr['post_name'];

	return $data;
}
add_filter( 'wp_insert_post_data', 'pbtv_preserve_video_post_slug', 10, 2 );

/**
 * Point "videos" post permalinks at the theme's custom video route
 * (/videos/{video_id}/).
 *
 * The post type is registered without rewrite rules of its own (the
 * route lives in inc/routes.php), so without this filter
 * get_permalink() would fall back to a broken query-string URL. This
 * is what wp-comments-post.php uses to redirect back to the video
 * page after a comment is submitted.
 *
 * @param string  $post_link The post's URL.
 * @param WP_Post $post      Post object.
 *
 * @return string
 */
function pbtv_video_post_permalink( string $post_link, WP_Post $post ): string {
	if ( 'videos' !== $post->post_type ) {
		return $post_link;
	}

	return home_url( user_trailingslashit( 'videos/' . $post->post_name ) );
}
add_filter( 'post_type_link', 'pbtv_video_post_permalink', 10, 2 );

/**
 * Finds an existing "videos" post by its video_id meta value.
 *
 * @param string $video_id YouTube video ID.
 *
 * @return WP_Post|null
 */
function pbtv_get_video_post( string $video_id ): ?WP_Post {
	if ( ! $video_id ) {
		return null;
	}

	$posts = get_posts(
		array(
			'post_type'      => 'videos',
			'post_status'    => 'any',
			'meta_key'       => 'video_id',
			'meta_value'     => $video_id,
			'posts_per_page' => 1,
			'no_found_rows'  => true,
		)
	);

	return $posts[0] ?? null;
}

/**
 * Returns the "videos" post for a YouTube video, creating it first
 * when it doesn't exist yet. Title and thumbnail are pulled from the
 * YouTube oEmbed endpoint on first import.
 *
 * @param string $video_id YouTube video ID.
 *
 * @return WP_Post|null The existing or newly created post, or null
 *                       when creation failed.
 */
function pbtv_get_or_create_video_post( string $video_id ): ?WP_Post {
	$existing = pbtv_get_video_post( $video_id );

	if ( $existing ) {
		return $existing;
	}

	$oembed = pbtv_get_youtube_video_oembed( $video_id );

	$post_id = wp_insert_post(
		array(
			'post_type'      => 'videos',
			'post_status'    => 'publish',
			'comment_status' => 'open',
			'post_title'     => $oembed['title'] ?? $video_id,
			'post_name'      => $video_id,
			'meta_input'     => array(
				'video_id' => $video_id,
			),
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		return null;
	}

	if ( ! empty( $oembed['thumbnail'] ) ) {
		update_post_meta( $post_id, 'video_thumbnail', $oembed['thumbnail'] );
	}

	return get_post( $post_id );
}
