( function ( blocks, blockEditor, components, element, i18n, ServerSideRender ) {
	var registerBlockType = blocks.registerBlockType;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var TextareaControl = components.TextareaControl;
	var el = element.createElement;
	var Fragment = element.Fragment;
	var __ = i18n.__;

	registerBlockType( 'pbtv/about', {
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
						{ title: __( 'About Settings', 'pbtv' ) },
						el( TextControl, {
							label: __( 'Image URL', 'pbtv' ),
							value: attributes.imageUrl,
							onChange: function ( value ) {
								setAttributes( { imageUrl: value } );
							},
						} ),
						el( TextControl, {
							label: __( 'Image alt text', 'pbtv' ),
							value: attributes.imageAlt,
							onChange: function ( value ) {
								setAttributes( { imageAlt: value } );
							},
						} ),
						el( TextControl, {
							label: __( 'Heading', 'pbtv' ),
							value: attributes.heading,
							onChange: function ( value ) {
								setAttributes( { heading: value } );
							},
						} ),
						el( TextareaControl, {
							label: __( 'Description', 'pbtv' ),
							value: attributes.description,
							onChange: function ( value ) {
								setAttributes( { description: value } );
							},
						} )
					)
				),
				el(
					'div',
					blockProps,
					el( ServerSideRender, {
						block: 'pbtv/about',
						attributes: attributes,
					} )
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
