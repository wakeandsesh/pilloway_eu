<?php
/**
 * WooCommerce Price Based on Country admin metaboxes
 *
 * @package WCPBC
 * @version 1.8.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * WCPBC_Admin_Product_Data Class
 */
class WCPBC_Admin_Meta_Boxes {

	/**
	 * Init hooks
	 */
	public static function init() {

		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'options_general_product_data' ) );
		add_action( 'woocommerce_product_after_variable_attributes', array( __CLASS__, 'after_variable_attributes' ), 10, 3 );
		add_action( 'woocommerce_process_product_meta_simple', array( __CLASS__, 'process_product_meta' ) );
		add_action( 'woocommerce_process_product_meta_external', array( __CLASS__, 'process_product_meta' ) );
		add_action( 'woocommerce_save_product_variation', array( __CLASS__, 'process_product_meta' ), 10, 2 );
		add_action( 'woocommerce_coupon_options', array( __CLASS__, 'coupon_options' ) );
		add_action( 'woocommerce_coupon_options_save', array( __CLASS__, 'coupon_options_save' ) );
	}

	/**
	 * Output the zone pricing for simple products
	 */
	public static function options_general_product_data() {
		$wrapper_class = array( 'options_group', 'show_if_simple', 'show_if_external', 'hide_if_variable' );
		if ( ! wcpbc_is_pro() ) {
			foreach ( array_keys( wcpbc_product_types_supported( 'pro', 'product-data' ) ) as $product_type ) {
				$wrapper_class[] = 'hide_if_' . $product_type;
			}
		}

		$field = array(
			'wrapper_class' => implode( ' ', $wrapper_class ),
			'fields'        => array_merge(
				array(
					array(
						'name'  => '_regular_price',
						// Translators: currency symbol.
						'label' => __( 'Regular price (%s)', 'woocommerce-product-price-based-on-countries' ),
					),
					array(
						'name'  => '_sale_price',
						// Translators: currency symbol.
						'label' => __( 'Sale price (%s)', 'woocommerce-product-price-based-on-countries' ),
						'class' => 'wcpbc_sale_price',
					),
					array(
						'name'          => '_sale_price_dates',
						'type'          => 'radio',
						'default_value' => 'default',
						'class'         => 'wcpbc_sale_price_dates',
						'label'         => __( 'Sale price dates', 'woocommerce-product-price-based-on-countries' ),
						'options'       => array(
							'default' => __( 'Same as default price', 'woocommerce-product-price-based-on-countries' ),
							'manual'  => __( 'Set specific dates', 'woocommerce-product-price-based-on-countries' ),
						),
					),
					array(
						'name'          => '_sale_price_dates_from',
						'label'         => '',
						'data_type'     => 'date',
						'class'         => 'sale_price_dates_from',
						'wrapper_class' => 'sale_price_dates_fields wcpbc_hide_if_sale_dates_default',
						'placeholder'   => _x( 'From&hellip;', 'placeholder', 'woocommerce-product-price-based-on-countries' ) . ' YYYY-MM-DD',
					),
					array(
						'name'          => '_sale_price_dates_to',
						'label'         => '',
						'data_type'     => 'date',
						'class'         => 'sale_price_dates_to',
						'wrapper_class' => 'sale_price_dates_fields wcpbc_hide_if_sale_dates_default',
						'placeholder'   => _x( 'To&hellip;', 'placeholder', 'woocommerce-product-price-based-on-countries' ) . ' YYYY-MM-DD',
					),
				),
				apply_filters( 'wc_price_based_country_product_simple_fields', array() )
			),
		);

		// Output the input control.
		foreach ( WCPBC_Pricing_Zones::get_zones() as $zone ) {
			wcpbc_pricing_input( $field, $zone );
		}
	}

	/**
	 * Output the zone pricing for variations
	 *
	 * @param int                  $loop Variations loop index.
	 * @param array                $variation_data Array of variation data @deprecated.
	 * @param WC_Product_Variation $variation The variation product instance.
	 */
	public static function after_variable_attributes( $loop, $variation_data, $variation ) {
		$post_id = $variation->ID;
		$field   = array(
			'name'          => "_variable_price_method[$loop]",
			'metakey'       => '_price_method',
			'wrapper_class' => wcpbc_is_pro() ? '' : 'hide_if_variable-subscription hide_if_nyp-wcpbc',
			'fields'        => array_merge(
				array(
					'_regular_price'         => array(
						'metakey'       => '_regular_price',
						'name'          => "_variable_regular_price[$loop]",
						// Translators: currency symbol.
						'label'         => __( 'Regular price (%s)', 'woocommerce-product-price-based-on-countries' ),
						'wrapper_class' => 'form-row form-row-first _variable_regular_price_wcpbc_field',
						'data_type'     => 'price',
					),
					'_sale_price'            => array(
						'metakey'       => '_sale_price',
						'name'          => "_variable_sale_price[$loop]",
						// Translators: currency symbol.
						'label'         => __( 'Sale price (%s)', 'woocommerce-product-price-based-on-countries' ),
						'class'         => 'wcpbc_sale_price',
						'wrapper_class' => 'form-row form-row-last _variable_sale_price_wcpbc_field',
						'data_type'     => 'price',
					),
					'_sale_price_dates'      => array(
						'metakey'       => '_sale_price_dates',
						'name'          => "_variable_sale_price_dates[$loop]",
						'type'          => 'radio',
						'class'         => 'wcpbc_sale_price_dates',
						'wrapper_class' => 'wcpbc_sale_price_dates_wrapper',
						'default_value' => 'default',
						'label'         => __( 'Sale price dates', 'woocommerce-product-price-based-on-countries' ),
						'options'       => array(
							'default' => __( 'Same as default price', 'woocommerce-product-price-based-on-countries' ),
							'manual'  => __( 'Set specific dates', 'woocommerce-product-price-based-on-countries' ),
						),
					),
					'_sale_price_dates_from' => array(
						'metakey'       => '_sale_price_dates_from',
						'name'          => "_variable_sale_price_dates_from[$loop]",
						'label'         => __( 'Sale start date', 'woocommerce-product-price-based-on-countries' ),
						'data_type'     => 'date',
						'class'         => 'sale_price_dates_from',
						'wrapper_class' => 'form-row form-row-first sale_price_dates_fields wcpbc_hide_if_sale_dates_default',
						'placeholder'   => _x( 'From&hellip;', 'placeholder', 'woocommerce-product-price-based-on-countries' ) . ' YYYY-MM-DD',
					),
					'_sale_price_dates_to'   => array(
						'metakey'       => '_sale_price_dates_to',
						'name'          => "_variable_sale_price_dates_to[$loop]",
						'label'         => __( 'Sale end date', 'woocommerce-product-price-based-on-countries' ),
						'data_type'     => 'date',
						'class'         => 'sale_price_dates_to',
						'wrapper_class' => 'form-row form-row-last sale_price_dates_fields wcpbc_hide_if_sale_dates_default',
						'placeholder'   => _x( 'To&hellip;', 'placeholder', 'woocommerce-product-price-based-on-countries' ) . ' YYYY-MM-DD',
					),
				),
				apply_filters( 'wc_price_based_country_product_variation_fields', array(), $loop )
			),
		);

		// Output the input control.
		foreach ( WCPBC_Pricing_Zones::get_zones() as $zone ) {
			foreach ( $field['fields'] as $key => $field_data ) {
				if ( ! empty( $field_data['metakey'] ) ) {
					continue;
				}

				$field['fields'][ $key ]['value'] = $zone->get_postmeta( $post_id, $key );
			}

			wcpbc_pricing_input( $field, $zone, $post_id );
		}
	}

	/**
	 * Save product metadata
	 *
	 * @param int $post_id Post ID.
	 * @param int $index Index of variations to save.
	 */
	public static function process_product_meta( $post_id, $index = false ) {
		$fields = array( '_price_method', '_regular_price', '_sale_price', '_sale_price_dates', '_sale_price_dates_from', '_sale_price_dates_to' );
		foreach ( WCPBC_Pricing_Zones::get_zones() as $zone ) {
			$data = array();
			foreach ( $fields as $field ) {
				$var_name       = false !== $index ? '_variable' . $field : $field;
				$data[ $field ] = $zone->get_input_var( $var_name, $index );
			}

			// Save metadata.
			wcpbc_update_product_pricing( $post_id, $zone, $data );
		}
	}

	/**
	 * Display coupon amount options.
	 *
	 * @param int $post_id Post ID.
	 * @since 1.6
	 */
	public static function coupon_options( $post_id ) {
		$value = get_post_meta( $post_id, 'zone_pricing_type', true );

		woocommerce_wp_checkbox(
			array(
				'id'          => 'zone_pricing_type',
				'cbvalue'     => 'exchange_rate',
				'label'       => __( 'Calculate amount by exchange rate', 'woocommerce-product-price-based-on-countries' ),
				// Translators: HTML tags.
				'description' => sprintf( __( 'Check this box if, for the pricing zones, the coupon amount must be calculated using the exchange rate. %1$s(%2$sUpgrade to Price Based on Country Pro to set copupon amount by zone%3$s)', 'woocommerce-product-price-based-on-countries' ), '<br />', '<a target="_blank" el="noopener noreferrer" href="' . esc_url( wcpbc_home_url( 'coupon' ) ) . '">', '</a>' ),
				'value'       => wcpbc_is_exchange_rate( $value ) ? 'exchange_rate' : '',
			)
		);
	}

	/**
	 * Save coupon amount options.
	 *
	 * @since 1.6
	 * @param int $post_id Post ID.
	 */
	public static function coupon_options_save( $post_id ) {
		$discount_type     = empty( $_POST['discount_type'] ) ? 'fixed_cart' : wc_clean( wp_unslash( $_POST['discount_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$zone_pricing_type = in_array( $discount_type, array( 'fixed_cart', 'fixed_product' ), true ) && isset( $_POST['zone_pricing_type'] ) ? 'exchange_rate' : 'manual'; // phpcs:ignore WordPress.Security.NonceVerification
		update_post_meta( $post_id, 'zone_pricing_type', $zone_pricing_type );
	}
}
