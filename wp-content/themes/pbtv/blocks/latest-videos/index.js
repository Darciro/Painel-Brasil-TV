( function ( blocks, blockEditor, components, element, i18n, ServerSideRender ) {
	var registerBlockType = blocks.registerBlockType;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var RangeControl = components.RangeControl;
	var SelectControl = components.SelectControl;
	var el = element.createElement;
	var Fragment = element.Fragment;
	var __ = i18n.__;

	registerBlockType( 'pbtv/latest-videos', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Latest Videos Settings', 'pbtv' ) },
						el( TextControl, {
							label: __( 'Heading', 'pbtv' ),
							value: attributes.heading,
							onChange: function ( value ) {
								setAttributes( { heading: value } );
							},
						} ),
						el( TextControl, {
							label: __( 'YouTube channel ID', 'pbtv' ),
							value: attributes.channelId,
							onChange: function ( value ) {
								setAttributes( { channelId: value } );
							},
						} ),
						el( RangeControl, {
							label: __( 'Number of videos', 'pbtv' ),
							value: attributes.maxResults,
							min: 1,
							max: 12,
							onChange: function ( value ) {
								setAttributes( { maxResults: value } );
							},
						} ),
						el( SelectControl, {
							label: __( 'Formato', 'pbtv' ),
							value: attributes.format,
							options: [
								{ label: __( 'Embed', 'pbtv' ), value: 'embed' },
								{ label: __( 'Miniatura', 'pbtv' ), value: 'thumbnail' },
							],
							onChange: function ( value ) {
								setAttributes( { format: value } );
							},
						} )
					)
				),
				el(
					'div',
					blockProps,
					attributes.channelId
						? el( ServerSideRender, {
								block: 'pbtv/latest-videos',
								attributes: attributes,
						  } )
						: el(
								'p',
								null,
								__( 'Set a YouTube channel ID in the block settings to display the latest live videos.', 'pbtv' )
						  )
				)
			);
		},
		save: function () {
			return null;
		},
	} );
} )(
	window.wp.blocks,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.element,
	window.wp.i18n,
	window.wp.serverSideRender
);
