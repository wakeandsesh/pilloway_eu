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
                set_transient( 'npforwc_hs_code_error_' . get_current_user_id(), __( 'HS Code is invalid. It must be a numeric value with 8-10 digits.', 'nova-post-for-woocommerce' ), 45 );
                return false;
            }
        }

        update_post_meta( $post_id, '_hs_code', $hs_code );
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
    }

    /**
     * Display HS Code validation error notice.
     *
     * @return void
     */
    public static function display_hs_code_error() {
        $error = get_transient( 'npforwc_hs_code_error_' . get_current_user_id() );

        if ( $error ) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html( $error ); ?></p>
            </div>
            <?php
            delete_transient( 'npforwc_hs_code_error_' . get_current_user_id() );
        }
    }
}
