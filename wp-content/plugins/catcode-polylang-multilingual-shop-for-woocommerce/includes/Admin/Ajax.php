<?php
/**
 * Admin AJAX: licence key, trial, notice dismissal.
 *
 * Every Pro-side action is refused on the server (not merely greyed out in the
 * form): a forged POST from an install without a licence must get a refusal,
 * not the feature.
 *
 * @package CatCodePolylangShop
 */

namespace CatCode\PolylangShop\Admin;

use CatCode\PolylangShop\Core\Lang;
use CatCode\PolylangShop\Pro\Bulk;
use CatCode\PolylangShop\Pro\License;

defined( 'ABSPATH' ) || exit;

class Ajax {

	public const NONCE = 'catcode_plms_admin';

	public static function register(): void {
		add_action( 'wp_ajax_catcode_plms_license', array( __CLASS__, 'handle_license' ) );
		add_action( 'wp_ajax_catcode_plms_trial', array( __CLASS__, 'handle_trial' ) );
		add_action( 'wp_ajax_catcode_plms_bulk', array( __CLASS__, 'handle_bulk' ) );
	}

	/** One step of "translate the catalogue" — refused without Pro, whatever the form showed. */
	public static function handle_bulk(): void {
		self::guard();
		if ( ! License::is_pro() ) {
			wp_send_json_error( array( 'message' => __( 'Translating the whole catalogue needs an active licence or trial.', 'catcode-polylang-multilingual-shop-for-woocommerce' ) ), 403 );
		}
		if ( ! current_user_can( 'edit_products' ) || ! current_user_can( 'manage_product_terms' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'catcode-polylang-multilingual-shop-for-woocommerce' ) ), 403 );
		}
		$lang = sanitize_key( self::post( 'target' ) );
		if ( ! Lang::is_valid( $lang ) || Lang::default() === $lang ) {
			wp_send_json_error( array( 'message' => __( 'Choose a language other than the default one.', 'catcode-polylang-multilingual-shop-for-woocommerce' ) ) );
		}
		$step = Bulk::step(
			$lang,
			sanitize_key( self::post( 'stage' ) ),
			absint( self::post( 'offset' ) ),
			'source' === self::post( 'status' ) ? 'source' : 'draft'
		);
		wp_send_json_success( $step );
	}

	private static function guard(): void {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'catcode-polylang-multilingual-shop-for-woocommerce' ) ), 403 );
		}
	}

	private static function post( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- guard() ran above.
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) ) : '';
	}

	/** Activate a purchased key, or release it from this site. */
	public static function handle_license(): void {
		self::guard();

		if ( 'deactivate' === self::post( 'mode' ) ) {
			License::deactivate();
			wp_send_json_success(
				array(
					'message' => __( 'Licence released from this site — the key can now be used on another store.', 'catcode-polylang-multilingual-shop-for-woocommerce' ),
					'status'  => License::describe(),
				)
			);
		}

		$result = License::activate( self::post( 'key' ) );

		if ( ! empty( $result['ok'] ) ) {
			wp_send_json_success(
				array(
					'message' => __( 'Licence active — the Pro features are unlocked.', 'catcode-polylang-multilingual-shop-for-woocommerce' ),
					'status'  => License::describe(),
				)
			);
		}

		wp_send_json_error(
			array(
				'message' => License::error_message( (string) ( isset( $result['error'] ) ? $result['error'] : '' ) ),
				'status'  => License::describe(),
			)
		);
	}

	/** The trial starts here and nowhere else — i.e. only on an explicit click. */
	public static function handle_trial(): void {
		self::guard();

		$result = License::start_trial( sanitize_email( self::post( 'email' ) ) );

		if ( ! empty( $result['ok'] ) ) {
			$until = (string) ( isset( $result['expires_at'] ) ? $result['expires_at'] : '' );
			$until = '' !== $until ? gmdate( 'd.m.Y', (int) strtotime( $until ) ) : '';

			wp_send_json_success(
				array(
					'message' => '' !== $until
						? sprintf(
							/* translators: %s: date the trial ends. */
							__( 'Trial started — Pro works until %s. The key has also been e-mailed to you.', 'catcode-polylang-multilingual-shop-for-woocommerce' ),
							$until
						)
						: __( 'Trial started. The key has also been e-mailed to you.', 'catcode-polylang-multilingual-shop-for-woocommerce' ),
					'key'     => (string) ( isset( $result['key'] ) ? $result['key'] : '' ),
					'status'  => License::describe(),
				)
			);
		}

		wp_send_json_error(
			array(
				'message' => License::error_message( (string) ( isset( $result['error'] ) ? $result['error'] : '' ) ),
				'status'  => License::describe(),
			)
		);
	}
}
