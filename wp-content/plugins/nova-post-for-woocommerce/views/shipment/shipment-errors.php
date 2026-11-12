<?php
/**
 * Shipment API errors block
 *
 * @package NovaPostForWoocommerce
 *
 * @var array $errors
 * @var bool  $is_hidden
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$hidden_attr = $is_hidden ? ' hidden' : '';
?>
<div class="npw-content__errors"<?php echo esc_attr( $hidden_attr ); ?> role="alert">
    <h2 class="npw-content__errors-title">
        <?php echo esc_html__( 'Errors', 'nova-post-for-woocommerce' ); ?>:
    </h2>

    <ul class="npw-content__errors-list">
        <?php
        $error_items = is_array( $errors ) ? $errors : array( 'error' => (string) $errors );
        foreach ( $error_items as $field => $message ) :
            ?>
            <li class="npw-content__error-item">
                <strong><?php echo esc_html( (string) $field ); ?>:</strong>
                <span><?php echo esc_html( is_array( $message ) ? wp_json_encode( $message ) : (string) str_replace( array( '.', '_' ), ' ', $message ) ); ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
