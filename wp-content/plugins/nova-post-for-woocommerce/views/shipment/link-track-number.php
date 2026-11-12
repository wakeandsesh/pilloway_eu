<?php
/**
 * Link Track number template.
 *
 * @package NovaPostForWoocommerce
 *
 * @var string $tracking_url
 * @var string $track_number
 * @var string $sprite_url
 * @var bool $is_hidden
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?>
<?php $hidden_attr = ! empty( $is_hidden ) ? ' hidden' : ''; ?>
<a href="<?php echo esc_url( $tracking_url ); ?>" target="_blank" class="npw-button --link --accent npw-content__track-number"<?php echo esc_attr( $hidden_attr ); ?>>
    <svg class="npw-content__track-number-icon" width="20" height="20" aria-hidden="true" focusable="false">
        <use href="<?php echo esc_url( $sprite_url ); ?>#icon-nova-post"></use>
    </svg>
    <span class="npw-content__track-number-text"><?php echo esc_html( $track_number ); ?></span>
</a>
