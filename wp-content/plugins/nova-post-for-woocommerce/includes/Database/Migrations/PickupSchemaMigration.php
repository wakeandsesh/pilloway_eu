<?php
/**
 * Pickups table schema migration.
 *
 * @package NovaPostForWoocommerce
 */

namespace NovaPostForWoocommerce\Database\Migrations;

/**
 * Class PickupSchemaMigration
 *
 * @package NovaPostForWoocommerce\Database\Migrations
 */
class PickupSchemaMigration {

    /**
     * The current version of the `np_pickups` table schema.
     */
    private const SCHEMA_VERSION = '1.0.0';

    /**
     * The option name for storing the schema version.
     */
    private const OPTION_NAME = 'npforwc_pickups_schema_version';

    /**
     * Check if the `np_pickups` table schema needs to be updated and run the migration if it does.
     *
     * @return void
     */
    public static function maybe_update() {
        $current_version = get_option( self::OPTION_NAME, '0.0.0' );

        if ( version_compare( $current_version, self::SCHEMA_VERSION, '<' ) ) {
            self::run();
            update_option( self::OPTION_NAME, self::SCHEMA_VERSION );
        }
    }

    /**
     * Create or update the `np_pickups` table.
     *
     * @return void
     */
    public static function run() {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'np_pickups';
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            number VARCHAR(32) NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'Draft',
            order_count INT UNSIGNED NOT NULL DEFAULT 0,
            lock_version INT UNSIGNED NOT NULL DEFAULT 1,
            window_start DATETIME(6) NULL,
            window_end DATETIME(6) NULL,
            contact_name VARCHAR(120) NULL,
            contact_phone VARCHAR(20) NULL,
            contact_email VARCHAR(200) NULL,
            country_code CHAR(2) NULL,
            address_parts JSON NULL,
            request_payload JSON NULL,
            response_payload JSON NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_window_start (window_start),
            KEY idx_contact_email (contact_email),
            UNIQUE KEY idx_number (number)
        ) {$charset_collate};";

        dbDelta( $sql );
    }
}
