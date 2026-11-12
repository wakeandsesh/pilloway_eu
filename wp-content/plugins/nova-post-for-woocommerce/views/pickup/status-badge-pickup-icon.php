<?php
/**
 * Status Badge pickup template.
 *
 * @package NovaPostForWoocommerce
 *
 * @var string $status waiting|added|error
 * @var int $tooltip_text
 * @var bool $is_hidden
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


$hidden_attr = $is_hidden ? ' hidden' : '';

?>
<div class="npw-status-pickup-icon" data-status-pickup="<?php echo esc_attr( $status ?? '' ); ?>" <?php echo esc_attr( $hidden_attr ); ?>>
    <div class="npw-tooltip npw-status-badge__tooltip" data-tooltip="<?php echo esc_attr( $tooltip_text ); ?>">
        <span class="material-icons-outlined"></span>
    </div>
</div>
