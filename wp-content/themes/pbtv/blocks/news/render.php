<?php
/**
 * Server-side render for the pbtv/news block.
 *
 * @var array<string, mixed> $attributes Block attributes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$heading   = isset( $attributes['heading'] ) ? sanitize_text_field( $attributes['heading'] ) : '';
$max_items = isset( $attributes['maxItems'] ) ? absint( $attributes['maxItems'] ) : 8;

$items = pbtv_get_news_items( max( 1, $max_items ) );

if ( ! $items ) {
	if ( is_admin() ) {
		printf(
			'<p %s>%s</p>',
			wp_kses_post( get_block_wrapper_attributes() ),
			esc_html__( 'No news items available at the moment.', 'pbtv' )
		);
	}

	return;
}
?>
<div <?php echo wp_kses_post( get_block_wrapper_attributes() ); ?>>
	<?php if ( $heading ) : ?>
		<div class="flex items-center gap-2 mb-4">
			<span class="w-2 h-2 rounded-full bg-pbtv-red" aria-hidden="true"></span>
			<h3 class="text-pbtv-green uppercase font-bold"><?php echo esc_html( $heading ); ?></h3>
		</div>
	<?php endif; ?>

	<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
		<?php foreach ( $items as $item ) : ?>
			<a
				class="block rounded-xl overflow-hidden bg-white shadow-sm hover:shadow-md hover:-translate-y-0.5 transition"
				href="<?php echo esc_url( $item['link'] ); ?>"
				target="_blank"
				rel="noopener noreferrer"
			>
				<div class="relative aspect-video bg-gray-100">
					<?php if ( $item['image'] ) : ?>
						<img
							class="w-full h-full object-cover"
							src="<?php echo esc_url( $item['image'] ); ?>"
							alt=""
							loading="lazy"
						/>
					<?php else : ?>
						<div
							class="w-full h-full flex items-center justify-center text-white text-3xl font-bold"
							style="background-color: <?php echo esc_attr( $item['color'] ); ?>;"
						>
							<?php echo esc_html( mb_substr( $item['source'], 0, 1 ) ); ?>
						</div>
					<?php endif; ?>
					<span class="absolute top-2 left-2 rounded-full bg-black/65 text-white text-[10px] font-bold uppercase tracking-wide px-2 py-1"><?php echo esc_html( $item['source'] ); ?></span>
					<span class="absolute top-2 right-2 rounded-full bg-white/90 text-gray-900 text-[10px] font-bold uppercase tracking-wide px-2 py-1"><?php echo esc_html( $item['topic'] ); ?></span>
				</div>
				<div class="p-3">
					<p class="text-sm font-semibold text-gray-900 leading-snug mb-2 line-clamp-3"><?php echo esc_html( $item['title'] ); ?></p>
					<p class="text-xs text-gray-500"><?php echo esc_html( wp_date( 'd M, H:i', $item['timestamp'] ) ); ?></p>
				</div>
			</a>
		<?php endforeach; ?>
	</div>
</div>
