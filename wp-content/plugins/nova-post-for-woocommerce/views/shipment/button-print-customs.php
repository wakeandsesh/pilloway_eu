<?php
/**
 * Button print customers template.
 *
 * @package NovaPostForWoocommerce
 *
 * @var int $order_id
 * @var bool $is_hidden
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$hidden_attr = $is_hidden ? ' hidden' : '';
?>
<button 
    <?php echo esc_html( $hidden_attr ); ?>
    type="button" 
    class="npw-button --icon" 
    data-npw-order-id="<?php echo esc_attr( $order_id ); ?>" 
    data-npw-action="print_customs" 
    title="<?php echo esc_attr__( 'Customs declaration printing', 'nova-post-for-woocommerce' ); ?>"
>
    <span class="material-icons-outlined">request_quote</span>
</button>
