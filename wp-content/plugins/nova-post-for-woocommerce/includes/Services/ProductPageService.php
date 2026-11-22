<?php
/**
 * Product Page Service Class
 *
 * Handles product page custom fields and validations.
 *
 * @package NovaPostForWoocommerce
 */

namespace NovaPostForWoocommerce\Services;

use NovaPostForWoocommerce\Validation\Validator;

defined( 'ABSPATH' ) || exit;

/**
 * Product Page Service Class
 *
 * Handles product page custom fields and validations.
 */
class ProductPageService {

    /**
     * Product meta key for the manufacturer SKU / model code.
     */
    public const MANUFACTURER_SKU_META_KEY = '_manufacturer_sku';

    /**
     * Allowed format of the manufacturer SKU / model code.
     */
    public const MANUFACTURER_SKU_PATTERN = '/^[A-Za-zА-Яа-яІіЇїЄєҐґ0-9 ._\/\\\\-]{1,50}$/u';

    /**
     * Add actions for cross-border fields.
     *
     * @return void
     */
    public static function add_cross_border_fields() {
        add_action( 'woocommerce_product_options_shipping_product_data', array( __CLASS__, 'add_english_name_field' ) );
        add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_product_cross_border_fields' ) );
        add_action( 'admin_notices', array( __CLASS__, 'display_hs_code_error' ) );
    }

    /**
     * Add English name and HS Code fields to product page.
     *
     * @return void
     */
    public static function add_english_name_field() {
        echo '<div class="options_group">';
        woocommerce_wp_text_input(
            array(
                'id'          => '_english_name',
                'label'       => __( 'Product Title in English', 'nova-post-for-woocommerce' ),
                'placeholder' => __( 'Enter product title in English', 'nova-post-for-woocommerce' ),
                'desc_tip'    => 'true',
                'description' => __( 'Enter the name of the product in English. This field is required for correct completion of customs declarations. Example: Cotton T-shirt', 'nova-post-for-woocommerce' ),
            )
        );
        woocommerce_wp_text_input(
            array(
                'id'          => '_hs_code',
                'label'       => __( 'HS Code', 'nova-post-for-woocommerce' ),
                'placeholder' => __( 'Enter HS code of the product', 'nova-post-for-woocommerce' ),
                'desc_tip'    => 'true',
                'description' => __( 'Enter the individual customs code (Harmonized System code) for this product. It is required for international shipments. Example: 8517120000', 'nova-post-for-woocommerce' ),
            )
        );
        woocommerce_wp_text_input(
            array(
                'id'          => self::MANUFACTURER_SKU_META_KEY,
                'label'       => __( 'Manufacturer SKU/model code', 'nova-post-for-woocommerce' ),
                'desc_tip'    => 'true',
                'description' => __( 'Original model code assigned by the manufacturer', 'nova-post-for-woocommerce' ),
                'maxlength'   => 50,
            )
        );
        echo '</div>';
    }

    /**
     * Save English name field.
     *
     * @param int $post_id The post ID.
     * @return void
     */
    private static function save_english_name( $post_id ) {
        // Verify nonce for security.
        if ( ! isset( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['woocommerce_meta_nonce'] ), 'woocommerce_save_data' ) ) {
            return;
        }

        $english_name = isset( $_POST['_english_name'] ) ? sanitize_text_field( wp_unslash( $_POST['_english_name'] ) ) : '';
        update_post_meta( $post_id, '_english_name', $english_name );
    }

    /**
     * Save and validate HS Code field.
     *
     * @param int $post_id The post ID.
     * @return bool True if HS Code is valid or empty, false if validation fails.
     */
    private static function save_hs_code( $post_id ) {
        // Verify nonce for security.
        if ( ! isset( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['woocommerce_meta_nonce'] ), 'woocommerce_save_data' ) ) {
            return false;
        }

        if ( ! isset( $_POST['_hs_code'] ) ) {
            return true;
        }

        $hs_code = sanitize_text_field( wp_unslash( $_POST['_hs_code'] ) );

        if ( ! empty( $hs_code ) ) {
            $validator = Validator::validate(
                array( 'hs_code' => $hs_code ),
                array( 'hs_code' => 'nullable|numeric|digits_between:8,10' )
            );

            if ( $validator->fails() ) {
                self::add_error( __( 'HS Code is invalid. It must be a numeric value with 8-10 digits.', 'nova-post-for-woocommerce' ) );
                return false;
            }
        }

        update_post_meta( $post_id, '_hs_code', $hs_code );
        return true;
    }

    /**
     * Save and validate Manufacturer SKU/model code field.
     *
     * @param int $post_id The post ID.
     * @return bool True if the value is valid or empty, false if validation fails.
     */
    private static function save_manufacturer_sku( $post_id ) {
        // Verify nonce for security.
        if ( ! isset( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['woocommerce_meta_nonce'] ), 'woocommerce_save_data' ) ) {
            return false;
        }

        if ( ! isset( $_POST[ self::MANUFACTURER_SKU_META_KEY ] ) ) {
            return true;
        }

        $value = trim( sanitize_text_field( wp_unslash( $_POST[ self::MANUFACTURER_SKU_META_KEY ] ) ) );

        if ( '' !== $value && ! preg_match( self::MANUFACTURER_SKU_PATTERN, $value ) ) {
            self::add_error( __( 'Manufacturer SKU/model code is invalid. Allowed: letters, digits, spaces and . _ / \\ - (up to 50 characters).', 'nova-post-for-woocommerce' ) );
            return false;
        }

        update_post_meta( $post_id, self::MANUFACTURER_SKU_META_KEY, wp_slash( $value ) );
        return true;
    }

    /**
     * Save English name and HS Code fields.
     *
     * @param int $post_id The post ID.
     * @return void
     */
    public static function save_product_cross_border_fields( $post_id ) {
        // Check if this is an autosave.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Check user permissions.
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Verify nonce for security.
        if ( ! isset( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['woocommerce_meta_nonce'] ), 'woocommerce_save_data' ) ) {
            return;
        }

        self::save_english_name( $post_id );
        self::save_hs_code( $post_id );
        self::save_manufacturer_sku( $post_id );
    }

    /**
     * Queue a validation error to be shown on the next admin page load.
     *
     * @param string $message Error message.
     * @return void
     */
    private static function add_error( string $message ) {
        $key    = 'npforwc_hs_code_error_' . get_current_user_id();
        $errors = (array) get_transient( $key );

        $errors[] = $message;
        set_transient( $key, array_values( array_filter( array_unique( $errors ) ) ), 45 );
    }

    /**
     * Display product field validation error notices.
     *
     * @return void
     */
    public static function display_hs_code_error() {
        $key    = 'npforwc_hs_code_error_' . get_current_user_id();
        $errors = array_filter( (array) get_transient( $key ) );

        if ( $errors ) {
            foreach ( $errors as $error ) {
                ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php echo esc_html( $error ); ?></p>
                </div>
                <?php
            }
            delete_transient( $key );
        }
    }
}
