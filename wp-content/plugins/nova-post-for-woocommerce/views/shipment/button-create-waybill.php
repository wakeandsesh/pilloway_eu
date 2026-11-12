<?php
/**
 * Button waybill template
 *
 * @package NovaPostForWoocommerce
 *
 * @var int $order_id
 * @var string $sprite_url
 * @var bool $is_hidden
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$hidden_attr = $is_hidden ? ' hidden' : '';
?>
<div class="npw-content__create" <?php echo esc_attr( $hidden_attr ); ?>>
    <button type="button" class="npw-button npw-content__create-waybill" data-npw-order-id="<?php echo esc_attr( $order_id ); ?>" data-npw-action="create_waybill">
        <span><?php echo esc_html__( 'Create waybill', 'nova-post-for-woocommerce' ); ?></span>
    </button>
</div>
