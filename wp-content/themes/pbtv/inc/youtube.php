<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gets the Google API key used to query the YouTube Data API.
 *
 * Reads the PBTV_YOUTUBE_API_KEY constant, which should be defined in
 * wp-config.php (or another environment-specific config) and must never
 * be committed to the repository.
 *
 * @return string
 */
function pbtv_youtube_api_key(): string {
	$api_key = defined( 'PBTV_YOUTUBE_API_KEY' ) ? PBTV_YOUTUBE_API_KEY : '';

	/**
	 * Filters the Google API key used to query the YouTube Data API.
	 *
	 * @param string $api_key The API key.
	 */
	return (string) apply_filters( 'pbtv_youtube_api_key', $api_key );
}

/**
 * Retrieves the latest videos from a YouTube channel's live area.
 *
 * Looks first for a broadcast currently live, then fills any remaining
 * slots with the channel's most recent completed live broadcasts, and
 * finally with the channel's most recent uploads if it has no live
 * broadcasts at all. Results are cached in a transient so the YouTube
 * Data API is not queried on every pageview.
 *
 * @param string $channel_id  YouTube channel ID.
 * @param int    $max_results Maximum number of videos to return.
 *
 * @return array<int, array<string, string>> List of videos, each with
 *                                            id, title, thumbnail and
 *                                            status keys.
 */
function pbtv_get_youtube_live_videos( string $channel_id, int $max_results = 3 ): array {
	if ( ! $channel_id ) {
		return array();
	}

	// The YouTube Data API caps a single search.list request at 50 results.
	$max_results = min( 50, max( 1, $max_results ) );

	$cache_key = 'pbtv_yt_live_' . md5( $channel_id . '|' . $max_results );
	$cached    = get_transient( $cache_key );

	if ( false !== $cached ) {
		return $cached;
	}

	$videos = pbtv_fetch_youtube_live_videos( $channel_id, $max_results );

	// Cache failures/empty results briefly so a down API isn't hit on every pageview.
	$ttl = $videos ? 5 * MINUTE_IN_SECONDS : MINUTE_IN_SECONDS;

	set_transient( $cache_key, $videos, $ttl );

	return $videos;
}

/**
 * Queries the YouTube Data API for a channel's live broadcasts, falling
 * back to its most recent uploads when it has none.
 *
 * @param string $channel_id  YouTube channel ID.
 * @param int    $max_results Maximum number of videos to return.
 *
 * @return array<int, array<string, string>>
 */
function pbtv_fetch_youtube_live_videos( string $channel_id, int $max_results ): array {
	$api_key = pbtv_youtube_api_key();

	if ( ! $api_key ) {
		return array();
	}

	$videos = pbtv_query_youtube_search( $channel_id, $api_key, 'live', $max_results );

	foreach ( array( 'completed', '' ) as $event_type ) {
		$remaining = $max_results - count( $videos );

		if ( $remaining <= 0 ) {
			break;
		}

		$found  = pbtv_query_youtube_search( $channel_id, $api_key, $event_type, $remaining );
		$videos = array_merge( $videos, $found );
	}

	return array_slice( $videos, 0, $max_results );
}

/**
 * Runs a single YouTube Data API `search.list` request scoped to a
 * channel, optionally filtered to a live event type.
 *
 * @param string $channel_id  YouTube channel ID.
 * @param string $api_key     Google API key.
 * @param string $event_type  'live', 'completed', or '' for any video
 *                             (the channel's most recent uploads).
 * @param int    $max_results Maximum number of results to request.
 *
 * @return array<int, array<string, string>>
 */
function pbtv_query_youtube_search( string $channel_id, string $api_key, string $event_type, int $max_results ): array {
	if ( $max_results < 1 ) {
		return array();
	}

	$args = array(
		'part'       => 'snippet',
		'channelId'  => $channel_id,
		'type'       => 'video',
		'order'      => 'date',
		'maxResults' => $max_results,
		'key'        => $api_key,
	);

	if ( $event_type ) {
		$args['eventType'] = $event_type;
	}

	$url = add_query_arg( $args, 'https://www.googleapis.com/youtube/v3/search' );

	$response = wp_remote_get(
		$url,
		array(
			'timeout' => 5,
		)
	);

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return array();
	}

	$body  = json_decode( wp_remote_retrieve_body( $response ), true );
	$items = is_array( $body['items'] ?? null ) ? $body['items'] : array();

	$videos = array();

	foreach ( $items as $item ) {
		$video_id = $item['id']['videoId'] ?? '';

		if ( ! $video_id ) {
			continue;
		}

		$thumbnails = $item['snippet']['thumbnails'] ?? array();
		$thumbnail  = $thumbnails['high']['url'] ?? ( $thumbnails['default']['url'] ?? '' );

		$videos[] = array(
			'id'        => sanitize_text_field( $video_id ),
			'title'     => sanitize_text_field( $item['snippet']['title'] ?? '' ),
			'thumbnail' => esc_url_raw( $thumbnail ),
			'status'    => $event_type ? $event_type : 'upload',
		);
	}

	return $videos;
}

/**
 * Builds a YouTube embed URL with the player parameters that strip
 * back the extra UI YouTube overlays on top of the video itself:
 * end-of-video suggestions from other channels, the "watch on YouTube"
 * branding, and annotation cards.
 *
 * @param string $video_id YouTube video ID.
 *
 * @return string The embed URL.
 */
function pbtv_get_youtube_embed_url( string $video_id ): string {
	return add_query_arg(
		array(
			'rel'            => 0,
			'modestbranding' => 1,
			'iv_load_policy' => 3,
		),
		'https://www.youtube.com/embed/' . $video_id
	);
}

/**
 * Builds a canonical YouTube watch URL for a video ID.
 *
 * @param string $video_id YouTube video ID.
 *
 * @return string The watch URL.
 */
function pbtv_get_youtube_watch_url( string $video_id ): string {
	return add_query_arg( 'v', $video_id, 'https://www.youtube.com/watch' );
}

/**
 * Extracts an 11-character YouTube video ID from a watch/embed/short
 * URL, a youtu.be link, or a bare ID.
 *
 * @param string $input Raw video URL or ID.
 *
 * @return string The video ID, or an empty string when none was found.
 */
function pbtv_extract_youtube_video_id( string $input ): string {
	$input = trim( $input );

	if ( preg_match( '/^[A-Za-z0-9_-]{11}$/', $input ) ) {
		return $input;
	}

	if ( preg_match( '#(?:youtube\.com/(?:watch\?v=|embed/|live/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})#', $input, $matches ) ) {
		return $matches[1];
	}

	return '';
}

/**
 * Retrieves a YouTube video's title and thumbnail via the public oEmbed
 * endpoint, which requires no API key. Results are cached in a transient
 * since a video's metadata rarely changes.
 *
 * @param string $video_id YouTube video ID.
 *
 * @return array<string, string> Array with title and thumbnail keys,
 *                                empty when the video could not be
 *                                resolved.
 */
function pbtv_get_youtube_video_oembed( string $video_id ): array {
	if ( ! $video_id ) {
		return array();
	}

	$cache_key = 'pbtv_yt_oembed_' . $video_id;
	$cached    = get_transient( $cache_key );

	if ( false !== $cached ) {
		return $cached;
	}

	$data = pbtv_fetch_youtube_video_oembed( $video_id );

	// Cache a resolved video for a day, but retry sooner if oEmbed failed.
	$ttl = $data ? DAY_IN_SECONDS : 5 * MINUTE_IN_SECONDS;

	set_transient( $cache_key, $data, $ttl );

	return $data;
}

/**
 * Queries the YouTube oEmbed endpoint for a single video's metadata.
 *
 * @param string $video_id YouTube video ID.
 *
 * @return array<string, string>
 */
function pbtv_fetch_youtube_video_oembed( string $video_id ): array {
	$url = add_query_arg(
		array(
			'url'    => 'https://www.youtube.com/watch?v=' . $video_id,
			'format' => 'json',
		),
		'https://www.youtube.com/oembed'
	);

	$response = wp_remote_get( $url, array( 'timeout' => 5 ) );

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return array();
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! is_array( $body ) ) {
		return array();
	}

	return array(
		'title'     => sanitize_text_field( $body['title'] ?? '' ),
		'thumbnail' => esc_url_raw( $body['thumbnail_url'] ?? '' ),
	);
}

/**
 * Formats a raw live video for display, adding the single-video page URL
 * and embed URL the pbtv/latest-videos block's view script needs to build
 * its markup.
 *
 * @param array<string, string> $video Raw video, see pbtv_query_youtube_search().
 *
 * @return array<string, string> Video ready for display.
 */
function pbtv_latest_video_prepare_item_for_display( array $video ): array {
	return array(
		'id'        => (string) $video['id'],
		'title'     => (string) $video['title'],
		'thumbnail' => (string) $video['thumbnail'],
		'url'       => home_url( '/videos/' . $video['id'] . '/' ),
		'embedUrl'  => pbtv_get_youtube_embed_url( $video['id'] ),
	);
}

/**
 * Registers the REST route the pbtv/latest-videos block's view script uses
 * to fetch a channel's latest live videos client-side, keeping the YouTube
 * Data API calls off the page's initial render so the block's loading
 * skeleton is what visitors actually see while they load.
 *
 * @return void
 */
function pbtv_register_latest_videos_rest_route(): void {
	register_rest_route(
		'pbtv/v1',
		'/latest-videos',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'pbtv_rest_get_latest_videos',
			'permission_callback' => '__return_true',
			'args'                => array(
				'channelId'  => array(
					'type'              => 'string',
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
				),
				'maxResults' => array(
					'type'              => 'integer',
					'default'           => 3,
					'sanitize_callback' => 'absint',
				),
			),
		)
	);
}

add_action( 'rest_api_init', 'pbtv_register_latest_videos_rest_route' );

/**
 * REST callback returning a channel's latest live videos for the
 * pbtv/latest-videos block's client-side fetch.
 *
 * @param WP_REST_Request $request REST request.
 *
 * @return WP_REST_Response
 */
function pbtv_rest_get_latest_videos( WP_REST_Request $request ): WP_REST_Response {
	$channel_id  = (string) $request->get_param( 'channelId' );
	$max_results = max( 1, min( 50, absint( $request->get_param( 'maxResults' ) ) ) );

	if ( ! $channel_id ) {
		return new WP_REST_Response( array( 'videos' => array() ) );
	}

	$videos = pbtv_get_youtube_live_videos( $channel_id, $max_results );

	return new WP_REST_Response(
		array(
			'videos' => array_map( 'pbtv_latest_video_prepare_item_for_display', $videos ),
		)
	);
}
