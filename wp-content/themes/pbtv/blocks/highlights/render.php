<?php
/**
 * Server-side render for the pbtv/highlights block.
 *
 * @var array<string, mixed> $attributes Block attributes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$heading = isset( $attributes['heading'] ) ? sanitize_text_field( $attributes['heading'] ) : '';
$videos  = is_array( $attributes['videos'] ?? null ) ? $attributes['videos'] : array();

$highlights = array();

foreach ( $videos as $video ) {
	$video_id = pbtv_extract_youtube_video_id( sanitize_text_field( (string) $video ) );

	if ( ! $video_id ) {
		continue;
	}

	$oembed = pbtv_get_youtube_video_oembed( $video_id );

	$highlights[] = array(
		'id'    => $video_id,
		'title' => $oembed['title'] ?? '',
	);
}

?>
<div <?php echo wp_kses_post( get_block_wrapper_attributes() ); ?>>
	<?php if ( $heading ) : ?>
		<h3 class="text-pbtv-green uppercase font-bold mb-4"><?php echo esc_html( $heading ); ?></h3>
	<?php endif; ?>

	<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
		<?php if ( $highlights ) : ?>
			<?php foreach ( $highlights as $highlight ) : ?>
				<div class="highlight-video">
					<div class="aspect-video">
						<iframe
							class="w-full h-full"
							src="<?php echo esc_url( pbtv_get_youtube_embed_url( $highlight['id'] ) ); ?>"
							title="<?php echo esc_attr( $highlight['title'] ? $highlight['title'] : __( 'YouTube video', 'pbtv' ) ); ?>"
							loading="lazy"
							allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
							allowfullscreen
						></iframe>
					</div>
					<?php if ( $highlight['title'] ) : ?>
						<p class="mt-2 text-sm font-semibold text-gray-900"><?php echo esc_html( $highlight['title'] ); ?></p>
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
