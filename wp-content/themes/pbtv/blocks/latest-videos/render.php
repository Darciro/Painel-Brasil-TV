<?php
/**
 * Server-side render for the pbtv/latest-videos block.
 *
 * @var array<string, mixed> $attributes Block attributes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$channel_id  = isset( $attributes['channelId'] ) ? sanitize_text_field( $attributes['channelId'] ) : '';
$max_results = isset( $attributes['maxResults'] ) ? absint( $attributes['maxResults'] ) : 3;
$heading     = isset( $attributes['heading'] ) ? sanitize_text_field( $attributes['heading'] ) : '';
$format      = isset( $attributes['format'] ) && 'embed' === $attributes['format'] ? 'embed' : 'thumbnail';

if ( ! $channel_id ) {
	if ( is_admin() ) {
		printf(
			'<p %s>%s</p>',
			wp_kses_post( get_block_wrapper_attributes() ),
			esc_html__( 'Set a YouTube channel ID in the block settings to display the latest live videos.', 'pbtv' )
		);
	}

	return;
}

$videos = pbtv_get_youtube_live_videos( $channel_id, max( 1, $max_results ) );

if ( ! $videos ) {
	return;
}

$primary     = array_shift( $videos );
$secondary   = $videos;
$videos_page = get_page_by_path( 'videos' );
$videos_url  = $videos_page ? get_permalink( $videos_page ) : home_url( '/videos' );

/**
 * Renders a video as an iframe embed or a linked thumbnail, depending on
 * the block's "format" attribute.
 *
 * @param array<string, string> $video Video data with id, title and
 *                                      thumbnail keys.
 * @param string                $format Either 'embed' or 'thumbnail'.
 */
$pbtv_render_latest_video_media = function ( array $video, string $format ): void {
	if ( 'thumbnail' === $format ) {
		?>
		<a
			class="video-thumbnail relative block w-full h-full"
			href="<?php echo esc_url( home_url( '/videos/' . $video['id'] . '/' ) ); ?>"
			aria-label="<?php echo esc_attr( $video['title'] ); ?>"
		>
			<img
				class="w-full h-full object-cover"
				src="<?php echo esc_url( $video['thumbnail'] ); ?>"
				alt="<?php echo esc_attr( $video['title'] ); ?>"
				loading="lazy"
			/>
			<span class="video-thumbnail__play" aria-hidden="true"></span>
		</a>
		<?php
		return;
	}
	?>
	<iframe
		class="w-full h-full"
		src="<?php echo esc_url( pbtv_get_youtube_embed_url( $video['id'] ) ); ?>"
		title="<?php echo esc_attr( $video['title'] ); ?>"
		loading="lazy"
		allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
		allowfullscreen
	></iframe>
	<?php
};
?>
<div <?php echo wp_kses_post( get_block_wrapper_attributes() ); ?>>
	<?php if ( $heading ) : ?>
		<h3 class="text-pbtv-red uppercase font-bold mb-4"><?php echo esc_html( $heading ); ?></h3>
	<?php endif; ?>

	<div class="grid grid-cols-1 md:grid-cols-[1fr_360px] gap-4">
		<div class="live-video">
			<div class="aspect-video">
				<?php $pbtv_render_latest_video_media( $primary, $format ); ?>
			</div>
			<h2 class="mt-2 text-2xl font-bold text-pbtv-red"><?php echo esc_html( $primary['title'] ); ?></h2>
		</div>

		<?php if ( $secondary ) : ?>
			<div class="grid grid-cols-1 gap-4">
				<?php foreach ( $secondary as $video ) : ?>
					<div class="latest-video">
						<div class="aspect-video">
							<?php $pbtv_render_latest_video_media( $video, $format ); ?>
						</div>
						<h2 class="mt-2 text-lg font-bold text-pbtv-red"><?php echo esc_html( $video['title'] ); ?></h2>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>

	<div class="mt-8">
		<a href="<?php echo esc_url( $videos_url ); ?>" class="font-bold text-white uppercase bg-pbtv-green ps-4 pe-16 py-2 rounded relative inline-block">Mais vídeos 
			<svg class="inline-block absolute right-4 top-1/2 transform -translate-y-1/2 max-w-9 fill-white" data-bbox="9 70.9 181 59" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200">
    			<g>
        			<path d="M159 70.9l-2.2 2.4L183.6 99H9v3h174.6l-26.2 25.3 2.1 2.6 30.5-29.3-31-29.7z"></path>
    			</g>
			</svg>
		</a>
	</div>
</div>
