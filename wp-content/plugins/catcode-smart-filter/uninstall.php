<?php
/**
 * Uninstall — the explicit act that removes the data deactivation preserves.
 *
 * @package CatCodeSmartFilter
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/includes/Core/Settings.php';
require_once __DIR__ . '/includes/Core/Repository.php';

\CatCode\SmartFilter\Core\Repository::drop();

delete_option( \CatCode\SmartFilter\Core\Settings::OPTION );
delete_option( 'catcode_sf_index_version' );
delete_option( 'catcode_sf_version' );
delete_option( 'catcode_sf_value_aliases' );
delete_option( 'catcode_sf_db_version' );
delete_option( 'catcode_sf_rebuild_cursor' );

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'catcode_sf_rebuild_tick', array(), 'catcode-smart-filter' );
}

wp_clear_scheduled_hook( 'catcode_sf_rebuild_tick' );

// Counter answers live in transients keyed by the catalogue version; they expire
// on their own, but leaving a few thousand rows behind after an uninstall is
// exactly the litter people complain about.
global $wpdb;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_catcode_sf_c_%' OR option_name LIKE '_transient_timeout_catcode_sf_c_%'" );
// phpcs:enable
