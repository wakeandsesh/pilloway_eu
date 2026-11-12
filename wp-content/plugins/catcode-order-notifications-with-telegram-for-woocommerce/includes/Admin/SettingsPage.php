<?php
/**
 * Settings page under the WooCommerce menu — native WP admin components
 * (.form-table, .nav-tab-wrapper, submit_button). Two tabs: settings + log.
 *
 * The bot token is never echoed back; an empty submit never wipes a stored one.
 *
 * @package CatCodeTelegramNotify
 */

namespace CatCode\TelegramNotify\Admin;

use CatCode\TelegramNotify\Api\Client;
use CatCode\TelegramNotify\Core\Installer;
use CatCode\TelegramNotify\Core\Logger;
use CatCode\TelegramNotify\Core\Settings;

defined( 'ABSPATH' ) || exit;

class SettingsPage {

	/** @var string Page hook suffix returned by add_submenu_page(). */
	private $hook_suffix = '';

	private const SLUG = 'telegram-order-notifications';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_filter( 'plugin_action_links_' . CATCODE_TGNOTIFY_BASENAME, array( $this, 'action_links' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_catcode_tgnotify_save_settings', array( $this, 'handle_save' ) );
		add_action( 'admin_post_catcode_tgnotify_test_message', array( $this, 'handle_test' ) );
		add_action( 'admin_post_catcode_tgnotify_find_chats', array( $this, 'handle_find_chats' ) );
	}

	/**
	 * Without WooCommerce there is no Woo menu to hang under and no
	 * manage_woocommerce capability to check - fall back to Settings.
	 */
	public static function capability(): string {
		return \CatCode\TelegramNotify\Core\Plugin::has_woocommerce() ? 'manage_woocommerce' : 'manage_options';
	}

	/** Admin page this plugin's screen lives on. */
	private static function parent_file(): string {
		return \CatCode\TelegramNotify\Core\Plugin::has_woocommerce() ? 'admin.php' : 'options-general.php';
	}

	/**
	 * A "Settings" link in the plugins list — without it the only trace of the
	 * plugin after activation is a submenu hidden inside the WooCommerce menu.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public function action_links( $links ): array {
		$link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( self::parent_file() . '?page=' . self::SLUG ) ),
			esc_html__( 'Settings', 'catcode-order-notifications-with-telegram-for-woocommerce' )
		);

		array_unshift( $links, $link );

		return $links;
	}

	public function register_menu(): void {
		$this->hook_suffix = (string) add_submenu_page(
			\CatCode\TelegramNotify\Core\Plugin::has_woocommerce() ? 'woocommerce' : 'options-general.php',
			__( 'Telegram Notifications', 'catcode-order-notifications-with-telegram-for-woocommerce' ),
			__( 'Telegram Notifications', 'catcode-order-notifications-with-telegram-for-woocommerce' ),
			self::capability(),
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Enqueue the admin stylesheet only on this plugin's settings screen.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_assets( $hook_suffix ): void {
		if ( '' === $this->hook_suffix || $hook_suffix !== $this->hook_suffix ) {
			return;
		}
		wp_enqueue_style(
			'catcode-tgnotify-admin',
			CATCODE_TGNOTIFY_URL . 'assets/css/admin.css',
			array(),
			CATCODE_TGNOTIFY_VERSION
		);
	}

	public function render(): void {
		if ( ! current_user_can( self::capability() ) ) {
			return;
		}
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo '<div class="wrap catcode-tgnotify-wrap">';
		echo '<h1>' . esc_html__( 'Telegram Notifications', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</h1>';
		echo '<p class="catcode-tgnotify-lead">' . esc_html( \CatCode\TelegramNotify\Core\Plugin::has_woocommerce() ? __( 'Instant WooCommerce order notifications to a Telegram chat, group or topic.', 'catcode-order-notifications-with-telegram-for-woocommerce' ) : __( 'Instant notifications about site form submissions to a Telegram chat, group or topic.', 'catcode-order-notifications-with-telegram-for-woocommerce' ) ) . '</p>';

		if ( isset( $_GET['catcode_tgnotify_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$type = isset( $_GET['catcode_tgnotify_err'] ) ? 'notice-error' : 'notice-success'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice ' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( (string) $_GET['catcode_tgnotify_msg'] ) ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$base = admin_url( self::parent_file() . '?page=' . self::SLUG );
		echo '<h2 class="nav-tab-wrapper">';
		echo '<a href="' . esc_url( $base ) . '" class="nav-tab' . ( 'log' !== $tab ? ' nav-tab-active' : '' ) . '">' . esc_html__( 'Settings', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</a>';
		echo '<a href="' . esc_url( $base . '&tab=log' ) . '" class="nav-tab' . ( 'log' === $tab ? ' nav-tab-active' : '' ) . '">' . esc_html__( 'Log', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</a>';
		echo '</h2>';

		if ( 'log' === $tab ) {
			$this->render_log();
		} else {
			$this->render_settings();
		}

		echo '</div>';
	}

	private function render_settings(): void {
		$cfg       = Settings::all();
		$has_token = '' !== (string) $cfg['bot_token'];

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="catcode_tgnotify_save_settings"/>';
		wp_nonce_field( 'catcode_tgnotify_save_settings' );

		// --- Connection ---------------------------------------------------
		echo '<div class="catcode-tgnotify-card">';
		echo '<h2>' . esc_html__( 'Connection', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</h2>';
		echo '<table class="form-table" role="presentation">';

		echo '<tr><th scope="row"><label for="catcode-tgnotify-token">' . esc_html__( 'Bot token', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</label></th><td>';
		echo '<input type="password" class="regular-text" id="catcode-tgnotify-token" name="bot_token" value="" autocomplete="new-password" placeholder="' . esc_attr( $has_token ? __( '•••••• saved — enter to replace', 'catcode-order-notifications-with-telegram-for-woocommerce' ) : '123456789:AA...' ) . '"/>';
		echo '<p class="description">' . esc_html__( 'Create a bot via @BotFather → /newbot and paste the token you receive. It is stored encrypted.', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . ( $has_token ? ' <span class="catcode-tgnotify-saved">' . esc_html__( 'Token saved.', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</span>' : '' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="catcode-tgnotify-chats">' . esc_html__( 'Chats for notifications', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</label></th><td>';
		echo '<textarea id="catcode-tgnotify-chats" name="chat_ids" rows="4" class="large-text code" placeholder="123456789&#10;-1001234567890:42">' . esc_textarea( (string) $cfg['chat_ids'] ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'One per line: a chat id, a group id (negative) or a channel @username. For a topic in a forum group append ":topic_id" — e.g. -1001234567890:42.', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Silent messages', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</th><td>';
		echo '<label><input type="checkbox" name="silent" value="yes"' . checked( 'yes' === $cfg['silent'], true, false ) . '/> ' . esc_html__( 'Send notifications without sound', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</label>';
		echo '</td></tr>';

		echo '</table></div>';

		// --- Events -------------------------------------------------------
		echo '<div class="catcode-tgnotify-card">';
		echo '<h2>' . esc_html__( 'Events', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</h2>';
		echo '<table class="form-table" role="presentation">';

		if ( \CatCode\TelegramNotify\Core\Plugin::has_woocommerce() ) {
			echo '<tr><th scope="row">' . esc_html__( 'New order', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</th><td>';
			echo '<label class="catcode-tgnotify-toggle-row"><input type="checkbox" name="notify_new_order" value="yes"' . checked( 'yes' === $cfg['notify_new_order'], true, false ) . '/> ' . esc_html__( 'Notify about new orders', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</label>';
			echo '<div class="catcode-tgnotify-statuses">' . $this->status_checkboxes( 'new_order_statuses', (array) $cfg['new_order_statuses'] ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built escaped below.
			echo '<p class="description">' . esc_html__( 'An order is announced once — the first time it enters one of the selected statuses.', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</p>';
			echo '</td></tr>';

			echo '<tr><th scope="row">' . esc_html__( 'Status change', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</th><td>';
			echo '<label class="catcode-tgnotify-toggle-row"><input type="checkbox" name="notify_status" value="yes"' . checked( 'yes' === $cfg['notify_status'], true, false ) . '/> ' . esc_html__( 'Notify about order status changes', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</label>';
			echo '<div class="catcode-tgnotify-statuses">' . $this->status_checkboxes( 'status_statuses', (array) $cfg['status_statuses'] ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built escaped below.
			echo '</td></tr>';

			echo '<tr><th scope="row">' . esc_html__( 'Failed payment', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</th><td>';
			echo '<label><input type="checkbox" name="notify_failed" value="yes"' . checked( 'yes' === $cfg['notify_failed'], true, false ) . '/> ' . esc_html__( 'Notify about orders with the "Failed" status', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</label>';
			echo '</td></tr>';

			echo '<tr><th scope="row">' . esc_html__( 'Low stock', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</th><td>';
			echo '<label><input type="checkbox" name="notify_low_stock" value="yes"' . checked( 'yes' === $cfg['notify_low_stock'], true, false ) . '/> ' . esc_html__( 'Notify when a product reaches the low-stock threshold', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</label>';
			echo '<p class="description">' . esc_html__( 'The threshold is taken from WooCommerce → Settings → Products → Inventory.', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</p>';
			echo '</td></tr>';
		}

		echo '<tr><th scope="row">' . esc_html__( 'Site form leads', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</th><td>';
		echo '<label><input type="checkbox" name="notify_forms" value="yes"' . checked( 'yes' === $cfg['notify_forms'], true, false ) . '/> ' . esc_html__( 'Notify about leads from site forms', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Works with Contact Form 7 and with any plugin that fires the catcode_lead_captured action (KeyCRM Form Catcher, for example — then the lead also carries its type, product and SKU).', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</p>';
		echo '</td></tr>';

		echo '</table></div>';

		// --- Templates ----------------------------------------------------
		echo '<div class="catcode-tgnotify-card">';
		echo '<h2>' . esc_html__( 'Message templates', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</h2>';
		echo '<table class="form-table" role="presentation">';

		if ( \CatCode\TelegramNotify\Core\Plugin::has_woocommerce() ) {
			$this->template_row( 'template_new_order', __( 'New order', 'catcode-order-notifications-with-telegram-for-woocommerce' ), (string) $cfg['template_new_order'] );
			$this->template_row( 'template_status', __( 'Status change', 'catcode-order-notifications-with-telegram-for-woocommerce' ), (string) $cfg['template_status'] );
			$this->template_row( 'template_low_stock', __( 'Low stock', 'catcode-order-notifications-with-telegram-for-woocommerce' ), (string) $cfg['template_low_stock'] );
		}

		$this->template_row( 'template_form_lead', __( 'Form lead', 'catcode-order-notifications-with-telegram-for-woocommerce' ), (string) $cfg['template_form_lead'] );

		echo '</table>';
		echo '<p class="description">' . esc_html__( 'Available tags:', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</p>';
		// Tag + meaning side by side: a bare list of braces tells nobody what
		// {items_count} actually prints.
		echo '<div class="catcode-tgnotify-tags">';
		foreach ( self::placeholders() as $tag => $desc ) {
			echo '<div><code>{' . esc_html( $tag ) . '}</code> <span>' . esc_html( $desc ) . '</span></div>';
		}
		echo '</div>';
		echo '<p class="description">' . esc_html__( 'A line whose tags are all empty is removed from the message. Allowed Telegram markup: <b>, <i>, <u>, <s>, <code>, <a href>.', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</p>';
		echo '</div>';

		echo '<div class="catcode-tgnotify-actions">';
		submit_button( __( 'Save settings', 'catcode-order-notifications-with-telegram-for-woocommerce' ), 'primary large', 'submit', false );
		echo '</div>';
		echo '</form>';

		// Separate forms — these actions must not save half-filled settings.
		echo '<div class="catcode-tgnotify-tools">';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="catcode_tgnotify_test_message"/>';
		wp_nonce_field( 'catcode_tgnotify_test_message' );
		submit_button( __( 'Send a test message', 'catcode-order-notifications-with-telegram-for-woocommerce' ), 'secondary', 'submit', false );
		echo ' <span class="description">' . esc_html__( 'Checks the token and sends a test to all specified chats.', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</span>';
		echo '</form>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="catcode_tgnotify_find_chats"/>';
		wp_nonce_field( 'catcode_tgnotify_find_chats' );
		submit_button( __( 'Find chat_id', 'catcode-order-notifications-with-telegram-for-woocommerce' ), 'secondary', 'submit', false );
		echo ' <span class="description">' . esc_html__( 'Send the bot any message (or add it to a group) and click — it will show the chat ids.', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</span>';
		echo '</form>';

		echo '</div>';
	}

	private function template_row( string $name, string $label, string $value ): void {
		echo '<tr><th scope="row"><label for="catcode-tgnotify-' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<textarea id="catcode-tgnotify-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" rows="8" class="large-text code">' . esc_textarea( $value ) . '</textarea>';
		echo '</td></tr>';
	}

	/**
	 * @param string[] $selected Selected status slugs.
	 */
	private function status_checkboxes( string $name, array $selected ): string {
		$html = '';
		foreach ( wc_get_order_statuses() as $slug => $label ) {
			$short = 0 === strncmp( $slug, 'wc-', 3 ) ? substr( $slug, 3 ) : $slug;
			$html .= '<label><input type="checkbox" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( $short ) . '"'
				. checked( in_array( $short, $selected, true ), true, false ) . '/> ' . esc_html( $label ) . '</label>';
		}
		return $html;
	}

	private function render_log(): void {
		$rows = Logger::latest( 100 );

		echo '<div class="catcode-tgnotify-card" style="margin-top:16px">';
		echo '<h2>' . esc_html__( 'Recent deliveries', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</h2>';

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No events yet.', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</p></div>';
			return;
		}

		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Date', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Order', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Event', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Chat', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</th>';
		echo '<th>HTTP</th>';
		echo '<th>' . esc_html__( 'Result', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Message', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $rows as $r ) {
			$order_id = (int) $r['order_id'];
			$link     = $order_id > 0 ? admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order_id ) : '';
			echo '<tr>';
			echo '<td>' . esc_html( (string) $r['created_at'] ) . '</td>';
			echo '<td>' . ( $order_id > 0 ? '<a href="' . esc_url( $link ) . '">#' . esc_html( (string) $order_id ) . '</a>' : '—' ) . '</td>';
			echo '<td>' . esc_html( (string) $r['event'] ) . '</td>';
			echo '<td><code>' . esc_html( (string) $r['chat_id'] ) . '</code></td>';
			echo '<td>' . esc_html( (string) ( $r['http_status'] ?? '—' ) ) . '</td>';
			echo '<td>' . ( $r['success'] ? '<span style="color:#1a7f37;font-weight:600">OK</span>' : '<span style="color:#a00;font-weight:600">' . esc_html__( 'Error', 'catcode-order-notifications-with-telegram-for-woocommerce' ) . '</span>' ) . '</td>';
			echo '<td><code style="font-size:11px">' . esc_html( function_exists( 'mb_substr' ) ? mb_substr( (string) $r['message'], 0, 200 ) : substr( (string) $r['message'], 0, 200 ) ) . '</code></td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	public function handle_save(): void {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'catcode-order-notifications-with-telegram-for-woocommerce' ) );
		}
		check_admin_referer( 'catcode_tgnotify_save_settings' );

		$current = Settings::all();

		$token = isset( $_POST['bot_token'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_POST['bot_token'] ) ) ) : '';
		if ( '' === $token ) {
			$token = (string) $current['bot_token']; // Empty submit keeps the stored token.
		}

		$data = array(
			'bot_token'          => $token,
			'chat_ids'           => isset( $_POST['chat_ids'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['chat_ids'] ) ) : '',
			'notify_new_order'   => isset( $_POST['notify_new_order'] ) ? 'yes' : 'no',
			'new_order_statuses' => $this->posted_statuses( 'new_order_statuses' ),
			'notify_status'      => isset( $_POST['notify_status'] ) ? 'yes' : 'no',
			'status_statuses'    => $this->posted_statuses( 'status_statuses' ),
			'notify_failed'      => isset( $_POST['notify_failed'] ) ? 'yes' : 'no',
			'notify_low_stock'   => isset( $_POST['notify_low_stock'] ) ? 'yes' : 'no',
			'notify_forms'       => isset( $_POST['notify_forms'] ) ? 'yes' : 'no',
			'silent'             => isset( $_POST['silent'] ) ? 'yes' : 'no',
			'template_new_order' => $this->posted_template( 'template_new_order', 'new_order' ),
			'template_status'    => $this->posted_template( 'template_status', 'status' ),
			'template_low_stock' => $this->posted_template( 'template_low_stock', 'low_stock' ),
			'template_form_lead' => $this->posted_template( 'template_form_lead', 'form_lead' ),
		);

		// Without WooCommerce the order fields are not on screen; keep whatever
		// was stored for them instead of resetting it on every save.
		if ( ! \CatCode\TelegramNotify\Core\Plugin::has_woocommerce() ) {
			foreach ( array( 'notify_new_order', 'new_order_statuses', 'notify_status', 'status_statuses', 'notify_failed', 'notify_low_stock', 'template_new_order', 'template_status', 'template_low_stock' ) as $woo_key ) {
				$data[ $woo_key ] = $current[ $woo_key ];
			}
		}

		Settings::save( $data );

		$this->redirect( __( 'Settings saved.', 'catcode-order-notifications-with-telegram-for-woocommerce' ), false );
	}

	/**
	 * Unchecking everything is a legitimate choice (mute that event), so an
	 * empty list is stored as-is rather than snapped back to the defaults.
	 *
	 * @return string[]
	 */
	private function posted_statuses( string $field ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by the caller.
		if ( ! isset( $_POST[ $field ] ) || ! is_array( $_POST[ $field ] ) ) {
			return array();
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by the caller.
		$statuses = array_map( 'sanitize_key', wp_unslash( $_POST[ $field ] ) );

		return array_values( array_filter( $statuses ) );
	}

	/**
	 * Templates carry Telegram markup, so tags are kept — but only the handful
	 * Telegram itself understands. An emptied field falls back to the default
	 * rather than muting the notification silently.
	 */
	private function posted_template( string $field, string $which ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked by the caller; the value is sanitized by wp_kses() right below, which is what keeps the Telegram markup intact.
		$raw = isset( $_POST[ $field ] ) ? (string) wp_unslash( $_POST[ $field ] ) : '';
		$raw = trim(
			wp_kses(
				$raw,
				array(
					'b'    => array(),
					'i'    => array(),
					'u'    => array(),
					's'    => array(),
					'a'    => array( 'href' => array() ),
					'code' => array(),
					'pre'  => array(),
				)
			)
		);

		return '' === $raw ? Installer::default_template( $which ) : $raw;
	}

	public function handle_test(): void {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'catcode-order-notifications-with-telegram-for-woocommerce' ) );
		}
		check_admin_referer( 'catcode_tgnotify_test_message' );

		$client = new Client();
		$me     = $client->get_me();
		if ( ! $me['ok'] ) {
			/* translators: %s — error details. */
			$this->redirect( sprintf( __( 'Token error: %s', 'catcode-order-notifications-with-telegram-for-woocommerce' ), $me['error'] ), true );
		}

		$chats = Settings::chats();
		if ( empty( $chats ) ) {
			$this->redirect( __( 'No chats specified.', 'catcode-order-notifications-with-telegram-for-woocommerce' ), true );
		}

		$text = sprintf(
			/* translators: 1: line break, 2: site name, 3: line break. */
			__( '✅ <b>Test message</b>%1$sStore: %2$s%3$sTelegram notifications are configured.', 'catcode-order-notifications-with-telegram-for-woocommerce' ),
			"\n",
			\CatCode\TelegramNotify\Formatter::esc( (string) get_bloginfo( 'name' ) ),
			"\n"
		);

		$sent   = 0;
		$errors = array();
		foreach ( $chats as $chat ) {
			$res = $client->send_message( (string) $chat['chat_id'], $text, (int) $chat['thread_id'] );
			Logger::log( 0, 'test', (string) $chat['chat_id'], $res['status'], $res['ok'] ? 'OK' : $res['error'], $res['ok'] );
			if ( $res['ok'] ) {
				++$sent;
			} else {
				$errors[] = $chat['chat_id'] . ': ' . $res['error'];
			}
		}

		if ( empty( $errors ) ) {
			/* translators: 1: bot username, 2: number of chats. */
			$this->redirect( sprintf( __( 'Bot @%1$s — message sent to %2$d chat(s).', 'catcode-order-notifications-with-telegram-for-woocommerce' ), (string) ( $me['json']['result']['username'] ?? '' ), $sent ), false );
		}
		/* translators: %s — list of errors. */
		$this->redirect( sprintf( __( 'Not delivered: %s', 'catcode-order-notifications-with-telegram-for-woocommerce' ), implode( '; ', $errors ) ), true );
	}

	/**
	 * Read pending updates and report the chat ids seen there, so the admin does
	 * not have to hunt for a third-party "get my id" bot.
	 */
	public function handle_find_chats(): void {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'catcode-order-notifications-with-telegram-for-woocommerce' ) );
		}
		check_admin_referer( 'catcode_tgnotify_find_chats' );

		$res = ( new Client() )->get_updates();
		if ( ! $res['ok'] ) {
			/* translators: %s — error details. */
			$this->redirect( sprintf( __( 'Error: %s', 'catcode-order-notifications-with-telegram-for-woocommerce' ), $res['error'] ), true );
		}

		$found = array();
		foreach ( (array) ( $res['json']['result'] ?? array() ) as $update ) {
			foreach ( array( 'message', 'channel_post', 'my_chat_member' ) as $key ) {
				$chat = $update[ $key ]['chat'] ?? null;
				if ( ! is_array( $chat ) || ! isset( $chat['id'] ) ) {
					continue;
				}
				$title            = (string) ( $chat['title'] ?? trim( ( $chat['first_name'] ?? '' ) . ' ' . ( $chat['last_name'] ?? '' ) ) );
				$found[ (string) $chat['id'] ] = $chat['id'] . ( '' !== $title ? ' (' . $title . ')' : '' );
			}
		}

		if ( empty( $found ) ) {
			$this->redirect( __( 'No updates. Send the bot a message (or add it to a group) and try again. If the bot has a webhook enabled, getUpdates will not work.', 'catcode-order-notifications-with-telegram-for-woocommerce' ), true );
		}

		/* translators: %s — list of chat ids. */
		$this->redirect( sprintf( __( 'Chats found: %s', 'catcode-order-notifications-with-telegram-for-woocommerce' ), implode( ', ', $found ) ), false );
	}

	/**
	 * @return array<string,string> tag => description (used as a tooltip).
	 */
	private static function placeholders(): array {
		return array(
			'order_number'     => __( 'Order number', 'catcode-order-notifications-with-telegram-for-woocommerce' ),
			'order_id'         => __( 'Order ID', 'catcode-order-notifications-with-telegram-for-woocommerce' ),
			'order_total'      => __( 'Order total', 'catcode-order-notifications-with-telegram-for-woocommerce' ),
			'order_status'     => __( 'Current status', 'catcode-order-notifications-with-telegram-for-woocommerce' ),
			'order_status_old' => __( 'Previous status', 'catcode-order-notifications-with-telegram-for-woocommerce' ),
			'order_date'       => __( 'Order date', 'catcode-order-notifications-with-telegram-for-woocommerce' ),
			'items'            => __( 'Items list', 'catcode-order-notifications-with-telegram-for-woocommerce' ),
			'items_count'      => __( 'Item count', 'catcode-order-notifications-with-telegram-for-woocommerce' ),
			'customer_name'    => __( 'Customer name', 'catcode-order-notifications-with-telegram-for-woocommerce' ),
			'customer_phone'   => __( 'Phone', 'catcode-order-notifications-with-telegram-for-woocommerce' ),
			'customer_email'   => __( 'Email', 'catcode-order-notifications-with-telegram-for-woocommerce' ),
			'customer_note'    => __( 'Customer note', 'catcode-order-notifications-with-telegram-for-woocommerce' ),
			'payment_method'   => __( 'Payment method', 'catcode-order-notifications-with-telegram-for-woocommerce' ),
			'shipping_method'  => __( 'Shipping method', 'catcode-order-notifications-with-telegram-for-woocommerce' ),
			'shipping_total'   => __( 'Shipping total', 'catcode-order-notifications-with-telegram-for-woocommerce' ),
			'billing_address'  => __( 'Billing address', 'catcode-order-notifications-with-telegram-for-woocommerce' ),
			'shipping_address' => __( 'Shipping address', 'catcode-order-notifications-with-telegram-for-woocommerce' ),
			'admin_url'        => __( 'Admin link', 'catcode-order-notifications-with-telegram-for-woocommerce' ),
			'site_name'        => __( 'Store name', 'catcode-order-notifications-with-telegram-for-woocommerce' ),
			'product_name'     => __( 'Product name (low stock)', 'catcode-order-notifications-with-telegram-for-woocommerce' ),
			'product_sku'      => __( 'SKU (low stock)', 'catcode-order-notifications-with-telegram-for-woocommerce' ),
			'stock_qty'        => __( 'Stock quantity (low stock)', 'catcode-order-notifications-with-telegram-for-woocommerce' ),
			'type'             => __( 'Lead type (form lead)', 'catcode-order-notifications-with-telegram-for-woocommerce' ),
			'form_title'       => __( 'Form title (form lead)', 'catcode-order-notifications-with-telegram-for-woocommerce' ),
			'name'             => __( 'Name (form lead)', 'catcode-order-notifications-with-telegram-for-woocommerce' ),
			'phone'            => __( 'Phone (form lead)', 'catcode-order-notifications-with-telegram-for-woocommerce' ),
			'email'            => __( 'Email (form lead)', 'catcode-order-notifications-with-telegram-for-woocommerce' ),
			'comment'          => __( 'Comment (form lead)', 'catcode-order-notifications-with-telegram-for-woocommerce' ),
			'product'          => __( 'Product (form lead)', 'catcode-order-notifications-with-telegram-for-woocommerce' ),
			'extra'            => __( 'Other form fields', 'catcode-order-notifications-with-telegram-for-woocommerce' ),
			'page_url'         => __( 'Page the lead was sent from', 'catcode-order-notifications-with-telegram-for-woocommerce' ),
		);
	}

	private function redirect( string $msg, bool $is_error ): void {
		$url = add_query_arg(
			array_filter(
				array(
					'page'     => self::SLUG,
					'catcode_tgnotify_msg' => rawurlencode( $msg ),
					'catcode_tgnotify_err' => $is_error ? '1' : null,
				)
			),
			admin_url( self::parent_file() )
		);
		wp_safe_redirect( $url );
		exit;
	}
}
