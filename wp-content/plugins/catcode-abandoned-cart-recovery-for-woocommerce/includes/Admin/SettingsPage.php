<?php
/**
 * Settings screen — native WP admin components (.form-table, submit_button).
 *
 * @package CatCodeAbandonedCart
 */

namespace CatCode\AbandonedCart\Admin;

use CatCode\AbandonedCart\Core\Settings;

defined( 'ABSPATH' ) || exit;

class SettingsPage {

	public const SLUG = 'catcode-abandoned-cart-settings';

	/** @var string */
	private $hook_suffix = '';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 21 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_catcode_abandoned_cart_save', array( $this, 'handle_save' ) );
	}

	public function register_menu(): void {
		$this->hook_suffix = (string) add_submenu_page(
			'woocommerce',
			__( 'Abandoned Cart Settings', 'catcode-abandoned-cart-recovery-for-woocommerce' ),
			__( 'Abandoned Cart Settings', 'catcode-abandoned-cart-recovery-for-woocommerce' ),
			'manage_woocommerce',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_assets( $hook_suffix ): void {
		if ( '' === $this->hook_suffix || $hook_suffix !== $this->hook_suffix ) {
			return;
		}
		wp_register_style( 'catcode-abandoned-cart-admin', false, array(), CATCODE_ABANDONED_CART_VERSION );
		wp_enqueue_style( 'catcode-abandoned-cart-admin' );
		wp_add_inline_style( 'catcode-abandoned-cart-admin', CartsPage::css() );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$cfg = Settings::all();

		echo '<div class="wrap catcode-abandoned-cart-wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Abandoned Cart Settings', 'catcode-abandoned-cart-recovery-for-woocommerce' ) . '</h1>';
		echo ' <a href="' . esc_url( admin_url( 'admin.php?page=' . CartsPage::SLUG ) ) . '" class="page-title-action">' . esc_html__( 'Cart list', 'catcode-abandoned-cart-recovery-for-woocommerce' ) . '</a>';
		echo '<hr class="wp-header-end">';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flash message.
		if ( isset( $_GET['catcode_abandoned_cart_msg'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$msg = sanitize_text_field( wp_unslash( (string) $_GET['catcode_abandoned_cart_msg'] ) );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="catcode_abandoned_cart_save"/>';
		wp_nonce_field( 'catcode_abandoned_cart_save' );

		$this->section_timing( $cfg );
		$this->section_email( $cfg );
		$this->section_privacy( $cfg );

		submit_button( __( 'Save settings', 'catcode-abandoned-cart-recovery-for-woocommerce' ), 'primary large' );
		echo '</form>';
		echo '</div>';
	}

	/**
	 * @param array<string,mixed> $cfg Settings.
	 */
	private function section_timing( array $cfg ): void {
		echo '<div class="catcode-abandoned-cart-card">';
		echo '<h2>' . esc_html__( 'Timing', 'catcode-abandoned-cart-recovery-for-woocommerce' ) . '</h2>';
		echo '<table class="form-table" role="presentation">';

		$this->number_row(
			'abandon_after',
			__( 'Mark as abandoned after', 'catcode-abandoned-cart-recovery-for-woocommerce' ),
			(int) $cfg['abandon_after'],
			__( 'Minutes of inactivity before a cart is considered abandoned. The scan runs every 15 minutes.', 'catcode-abandoned-cart-recovery-for-woocommerce' ),
			__( 'minutes', 'catcode-abandoned-cart-recovery-for-woocommerce' )
		);

		$this->number_row(
			'token_lifetime',
			__( 'Recovery link lifetime', 'catcode-abandoned-cart-recovery-for-woocommerce' ),
			(int) $cfg['token_lifetime'],
			__( 'How long a recovery link stays valid. Afterwards the cart is marked as lost.', 'catcode-abandoned-cart-recovery-for-woocommerce' ),
			__( 'days', 'catcode-abandoned-cart-recovery-for-woocommerce' )
		);

		$this->number_row(
			'email_cooldown',
			__( 'Do not e-mail the same address more often than once every', 'catcode-abandoned-cart-recovery-for-woocommerce' ),
			(int) $cfg['email_cooldown'],
			__( 'Protects frequent shoppers from repeated reminders. Set to 0 to disable.', 'catcode-abandoned-cart-recovery-for-woocommerce' ),
			__( 'days', 'catcode-abandoned-cart-recovery-for-woocommerce' )
		);

		echo '</table></div>';
	}

	/**
	 * @param array<string,mixed> $cfg Settings.
	 */
	private function section_email( array $cfg ): void {
		echo '<div class="catcode-abandoned-cart-card">';
		echo '<h2>' . esc_html__( 'Reminder e-mail', 'catcode-abandoned-cart-recovery-for-woocommerce' ) . '</h2>';
		echo '<table class="form-table" role="presentation">';

		$this->number_row(
			'email_1_delay',
			__( 'Send after abandonment', 'catcode-abandoned-cart-recovery-for-woocommerce' ),
			(int) $cfg['email_1_delay'],
			'',
			__( 'minutes', 'catcode-abandoned-cart-recovery-for-woocommerce' )
		);

		echo '<tr><th scope="row"><label for="catcode-abandoned-cart-subject">' . esc_html__( 'Subject', 'catcode-abandoned-cart-recovery-for-woocommerce' ) . '</label></th><td>';
		echo '<input type="text" class="large-text" id="catcode-abandoned-cart-subject" name="email_1_subject" value="' . esc_attr( (string) $cfg['email_1_subject'] ) . '"/>';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="catcode-abandoned-cart-body">' . esc_html__( 'Message', 'catcode-abandoned-cart-recovery-for-woocommerce' ) . '</label></th><td>';
		echo '<textarea id="catcode-abandoned-cart-body" name="email_1_body" rows="10">' . esc_textarea( (string) $cfg['email_1_body'] ) . '</textarea>';
		echo '<p class="description catcode-abandoned-cart-tokens">' . esc_html__( 'Placeholders:', 'catcode-abandoned-cart-recovery-for-woocommerce' )
			. ' <code>{customer_name}</code> <code>{cart_items}</code> <code>{recovery_link}</code> <code>{store_name}</code></p>';
		echo '</td></tr>';

		echo '</table></div>';
	}

	/**
	 * @param array<string,mixed> $cfg Settings.
	 */
	private function section_privacy( array $cfg ): void {
		echo '<div class="catcode-abandoned-cart-card">';
		echo '<h2>' . esc_html__( 'Data retention', 'catcode-abandoned-cart-recovery-for-woocommerce' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'The plugin stores the e-mail address, the name and the cart contents of shoppers who did not complete an order. Rows are deleted automatically once they are older than the retention period.', 'catcode-abandoned-cart-recovery-for-woocommerce' ) . '</p>';
		echo '<table class="form-table" role="presentation">';
		$this->number_row(
			'retention_days',
			__( 'Delete cart data after', 'catcode-abandoned-cart-recovery-for-woocommerce' ),
			(int) $cfg['retention_days'],
			__( 'Set to 0 to keep the data indefinitely (not recommended).', 'catcode-abandoned-cart-recovery-for-woocommerce' ),
			__( 'days', 'catcode-abandoned-cart-recovery-for-woocommerce' )
		);
		echo '</table></div>';
	}

	/**
	 * One numeric form-table row.
	 */
	private function number_row( string $name, string $label, int $value, string $description = '', string $suffix = '' ): void {
		$id = 'catcode-abandoned-cart-' . str_replace( '_', '-', $name );

		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<input type="number" class="small-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" min="0" step="1"/>';
		if ( '' !== $suffix ) {
			echo ' <span class="description">' . esc_html( $suffix ) . '</span>';
		}
		if ( '' !== $description ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}
		echo '</td></tr>';
	}

	public function handle_save(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'catcode-abandoned-cart-recovery-for-woocommerce' ) );
		}
		check_admin_referer( 'catcode_abandoned_cart_save' );

		$current = Settings::all();

		$values = array(
			'abandon_after'   => $this->post_int( 'abandon_after', (int) $current['abandon_after'], 1 ),
			'token_lifetime'  => $this->post_int( 'token_lifetime', (int) $current['token_lifetime'], 1 ),
			'email_cooldown'  => $this->post_int( 'email_cooldown', (int) $current['email_cooldown'], 0 ),
			'retention_days'  => $this->post_int( 'retention_days', (int) $current['retention_days'], 0 ),
			'email_1_delay'   => $this->post_int( 'email_1_delay', (int) $current['email_1_delay'], 1 ),
			'email_1_subject' => $this->post_text( 'email_1_subject', (string) $current['email_1_subject'] ),
			'email_1_body'    => $this->post_textarea( 'email_1_body', (string) $current['email_1_body'] ),
		);

		Settings::save( $values );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                       => self::SLUG,
					'catcode_abandoned_cart_msg' => rawurlencode( __( 'Settings saved.', 'catcode-abandoned-cart-recovery-for-woocommerce' ) ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private function post_int( string $key, int $fallback, int $min ): int {
		// Nonce is verified by check_admin_referer() in handle_save().
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST[ $key ] ) ) {
			return $fallback;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$value = (int) sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) );
		return max( $min, $value );
	}

	private function post_text( string $key, string $fallback ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST[ $key ] ) ) {
			return $fallback;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) );
	}

	private function post_textarea( string $key, string $fallback ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST[ $key ] ) ) {
			return $fallback;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return sanitize_textarea_field( wp_unslash( (string) $_POST[ $key ] ) );
	}
}
