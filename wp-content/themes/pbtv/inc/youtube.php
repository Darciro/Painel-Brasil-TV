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
 * Gets the YouTube channel ID synced by the video sync cron and used as
 * the default channel across blocks/shortcodes that don't specify one.
 *
 * @return string
 */
function pbtv_youtube_channel_id(): string {
	$channel_id = defined( 'PBTV_YOUTUBE_CHANNEL_ID' ) ? PBTV_YOUTUBE_CHANNEL_ID : 'UC-NaUVi7uxYTceNy6RIWTRw';

	/**
	 * Filters the default YouTube channel ID.
	 *
	 * @param string $channel_id The channel ID.
	 */
	return (string) apply_filters( 'pbtv_youtube_channel_id', $channel_id );
}

/**
 * Retrieves the latest videos from a YouTube channel's live area.
 *
 * Reads from the local "videos" custom post type, which the
 * `pbtv_sync_youtube_videos` cron event keeps in sync with the
 * YouTube Data API (see inc/post-types.php). Serving requests from this
 * local copy instead of calling the API on every pageview is what keeps
 * the site within the API's daily quota.
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

	return pbtv_get_synced_videos( $channel_id, max( 1, $max_results ) );
}

/**
 * Gets the number of YouTube Data API quota units the video sync may
 * spend per quota day.
 *
 * Defaults to 9,000 of the API's standard 10,000 daily units, leaving
 * headroom for the on-demand calls made outside the sync (see
 * pbtv_fetch_youtube_video_published_at()) and for anything else sharing
 * the same API key.
 *
 * @return int
 */
function pbtv_youtube_daily_quota_budget(): int {
	/**
	 * Filters the daily YouTube Data API quota budget of the video sync.
	 *
	 * @param int $budget Quota units per day.
	 */
	return max( 0, (int) apply_filters( 'pbtv_youtube_daily_quota_budget', 9000 ) );
}

/**
 * Gets the current YouTube Data API quota day. Google resets the daily
 * quota at midnight Pacific Time, so usage is bucketed by that date
 * rather than the site's own timezone.
 *
 * @return string Date in Y-m-d format.
 */
function pbtv_youtube_quota_day(): string {
	return ( new DateTimeImmutable( 'now', new DateTimeZone( 'America/Los_Angeles' ) ) )->format( 'Y-m-d' );
}

/**
 * Gets the quota units this site has spent on the current quota day.
 *
 * This is the site's own tally of the requests it made, not a figure
 * reported by Google, so it can't account for other consumers of the
 * same API key.
 *
 * @return int
 */
function pbtv_youtube_quota_used(): int {
	$usage = get_option( 'pbtv_youtube_quota_usage', array() );

	if ( ! is_array( $usage ) || ( $usage['day'] ?? '' ) !== pbtv_youtube_quota_day() ) {
		return 0;
	}

	return (int) ( $usage['units'] ?? 0 );
}

/**
 * Adds to the quota units spent on the current quota day.
 *
 * @param int $units Units spent.
 *
 * @return void
 */
function pbtv_youtube_record_quota_usage( int $units ): void {
	update_option(
		'pbtv_youtube_quota_usage',
		array(
			'day'   => pbtv_youtube_quota_day(),
			'units' => pbtv_youtube_quota_used() + $units,
		),
		false
	);
}

/**
 * Gets the quota units the video sync may still spend today.
 *
 * @return int
 */
function pbtv_youtube_quota_remaining(): int {
	return max( 0, pbtv_youtube_daily_quota_budget() - pbtv_youtube_quota_used() );
}

/**
 * Runs a single YouTube Data API GET request and records its quota cost.
 *
 * When Google reports the quota as exhausted, the local tally is pushed
 * up to the budget so the sync stops trying until the next quota day.
 *
 * @param string               $endpoint Endpoint name, e.g. 'videos'.
 * @param array<string, mixed> $args     Query arguments, without the key.
 * @param int                  $cost     Quota units the request costs.
 *
 * @return array<string, mixed>|WP_Error Decoded response body, or a
 *                                       WP_Error whose code is the API's
 *                                       error reason when it gave one.
 */
function pbtv_youtube_api_get( string $endpoint, array $args, int $cost = 1 ): array|WP_Error {
	$api_key = pbtv_youtube_api_key();

	if ( ! $api_key ) {
		return new WP_Error( 'pbtv_youtube_missing_api_key', 'The YouTube Data API key is not configured.' );
	}

	$url = add_query_arg(
		array_merge( $args, array( 'key' => $api_key ) ),
		'https://www.googleapis.com/youtube/v3/' . $endpoint
	);

	$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	// Google bills a request whether or not it succeeds.
	pbtv_youtube_record_quota_usage( $cost );

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	$body = is_array( $body ) ? $body : array();
	$code = (int) wp_remote_retrieve_response_code( $response );

	if ( 200 !== $code ) {
		$reason = (string) ( $body['error']['errors'][0]['reason'] ?? 'pbtv_youtube_http_' . $code );

		if ( in_array( $reason, array( 'quotaExceeded', 'dailyLimitExceeded' ), true ) ) {
			pbtv_youtube_record_quota_usage( pbtv_youtube_quota_remaining() );
		}

		return new WP_Error( $reason, (string) ( $body['error']['message'] ?? 'YouTube Data API request failed.' ) );
	}

	return $body;
}

/**
 * Gets the ID of a channel's "uploads" playlist, which lists every public
 * video on the channel (live broadcasts included), newest first.
 *
 * YouTube derives it from the channel ID by swapping the "UC" prefix for
 * "UU", which saves a `channels.list` request per sync.
 *
 * @param string $channel_id YouTube channel ID.
 *
 * @return string The playlist ID, or an empty string for an unexpected
 *                channel ID format.
 */
function pbtv_youtube_uploads_playlist_id( string $channel_id ): string {
	return str_starts_with( $channel_id, 'UC' ) ? 'UU' . substr( $channel_id, 2 ) : '';
}

/**
 * Fetches one page (up to 50 videos) of a channel's uploads playlist via
 * `playlistItems.list`, at 1 quota unit per page.
 *
 * Used instead of `search.list`, which costs 100 units per page and stops
 * paginating after roughly 500 results, so it can neither backfill a
 * large channel nor do so within the daily quota.
 *
 * @param string $playlist_id Uploads playlist ID.
 * @param string $page_token  Page token, or '' for the first (newest) page.
 *
 * @return array{ids: string[], next: string}|WP_Error The page's video IDs
 *                                                   and the next page's
 *                                                   token ('' on the last).
 */
function pbtv_fetch_youtube_uploads_page( string $playlist_id, string $page_token = '' ): array|WP_Error {
	$args = array(
		'part'       => 'contentDetails',
		'playlistId' => $playlist_id,
		'maxResults' => 50,
		'fields'     => 'nextPageToken,items/contentDetails/videoId',
	);

	if ( $page_token ) {
		$args['pageToken'] = $page_token;
	}

	$body = pbtv_youtube_api_get( 'playlistItems', $args );

	if ( is_wp_error( $body ) ) {
		return $body;
	}

	$ids = array();

	foreach ( (array) ( $body['items'] ?? array() ) as $item ) {
		$video_id = pbtv_extract_youtube_video_id( (string) ( $item['contentDetails']['videoId'] ?? '' ) );

		if ( $video_id ) {
			$ids[] = $video_id;
		}
	}

	return array(
		'ids'  => $ids,
		'next' => sanitize_text_field( (string) ( $body['nextPageToken'] ?? '' ) ),
	);
}

/**
 * Fetches the details of up to 50 videos via `videos.list`, at 1 quota
 * unit per request regardless of how many IDs it asks for.
 *
 * Private and deleted videos, which the uploads playlist can still list,
 * are simply absent from the response.
 *
 * @param string[] $video_ids YouTube video IDs, at most 50.
 *
 * @return array<int, array<string, string>>|WP_Error List of videos, each
 *                                                    with id, title,
 *                                                    thumbnail, status and
 *                                                    published keys.
 */
function pbtv_fetch_youtube_videos( array $video_ids ): array|WP_Error {
	if ( ! $video_ids ) {
		return array();
	}

	$body = pbtv_youtube_api_get(
		'videos',
		array(
			'part'       => 'snippet,liveStreamingDetails',
			'id'         => implode( ',', array_slice( $video_ids, 0, 50 ) ),
			'maxResults' => 50,
		)
	);

	if ( is_wp_error( $body ) ) {
		return $body;
	}

	$videos = array();

	foreach ( (array) ( $body['items'] ?? array() ) as $item ) {
		$video_id = pbtv_extract_youtube_video_id( (string) ( $item['id'] ?? '' ) );

		if ( ! $video_id ) {
			continue;
		}

		$snippet    = (array) ( $item['snippet'] ?? array() );
		$thumbnails = (array) ( $snippet['thumbnails'] ?? array() );
		$thumbnail  = $thumbnails['high']['url'] ?? ( $thumbnails['default']['url'] ?? '' );

		$videos[] = array(
			'id'        => $video_id,
			'title'     => sanitize_text_field( (string) ( $snippet['title'] ?? '' ) ),
			'thumbnail' => esc_url_raw( (string) $thumbnail ),
			'status'    => pbtv_youtube_video_status( $item ),
			'published' => sanitize_text_field( (string) ( $snippet['publishedAt'] ?? '' ) ),
		);
	}

	return $videos;
}

/**
 * Classifies a `videos.list` item into the statuses the local "videos"
 * posts use: 'live' (broadcasting now), 'upcoming' (scheduled broadcast
 * or premiere, not shown on the site), 'completed' (ended live
 * broadcast) or 'upload' (regular video).
 *
 * @param array<string, mixed> $item Raw `videos.list` item.
 *
 * @return string
 */
function pbtv_youtube_video_status( array $item ): string {
	$broadcast = (string) ( $item['snippet']['liveBroadcastContent'] ?? 'none' );

	if ( in_array( $broadcast, array( 'live', 'upcoming' ), true ) ) {
		return $broadcast;
	}

	return empty( $item['liveStreamingDetails']['actualEndTime'] ) ? 'upload' : 'completed';
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
 * Queries the YouTube Data API `videos.list` endpoint for a single
 * video's publish date, which the oEmbed endpoint doesn't expose.
 *
 * Costs one unit of the API's daily quota, so it's only called once per
 * video, when the /videos/{id}/ route first imports it (see
 * pbtv_get_or_create_video_post()).
 *
 * @param string $video_id YouTube video ID.
 *
 * @return string The ISO 8601 publish date, or an empty string when it
 *                could not be retrieved.
 */
function pbtv_fetch_youtube_video_published_at( string $video_id ): string {
	if ( ! $video_id ) {
		return '';
	}

	$body = pbtv_youtube_api_get(
		'videos',
		array(
			'part' => 'snippet',
			'id'   => $video_id,
		)
	);

	if ( is_wp_error( $body ) ) {
		return '';
	}

	return sanitize_text_field( (string) ( $body['items'][0]['snippet']['publishedAt'] ?? '' ) );
}

/**
 * Formats a raw live video for display, adding the single-video page URL
 * and embed URL the pbtv/latest-videos block's view script needs to build
 * its markup.
 *
 * @param array<string, string> $video Raw video, see pbtv_fetch_youtube_videos().
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
	) + pbtv_get_video_date_fields( (string) ( $video['published'] ?? '' ) );
}

/**
 * Formats a video's publish date for display: the human-readable date in
 * the site's configured date format and timezone, plus the ISO 8601 value
 * for a <time> element's datetime attribute.
 *
 * @param string $published Publish date in any strtotime()-parseable format.
 *
 * @return array{date: string, datetime: string} Both empty when the date
 *                                               is missing or unparseable.
 */
function pbtv_get_video_date_fields( string $published ): array {
	$timestamp = $published ? strtotime( $published ) : false;

	return array(
		'date'     => $timestamp ? (string) wp_date( get_option( 'date_format' ), $timestamp ) : '',
		'datetime' => $timestamp ? (string) wp_date( DATE_W3C, $timestamp ) : '',
	);
}

/**
 * Renders a video's publish date line, wrapping the display date in a
 * <time> element carrying the machine-readable datetime. Server-side
 * counterpart of buildDate() in the blocks' view scripts.
 *
 * @param string $published Publish date in any strtotime()-parseable format.
 * @param string $class     Classes for the wrapping paragraph.
 *
 * @return string The markup, or an empty string when there is no date.
 */
function pbtv_render_video_date( string $published, string $class = 'text-[10px] text-pbtv-green font-bold uppercase' ): string {
	$fields = pbtv_get_video_date_fields( $published );

	if ( ! $fields['date'] ) {
		return '';
	}

	return sprintf(
		'<p class="%1$s"><time datetime="%2$s">%3$s</time></p>',
		esc_attr( $class ),
		esc_attr( $fields['datetime'] ),
		esc_html( $fields['date'] )
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
