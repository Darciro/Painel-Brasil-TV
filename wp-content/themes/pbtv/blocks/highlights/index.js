( function ( blocks, blockEditor, components, element, i18n, ServerSideRender ) {
	var registerBlockType = blocks.registerBlockType;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var TextareaControl = components.TextareaControl;
	var ToggleControl = components.ToggleControl;
	var SelectControl = components.SelectControl;
	var el = element.createElement;
	var Fragment = element.Fragment;
	var __ = i18n.__;

	registerBlockType( 'pbtv/highlights', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var videos = Array.isArray( attributes.videos ) ? attributes.videos : [];
			var blockProps = useBlockProps();

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Configurações dos destaques', 'pbtv' ) },
						el( TextControl, {
							label: __( 'Título', 'pbtv' ),
							value: attributes.heading,
							onChange: function ( value ) {
								setAttributes( { heading: value } );
							},
						} ),
						el( ToggleControl, {
							label: __( 'Mostrar título', 'pbtv' ),
							checked: attributes.showTitle,
							onChange: function ( value ) {
								setAttributes( { showTitle: value } );
							},
						} ),
						el( SelectControl, {
							label: __( 'Formato', 'pbtv' ),
							value: attributes.format,
							options: [
								{ label: __( 'Miniatura', 'pbtv' ), value: 'thumbnail' },
								{ label: __( 'Embed', 'pbtv' ), value: 'embed' },
							],
							onChange: function ( value ) {
								setAttributes( { format: value } );
							},
						} ),
						el( TextareaControl, {
							label: __( 'URLs ou IDs dos vídeos do YouTube', 'pbtv' ),
							help: __( 'Um vídeo por linha.', 'pbtv' ),
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
				el(
					'div',
					blockProps,
					el( ServerSideRender, {
						block: 'pbtv/highlights',
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
