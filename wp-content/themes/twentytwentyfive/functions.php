<?php
/**
 * Pilloway standalone theme setup.
 *
 * All theme templates and assets are owned by Pilloway; no parent theme is required.
 */

require_once __DIR__ . '/inc/theme-base.php';

function pilloway_theme_theme_setup() {
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
	add_theme_support( 'menus' );
	register_nav_menu( 'pilloway-primary', __( 'Primary navigation', 'pilloway' ) );
}
add_action( 'after_setup_theme', 'pilloway_theme_theme_setup', 20 );

/**
 * Allow the server-rendered menu block inside core/navigation.
 *
 * The block's menu data remains in WordPress; only its renderer lives in this theme.
 */
function pilloway_theme_allow_primary_menu_block( $settings, $metadata ) {
	if ( 'core/navigation' === ( $metadata['name'] ?? '' ) ) {
		$settings['allowed_blocks'] = array_values(
			array_unique(
				array_merge(
					$settings['allowed_blocks'] ?? array(),
					array( 'pilloway/primary-menu' )
				)
			)
		);
	}
	return $settings;
}
add_filter( 'block_type_metadata_settings', 'pilloway_theme_allow_primary_menu_block', 10, 2 );


/**
 * Register the custom navigation block from this child theme.
 */
function pilloway_theme_register_primary_menu_block() {
	$block_name = 'pilloway/primary-menu';
	$registry   = WP_Block_Type_Registry::get_instance();

	// Register our menu renderer from this standalone theme.
	if ( ! $registry->is_registered( $block_name ) ) {
		register_block_type( get_stylesheet_directory() . '/blocks/primary-menu' );
	}
}
add_action( 'init', 'pilloway_theme_register_primary_menu_block', 20 );

/**
 * Load the Pilloway stylesheet after the base stylesheet.
 */
function pilloway_theme_enqueue_styles() {
	wp_enqueue_style(
		'pilloway-theme',
		get_stylesheet_uri(),
		array( 'pilloway-base-style' ),
		wp_get_theme()->get( 'Version' )
	);
}
add_action( 'wp_enqueue_scripts', 'pilloway_theme_enqueue_styles', 20 );
