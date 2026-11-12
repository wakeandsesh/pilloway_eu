<?php
/**
 * Texts WooCommerce keeps in its settings — attribute names ("Colour", "Size")
 * and the subjects / headings / extra content of the e-mails — are registered
 * in Languages → Translations (Polylang strings) and shown in the shopper's or
 * the order's language.
 *
 * @package CatCodePolylangShop
 */

namespace CatCode\PolylangShop\Front;

use CatCode\PolylangShop\Core\Lang;

defined( 'ABSPATH' ) || exit;

class Strings {

	public const GROUP_ATTRIBUTES = 'WooCommerce: attributes';
	public const GROUP_EMAILS     = 'WooCommerce: e-mails';

	/** E-mail setting keys that hold customer-facing text. */
	public const EMAIL_KEYS = array( 'subject', 'heading', 'additional_content', 'subject_full', 'subject_partial', 'heading_full', 'heading_partial', 'subject_paid', 'heading_paid', 'subject_downloadable', 'heading_downloadable' );

	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_strings' ) );
		add_filter( 'woocommerce_attribute_label', array( $this, 'attribute_label' ), 20, 3 );
		add_filter( 'woocommerce_email_get_option', array( $this, 'email_option' ), 20, 4 );
		// Before WooCommerce replaces {site_title} and friends — the registered string has them.
		add_filter( 'woocommerce_email_footer_text', array( $this, 'footer_text' ), 5 );
	}

	/** Only on Polylang's Translations screen — that is the one place the list is read. */
	public static function on_strings_screen(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- screen detection only.
		return is_admin() && isset( $_GET['page'] ) && 'mlang_strings' === sanitize_key( wp_unslash( $_GET['page'] ) );
	}

	public function register_strings(): void {
		if ( ! self::on_strings_screen() || ! function_exists( 'pll_register_string' ) || ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
			return;
		}
		foreach ( (array) wc_get_attribute_taxonomies() as $attr ) {
			if ( ! empty( $attr->attribute_label ) ) {
				pll_register_string( 'pa_' . $attr->attribute_name, (string) $attr->attribute_label, self::GROUP_ATTRIBUTES );
			}
		}
		if ( ! function_exists( 'WC' ) ) {
			return;
		}
		foreach ( (array) WC()->mailer()->get_emails() as $email ) {
			if ( ! $email instanceof \WC_Email ) {
				continue;
			}
			$settings = get_option( $email->get_option_key(), array() );
			if ( ! is_array( $settings ) ) {
				continue;
			}
			foreach ( self::EMAIL_KEYS as $key ) {
				if ( isset( $settings[ $key ] ) && is_string( $settings[ $key ] ) && '' !== trim( $settings[ $key ] ) ) {
					pll_register_string( $email->id . '_' . $key, $settings[ $key ], self::GROUP_EMAILS, 'additional_content' === $key );
				}
			}
		}
		$footer = get_option( 'woocommerce_email_footer_text', '' );
		if ( is_string( $footer ) && '' !== trim( $footer ) ) {
			pll_register_string( 'woocommerce_email_footer_text', $footer, self::GROUP_EMAILS, true );
		}
	}

	/**
	 * @param string           $label   Attribute label.
	 * @param string           $name    Attribute name.
	 * @param \WC_Product|null $product Product.
	 */
	public function attribute_label( $label, $name = '', $product = null ) {
		unset( $product );
		$lang = Lang::current();
		if ( '' === $lang || ! is_string( $label ) ) {
			return $label;
		}
		unset( $name );
		return Lang::string( $label, $lang );
	}

	/**
	 * Custom subjects / headings / additional content typed in WooCommerce → Settings →
	 * Emails, in the language of the e-mail being built (see Order\Emails).
	 *
	 * @param mixed     $value Option value.
	 * @param \WC_Email $email E-mail.
	 * @param mixed     $raw   Same value, unfiltered.
	 * @param string    $key   Option key.
	 * @return mixed
	 */
	public function email_option( $value, $email, $raw = null, $key = '' ) {
		unset( $raw );
		if ( ! is_string( $value ) || '' === $value || ! in_array( (string) $key, self::EMAIL_KEYS, true ) ) {
			return $value;
		}
		$lang = Lang::current();
		return '' === $lang ? $value : Lang::string( $value, $lang );
	}

	/**
	 * @param string $text Footer text.
	 */
	public function footer_text( $text ) {
		$lang = Lang::current();
		return '' === $lang || ! is_string( $text ) ? $text : Lang::string( $text, $lang );
	}
}
