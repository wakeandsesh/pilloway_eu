<?php
/**
 * Uninstall — remove plugin settings.
 *
 * @package CcLiqPayWC
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'woocommerce_liqpay_settings' );
