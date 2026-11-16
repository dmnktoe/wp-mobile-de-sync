( function ( wp ) {
	'use strict';

	var registerBlockType = wp.blocks.registerBlockType;
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;
	var SelectControl = wp.components.SelectControl;
	var RangeControl = wp.components.RangeControl;
	var ServerSideRender = wp.serverSideRender;
	var __ = wp.i18n.__;

	// Disabled: the preview is the real markup, forms and links included, and
	// a click on "Show vehicles" would navigate the editor away.
	function preview( name, attributes ) {
		return el(
			wp.components.Disabled,
			null,
			el( ServerSideRender, {
				block: name,
				attributes: attributes,
			} )
		);
	}

	registerBlockType( 'wmds/vehicles', {
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
						{ title: __( 'Display', 'wp-mobile-de-sync' ), initialOpen: true },
						el( RangeControl, {
							label: __( 'Vehicles per page', 'wp-mobile-de-sync' ),
							value: attributes.postsPerPage,
							min: 1,
							max: 48,
							onChange: function ( value ) {
								setAttributes( { postsPerPage: value || 6 } );
							},
						} ),
						el( SelectControl, {
							label: __( 'Layout', 'wp-mobile-de-sync' ),
							value: attributes.layout,
							options: [
								{ label: __( 'Grid', 'wp-mobile-de-sync' ), value: 'grid' },
								{ label: __( 'List', 'wp-mobile-de-sync' ), value: 'list' },
							],
							onChange: function ( value ) {
								setAttributes( { layout: value } );
							},
						} ),
						el( SelectControl, {
							label: __( 'Columns', 'wp-mobile-de-sync' ),
							value: attributes.columns,
							options: [
								{ label: __( 'Theme default', 'wp-mobile-de-sync' ), value: '' },
								{ label: '2', value: '2' },
								{ label: '3', value: '3' },
								{ label: '4', value: '4' },
							],
							onChange: function ( value ) {
								setAttributes( { columns: value } );
							},
						} ),
						el( SelectControl, {
							label: __( 'Title level', 'wp-mobile-de-sync' ),
							value: attributes.heading,
							options: [
								{ label: 'H2', value: 'h2' },
								{ label: 'H3', value: 'h3' },
								{ label: 'H4', value: 'h4' },
							],
							onChange: function ( value ) {
								setAttributes( { heading: value } );
							},
						} ),
						el( ToggleControl, {
							label: __( 'Show filters', 'wp-mobile-de-sync' ),
							checked: !! attributes.filters,
							onChange: function ( value ) {
								setAttributes( { filters: value } );
							},
						} ),
						el( ToggleControl, {
							label: __( 'Pagination', 'wp-mobile-de-sync' ),
							checked: !! attributes.pagination,
							onChange: function ( value ) {
								setAttributes( { pagination: value } );
							},
						} )
					),
					el(
						PanelBody,
						{ title: __( 'Order', 'wp-mobile-de-sync' ), initialOpen: false },
						el( TextControl, {
							label: __( 'Order by', 'wp-mobile-de-sync' ),
							value: attributes.orderby,
							onChange: function ( value ) {
								setAttributes( { orderby: value } );
							},
						} ),
						el( SelectControl, {
							label: __( 'Direction', 'wp-mobile-de-sync' ),
							value: attributes.order,
							options: [
								{ label: __( 'Descending', 'wp-mobile-de-sync' ), value: 'DESC' },
								{ label: __( 'Ascending', 'wp-mobile-de-sync' ), value: 'ASC' },
							],
							onChange: function ( value ) {
								setAttributes( { order: value } );
							},
						} )
					)
				),
				preview( 'wmds/vehicles', attributes )
			);
		},
		save: function () {
			return null;
		},
	} );

	registerBlockType( 'wmds/vehicle-filter', {
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
						{ title: __( 'Filter', 'wp-mobile-de-sync' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Layout', 'wp-mobile-de-sync' ),
							value: attributes.layout,
							options: [
								{ label: __( 'Bar', 'wp-mobile-de-sync' ), value: 'bar' },
								{ label: __( 'Sidebar', 'wp-mobile-de-sync' ), value: 'sidebar' },
							],
							onChange: function ( value ) {
								setAttributes( { layout: value } );
							},
						} ),
						el( TextControl, {
							label: __( 'Facets', 'wp-mobile-de-sync' ),
							value: attributes.facets,
							help: __( 'Comma-separated, e.g. make,price,fuel. Empty = all.', 'wp-mobile-de-sync' ),
							onChange: function ( value ) {
								setAttributes( { facets: value } );
							},
						} ),
						el( TextControl, {
							label: __( 'Heading', 'wp-mobile-de-sync' ),
							value: attributes.heading,
							onChange: function ( value ) {
								setAttributes( { heading: value } );
							},
						} ),
						el( ToggleControl, {
							label: __( 'Show counts', 'wp-mobile-de-sync' ),
							checked: false !== attributes.counts,
							onChange: function ( value ) {
								setAttributes( { counts: value } );
							},
						} )
					)
				),
				preview( 'wmds/vehicle-filter', attributes )
			);
		},
		save: function () {
			return null;
		},
	} );

	registerBlockType( 'wmds/vehicle-count', {
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
						{ title: __( 'Count', 'wp-mobile-de-sync' ), initialOpen: true },
						el( ToggleControl, {
							label: __( 'Respect current filters', 'wp-mobile-de-sync' ),
							checked: false !== attributes.filtered,
							onChange: function ( value ) {
								setAttributes( { filtered: value } );
							},
						} )
					)
				),
				preview( 'wmds/vehicle-count', attributes )
			);
		},
		save: function () {
			return null;
		},
	} );

	registerBlockType( 'wmds/vehicle-enquiry', {
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
						{ title: __( 'Enquiry', 'wp-mobile-de-sync' ), initialOpen: true },
						el( TextControl, {
							label: __( 'Vehicle ID', 'wp-mobile-de-sync' ),
							type: 'number',
							value: attributes.vehicle || '',
							help: __( 'Empty = current vehicle.', 'wp-mobile-de-sync' ),
							onChange: function ( value ) {
								setAttributes( { vehicle: parseInt( value, 10 ) || 0 } );
							},
						} ),
						el( TextControl, {
							label: __( 'Heading', 'wp-mobile-de-sync' ),
							value: attributes.heading,
							onChange: function ( value ) {
								setAttributes( { heading: value } );
							},
						} )
					)
				),
				preview( 'wmds/vehicle-enquiry', attributes )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
