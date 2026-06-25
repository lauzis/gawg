(function ( blocks, element, blockEditor, components, ServerSideRender, apiFetch, i18n ) {
	'use strict';

	var __ = i18n.__;
	var el = element.createElement;
	var Fragment = element.Fragment;
	var useState = element.useState;
	var useEffect = element.useEffect;
	var useBlockProps = blockEditor.useBlockProps;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var TextControl = components.TextControl;
	var Placeholder = components.Placeholder;

	blocks.registerBlockType( 'gawg/form', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();

			var giveawaysState = useState( [ { value: '', label: __( '— Select a giveaway —', 'gawg' ) } ] );
			var giveaways = giveawaysState[ 0 ];
			var setGiveaways = giveawaysState[ 1 ];

			useEffect( function () {
				apiFetch( { path: '/gawg/v1/giveaways' } )
					.then( function ( items ) {
						setGiveaways(
							[ { value: '', label: __( '— Select a giveaway —', 'gawg' ) } ].concat( items )
						);
					} )
					.catch( function () {} );
			}, [] );

			var inspectorControls = el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'Giveaway Settings', 'gawg' ), initialOpen: true },
					el( SelectControl, {
						label: __( 'Giveaway', 'gawg' ),
						value: attributes.giveaway_uuid,
						options: giveaways,
						onChange: function ( val ) {
							setAttributes( { giveaway_uuid: val } );
						},
						help: __( 'Select which giveaway this form is for.', 'gawg' ),
					} ),
					el( TextControl, {
						label: __( 'Rules URL', 'gawg' ),
						type: 'url',
						value: attributes.rules_url,
						onChange: function ( val ) {
							setAttributes( { rules_url: val, rules_post_id: 0 } );
						},
						help: __( 'URL to the giveaway rules page. Leave empty to omit the rules checkbox.', 'gawg' ),
					} )
				)
			);

			var preview;
			if ( attributes.giveaway_uuid ) {
				preview = el(
					'div',
					blockProps,
					el( ServerSideRender, {
						block: 'gawg/form',
						attributes: attributes,
					} )
				);
			} else {
				preview = el(
					'div',
					blockProps,
					el( Placeholder, {
						icon: 'awards',
						label: __( 'Giveaway Form', 'gawg' ),
						instructions: __( 'Select a giveaway in the block settings panel on the right.', 'gawg' ),
					} )
				);
			}

			return el( Fragment, null, inspectorControls, preview );
		},

		save: function () {
			return null;
		},
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.serverSideRender,
	window.wp.apiFetch,
	window.wp.i18n
);
