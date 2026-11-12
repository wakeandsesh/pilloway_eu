<?php
/**
 * Button Delete Shipment template.
 *
 * @package NovaPostForWoocommerce
 *
 * @var int $order_id
 * @var bool $is_disabled
 * @var bool $is_hidden
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$disabled_attr = ( $is_disabled ) ? 'disabled' : '';
$hidden_attr   = ( $is_hidden ) ? ' hidden' : '';
?>
<button 
    <?php echo esc_attr( $disabled_attr ); ?> 
    <?php echo esc_attr( $hidden_attr ); ?>
    type="button" 
    title="<?php echo esc_attr__( 'Delete waybill', 'nova-post-for-woocommerce' ); ?>"
    class="npw-button --icon" 
    data-npw-order-id="<?php echo esc_attr( $order_id ); ?>" 
    data-npw-action="delete_shipment" 
    data-npw-action-description="<?php echo esc_attr__( 'This will delete the waybill for this order. This action cannot be undone.', 'nova-post-for-woocommerce' ); ?>"     
>
    <span class="material-icons-outlined">delete</span>
</button>
