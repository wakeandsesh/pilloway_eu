<?php
/**
 * Encapsulate a block type properties.
 *
 * @since 4.0.0
 * @package WCPBC
 */

defined( 'ABSPATH' ) || exit;

/**
 * WCPBC_Base_Block Class
 */
class WCPBC_Base_Block {

	const NAMESPACE = 'woocommerce-product-price-based-on-countries';

	/**
	 * Array of data.
	 *
	 * @var array
	 */
	protected $data = [];

	/**
	 * Constructor
	 *
	 * @param array $data Array of properties.
	 */
	public function __construct( $data = [] ) {
		$this->set_defaults();
		$this->set_props( $data );
	}

	/**
	 * Sets defaults.
	 */
	protected function set_defaults() {
		$this->data = [
			'id'          => '',
			'title'       => '',
			'description' => '',
			'attributes'  => [],
			'keywords'    => [],
			'supports'    => [
				'html' => false,
			],
		];
	}

	/**
	 * Returns a property.
	 *
	 * @param string $key Property name.
	 * @return mixed
	 */
	protected function get_prop( $key ) {
		return isset( $this->data[ $key ] ) ? $this->data[ $key ] : false;
	}

	/**
	 * Sets all properties.
	 *
	 * @param array $data Key, value pair array.
	 */
	protected function set_props( $data ) {
		foreach ( $data as $key => $value ) {

			if ( isset( $this->data[ $key ] ) ) {
				$this->data[ $key ] = $value;
			}
		}
		$this->data['name'] = self::NAMESPACE . '/' . $this->get_id();
	}

	/**
	 * Returns the Block ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->get_prop( 'id' );
	}

	/**
	 * Returns the Block type as string.
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->get_prop( 'name' );
	}

	/**
	 * Returns the Block title.
	 *
	 * @return string
	 */
	public function get_title() {
		return $this->get_prop( 'title' );
	}

	/**
	 * Returns the Block description.
	 *
	 * @return string
	 */
	public function get_description() {
		return $this->get_prop( 'description' );
	}

	/**
	 * Returns the Block attributes.
	 *
	 * @return array
	 */
	public function get_attributes() {
		return $this->get_prop( 'attributes' );
	}

	/**
	 * Returns the Block keywords.
	 *
	 * @return array
	 */
	public function get_keywords() {
		return $this->get_prop( 'keywords' );
	}

	/**
	 * Returns the Block supports.
	 *
	 * @return array
	 */
	public function get_supports() {
		return $this->get_prop( 'supports' );
	}

	/**
	 * Returns the Block editor script handler.
	 *
	 * @return string
	 */
	public function get_editor_script_handler() {
		$id = $this->get_id();
		return "wc-price-based-country-{$id}-block";
	}

	/**
	 * Returns the Block editor script file.
	 *
	 * @return string
	 */
	public function get_editor_script_file() {
		$filename = $this->get_id() . ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min' );
		return "/assets/js/blocks/{$filename}.js";
	}

	/**
	 * Returns the plugin file
	 *
	 * @return string
	 */
	public function get_plugin_file() {
		return WCPBC_PLUGIN_FILE;
	}

	/**
	 * Returns the textdomain
	 *
	 * @return string
	 */
	public function get_textdomain() {
		return self::NAMESPACE;
	}

	/**
	 * Returns the Block editor script data
	 *
	 * @return array
	 */
	public function get_editor_script_data() {
		return [];
	}

	/**
	 * Render the block. Extended by children.
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Block content.
	 * @return string Rendered block type output.
	 */
	public function render( $attributes, $content ) {
		return $content;
	}
}
