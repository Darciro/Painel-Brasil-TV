<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The YouTube Data API caps a single search.list request at 50 results,
 * so this is the hard ceiling on how many videos [pbtv-videos] can ever
 * load, initial batch plus every infinite-scroll page combined.
 */
const PBTV_VIDEOS_SHORTCODE_MAX_RESULTS = 50;

/**
 * Maximum number of videos the infinite-scroll REST route will return in
 * a single page, regardless of the `count`/`offset` requested. Keeps the
 * public route from being used to force oversized YouTube API requests.
 */
const PBTV_VIDEOS_SHORTCODE_MAX_PER_PAGE = 30;

/**
 * Registers the [pbtv-videos] shortcode, which renders a responsive
 * column grid of a YouTube channel's latest videos, reusing the same
 * data source as the pbtv/latest-videos block.
 *
 * @return void
 */
function pbtv_register_shortcodes(): void {
	add_shortcode( 'pbtv-videos', 'pbtv_videos_shortcode' );
}

add_action( 'init', 'pbtv_register_shortcodes' );

/**
 * Registers the view script module that drives the [pbtv-videos]
 * shortcode's infinite scroll, so it can be enqueued on demand only
 * when a page actually renders more videos than fit in one page.
 *
 * @return void
 */
function pbtv_register_videos_shortcode_view_script(): void {
	wp_register_script_module(
		'pbtv-videos-shortcode-view',
		get_theme_file_uri( 'assets/js/videos-infinite-scroll.js' ),
		array( '@wordpress/interactivity' ),
		wp_get_theme()->get( 'Version' )
	);
}

add_action( 'init', 'pbtv_register_videos_shortcode_view_script' );

/**
 * Renders the [pbtv-videos] shortcode.
 *
 * Usage: [pbtv-videos]
 *        [pbtv-videos count="15" columns="3"]
 *        [pbtv-videos channel_id="UCxxxxxxxxxxxxxxxxxxxxxx" count="12" columns="4" per_page="15"]
 *
 * @param array<string, string>|string $atts Shortcode attributes.
 *
 * @return string
 */
function pbtv_videos_shortcode( $atts ): string {
	$atts = shortcode_atts(
		array(
			'channel_id' => 'UC-NaUVi7uxYTceNy6RIWTRw',
			'count'      => 15,
			'columns'    => 3,
			'per_page'   => 15,
		),
		$atts,
		'pbtv-videos'
	);

	$channel_id = sanitize_text_field( (string) $atts['channel_id'] );
	$count      = max( 1, min( PBTV_VIDEOS_SHORTCODE_MAX_RESULTS, absint( $atts['count'] ) ) );
	$columns    = absint( $atts['columns'] );
	$per_page   = max( 1, min( PBTV_VIDEOS_SHORTCODE_MAX_PER_PAGE, absint( $atts['per_page'] ) ) );

	if ( ! $channel_id ) {
		if ( current_user_can( 'edit_theme_options' ) ) {
			return sprintf(
				'<p>%s</p>',
				esc_html__( 'Set a YouTube channel ID in the [pbtv-videos] shortcode to display the latest videos.', 'pbtv' )
			);
		}

		return '';
	}

	/*
	 * Always fetch (and cache) the same fixed-size batch, up to the hard
	 * cap, so every page of this channel's videos - the initial batch and
	 * every infinite-scroll page - is sliced from one consistent snapshot
	 * instead of triggering a separate, independently-cached YouTube API
	 * request per distinct count/offset combination.
	 */
	$all_videos = pbtv_get_youtube_live_videos( $channel_id, PBTV_VIDEOS_SHORTCODE_MAX_RESULTS );
	$videos     = array_slice( $all_videos, 0, $count );

	if ( ! $videos ) {
		ob_start();
		?>
		<div class="pbtv-videos-shortcode">
			<div class="<?php echo esc_attr( pbtv_videos_shortcode_grid_class( $columns ) ); ?>" aria-hidden="true">
				<?php for ( $placeholder = 0; $placeholder < $columns; $placeholder++ ) : ?>
					<div class="pbtv-videos-shortcode__placeholder">
						<div class="aspect-video bg-gray-200 animate-pulse rounded"></div>
						<div class="py-3">
							<div class="h-4 w-3/4 bg-gray-200 animate-pulse rounded"></div>
						</div>
					</div>
				<?php endfor; ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	$has_more = count( $all_videos ) > count( $videos );

	if ( $has_more ) {
		wp_enqueue_script_module( 'pbtv-videos-shortcode-view' );
	}

	$context = array(
		'channelId' => $channel_id,
		'offset'    => count( $videos ),
		'perPage'   => $per_page,
		'hasMore'   => $has_more,
		'isLoading' => false,
		'hasError'  => false,
		'restUrl'   => esc_url_raw( rest_url( 'pbtv/v1/videos' ) ),
	);

	ob_start();
	?>
	<div
		class="pbtv-videos-shortcode"
		<?php if ( $has_more ) : ?>
			data-wp-interactive="pbtv/videos"
			<?php echo wp_interactivity_data_wp_context( $context ); ?>
			data-wp-init="callbacks.initInfiniteScroll"
		<?php endif; ?>
	>
		<div class="pbtv-videos-shortcode__grid <?php echo esc_attr( pbtv_videos_shortcode_grid_class( $columns ) ); ?>">
			<?php foreach ( $videos as $video ) : ?>
				<?php echo pbtv_render_video_card( $video ); ?>
			<?php endforeach; ?>
		</div>

		<?php if ( $has_more ) : ?>
			<p
				class="text-center text-sm text-pbtv-red mt-4"
				data-wp-bind--hidden="!context.hasError"
				aria-live="polite"
			>
				<?php esc_html_e( 'Could not load more videos right now.', 'pbtv' ); ?>
			</p>

			<div
				class="flex justify-center py-8"
				data-wp-bind--hidden="!context.isLoading"
				aria-hidden="true"
			>
				<span class="h-8 w-8 rounded-full border-2 border-gray-300 border-t-pbtv-red animate-spin"></span>
			</div>

			<div class="pbtv-videos-shortcode__sentinel" aria-hidden="true"></div>
		<?php endif; ?>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * Renders a single video card: a linked thumbnail and title, used both
 * for the shortcode's initial server-rendered batch and reused in
 * spirit (as JSON) by the infinite-scroll REST route.
 *
 * @param array<string, string> $video Video data with id, title and
 *                                      thumbnail keys.
 *
 * @return string
 */
function pbtv_render_video_card( array $video ): string {
	ob_start();
	?>
	<a
		class="block hover:-translate-y-0.5 transition"
		href="<?php echo esc_url( home_url( '/videos/' . $video['id'] . '/' ) ); ?>"
	>
		<div class="relative aspect-video bg-gray-100">
			<?php if ( ! empty( $video['thumbnail'] ) ) : ?>
				<img
					class="w-full h-full object-cover"
					src="<?php echo esc_url( $video['thumbnail'] ); ?>"
					alt=""
					loading="lazy"
				/>
			<?php endif; ?>
		</div>
		<div class="py-3">
			<p class="text-sm font-bold text-pbtv-red leading-snug line-clamp-3"><?php echo esc_html( $video['title'] ); ?></p>
		</div>
	</a>
	<?php
	return (string) ob_get_clean();
}

/**
 * Maps a requested column count to a fixed set of responsive Tailwind
 * grid classes, keeping every class name a literal string so it is
 * picked up by Tailwind's static source scanning.
 *
 * @param int $columns Requested number of columns at the largest breakpoint.
 *
 * @return string
 */
function pbtv_videos_shortcode_grid_class( int $columns ): string {
	return match ( $columns ) {
		2 => 'grid grid-cols-1 sm:grid-cols-2 gap-4',
		4 => 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4',
		5 => 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4',
		default => 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4',
	};
}

/**
 * Registers the REST route the [pbtv-videos] shortcode's view script
 * uses to fetch additional videos as the visitor scrolls to the end of
 * the grid, without a full page reload.
 *
 * @return void
 */
function pbtv_register_videos_rest_route(): void {
	register_rest_route(
		'pbtv/v1',
		'/videos',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'pbtv_rest_get_more_videos',
			'permission_callback' => '__return_true',
			'args'                => array(
				'channelId' => array(
					'type'              => 'string',
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'pbtv_rest_validate_videos_channel_id',
				),
				'offset'    => array(
					'type'              => 'integer',
					'default'           => 0,
					'sanitize_callback' => 'absint',
				),
				'count'     => array(
					'type'              => 'integer',
					'default'           => 15,
					'sanitize_callback' => 'absint',
				),
			),
		)
	);
}

add_action( 'rest_api_init', 'pbtv_register_videos_rest_route' );

/**
 * Validates a `channelId` REST parameter against the YouTube channel ID
 * format (a "UC" prefix followed by 22 URL-safe characters), so the
 * public route can't be used to make the server fetch arbitrary values.
 *
 * @param string $value Parameter value.
 *
 * @return bool
 */
function pbtv_rest_validate_videos_channel_id( string $value ): bool {
	return 1 === preg_match( '/^UC[A-Za-z0-9_-]{22}$/', $value );
}

/**
 * REST callback returning the next page of videos for the [pbtv-videos]
 * shortcode's infinite scroll.
 *
 * @param WP_REST_Request $request REST request.
 *
 * @return WP_REST_Response
 */
function pbtv_rest_get_more_videos( WP_REST_Request $request ): WP_REST_Response {
	$channel_id = (string) $request->get_param( 'channelId' );
	$offset     = absint( $request->get_param( 'offset' ) );
	$count      = max( 1, min( PBTV_VIDEOS_SHORTCODE_MAX_PER_PAGE, absint( $request->get_param( 'count' ) ) ) );

	/*
	 * Sliced from the same fixed-size, single-cache-key batch the
	 * shortcode's initial render uses (see pbtv_videos_shortcode()), so
	 * every page lines up with what's already on screen instead of each
	 * offset triggering its own, independently-cached YouTube API call.
	 */
	$all_videos = pbtv_get_youtube_live_videos( $channel_id, PBTV_VIDEOS_SHORTCODE_MAX_RESULTS );
	$page       = array_slice( $all_videos, $offset, $count );

	return new WP_REST_Response(
		array(
			'videos'  => array_map( 'pbtv_videos_shortcode_prepare_item_for_display', $page ),
			'hasMore' => ( $offset + count( $page ) ) < count( $all_videos ),
		)
	);
}

/**
 * Shapes a video for the infinite-scroll REST response. The client
 * builds DOM nodes from this via textContent/attribute assignment
 * (never innerHTML), so no HTML escaping is done here beyond what
 * pbtv_get_youtube_live_videos() already applies when caching results.
 *
 * @param array<string, string> $video Video data with id, title and
 *                                      thumbnail keys.
 *
 * @return array<string, string>
 */
function pbtv_videos_shortcode_prepare_item_for_display( array $video ): array {
	return array(
		'title'     => $video['title'],
		'thumbnail' => $video['thumbnail'],
		'url'       => home_url( '/videos/' . $video['id'] . '/' ),
	);
}
