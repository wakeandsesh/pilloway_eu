<?php
/**
 * Nova Post Settings Model.
 *
 * @package   NovaPostForWoocommerce
 */

namespace NovaPostForWoocommerce\Models;

use NovaPostForWoocommerce\Helpers\MeasurementUnitConstants;

/**
 * Handles all Nova Post shipping settings.
 *
 * This class uses the singleton pattern to ensure only one instance exists.
 */
class NovaPostSettings {


    /**
     * Default settings.
     *
     * @var array
     */
    private static $default_settings = array(
        'divisions_filter_postomat'     => true,
        'divisions_filter_post_branch'  => true,
        'divisions_filter_cargo_branch' => true,
        'divisions_filter_pudo'         => true,
        'customs_fee_payer'             => 'Sender',
        'delivery_tariff'               => 'standard',
    );

    /**
     * The single instance of the class.
     *
     * @var NovaPostSettings|null
     */
    private static $instance = null;

    /**
     * The array of settings data.
     *
     * @var array
     */
    private $settings_data;

    /**
     * Constructor.
     *
     * Initializes the settings data by fetching it from the database.
     */
    private function __construct() {
        $this->settings_data = get_option( 'npforwc_shipping_settings', array() );
    }

    /**
     * Magic getter for settings properties.
     *
     * @param string $name The name of the property to get.
     * @return mixed|null
     */
    public function __get( $name ) {
        $setting = $this->settings_data[ $name ] ?? null;

        if ( ! isset( $setting ) ) {
            $setting = self::$default_settings[ $name ] ?? null;
        }

        return $setting;
    }

    /**
     * Magic setter for settings properties.
     *
     * @param string $name The name of the property to set.
     * @param mixed  $value The value to set.
     */
    public function __set( $name, $value ) {
        $this->settings_data[ $name ] = $value;
    }

    /**
     * Save settings to the database.
     */
    public function save() {
        $old_settings = get_option( 'npforwc_shipping_settings', array() );
        $settings     = array_merge( $old_settings, $this->settings_data );
        update_option( 'npforwc_shipping_settings', $settings );
    }

    /**
     * Get all settings.
     *
     * @return array
     */
    public static function all() {
        $settings = self::get_instance()->settings_data;

        $settings = array_merge( self::$default_settings, $settings );

        return self::normalize_legacy_settings( $settings );
    }

    /**
     * Get the single instance of the class.
     *
     * @return NovaPostSettings
     */
    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Update the settings in the database.
     *
     * @param array $settings The settings to update.
     */
    public static function update( $settings ) {
        $old_settings = get_option( 'npforwc_shipping_settings', array() );
        $settings     = array_merge( $old_settings, $settings );
        update_option( 'npforwc_shipping_settings', $settings );
    }

    /**
     * Get default width in millimeters - for box.
     *
     * @return int Default width in millimeters.
     */
    public function get_default_width_in_mm() {
        return round( $this->default_width * MeasurementUnitConstants::CM_TO_MM_MULTIPLIER );
    }

    /**
     * Get default length in millimeters - for box.
     *
     * @return int Default length in millimeters.
     */
    public function get_default_length_in_mm() {
        return round( $this->default_length * MeasurementUnitConstants::CM_TO_MM_MULTIPLIER );
    }

    /**
     * Get default height in millimeters - for box.
     *
     * @return int Default height in millimeters.
     */
    public function get_default_height_in_mm() {
        return round( $this->default_height * MeasurementUnitConstants::CM_TO_MM_MULTIPLIER );
    }

    /**
     * Retrieves the product dimension with a fallback to legacy settings.
     *
     * This method checks if a specific product dimension is set. If not, and if the
     * packaging type is empty but a legacy "box_for" setting exists, it falls back
     * to the default global dimension.
     *
     * @param string $dim The dimension to retrieve (e.g., 'width', 'height', 'length').
     *
     * @return float The dimension value, or 0.0 if not found.
     */
    private function get_product_dimension_with_fallback( string $dim ): float {
        $product_key = "default_{$dim}_product";
        $legacy_key  = "default_{$dim}";

        $value          = $this->$product_key;
        $packaging_type = $this->packaging_type;
        $box_for        = $this->default_box_for;

        if ( empty( $value ) && empty( $packaging_type ) && ! empty( $box_for ) ) {
            return (float) ( $this->$legacy_key ?? 0 );
        }

        return (float) ( $value ?? 0 );
    }

    /**
     * Get default width in millimeters - for product.
     *
     * @return int Default width in millimeters.
     */
    public function get_default_product_width_in_mm() {
        $width = $this->get_product_dimension_with_fallback( 'width' ) ?? 0;
        return round( $width * MeasurementUnitConstants::CM_TO_MM_MULTIPLIER );
    }

    /**
     * Get default length in millimeters - for product.
     *
     * @return int Default length in millimeters.
     */
    public function get_default_product_length_in_mm() {
        $length = $this->get_product_dimension_with_fallback( 'length' ) ?? 0;
        return round( $length * MeasurementUnitConstants::CM_TO_MM_MULTIPLIER );
    }

    /**
     * Get default height in millimeters - for product.
     *
     * @return int Default height in millimeters.
     */
    public function get_default_product_height_in_mm() {
        $height = $this->get_product_dimension_with_fallback( 'height' ) ?? 0;
        return round( $height * MeasurementUnitConstants::CM_TO_MM_MULTIPLIER );
    }

    /**
     * Get default weight in grams - for product.
     *
     * @return int Default weight in grams.
     */
    public function get_default_weight_in_gr() {
        return round( $this->default_weight * MeasurementUnitConstants::KG_TO_GR_MULTIPLIER );
    }

    /**
     * Check if Nova Post is configured properly.
     *
     * @return bool
     */
    public function is_available(): bool {
        return $this->debug ? (bool) $this->stage_api_key : (bool) $this->api_key;
    }

    /**
     * Maps old settings values to new ones to maintain backward compatibility.
     *
     * @param array $settings The raw settings data from the database.
     * @return array The normalized settings data.
     */
    private static function normalize_legacy_settings( array $settings ): array {
        $box_for        = $settings['default_box_for'] ?? '';
        $packaging_type = ! empty( $settings['packaging_type'] ) ? $settings['packaging_type'] : '';

        if ( empty( $packaging_type ) && ! empty( $box_for ) ) {
            $settings['packaging_type'] = ( 'order' === $box_for ) ? 'one_box' : 'novapost_boxes';

            $settings['default_length_product'] = $settings['default_length'];
            $settings['default_width_product']  = $settings['default_width'];
            $settings['default_height_product'] = $settings['default_height'];
        }

        return $settings;
    }
}
