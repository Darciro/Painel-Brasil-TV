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

	exit;
}

$embed_url = sprintf(
	'https://www.youtube-nocookie.com/embed/%s',
	rawurlencode( $video_id )
);
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

<body <?php body_class( 'pbtv-video-page' ); ?>>

<?php wp_body_open(); ?>

<?php

echo do_blocks(
	'<!-- wp:template-part {"slug":"header","tagName":"header"} /-->'
);

?>

<main class="wp-block-group">

	<div class="container py-10 mx-auto">

		<div class="pbtv-video-player">

			<iframe
				src="<?php echo esc_url( $embed_url ); ?>"
				title="<?php echo esc_attr__( 'YouTube video player', 'pbtv' ); ?>"
				loading="lazy"
				allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
				allowfullscreen
			></iframe>

		</div>

	</div>

</main>

<?php

echo do_blocks(
	'<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->'
);

?>

<?php wp_footer(); ?>

</body>

</html>