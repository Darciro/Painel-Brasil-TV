<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$video_id = get_query_var( 'pbtv_video_id' );

if (
	! is_string( $video_id ) ||
	! preg_match( '/^[A-Za-z0-9_-]{11}$/', $video_id )
) {
	status_header( 404 );
	nocache_headers();

	exit;
}

$embed_url = sprintf(
	'https://www.youtube-nocookie.com/embed/%s',
	rawurlencode( $video_id )
);

$video_post = pbtv_get_video_post( $video_id );

if ( $video_post ) {
	global $post;

	$post = $video_post;

	setup_postdata( $post );
}

$video_title = $video_post ? get_the_title( $video_post ) : __( 'Video', 'pbtv' );

$video_comments        = array();
$video_comments_number = 0;

if ( $video_post ) {
	$video_comments_number = get_comments_number( $video_post );

	if ( $video_comments_number || comments_open( $video_post ) ) {
		$video_comments = get_comments(
			array(
				'post_id' => $video_post->ID,
				'status'  => 'approve',
				'order'   => 'ASC',
			)
		);
	}
}
?>

<!doctype html>

<html <?php language_attributes(); ?>>

<head>

	<meta charset="<?php bloginfo( 'charset' ); ?>">

	<meta
		name="viewport"
		content="width=device-width, initial-scale=1"
	>

	<?php wp_head(); ?>

</head>

<body <?php body_class( 'videos-template pbtv-video-page' ); ?>>

<?php wp_body_open(); ?>

<?php echo do_blocks( '<!-- wp:template-part {"slug":"header","className":"sticky top-0 z-[1000] bg-white"} /-->' ); ?>

<main class="wp-block-group" aria-labelledby="pbtv-video-title">

	<div class="wp-block-group container mx-auto max-w-4xl px-4 py-10 xl:px-0 min-h-[calc(100vh-230px)]">
		<?php echo wp_kses_post( pbtv_render_video_date( (string) ( $video_post->post_date ?? '' ), 'text-[10px] text-pbtv-green font-bold uppercase' ) ); ?>
		<h1 id="pbtv-video-title" class="wp-block-post-title text-pbtv-red font-bold text-3xl md:text-4xl">
			<?php echo esc_html( $video_title ); ?>
		</h1>
			
		<section class="pbtv-video-player relative aspect-video w-full">

			<iframe
				class="absolute inset-0 h-full w-full"
				src="<?php echo esc_url( $embed_url ); ?>"
				title="<?php echo esc_attr__( 'Embedded YouTube video', 'pbtv' ); ?>"
				loading="lazy"
				allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
				allowfullscreen
			></iframe>

		</section>

		<section class="pbtv-video-comments mt-10">

			<?php if ( $video_post && ( $video_comments_number || comments_open( $video_post ) ) ) : ?>

				<?php if ( $video_comments ) : ?>

					<h2 class="text-xl font-bold">
						<?php
						printf(
							/* translators: %s: number of comments. */
							esc_html( _n( '%s comment', '%s comments', $video_comments_number, 'pbtv' ) ),
							esc_html( number_format_i18n( $video_comments_number ) )
						);
						?>
					</h2>

					<ol class="pbtv-video-comments-list">
						<?php
						wp_list_comments(
							array(
								'style' => 'ol',
							),
							$video_comments
						);
						?>
					</ol>

				<?php endif; ?>

				<?php
				/*
				 * comment_form() already prints its own "Comments are
				 * closed." notice (filterable via
				 * 'comment_form_comments_closed') when comments_open()
				 * is false, so no extra handling is needed here.
				 */
				comment_form( array(), $video_post );
				?>

			<?php endif; ?>

		</section>
	</div>

</main>

<?php echo do_blocks( '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->' ); ?>

<?php wp_footer(); ?>

</body>

</html>