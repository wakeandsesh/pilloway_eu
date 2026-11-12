<?php
/**
 * Виконується при повному видаленні плагіна з WP.
 * Free version: тільки options, БД-таблиці (wp_catcode_iban_smart_invoice_payments) належать Pro
 * add-on і чистяться його uninstall.php.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// The full version from catcode.com.ua uses the same settings: leave them while it is installed.
if ( glob( WP_PLUGIN_DIR . '/*/iban-smart-invoice.php' ) ) {
	return;
}

delete_option('catcode_iban_smart_invoice_settings');
delete_option('catcode_iban_smart_invoice_db_version');
