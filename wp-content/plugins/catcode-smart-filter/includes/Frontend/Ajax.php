<?php
/**
 * "N products" for an arbitrary selection.
 *
 * Used by the price slider and by the apply button when the shop asks for manual
 * apply: the shopper sees the size of the result before committing to a page
 * load. Read-only and public by design — it answers the same question the page
 * itself would, so a nonce would only break caching for no gain.
 *
 * @package CatCodeSmartFilter
 */

namespace CatCode\SmartFilter\Frontend;

use CatCode\SmartFilter\Core\Params;
use CatCode\SmartFilter\Core\Query;
use CatCode\SmartFilter\Core\Settings;

defined( 'ABSPATH' ) || exit;

class Ajax {

	public static function init(): void {
		add_action( 'wp_ajax_catcode_sf_count', array( __CLASS__, 'count' ) );
		add_action( 'wp_ajax_nopriv_catcode_sf_count', array( __CLASS__, 'count' ) );
	}

	public static function count(): void {
		if ( ! Settings::is_on( 'enabled' ) ) {
			wp_send_json( array( 'total' => 0 ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only counter.
		$raw = isset( $_GET[ Settings::PARAM ] ) ? sanitize_text_field( wp_unslash( $_GET[ Settings::PARAM ] ) ) : '';

		$scope = array();

		if ( ! empty( $_GET['ccf_tax'] ) && ! empty( $_GET['ccf_term'] ) ) {
			$scope['taxonomy'] = sanitize_key( wp_unslash( $_GET['ccf_tax'] ) );
			$scope['term_id']  = (int) $_GET['ccf_term'];
		}

		if ( ! empty( $_GET['s'] ) ) {
			$scope['search'] = sanitize_text_field( wp_unslash( $_GET['s'] ) );
		}
		// phpcs:enable

		$selection = Params::decode( $raw );

		wp_send_json( array( 'total' => Query::total( $selection, $scope ) ) );
	}
}
