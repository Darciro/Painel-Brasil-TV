<?php
/**
 * Server-side render for the pbtv/about block.
 *
 * @var array<string, mixed> $attributes Block attributes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$image_url   = isset( $attributes['imageUrl'] ) ? esc_url_raw( $attributes['imageUrl'] ) : '';
$image_alt   = isset( $attributes['imageAlt'] ) ? sanitize_text_field( $attributes['imageAlt'] ) : '';
$heading     = isset( $attributes['heading'] ) ? sanitize_text_field( $attributes['heading'] ) : '';
$description = isset( $attributes['description'] ) ? sanitize_textarea_field( $attributes['description'] ) : '';

if ( ! $image_url && ! $heading && ! $description ) {
	if ( is_admin() ) {
		printf(
			'<p %s>%s</p>',
			wp_kses_post( get_block_wrapper_attributes() ),
			esc_html__( 'Add a portrait image, a heading, and a description to display the about section.', 'pbtv' )
		);
	}

	return;
}
?>
<div <?php echo wp_kses_post( get_block_wrapper_attributes() ); ?>>
	<div class="grid grid-cols-1 md:grid-cols-[auto_1fr] gap-6 items-center mx-auto">
		<?php if ( $image_url ) : ?>
			<img
				class="rounded-full w-64 h-64 object-cover mx-auto"
				src="<?php echo esc_url( $image_url ); ?>"
				alt="<?php echo esc_attr( $image_alt ); ?>"
				loading="lazy"
			/>
		<?php endif; ?>

		<div>
			<?php if ( $heading ) : ?>
				<h2 class="wp-block-heading text-pbtv-red font-bold text-4xl mb-4 text-center md:text-left"><?php echo esc_html( $heading ); ?></h2>
			<?php endif; ?>

			<?php if ( $description ) : ?>
				<p><?php echo esc_html( $description ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</div>
