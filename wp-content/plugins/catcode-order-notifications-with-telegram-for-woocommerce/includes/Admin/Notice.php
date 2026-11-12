<?php
/**
 * The only notice this plugin is allowed to show.
 *
 * It appears after the plugin has actually delivered something to Telegram —
 * never right after activation, when the owner is still setting things up and
 * any banner reads as noise. The module is free, so there is nothing to sell
 * here: it just asks for a review. Dismissed once → never shown again.
 *
 * @package CatCodeTelegramNotify
 */

namespace CatCode\TelegramNotify\Admin;

defined( 'ABSPATH' ) || exit;

class Notice {

	/** Option: time of the first delivered message, 0 = nothing has happened yet. */
	public const OPTION_SUCCESS = 'catcode_tgnotify_first_success';

	/** User meta: this user closed the notice. */
	public const META_DISMISSED = 'catcode_tgnotify_notice_dismissed';

	/** Where a review can be left. */
	public const MODULE_URL = 'https://catcode.com.ua/modules/telegram-order-notifications-for-woocommerce/';

	public static function register(): void {
		add_action( 'admin_notices', array( __CLASS__, 'render' ) );
		add_action( 'wp_ajax_catcode_tgnotify_dismiss_notice', array( __CLASS__, 'dismiss' ) );
		add_action( 'catcode_tgnotify_sent', array( __CLASS__, 'mark_success' ) );
	}

	/** Remember that something worked. The first call wins, later ones are no-ops. */
	public static function mark_success(): void {
		if ( (int) get_option( self::OPTION_SUCCESS, 0 ) > 0 ) {
			return;
		}
		update_option( self::OPTION_SUCCESS, time(), false );
	}

	private static function should_show(): bool {
		if ( ! current_user_can( SettingsPage::capability() ) ) {
			return false;
		}
		if ( (int) get_option( self::OPTION_SUCCESS, 0 ) <= 0 ) {
			return false;
		}
		return ! get_user_meta( get_current_user_id(), self::META_DISMISSED, true );
	}

	public static function render(): void {
		if ( ! self::should_show() ) {
			return;
		}

		echo '<div class="notice notice-info is-dismissible catcode-tgnotify-notice" data-catcode-tgnotify-nonce="'
			. esc_attr( wp_create_nonce( 'catcode_tgnotify_notice' ) ) . '"><p><strong>'
			. esc_html__( 'Telegram notifications are working.', 'catcode-order-notifications-with-telegram-for-woocommerce' )
			. '</strong> '
			. esc_html__( 'The plugin is free — if it works for you, please leave a review, it helps keep it going.', 'catcode-order-notifications-with-telegram-for-woocommerce' )
			. ' <a class="button button-small" href="' . esc_url( self::MODULE_URL ) . '" target="_blank" rel="noopener">'
			. esc_html__( 'Leave a review', 'catcode-order-notifications-with-telegram-for-woocommerce' )
			. '</a></p></div>';

		// The notice can appear on any admin screen, including ones where our
		// styles are not printed — so "never again" travels with it.
		wp_print_inline_script_tag(
			'document.addEventListener("click",function(e){' .
			'var n=e.target.closest(".catcode-tgnotify-notice .notice-dismiss");if(!n)return;' .
			'var box=e.target.closest(".catcode-tgnotify-notice");' .
			'fetch(' . wp_json_encode( admin_url( 'admin-ajax.php' ) ) . ',{method:"POST",credentials:"same-origin",' .
			'headers:{"Content-Type":"application/x-www-form-urlencoded"},' .
			'body:"action=catcode_tgnotify_dismiss_notice&nonce="+encodeURIComponent(box.dataset.catcodeTgnotifyNonce)});' .
			'});'
		);
	}

	/** Close it for good, for the current user. */
	public static function dismiss(): void {
		check_ajax_referer( 'catcode_tgnotify_notice', 'nonce' );
		if ( ! current_user_can( SettingsPage::capability() ) ) {
			wp_send_json_error( array(), 403 );
		}
		update_user_meta( get_current_user_id(), self::META_DISMISSED, 1 );
		wp_send_json_success();
	}
}
