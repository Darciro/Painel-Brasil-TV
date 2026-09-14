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

$primary   = array_shift( $videos );
$secondary = $videos;
?>
<div <?php echo wp_kses_post( get_block_wrapper_attributes() ); ?>>
	<?php if ( $heading ) : ?>
		<h3 class="text-pbtv-red uppercase font-bold mb-4"><?php echo esc_html( $heading ); ?></h3>
	<?php endif; ?>

	<div class="grid grid-cols-1 md:grid-cols-[1fr_360px] gap-4">
		<div class="live-video">
			<div class="aspect-video">
				<iframe
					class="w-full h-full"
					src="<?php echo esc_url( pbtv_get_youtube_embed_url( $primary['id'] ) ); ?>"
					title="<?php echo esc_attr( $primary['title'] ); ?>"
					loading="lazy"
					allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
					allowfullscreen
				></iframe>
			</div>
			<h2 class="mt-2 text-2xl font-bold text-pbtv-red"><?php echo esc_html( $primary['title'] ); ?></h2>
		</div>

		<?php if ( $secondary ) : ?>
			<div class="grid grid-cols-1 gap-4">
				<?php foreach ( $secondary as $video ) : ?>
					<div class="latest-video">
						<div class="aspect-video">
							<iframe
								class="w-full h-full"
								src="<?php echo esc_url( pbtv_get_youtube_embed_url( $video['id'] ) ); ?>"
								title="<?php echo esc_attr( $video['title'] ); ?>"
								loading="lazy"
								allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
								allowfullscreen
							></iframe>
						</div>
						<h2 class="mt-2 text-lg font-bold text-pbtv-red"><?php echo esc_html( $video['title'] ); ?></h2>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</div>
