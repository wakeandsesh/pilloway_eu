<?php
/**
 * Status Badge template.
 *
 * @package NovaPostForWoocommerce
 *
 * @var int|string|null $status_code
 * @var string $status
 * @var int $order_id
 * @var bool $is_hidden
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

    $status_value        = '' !== trim( $status ) ? $status : __( 'Not available', 'nova-post-for-woocommerce' );
    $status_code_display = 0 !== $status_code ? $status_code : '—';
    $hidden_attr         = ! empty( $is_hidden ) ? ' hidden' : '';
?>

<div class="npw-status-badge-icon" data-status-code="<?php echo esc_attr( $status_code_display ); ?>" data-npw-order-id="<?php echo esc_attr( $order_id ?? '' ); ?>" <?php echo esc_attr( $hidden_attr ); ?>>
    <div class="npw-tooltip npw-status-badge__tooltip" data-tooltip="<?php echo esc_attr( $status_value ); ?>">
        <span class="material-icons-outlined"></span>
    </div>
</div>

