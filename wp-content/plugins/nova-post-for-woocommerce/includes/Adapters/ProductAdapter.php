<?php
/**
 * Product Adapter for Nova Post
 *
 * Handles product data conversion and formatting for Nova Post API
 *
 * @package NovaPostForWoocommerce\Adapters
 */

namespace NovaPostForWoocommerce\Adapters;

use WC_Product;
use NovaPostForWoocommerce\Models\NovaPostSettings;
use NovaPostForWoocommerce\Helpers\MeasurementUnitConstants;

/**
 * Handles product dimension and weight conversion for Nova Post API
 */
class ProductAdapter {

    /**
     * WooCommerce product instance
     *
     * @var WC_Product
     */
    private $product;

    /**
     * Plugin settings instance
     *
     * @var NovaPostSettings
     */
    private $settings;

    /**
     * Constructor
     *
     * @param WC_Product       $product WooCommerce product instance.
     * @param NovaPostSettings $settings Plugin settings instance.
     */
    public function __construct( WC_Product $product, ?NovaPostSettings $settings = null ) {
        $this->product  = $product;
        $this->settings = $settings ? $settings : NovaPostSettings::get_instance();
    }

    /**
     * Get the length unit from WooCommerce settings
     *
     * @return string Length unit
     */
    protected function get_length_unit() {
        return get_option( 'woocommerce_dimension_unit' );
    }

    /**
     * Get the weight unit from WooCommerce settings
     *
     * @return string Weight unit
     */
    protected function get_weight_unit() {
        return get_option( 'woocommerce_weight_unit' );
    }

    /**
     * Get product width in millimeters
     *
     * @return float|null Width in millimeters or null if not set
     */
    public function get_width_in_mm() {
        $width = $this->product->get_width();
        if ( empty( $width ) ) {
            return $this->settings->get_default_width_in_mm();
        }
        return $this->convert_to_mm( $width );
    }

    /**
     * Get product length in millimeters
     *
     * @return float|null Length in millimeters or null if not set
     */
    public function get_length_in_mm() {
        $length = $this->product->get_length();
        if ( empty( $length ) ) {
            return $this->settings->get_default_length_in_mm();
        }
        return $this->convert_to_mm( $length );
    }

    /**
     * Get product height in millimeters
     *
     * @return float|null Height in millimeters or null if not set
     */
    public function get_height_in_mm() {
        $height = $this->product->get_height();
        if ( empty( $height ) ) {
            return $this->settings->get_default_height_in_mm();
        }

        return $this->convert_to_mm( $height );
    }

    /**
     * Get product width in millimeters
     *
     * @return int Width in millimeters (minimum 10)
     */
    public function get_product_width_in_mm(): int {
        $width = $this->product->get_width();

        if ( empty( $width ) ) {
            $width = $this->settings->get_default_product_width_in_mm();
        } else {
            $width = $this->convert_to_mm( $width );
        }

        return max( (int) $width, 10 );
    }

    /**
     * Get product length in millimeters
     *
     * @return int Length in millimeters (minimum 10)
     */
    public function get_product_length_in_mm(): int {
        $length = $this->product->get_length();

        if ( empty( $length ) ) {
            $length = $this->settings->get_default_product_length_in_mm();
        } else {
            $length = $this->convert_to_mm( $length );
        }

        return max( (int) $length, 10 );
    }

    /**
     * Get product height in millimeters
     *
     * @return int Height in millimeters (minimum 10)
     */
    public function get_product_height_in_mm(): int {
        $height = $this->product->get_height();

        if ( empty( $height ) ) {
            $height = $this->settings->get_default_product_height_in_mm();
        } else {
            $height = $this->convert_to_mm( $height );
        }

        return max( (int) $height, 10 );
    }

    /**
     * Convert value to millimeters based on WooCommerce length unit
     *
     * @param float|null $value Value to convert.
     * @return float|null Converted value in millimeters or null if input is invalid
     */
    private function convert_to_mm( $value ) {

        if ( empty( $value ) || ! is_numeric( $value ) ) {
            return null;
        }

        switch ( $this->get_length_unit() ) {
            case 'cm':
                $value *= MeasurementUnitConstants::CM_TO_MM_MULTIPLIER;
                break;
            case 'm':
                $value *= MeasurementUnitConstants::M_TO_MM_MULTIPLIER;
                break;
            case 'in':
                $value *= MeasurementUnitConstants::IN_TO_MM_MULTIPLIER;
                break;
            case 'yd':
                $value *= MeasurementUnitConstants::YD_TO_MM_MULTIPLIER;
                break;
        }

        return round( $value );
    }

    /**
     * Get product weight in grams
     *
     * @return float Weight in grams, rounded to nearest 10
     */
    public function get_weight_in_grams(): float {

        $weight = $this->product->get_weight();

        if ( empty( $weight ) || ! is_numeric( $weight ) ) {
            $default_weight = (float) $this->settings->get_default_weight_in_gr();
            return ( $default_weight - ( $default_weight % 10 ) );
        }

        switch ( $this->get_weight_unit() ) {
            case 'kg':
                $weight *= MeasurementUnitConstants::KG_TO_GR_MULTIPLIER;
                break;
            case 'lbs':
                $weight *= MeasurementUnitConstants::LB_TO_GR_MULTIPLIER;
                break;
            case 'oz':
                $weight *= MeasurementUnitConstants::OZ_TO_GR_MULTIPLIER;
        }

        $weight_int = (int) $weight;

        return (float) ( $weight_int - ( $weight_int % 10 ) );
    }

    /**
     * Determine the cargo category based on weight and dimensions.
     *
     * @return string Cargo category: 'documents', 'cargo', or 'parcel'
     */
    public function get_cargo_category() {
        $weight_grams = $this->get_weight_in_grams();

        $length_in_mm = $this->get_length_in_mm();
        $width_in_mm  = $this->get_width_in_mm();
        $height_in_mm = $this->get_height_in_mm();

        $volumetric_weight = ( $length_in_mm * $width_in_mm * $height_in_mm ) / MeasurementUnitConstants::VOLUMETRIC_WEIGHT_MULTIPLIER;

        $weight_grams = max( $weight_grams, $volumetric_weight );

        $dims_mm = array(
            $length_in_mm,
            $width_in_mm,
            $height_in_mm,
        );
        // Sort dimensions from smallest to largest.
        sort( $dims_mm );

        // Rule for 'documents' - weight <= 1kg and dimensions <= 35x25x2 cm.
        if (
            $weight_grams <= MeasurementUnitConstants::LOWER_WEIGHT_LIMIT &&
            $dims_mm[0] <= MeasurementUnitConstants::LOWER_SIZE_LIMIT_1 && // 2 cm.
            $dims_mm[1] <= MeasurementUnitConstants::LOWER_SIZE_LIMIT_2 && // 25 cm.
            $dims_mm[2] <= MeasurementUnitConstants::LOWER_SIZE_LIMIT_3    // 35 cm.
        ) {
            return 'documents';
        }

        /*
        - Cargo type is deleted from API.
        // Rule for 'cargo' - weight > 30kg or dimensions > 300x120x120 cm.
        if (
            $weight_grams > MeasurementUnitConstants::UPPER_WEIGHT_LIMIT ||
            $dims_mm[2] > MeasurementUnitConstants::UPPER_SIZE_LIMIT
        ) {
            return 'cargo';
        }
        */

        // Default to 'parcel' if not documents or cargo.
        return 'parcel';
    }


    /**
     * Function helper for packing 1 product item.
     *
     * @return array Formatted packing data.
     */
    public function to_packing_item(): array {
        return array(
            'id'             => $this->product->get_id(),
            'width'          => $this->get_product_width_in_mm(),
            'length'         => $this->get_product_length_in_mm(),
            'height'         => $this->get_product_height_in_mm(),
            'weight'         => $this->get_weight_in_grams(),
            'cargo_category' => 'parcel',
            'qty'            => 1,
            'no_rotate'      => false,
        );
    }

    /**
     * Converts box dimensions from CM to MM without needing a product instance.
     *
     * @param array $custom_boxes Array of custom box definitions with dimensions in CM.
     * @return array Modified array with all box dimensions converted to MM.
     */
    public static function convert_box_dimensions_to_mm( array $custom_boxes ): array {
        if ( empty( $custom_boxes ) ) {
            return array();
        }

        $multiplier = defined( 'MeasurementUnitConstants::CM_TO_MM_MULTIPLIER' )
            ? MeasurementUnitConstants::CM_TO_MM_MULTIPLIER
            : 10;

        return array_map(
            function ( $box ) use ( $multiplier ) {
                return array(
                    'width'  => (int) ( ( $box['width'] ?? 0 ) * $multiplier ),
                    'length' => (int) ( ( $box['length'] ?? 0 ) * $multiplier ),
                    'height' => (int) ( ( $box['height'] ?? 0 ) * $multiplier ),
                );
            },
            $custom_boxes
        );
    }
}
