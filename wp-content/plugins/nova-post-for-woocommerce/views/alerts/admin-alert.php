<?php
/**
 * Admin Alert View
 *
 * @package    NovaPostForWoocommerce
 * @subpackage Views
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$allowed_html = array(
    'a'    => array(
        'href'   => array(),
        'target' => array( '_blank' => true ),
    ),
    'span' => array(
        'class'    => array(),
        'data-tip' => array(),
    ),
);

$alert_type = $alert_type ?? 'success';

if ( ! in_array( $alert_type, array( 'warning', 'success', 'error', 'info' ), true ) ) {
    $alert_type = 'success';
}

$is_dismissible = $is_dismissible ?? false;

$class = $class ?? '';
?>
<div class="notice notice-<?php echo esc_attr( $alert_type ); ?> <?php echo esc_attr( $is_dismissible ? 'is-dismissible' : '' ); ?> <?php echo esc_attr( $class ); ?>">
    <p>
        <?php
        printf(
            wp_kses(
                /* translators: %s: link to WooCommerce settings */
                $message,
                $allowed_html
            ),
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ...$args
        );
        ?>
    </p>
</div>
