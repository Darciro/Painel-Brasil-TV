( function ( blocks, element, i18n, ServerSideRender ) {
	var registerBlockType = blocks.registerBlockType;
	var el = element.createElement;
	var __ = i18n.__;

	registerBlockType( 'pbtv/highlights', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var videos = attributes.videos || [];

			return videos.filter( Boolean ).length
				? el( ServerSideRender, {
						block: 'pbtv/highlights',
						attributes: attributes,
				  } )
				: el(
						'p',
						null,
						__( 'Add one or more YouTube videos to the block attributes to mark them as highlights.', 'pbtv' )
				  );
		},
		save: function () {
			return null;
		},
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.i18n,
	window.wp.serverSideRender
);
