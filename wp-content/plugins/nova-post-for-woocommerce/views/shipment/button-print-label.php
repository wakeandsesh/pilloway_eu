<?php
/**
 * Button print label template.
 *
 * @package NovaPostForWoocommerce
 *
 * @var int $order_id
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?>
<button type="button" class="npw-button --icon" data-npw-order-id="<?php echo esc_attr( $order_id ); ?>" data-npw-action="print_label" title="<?php echo esc_attr__( 'Label printing', 'nova-post-for-woocommerce' ); ?>">
    <span class="material-icons-outlined">print</span>
</button>
