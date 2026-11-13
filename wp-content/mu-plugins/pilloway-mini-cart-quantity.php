<?php
/** Plugin Name: Pilloway Mini-cart Quantity Updates */
defined( 'ABSPATH' ) || exit;
add_action( 'wp_enqueue_scripts', static function () {
    wp_enqueue_style( 'pilloway-mini-cart-quantity', plugins_url( 'pilloway-mini-cart-quantity.css', __FILE__ ), array(), '1.1.0' );
    wp_enqueue_script( 'pilloway-mini-cart-quantity', plugins_url( 'pilloway-mini-cart-quantity.js', __FILE__ ), array(), '1.1.0', true );
} );
