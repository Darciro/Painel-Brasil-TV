<?php
/**
 * Server-side render for the pbtv/highlights block.
 *
 * @var array<string, mixed> $attributes Block attributes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$heading    = isset( $attributes['heading'] ) ? sanitize_text_field( $attributes['heading'] ) : '';
$videos     = is_array( $attributes['videos'] ?? null ) ? $attributes['videos'] : array();
$show_title = isset( $attributes['showTitle'] ) ? (bool) $attributes['showTitle'] : true;
$format     = isset( $attributes['format'] ) && 'embed' === $attributes['format'] ? 'embed' : 'thumbnail';

$highlights = array();

foreach ( $videos as $video ) {
	$video_id = pbtv_extract_youtube_video_id( sanitize_text_field( (string) $video ) );

	if ( ! $video_id ) {
		continue;
	}

	$oembed = pbtv_get_youtube_video_oembed( $video_id );

	/*
	 * oEmbed carries no publish date, so it's read from the video's
	 * "videos" post, which is created (with its YouTube publish date)
	 * the first time the video is needed and reused from then on.
	 */
	$video_post = pbtv_get_or_create_video_post( $video_id );

	$highlights[] = array(
		'id'        => $video_id,
		'title'     => $oembed['title'] ?? '',
		'thumbnail' => $oembed['thumbnail'] ?? '',
		'published' => $video_post ? (string) get_post_time( DATE_W3C, true, $video_post ) : '',
	);
}

/**
 * Builds the single video page URL for a given video id.
 *
 * @param string $video_id YouTube video id.
 */
$pbtv_get_highlight_url = function ( string $video_id ): string {
	return home_url( '/videos/' . $video_id . '/' );
};

/**
 * Renders a highlight video as an iframe embed or a linked thumbnail,
 * depending on the block's "format" attribute.
 *
 * @param array<string, string> $highlight Highlight data with id, title
 *                                          and thumbnail keys.
 * @param string                $format    Either 'embed' or 'thumbnail'.
 */
$pbtv_render_highlight_media = function ( array $highlight, string $format ) use ( $pbtv_get_highlight_url ): void {
	$title = $highlight['title'] ? $highlight['title'] : __( 'YouTube video', 'pbtv' );

	if ( 'thumbnail' === $format && $highlight['thumbnail'] ) {
		?>
		<a
			class="video-thumbnail relative block w-full h-full"
			href="<?php echo esc_url( $pbtv_get_highlight_url( $highlight['id'] ) ); ?>"
			aria-label="<?php echo esc_attr( $title ); ?>"
		>
			<img
				class="w-full h-full object-cover"
				src="<?php echo esc_url( $highlight['thumbnail'] ); ?>"
				alt="<?php echo esc_attr( $title ); ?>"
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
		src="<?php echo esc_url( pbtv_get_youtube_embed_url( $highlight['id'] ) ); ?>"
		title="<?php echo esc_attr( $title ); ?>"
		loading="lazy"
		allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
		allowfullscreen
	></iframe>
	<?php
};
?>
<div <?php echo wp_kses_post( get_block_wrapper_attributes() ); ?>>
	<div class="container py-10 mx-auto px-4 xl:px-0">
		<?php if ( $heading ) : ?>
			<h3 class="text-pbtv-green uppercase font-bold mb-4"><?php echo esc_html( $heading ); ?></h3>
		<?php endif; ?>

		<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
			<?php if ( $highlights ) : ?>
				<?php foreach ( $highlights as $highlight ) : ?>
					<div class="highlight-video">
						<div class="aspect-video">
							<?php $pbtv_render_highlight_media( $highlight, $format ); ?>
						</div>
						<?php echo wp_kses_post( pbtv_render_video_date( $highlight['published'] ) ); ?>
						<?php if ( $show_title && $highlight['title'] ) : ?>
							<p class="text-sm font-semibold text-gray-900">
								<a href="<?php echo esc_url( $pbtv_get_highlight_url( $highlight['id'] ) ); ?>"><?php echo esc_html( $highlight['title'] ); ?></a>
							</p>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			<?php else : ?>
				<?php for ( $placeholder = 0; $placeholder < 3; $placeholder++ ) : ?>
					<div class="highlight-video highlight-video--placeholder" aria-hidden="true">
						<div class="aspect-video bg-gray-200 animate-pulse rounded"></div>
					</div>
				<?php endfor; ?>
			<?php endif; ?>
		</div>
	</div>
</div>
