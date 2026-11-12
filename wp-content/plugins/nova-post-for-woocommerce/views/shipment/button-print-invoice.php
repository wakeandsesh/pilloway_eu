<?php
/**
 * Button Print invoice template.
 *
 * @package NovaPostForWoocommerce
 *
 * @var int $order_id
 * @var bool $is_hidden
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$hidden_attr = ( $is_hidden ) ? ' hidden' : '';
?>
<button 
    <?php echo esc_attr( $hidden_attr ); ?>
    type="button" 
    class="npw-button --icon" 
    data-npw-order-id="<?php echo esc_attr( $order_id ); ?>" 
    data-npw-action="print_invoice" 
    title="<?php echo esc_attr__( 'Invoice printing', 'nova-post-for-woocommerce' ); ?>"
>
    <span class="material-icons-outlined">receipt_long</span>
</button>
