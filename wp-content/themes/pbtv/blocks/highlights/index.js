( function ( blocks, blockEditor, components, element, i18n, ServerSideRender ) {
	var registerBlockType = blocks.registerBlockType;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var TextareaControl = components.TextareaControl;
	var el = element.createElement;
	var Fragment = element.Fragment;
	var __ = i18n.__;

	registerBlockType( 'pbtv/highlights', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var videos = Array.isArray( attributes.videos ) ? attributes.videos : [];

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Highlights Settings', 'pbtv' ) },
						el( TextControl, {
							label: __( 'Heading', 'pbtv' ),
							value: attributes.heading,
							onChange: function ( value ) {
								setAttributes( { heading: value } );
							},
						} ),
						el( TextareaControl, {
							label: __( 'YouTube video URLs or IDs', 'pbtv' ),
							help: __( 'One video per line.', 'pbtv' ),
							value: videos.join( '\n' ),
							onChange: function ( value ) {
								setAttributes( {
									videos: value
										.split( '\n' )
										.map( function ( line ) {
											return line.trim();
										} )
										.filter( Boolean ),
								} );
							},
						} )
					)
				),
				el( ServerSideRender, {
					block: 'pbtv/highlights',
					attributes: attributes,
				} )
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
