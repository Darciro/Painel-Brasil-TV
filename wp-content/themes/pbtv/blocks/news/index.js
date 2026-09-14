( function ( blocks, blockEditor, components, element, i18n, ServerSideRender ) {
	var registerBlockType = blocks.registerBlockType;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var RangeControl = components.RangeControl;
	var el = element.createElement;
	var Fragment = element.Fragment;
	var __ = i18n.__;

	registerBlockType( 'pbtv/news', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'News Settings', 'pbtv' ) },
						el( TextControl, {
							label: __( 'Heading', 'pbtv' ),
							value: attributes.heading,
							onChange: function ( value ) {
								setAttributes( { heading: value } );
							},
						} ),
						el( RangeControl, {
							label: __( 'Number of news items', 'pbtv' ),
							value: attributes.maxItems,
							min: 4,
							max: 12,
							onChange: function ( value ) {
								setAttributes( { maxItems: value } );
							},
						} )
					)
				),
				el( ServerSideRender, {
					block: 'pbtv/news',
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
