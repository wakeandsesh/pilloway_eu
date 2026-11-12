<?php
/**
 * Block controller.
 *
 * @since 4.0.0
 * @package WCPBC
 */

defined( 'ABSPATH' ) || exit;

/**
 * WCPBC_Blocks_Controller class.
 */
class WCPBC_Blocks_Controller {

	/**
	 * Array of block.
	 *
	 * @var array
	 */
	private static $block_types = [];

	/**
	 * Init.
	 */
	public static function init() {
		add_filter( 'block_categories_all', [ __CLASS__, 'block_categories_all' ] );
		add_action( 'init', [ __CLASS__, 'register_blocks' ] );
		add_action( 'admin_footer', [ __CLASS__, 'block_script_data' ] );
		add_action( 'customize_controls_print_footer_scripts', [ __CLASS__, 'block_script_data' ], 1 );
	}

	/**
	 * Adds the Price Based on Country blocks category
	 *
	 * @param array $block_categories Array of categories for block types.
	 */
	public static function block_categories_all( $block_categories ) {
		return array_merge(
			$block_categories,
			[
				[
					'slug'  => 'woocommerce-product-price-based-on-countries',
					'title' => 'Price Based on Country for WooCommerce',
				],
			]
		);
	}

	/**
	 * Register blocks.
	 */
	public static function register_blocks() {
		foreach ( self::get_block_types() as $class ) {

			$classname = "WCPBC_{$class}_Block";

			if ( ! class_exists( $classname ) ) {
				continue;
			}

			$block = new $classname();

			self::register_block_script( $block );
			self::register_block_type( $block );

			self::$block_types[ $block->get_name() ] = $block;
		}
	}

	/**
	 * Returns the blocks.
	 */
	private static function get_block_types() {
		return [
			'Country_Switcher',
			'Currency_Switcher',
		];
	}

	/**
	 * Register a block type
	 *
	 * @param WCPBC_Base_Block $block Block type instance.
	 */
	private static function register_block_type( $block ) {
		register_block_type(
			$block->get_name(),
			[
				'render_callback' => [ __CLASS__, 'render' ],
				'api_version'     => 3,
				'category'        => 'woocommerce-product-price-based-on-countries',
				'title'           => $block->get_title(),
				'description'     => $block->get_description(),
				'keywords'        => array_merge(
					[ 'WooCommerce' ],
					$block->get_keywords()
				),
				'textdomain'      => $block->get_textdomain(),
				'editor_script'   => $block->get_editor_script_handler(),
				'attributes'      => $block->get_attributes(),
				'supports'        => $block->get_supports(),
			]
		);
	}

	/**
	 * Register script
	 *
	 * @param WCPBC_Base_Block $block Block type instance.
	 */
	private static function register_block_script( $block ) {
		$script_handler = $block->get_editor_script_handler();
		$script_file    = $block->get_editor_script_file();
		$plugin_file    = $block->get_plugin_file();
		$script_version = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG && file_exists( dirname( $plugin_file ) . $script_file ) ? filemtime( dirname( $plugin_file ) . $script_file ) : WCPBC()->version;

		wp_register_script(
			$script_handler,
			plugins_url( $script_file, $plugin_file ),
			[ 'wp-blocks', 'wp-element', 'wp-components', 'wp-i18n' ],
			$script_version,
			true
		);
	}

	/**
	 * Add script data.
	 */
	public static function block_script_data() {

		foreach ( self::$block_types as $block ) {

			$script_handler = $block->get_editor_script_handler();

			if ( ! wp_script_is( $script_handler, 'enqueued' ) ) {
				continue;
			}

			$data = $block->get_editor_script_data();

			if ( ! empty( $data ) ) {

				wp_localize_script(
					$script_handler,
					str_replace( '-', '_', $script_handler ) . '_data',
					$data
				);
			}
		}
	}

	/**
	 * Render the block.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Block content.
	 * @param WP_Block $block WP_Block instance.
	 * @return string Rendered block type output.
	 */
	public static function render( $attributes, $content, $block ) {
		$block_name = $block->block_type->name;

		if ( isset( self::$block_types[ $block_name ] ) ) {
			return self::$block_types[ $block_name ]->render( $attributes, $content );
		}

		return '';
	}
}
