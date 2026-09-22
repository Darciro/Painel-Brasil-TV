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

$videos_page = get_page_by_path( 'videos' );
$videos_url  = $videos_page ? get_permalink( $videos_page ) : home_url( '/videos' );

/*
 * The videos are fetched client-side (see view.js) instead of here, so the
 * page's initial response never blocks on the YouTube Data API: this block
 * always renders its loading skeleton first, then the view script swaps in
 * the real videos once the pbtv/v1/latest-videos REST route responds.
 */
$context = array(
	'channelId'  => $channel_id,
	'maxResults' => max( 1, $max_results ),
	'format'     => $format,
	'restUrl'    => esc_url_raw( rest_url( 'pbtv/v1/latest-videos' ) ),
	'isLoading'  => true,
	'hasError'   => false,
	'errorLabel' => __( 'Não foi possível carregar os vídeos agora.', 'pbtv' ),
	'emptyLabel' => __( 'Nenhum vídeo encontrado.', 'pbtv' ),
);
?>
<div
	<?php echo wp_kses_post( get_block_wrapper_attributes() ); ?>
	data-wp-interactive="pbtv/latest-videos"
	data-wp-init="callbacks.init"
	<?php echo wp_interactivity_data_wp_context( $context ); ?>
>
	<?php if ( $heading ) : ?>
		<h3 class="text-pbtv-red uppercase font-bold mb-4"><?php echo esc_html( $heading ); ?></h3>
	<?php endif; ?>

	<p class="text-sm text-pbtv-red mb-4" data-wp-bind--hidden="!context.hasError" aria-live="polite"><?php echo esc_html( $context['errorLabel'] ); ?></p>

	<div class="grid grid-cols-1 md:grid-cols-[1fr_360px] gap-4" aria-hidden="true" data-wp-bind--hidden="!context.isLoading">
		<div class="live-video live-video--placeholder">
			<div class="aspect-video bg-gray-200 animate-pulse rounded"></div>
			<div class="mt-2 h-6 w-3/4 bg-gray-200 animate-pulse rounded"></div>
		</div>
		<div class="grid grid-cols-1 gap-4">
			<?php for ( $placeholder = 0; $placeholder < 2; $placeholder++ ) : ?>
				<div class="latest-video latest-video--placeholder">
					<div class="aspect-video bg-gray-200 animate-pulse rounded"></div>
					<div class="mt-2 h-5 w-2/3 bg-gray-200 animate-pulse rounded"></div>
				</div>
			<?php endfor; ?>
		</div>
	</div>

	<div
		class="latest-videos-grid grid grid-cols-1 md:grid-cols-[1fr_360px] gap-4"
		data-wp-bind--hidden="context.isLoading"
		aria-live="polite"
		data-empty-label="<?php echo esc_attr( $context['emptyLabel'] ); ?>"
	></div>

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
