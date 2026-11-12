<?php
/**
 * Order Division Information View
 *
 * Displays Nova Post division information on the order edit page.
 *
 * @package NovaPostForWoocommerce
 *
 * @var int      $order_id               The WooCommerce order ID.
 * @var array    $shipment_info          Shipment information if exists.
 * @var string   $division_id            Division ID.
 * @var string   $division_type          Division type.
 * @var string   $division_name          Division short name.
 * @var string   $division_address       Division address.
 */

defined( 'ABSPATH' ) || exit;
?>

<hr class="clear" />
<div class="npw-division-data">

    <div class="npw-division-header">
        <h3><?php esc_html_e( 'Division', 'nova-post-for-woocommerce' ); ?></h3>
        <button type="button" class="npw-division-toggle" aria-label="<?php esc_attr_e( 'Edit division', 'nova-post-for-woocommerce' ); ?>">
            <svg class="npw-icon npw-icon-edit" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                <path d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z"/>
            </svg>
            <svg class="npw-icon npw-icon-close" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                <path d="M320 112C434.9 112 528 205.1 528 320C528 434.9 434.9 528 320 528C205.1 528 112 434.9 112 320C112 205.1 205.1 112 320 112zM320 576C461.4 576 576 461.4 576 320C576 178.6 461.4 64 320 64C178.6 64 64 178.6 64 320C64 461.4 178.6 576 320 576zM231 231C221.6 240.4 221.6 255.6 231 264.9L286 319.9L231 374.9C221.6 384.3 221.6 399.5 231 408.8C240.4 418.1 255.6 418.2 264.9 408.8L319.9 353.8L374.9 408.8C384.3 418.2 399.5 418.2 408.8 408.8C418.1 399.4 418.2 384.2 408.8 374.9L353.8 319.9L408.8 264.9C418.2 255.5 418.2 240.3 408.8 231C399.4 221.7 384.2 221.6 374.9 231L319.9 286L264.9 231C255.5 221.6 240.3 221.6 231 231z"/>
            </svg>
        </button>
    </div>

    <div class="division">
        <p class="division-name">
        <?php if ( $division_name ) : ?>
            <?php echo esc_html( $division_name ); ?>
        <?php else : ?>
            <?php esc_html_e( 'No division selected', 'nova-post-for-woocommerce' ); ?>
        <?php endif; ?>
        </p>

        <?php if ( $division_address ) : ?>
            <p>
                <strong><?php esc_html_e( 'Address', 'nova-post-for-woocommerce' ); ?>:</strong>
                <span class="division-address"><?php echo esc_html( $division_address ); ?></span>
            </p>
        <?php endif; ?>
    </div>
    <div class="edit_division">
        <!-- React widget container -->
        <?php if ( $shipment_info['track_number'] ) : ?>
            <div class="npw-notice --warning">
                <strong><?php esc_html_e( 'Warning', 'nova-post-for-woocommerce' ); ?>:</strong><br>
                <?php esc_html_e( 'Changing the division will not affect the already created shipment. You need to delete the shipment.', 'nova-post-for-woocommerce' ); ?>
            </div>
        <?php endif; ?>
        <div id="npforwc-order-widget" data-order-id="<?php echo esc_attr( $order_id ); ?>"></div>
    </div>
</div>
