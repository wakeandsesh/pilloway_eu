<?php
/**
 * Activation / deactivation.
 *
 * @package CcLiqPayWC
 */

namespace CatCode\LiqPayWC\Core;

defined( 'ABSPATH' ) || exit;

class Installer {

	public static function activate() {
		// Ensure the REST webhook route is reachable immediately.
		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}
}
