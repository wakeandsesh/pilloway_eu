( function ( wp, data ) {
    const el = wp.element.createElement,
	components = wp.components,
	__ = wp.i18n.__;

	const icon = function() {
		//https://icon-sets.iconify.design/tabler/world-pin/
		return el(
			wp.primitives.SVG,
			{
				xmlns: 'http://www.w3.org/2000/svg',
				viewBox: '0 0 24 24',
			},
			el(
				'g', {
					'fill': "none",
					'stroke': "currentColor",
					'strokeLinecap': "round",
					'strokeLinejoin': "round",
					'strokeWidth': "2"
				},
				el(
					'path', {
						d: "M20.972 11.291a9 9 0 1 0-8.322 9.686M3.6 9h16.8M3.6 15h8.9"
					}
				),
				el(
					'path', {
						d: "M11.5 3a17 17 0 0 0 0 18m1-18a17 17 0 0 1 2.578 9.018m6.043 8.103a3 3 0 1 0-4.242 0Q17.506 20.749 19 22q1.577-1.335 2.121-1.879M19 18v.01"
					}
				)
			)
		);
	};

    wp.blocks.registerBlockType( 'woocommerce-product-price-based-on-countries/country-switcher', {

        edit: function ( {attributes, setAttributes} ) {

			function settingsControls() {
				return [
					el(
						components.ToggleControl, {
						label: __( 'Display flags in supported devices', 'woocommerce-product-price-based-on-countries' ),
						checked: attributes.flag,
						onChange: (value) => { setAttributes({ flag: value }); }
					}),
					el(
						components.ToggleControl, {
						label: __( 'Display the "other countries" option', 'woocommerce-product-price-based-on-countries' ),
						checked: !attributes.remove_other_countries,
						onChange: (value) => { setAttributes({remove_other_countries: !value});}
					}),
					el(
						components.TextControl, {
						label: __( 'Other countries text', 'woocommerce-product-price-based-on-countries' ),
						value: attributes.other_countries_text,
						disabled: attributes.remove_other_countries,
						onChange: (value) => { setAttributes({ other_countries_text: value }); }
					})
				];

			};

			function dropdownOption(country)  {
				const icon = attributes.flag ? country.emoji_flag : '';
				return el(
					'option', {
						className: 'wp-exclude-emoji'
					},
					`${icon} ${country.name}`
				);
			};

			function restAllWorldOption() {
				if ( data.rest_all_world_key && ! attributes.remove_other_countries ) {
					return el(
						'option',
						{},
						attributes.other_countries_text
					);
				}
			};

			return el(
                'div',
                wp.blockEditor.useBlockProps(),
				el(
					wp.blockEditor.InspectorControls,
					{},
					el(
						components.PanelBody,
						{
							title: 'Settings',
							initialOpen: true
						},
						...settingsControls()
					)
				),
				el(
					'select', {
						className: 'wp-exclude-emoji'
					},
					...data.data.map(dropdownOption),
					restAllWorldOption()
				)
            );
        },
		icon: icon,
    } );
} )(
	wp,
	( 'undefined' === typeof wc_price_based_country_country_switcher_block_data ? {data:[], rest_all_world_key:false} : wc_price_based_country_country_switcher_block_data)
);