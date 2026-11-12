<?php
/**
 * Uninstall: the version mark and the scheduled check go always; the settings,
 * the links between variations and the language saved in orders only when the
 * owner ticked "Delete data on uninstall". Products, their translations and the
 * translated pages are content — they always stay.
 *
 * While another copy of the plugin is still installed (the wordpress.org copy
 * catcode-multilingual-shop-for-woocommerce-and-polylang next to the build from
 * catcode.com.ua), the shared data stays for that copy.
 *
 * @package CatCodePolylangShop
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

wp_clear_scheduled_hook( 'catcode_plms_license_check' );

$catcode_plms_other = false;
$catcode_plms_copies = array_merge(
	(array) glob( WP_PLUGIN_DIR . '/*/catcode-polylang-multilingual-shop-for-woocommerce.php' ),
	(array) glob( WP_PLUGIN_DIR . '/*/catcode-multilingual-shop-for-woocommerce-and-polylang.php' )
);
foreach ( $catcode_plms_copies as $catcode_plms_file ) {
	if ( wp_normalize_path( dirname( $catcode_plms_file ) ) !== wp_normalize_path( __DIR__ ) ) {
		$catcode_plms_other = true;
	}
}
if ( $catcode_plms_other ) {
	// The licence belongs to this build only.
	$catcode_plms_settings = get_option( 'catcode_plms_settings' );
	if ( is_array( $catcode_plms_settings ) ) {
		foreach ( array( 'license_key', 'license_status', 'license_checked_at', 'license_expires_at', 'license_data', 'license_kind', 'license_owned' ) as $catcode_plms_key ) {
			unset( $catcode_plms_settings[ $catcode_plms_key ] );
		}
		update_option( 'catcode_plms_settings', $catcode_plms_settings, false );
	}
	return;
}

$catcode_plms_settings = get_option( 'catcode_plms_settings', array() );
$catcode_plms_wipe     = is_array( $catcode_plms_settings ) && isset( $catcode_plms_settings['delete_data'] ) && 'yes' === $catcode_plms_settings['delete_data'];

delete_option( 'catcode_plms_version' );

if ( $catcode_plms_wipe ) {
	delete_option( 'catcode_plms_settings' );
	delete_post_meta_by_key( '_catcode_plms_vgroup' );
	delete_post_meta_by_key( '_catcode_plms_import_group' );

	global $wpdb;
	// Order language: legacy order storage keeps it in post meta, HPOS in its own meta table.
	delete_post_meta_by_key( '_catcode_plms_lang' );
	$catcode_plms_table = $wpdb->prefix . 'wc_orders_meta';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- checking for the HPOS table of this site.
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $catcode_plms_table ) ) ) === $catcode_plms_table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- removing the plugin's own order meta.
		$wpdb->delete( $catcode_plms_table, array( 'meta_key' => '_catcode_plms_lang' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	}
}
