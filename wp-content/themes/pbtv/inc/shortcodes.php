<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
 * Renders the [pbtv-videos] shortcode.
 *
 * Usage: [pbtv-videos]
 *        [pbtv-videos count="15" columns="3"]
 *        [pbtv-videos channel_id="UCxxxxxxxxxxxxxxxxxxxxxx" count="12" columns="4"]
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
		),
		$atts,
		'pbtv-videos'
	);

	$channel_id = sanitize_text_field( (string) $atts['channel_id'] );
	$count      = max( 1, absint( $atts['count'] ) );
	$columns    = absint( $atts['columns'] );

	if ( ! $channel_id ) {
		if ( current_user_can( 'edit_theme_options' ) ) {
			return sprintf(
				'<p>%s</p>',
				esc_html__( 'Set a YouTube channel ID in the [pbtv-videos] shortcode to display the latest videos.', 'pbtv' )
			);
		}

		return '';
	}

	$videos = pbtv_get_youtube_live_videos( $channel_id, $count );

	if ( ! $videos ) {
		return '';
	}

	ob_start();
	?>
	<div class="pbtv-videos-shortcode">
		<div class="<?php echo esc_attr( pbtv_videos_shortcode_grid_class( $columns ) ); ?>">
			<?php foreach ( $videos as $video ) : ?>
				<a
					class="block rounded-xl overflow-hidden bg-white shadow-sm hover:shadow-md hover:-translate-y-0.5 transition"
					href="<?php echo esc_url( pbtv_get_youtube_watch_url( $video['id'] ) ); ?>"
					target="_blank"
					rel="noopener noreferrer"
				>
					<div class="relative aspect-video bg-gray-100">
						<?php if ( $video['thumbnail'] ) : ?>
							<img
								class="w-full h-full object-cover"
								src="<?php echo esc_url( $video['thumbnail'] ); ?>"
								alt=""
								loading="lazy"
							/>
						<?php endif; ?>
					</div>
					<div class="p-3">
						<p class="text-sm font-semibold text-gray-900 leading-snug line-clamp-3"><?php echo esc_html( $video['title'] ); ?></p>
					</div>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
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
