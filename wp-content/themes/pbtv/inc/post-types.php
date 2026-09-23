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
 * fields the video sync (see pbtv_sync_video_post()) fills in.
 */
function pbtv_register_video_post_meta(): void {
	$auth_callback = function (): bool {
		return current_user_can( 'edit_posts' );
	};

	foreach ( array( 'video_id', 'video_thumbnail', 'video_channel_id', 'video_status', 'video_migrated_at' ) as $meta_key ) {
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
 * Builds the wp_insert_post() date fields that pin a "videos" post's
 * date to the video's YouTube publish date instead of the moment it was
 * imported into WordPress.
 *
 * @param string $published ISO 8601 publish date from the YouTube Data API.
 *
 * @return array<string, mixed> The date fields, or an empty array when
 *                              the date is missing or unparseable, so
 *                              callers never overwrite a post's date with
 *                              the import time.
 */
function pbtv_video_post_date_args( string $published ): array {
	$timestamp = $published ? strtotime( $published ) : false;

	if ( ! $timestamp ) {
		return array();
	}

	$published_gmt = gmdate( 'Y-m-d H:i:s', $timestamp );

	return array(
		'post_date_gmt' => $published_gmt,
		'post_date'     => get_date_from_gmt( $published_gmt ),
		'edit_date'     => true,
	);
}

/**
 * Returns the "videos" post for a YouTube video, creating it first
 * when it doesn't exist yet. Title and thumbnail are pulled from the
 * YouTube oEmbed endpoint on first import, and the publish date from the
 * YouTube Data API.
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

	$postarr = array(
		'post_type'      => 'videos',
		'post_status'    => 'publish',
		'comment_status' => 'open',
		'post_title'     => $oembed['title'] ?? $video_id,
		'post_name'      => $video_id,
		'meta_input'     => array(
			'video_id'          => $video_id,
			'video_migrated_at' => current_time( 'mysql', true ),
			/*
			 * A direct /videos/{id}/ visit carries no channel context,
			 * so this assumes the site's single default channel. Also
			 * default to 'upload' status so this video isn't wrongly
			 * prioritized as live/completed in pbtv_get_synced_videos()
			 * ahead of videos the video sync has actually classified.
			 */
			'video_channel_id'  => pbtv_youtube_channel_id(),
			'video_status'      => 'upload',
		),
	);

	$postarr += pbtv_video_post_date_args( pbtv_fetch_youtube_video_published_at( $video_id ) );

	$post_id = wp_insert_post( $postarr, true );

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
 * called by the `pbtv_sync_youtube_videos` cron event (see
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
 * Shapes a "videos" post the same way pbtv_fetch_youtube_videos() shapes
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
		'published' => (string) get_post_time( DATE_W3C, true, $post ),
	);
}

/**
 * Creates or updates the "videos" post for a single synced video,
 * keyed by its video_id meta so re-syncing the same video updates its
 * existing post instead of duplicating it.
 *
 * @param string                 $channel_id YouTube channel ID the video belongs to.
 * @param array<string, string>  $video      Video data, see pbtv_fetch_youtube_videos().
 *
 * @return int|null The post ID, or null when the upsert failed.
 */
function pbtv_sync_video_post( string $channel_id, array $video ): ?int {
	$video_id = (string) ( $video['id'] ?? '' );

	if ( ! $video_id ) {
		return null;
	}

	$existing = pbtv_get_video_post( $video_id );

	$postarr = array(
		'post_type'      => 'videos',
		'post_status'    => 'publish',
		'comment_status' => 'open',
		'post_title'     => $video['title'] ? $video['title'] : $video_id,
		'post_name'      => $video_id,
		'meta_input'     => array(
			'video_id'         => $video_id,
			'video_channel_id' => $channel_id,
			'video_status'     => $video['status'] ? $video['status'] : 'upload',
		),
	);

	$postarr += pbtv_video_post_date_args( (string) ( $video['published'] ?? '' ) );

	if ( $existing ) {
		$postarr['ID'] = $existing->ID;
	} else {
		$postarr['meta_input']['video_migrated_at'] = current_time( 'mysql', true );
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
 * Gets the existing "videos" posts for a batch of YouTube video IDs in a
 * single query.
 *
 * @param string[] $video_ids YouTube video IDs.
 *
 * @return array<string, WP_Post> Posts keyed by YouTube video ID.
 */
function pbtv_get_video_posts_by_ids( array $video_ids ): array {
	if ( ! $video_ids ) {
		return array();
	}

	$posts = get_posts(
		array(
			'post_type'      => 'videos',
			'post_status'    => 'any',
			'posts_per_page' => count( $video_ids ),
			'no_found_rows'  => true,
			'meta_query'     => array(
				array(
					'key'     => 'video_id',
					'value'   => $video_ids,
					'compare' => 'IN',
				),
			),
		)
	);

	$by_id = array();

	foreach ( $posts as $post ) {
		$by_id[ (string) get_post_meta( $post->ID, 'video_id', true ) ] = $post;
	}

	return $by_id;
}

/**
 * Whether a synced video's data differs from its existing post, so
 * unchanged videos can skip a needless wp_insert_post() on every run.
 *
 * @param WP_Post               $post  Existing "videos" post.
 * @param array<string, string> $video Video data, see pbtv_fetch_youtube_videos().
 *
 * @return bool
 */
function pbtv_video_post_needs_update( WP_Post $post, array $video ): bool {
	return $post->post_title !== $video['title']
		|| get_post_meta( $post->ID, 'video_status', true ) !== $video['status']
		|| get_post_meta( $post->ID, 'video_thumbnail', true ) !== $video['thumbnail'];
}

/**
 * Syncs one page of a channel's uploads playlist into "videos" posts.
 *
 * Videos that already have a post are skipped without spending a
 * `videos.list` request on them, unless $refresh is set, in which case
 * they're re-fetched and updated when their title, thumbnail or status
 * changed (e.g. a live broadcast that has since ended).
 *
 * @param string $channel_id  YouTube channel ID.
 * @param string $playlist_id The channel's uploads playlist ID.
 * @param string $page_token  Page token, or '' for the newest page.
 * @param bool   $refresh     Whether to also update already synced videos.
 *
 * @return array{next: string, has_known: bool}|WP_Error The next page's
 *                                                       token and whether
 *                                                       the page contained
 *                                                       any already synced
 *                                                       video.
 */
function pbtv_sync_uploads_page( string $channel_id, string $playlist_id, string $page_token, bool $refresh ): array|WP_Error {
	$page = pbtv_fetch_youtube_uploads_page( $playlist_id, $page_token );

	if ( is_wp_error( $page ) ) {
		return $page;
	}

	$existing = pbtv_get_video_posts_by_ids( $page['ids'] );
	$to_fetch = $refresh ? $page['ids'] : array_values( array_diff( $page['ids'], array_keys( $existing ) ) );
	$videos   = pbtv_fetch_youtube_videos( $to_fetch );

	if ( is_wp_error( $videos ) ) {
		return $videos;
	}

	foreach ( $videos as $video ) {
		$post = $existing[ $video['id'] ] ?? null;

		if ( ! $post || pbtv_video_post_needs_update( $post, $video ) ) {
			pbtv_sync_video_post( $channel_id, $video );
		}
	}

	return array(
		'next'      => $page['next'],
		'has_known' => (bool) $existing,
	);
}

/**
 * Gets a channel's backfill progress through its uploads playlist.
 *
 * @param string $channel_id YouTube channel ID.
 *
 * @return array{page_token: string, completed_at: int} The page to resume
 *                                                      from, and when the
 *                                                      backfill last
 *                                                      reached the end of
 *                                                      the playlist (0
 *                                                      while in progress).
 */
function pbtv_get_video_backfill_state( string $channel_id ): array {
	$state = get_option( 'pbtv_video_backfill_' . $channel_id, array() );
	$state = is_array( $state ) ? $state : array();

	return array(
		'page_token'   => (string) ( $state['page_token'] ?? '' ),
		'completed_at' => (int) ( $state['completed_at'] ?? 0 ),
	);
}

/**
 * Saves a channel's backfill progress through its uploads playlist.
 *
 * @param string                                     $channel_id YouTube channel ID.
 * @param array{page_token: string, completed_at: int} $state    Backfill state.
 *
 * @return void
 */
function pbtv_save_video_backfill_state( string $channel_id, array $state ): void {
	update_option( 'pbtv_video_backfill_' . $channel_id, $state, false );
}

/**
 * Whether the current sync run may fetch another playlist page: it must
 * still be within its time limit, and today's quota budget must cover
 * the page's worst case of one `playlistItems.list` plus one
 * `videos.list` request.
 *
 * @param float $deadline Unix timestamp the run must stop by.
 *
 * @return bool
 */
function pbtv_video_sync_can_continue( float $deadline ): bool {
	return microtime( true ) < $deadline && pbtv_youtube_quota_remaining() >= 2;
}

/**
 * Syncs a channel's videos from its YouTube uploads playlist into the
 * "videos" post type, in two phases:
 *
 * 1. Latest: always re-reads the newest page, so new uploads and status
 *    changes (a live broadcast that ended) land on every run. It keeps
 *    paging only while whole pages are new, i.e. when more videos were
 *    published since the last run than fit in one page.
 * 2. Backfill: walks the rest of the playlist from where the previous
 *    run stopped (the page token saved per channel), skipping videos that
 *    are already synced, until the run's time limit or the day's quota
 *    budget is spent. Once it reaches the end it rests, and restarts from
 *    the top after pbtv_video_backfill_rescan_interval() to pick up
 *    anything missed; with known videos skipped, a rescan costs about 1
 *    quota unit per 50 videos.
 *
 * @param string $channel_id YouTube channel ID.
 *
 * @return void
 */
function pbtv_sync_channel_videos( string $channel_id ): void {
	$playlist_id = pbtv_youtube_uploads_playlist_id( $channel_id );

	if ( ! $playlist_id || ! pbtv_youtube_api_key() ) {
		return;
	}

	/**
	 * Filters how many seconds a single video sync run may spend, keeping
	 * it well inside PHP's execution time limit. The backfill simply
	 * resumes on the next run.
	 *
	 * @param int $seconds Time limit in seconds.
	 */
	$deadline = microtime( true ) + (int) apply_filters( 'pbtv_youtube_sync_time_limit', 20 );
	$state    = pbtv_get_video_backfill_state( $channel_id );

	if ( $state['completed_at'] && time() - $state['completed_at'] >= pbtv_video_backfill_rescan_interval() ) {
		$state = array(
			'page_token'   => '',
			'completed_at' => 0,
		);
		pbtv_save_video_backfill_state( $channel_id, $state );
	}

	// Phase 1: latest videos.
	$page_token = '';
	$refresh    = true;

	do {
		if ( ! pbtv_video_sync_can_continue( $deadline ) ) {
			return;
		}

		$page = pbtv_sync_uploads_page( $channel_id, $playlist_id, $page_token, $refresh );

		if ( is_wp_error( $page ) ) {
			return;
		}

		$page_token = $page['next'];
		$refresh    = false;
	} while ( $page_token && ! $page['has_known'] );

	// Phase 2: backfill older videos from the saved cursor.
	while ( ! $state['completed_at'] && pbtv_video_sync_can_continue( $deadline ) ) {
		$page = pbtv_sync_uploads_page( $channel_id, $playlist_id, $state['page_token'], false );

		if ( is_wp_error( $page ) ) {
			// A stale page token can't be resumed; restart from the top,
			// which is cheap since synced videos are skipped.
			$transient = array( 'http_request_failed', 'pbtv_youtube_missing_api_key' );

			if ( $state['page_token'] && ! in_array( $page->get_error_code(), $transient, true ) && pbtv_youtube_quota_remaining() > 0 ) {
				$state['page_token'] = '';
				pbtv_save_video_backfill_state( $channel_id, $state );
			}

			return;
		}

		$state = array(
			'page_token'   => $page['next'],
			'completed_at' => $page['next'] ? 0 : time(),
		);
		pbtv_save_video_backfill_state( $channel_id, $state );
	}
}

/**
 * Gets how long a completed backfill rests before walking the uploads
 * playlist again to catch videos any earlier run missed.
 *
 * @return int Interval in seconds.
 */
function pbtv_video_backfill_rescan_interval(): int {
	/**
	 * Filters how long a completed video backfill rests before rescanning.
	 *
	 * @param int $interval Interval in seconds.
	 */
	return max( HOUR_IN_SECONDS, (int) apply_filters( 'pbtv_youtube_backfill_rescan_interval', WEEK_IN_SECONDS ) );
}

/**
 * Schedules the `pbtv_sync_youtube_videos` hourly cron event, replacing
 * the daily schedule earlier versions registered. Each run costs only a
 * couple of quota units once the channel is backfilled, so running hourly
 * gets new videos onto the site quickly while the daily quota budget
 * caps the backfill.
 *
 * @return void
 */
function pbtv_schedule_youtube_video_sync(): void {
	if ( wp_next_scheduled( 'pbtv_sync_youtube_videos' ) && 'hourly' !== wp_get_schedule( 'pbtv_sync_youtube_videos' ) ) {
		wp_clear_scheduled_hook( 'pbtv_sync_youtube_videos' );
	}

	if ( ! wp_next_scheduled( 'pbtv_sync_youtube_videos' ) ) {
		wp_schedule_event( time(), 'hourly', 'pbtv_sync_youtube_videos' );
	}
}
add_action( 'init', 'pbtv_schedule_youtube_video_sync' );

/**
 * Cron callback for `pbtv_sync_youtube_videos`: syncs the configured
 * YouTube channel's videos into the "videos" post type, so front-end
 * blocks and shortcodes can read from the local database instead of
 * calling the YouTube Data API on every pageview.
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
 * filters on both fields - until the video sync happened to include
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

/**
 * One-time backfill of the video_migrated_at meta for "videos" posts
 * imported before it existed. Those posts were all stamped with their
 * import time as the post date, so that date is exactly when each one
 * was migrated into WordPress. Runs before the date gets corrected to
 * the YouTube publish date by the daily sync.
 *
 * @return void
 */
function pbtv_backfill_video_migrated_at(): void {
	if ( get_option( 'pbtv_video_migrated_at_backfilled' ) ) {
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
					'key'     => 'video_migrated_at',
					'compare' => 'NOT EXISTS',
				),
			),
		)
	);

	foreach ( $posts as $post ) {
		update_post_meta( $post->ID, 'video_migrated_at', $post->post_date_gmt );
	}

	update_option( 'pbtv_video_migrated_at_backfilled', 1, false );
}
add_action( 'init', 'pbtv_backfill_video_migrated_at' );
