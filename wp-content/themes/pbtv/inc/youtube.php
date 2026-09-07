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

	$cache_key = 'pbtv_yt_live_' . md5( $channel_id . '|' . $max_results );
	$cached    = get_transient( $cache_key );

	if ( false !== $cached ) {
		return $cached;
	}

	$videos = pbtv_fetch_youtube_live_videos( $channel_id, $max_results );

	set_transient( $cache_key, $videos, 5 * MINUTE_IN_SECONDS );

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
