<?php
/**
 * Country Switcher block.
 *
 * @since 4.0.0
 * @package WCPBC
 */

defined( 'ABSPATH' ) || exit;

/**
 * WCPBC_Country_Switcher_Block Class
 */
class WCPBC_Country_Switcher_Block extends WCPBC_Base_Block {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct(
			[
				'id'          => 'country-switcher',
				'title'       => __( 'Country Switcher', 'woocommerce-product-price-based-on-countries' ),
				'description' => __( 'Display a country switcher using a dropdown.', 'woocommerce-product-price-based-on-countries' ),
				'keywords'    => [ 'country' ],
				'attributes'  => [
					'flag'                   => [
						'type'    => 'boolean',
						'default' => false,
					],
					'remove_other_countries' => [
						'type'    => 'boolean',
						'default' => false,
					],
					'other_countries_text'   => [
						'type'    => 'string',
						'default' => __( 'Other countries', 'woocommerce-product-price-based-on-countries' ),
					],
				],
			]
		);
	}

	/**
	 * Returns the Block editor script data
	 *
	 * @return array
	 */
	public function get_editor_script_data() {
		return WCPBC_Widget_Country_Selector::get_data();
	}

	/**
	 * Render the block.
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Block content.
	 * @return string Rendered block type output.
	 */
	public function render( $attributes, $content ) {
		$attributes['title'] = '';
		ob_start();
		the_widget(
			'WCPBC_Widget_Country_Selector',
			$attributes,
			array(
				'before_widget' => '',
				'after_widget'  => '',
			)
		);

		return ob_get_clean();
	}
}

