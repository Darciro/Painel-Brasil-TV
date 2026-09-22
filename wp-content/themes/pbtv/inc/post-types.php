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
 * Register the post meta used by "videos" posts: the YouTube video_id
 * that identifies a video that has already been imported, plus the
 * fields the daily sync (see pbtv_sync_video_post()) fills in.
 */
function pbtv_register_video_post_meta(): void {
	$auth_callback = function (): bool {
		return current_user_can( 'edit_posts' );
	};

	foreach ( array( 'video_id', 'video_thumbnail', 'video_channel_id', 'video_status' ) as $meta_key ) {
		register_post_meta(
			'videos',
			$meta_key,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $auth_callback,
			)
		);
	}
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
				'video_id'         => $video_id,
				/*
				 * A direct /videos/{id}/ visit carries no channel context,
				 * so this assumes the site's single default channel. Also
				 * default to 'upload' status so this video isn't wrongly
				 * prioritized as live/completed in pbtv_get_synced_videos()
				 * ahead of videos the daily sync has actually classified.
				 */
				'video_channel_id' => pbtv_youtube_channel_id(),
				'video_status'     => 'upload',
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

/**
 * Queries the local "videos" posts synced for a channel, replicating the
 * live -> completed -> upload fallback order the YouTube Data API search
 * previously provided directly: live broadcasts first, then recently
 * ended live broadcasts, then regular uploads, each newest first.
 *
 * This is what pbtv_get_youtube_live_videos() reads from instead of
 * calling the YouTube Data API on every pageview; the API is only ever
 * called by the `pbtv_sync_youtube_videos` daily cron event (see
 * pbtv_sync_channel_videos() below).
 *
 * @param string $channel_id  YouTube channel ID.
 * @param int    $max_results Maximum number of videos to return.
 *
 * @return array<int, array<string, string>> List of videos, each with
 *                                            id, title, thumbnail and
 *                                            status keys.
 */
function pbtv_get_synced_videos( string $channel_id, int $max_results ): array {
	if ( ! $channel_id ) {
		return array();
	}

	$videos = array();

	foreach ( array( 'live', 'completed', 'upload' ) as $status ) {
		$remaining = $max_results - count( $videos );

		if ( $remaining <= 0 ) {
			break;
		}

		$posts = get_posts(
			array(
				'post_type'      => 'videos',
				'post_status'    => 'publish',
				'posts_per_page' => $remaining,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'   => 'video_channel_id',
						'value' => $channel_id,
					),
					array(
						'key'   => 'video_status',
						'value' => $status,
					),
				),
			)
		);

		foreach ( $posts as $post ) {
			$videos[] = pbtv_video_post_to_array( $post );
		}
	}

	return $videos;
}

/**
 * Shapes a "videos" post the same way pbtv_query_youtube_search() shapes
 * a raw YouTube Data API result, so every reader of
 * pbtv_get_youtube_live_videos() keeps working unchanged regardless of
 * whether the data came straight from the API or from the local sync.
 *
 * @param WP_Post $post A "videos" post.
 *
 * @return array<string, string>
 */
function pbtv_video_post_to_array( WP_Post $post ): array {
	return array(
		'id'        => (string) get_post_meta( $post->ID, 'video_id', true ),
		'title'     => get_the_title( $post ),
		'thumbnail' => (string) get_post_meta( $post->ID, 'video_thumbnail', true ),
		'status'    => (string) get_post_meta( $post->ID, 'video_status', true ),
	);
}

/**
 * Creates or updates the "videos" post for a single synced video,
 * keyed by its video_id meta so re-syncing the same video updates its
 * existing post instead of duplicating it.
 *
 * @param string                 $channel_id YouTube channel ID the video belongs to.
 * @param array<string, string>  $video      Video data, see pbtv_query_youtube_search().
 *
 * @return int|null The post ID, or null when the upsert failed.
 */
function pbtv_sync_video_post( string $channel_id, array $video ): ?int {
	$video_id = (string) ( $video['id'] ?? '' );

	if ( ! $video_id ) {
		return null;
	}

	$existing      = pbtv_get_video_post( $video_id );
	$published_gmt = ! empty( $video['published'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $video['published'] ) ) : current_time( 'mysql', true );

	$postarr = array(
		'post_type'      => 'videos',
		'post_status'    => 'publish',
		'comment_status' => 'open',
		'post_title'     => $video['title'] ? $video['title'] : $video_id,
		'post_name'      => $video_id,
		'post_date_gmt'  => $published_gmt,
		'post_date'      => get_date_from_gmt( $published_gmt ),
		'edit_date'      => true,
		'meta_input'     => array(
			'video_id'         => $video_id,
			'video_channel_id' => $channel_id,
			'video_status'     => $video['status'] ? $video['status'] : 'upload',
		),
	);

	if ( $existing ) {
		$postarr['ID'] = $existing->ID;
	}

	$post_id = wp_insert_post( $postarr, true );

	if ( is_wp_error( $post_id ) ) {
		return null;
	}

	if ( ! empty( $video['thumbnail'] ) ) {
		update_post_meta( $post_id, 'video_thumbnail', $video['thumbnail'] );
	}

	return $post_id;
}

/**
 * Fetches a channel's latest live/completed/uploaded videos from the
 * YouTube Data API and syncs each one into the "videos" post type.
 *
 * @param string $channel_id  YouTube channel ID.
 * @param int    $max_results Maximum number of videos to fetch and sync.
 *
 * @return void
 */
function pbtv_sync_channel_videos( string $channel_id, int $max_results = 50 ): void {
	if ( ! $channel_id ) {
		return;
	}

	$videos = pbtv_fetch_youtube_live_videos( $channel_id, $max_results );

	foreach ( $videos as $video ) {
		pbtv_sync_video_post( $channel_id, $video );
	}
}

/**
 * Schedules the `pbtv_sync_youtube_videos` daily cron event if it isn't
 * already scheduled.
 *
 * @return void
 */
function pbtv_schedule_youtube_video_sync(): void {
	if ( ! wp_next_scheduled( 'pbtv_sync_youtube_videos' ) ) {
		wp_schedule_event( time(), 'daily', 'pbtv_sync_youtube_videos' );
	}
}
add_action( 'init', 'pbtv_schedule_youtube_video_sync' );

/**
 * Cron callback for `pbtv_sync_youtube_videos`: syncs the configured
 * YouTube channel's latest videos into the "videos" post type, so
 * front-end blocks and shortcodes can read from the local database
 * instead of calling the YouTube Data API on every pageview.
 *
 * @return void
 */
function pbtv_sync_youtube_videos_cron(): void {
	pbtv_sync_channel_videos( pbtv_youtube_channel_id() );
}
add_action( 'pbtv_sync_youtube_videos', 'pbtv_sync_youtube_videos_cron' );

/**
 * Unschedules the video sync cron event when the theme is switched away
 * from, so it doesn't keep firing (and failing to find its callback)
 * under a different theme.
 *
 * @return void
 */
function pbtv_unschedule_youtube_video_sync(): void {
	wp_clear_scheduled_hook( 'pbtv_sync_youtube_videos' );
}
add_action( 'switch_theme', 'pbtv_unschedule_youtube_video_sync' );

/**
 * One-time backfill for "videos" posts imported before the
 * video_channel_id/video_status meta existed (via the lazy
 * /videos/{id}/ route import). Without this, those posts would be
 * silently excluded from pbtv_get_synced_videos() results - which
 * filters on both fields - until the daily sync happened to include
 * them again.
 *
 * @return void
 */
function pbtv_backfill_video_post_meta(): void {
	if ( get_option( 'pbtv_video_meta_backfilled' ) ) {
		return;
	}

	$posts = get_posts(
		array(
			'post_type'      => 'videos',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'meta_query'     => array(
				array(
					'key'     => 'video_channel_id',
					'compare' => 'NOT EXISTS',
				),
			),
		)
	);

	foreach ( $posts as $post ) {
		update_post_meta( $post->ID, 'video_channel_id', pbtv_youtube_channel_id() );
		update_post_meta( $post->ID, 'video_status', 'upload' );
	}

	update_option( 'pbtv_video_meta_backfilled', 1, false );
}
add_action( 'init', 'pbtv_backfill_video_post_meta' );
