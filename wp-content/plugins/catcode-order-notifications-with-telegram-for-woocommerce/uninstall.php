<?php
/**
 * Uninstall handler. Removes options. The log table is kept (audit data).
 *
 * @package CatCodeTelegramNotify
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'catcode_tgnotify_settings' );
delete_option( 'catcode_tgnotify_version' );
delete_option( 'catcode_tgnotify_crypto_key' );
delete_option( 'catcode_tgnotify_first_success' );
delete_metadata( 'user', 0, 'catcode_tgnotify_notice_dismissed', '', true );
