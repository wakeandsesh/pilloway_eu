<?php
/**
 * Site-form leads → Telegram.
 *
 * Two ways in:
 *   1. The generic `catcode_lead_captured` action — fired by KeyCRM Form Catcher
 *      (and any other CatCode module that captures a lead). Preferred: the lead
 *      is already normalised there, product and SKU included.
 *   2. Contact Form 7 directly — fallback so this plugin stands on its own when
 *      no catcher is installed.
 *
 * @package CatCodeTelegramNotify
 */

namespace CatCode\TelegramNotify;

use CatCode\TelegramNotify\Api\Client;
use CatCode\TelegramNotify\Core\Logger;
use CatCode\TelegramNotify\Core\Settings;

defined( 'ABSPATH' ) || exit;

class Forms {

	/**
	 * Leads already announced in this request, keyed by their fingerprint —
	 * guards against a catcher firing the action while CF7 also runs our hook.
	 *
	 * @var array<string,bool>
	 */
	private $sent = array();

	public function register_hooks(): void {
		add_action( 'catcode_lead_captured', array( $this, 'on_lead' ), 10, 1 );
		add_action( 'wpcf7_before_send_mail', array( $this, 'on_cf7' ), 30, 3 );
	}

	/**
	 * @param array $lead Normalised lead.
	 */
	public function on_lead( $lead ): void {
		if ( ! is_array( $lead ) ) {
			return;
		}
		$this->dispatch( $this->normalise( $lead ) );
	}

	/**
	 * @param \WPCF7_ContactForm $contact_form Submitted form.
	 * @param bool               $abort        Whether CF7 aborts (untouched here).
	 * @param \WPCF7_Submission  $submission   Submission.
	 */
	public function on_cf7( $contact_form, $abort, $submission ): void {
		// A catcher plugin owns the parsing (and fires catcode_lead_captured).
		if ( function_exists( 'kfcwp_settings' ) ) {
			return;
		}
		if ( ! is_object( $submission ) || ! method_exists( $submission, 'get_posted_data' ) ) {
			return;
		}

		$this->dispatch( $this->normalise( self::lead_from_cf7( $contact_form, $submission ) ) );
	}

	/**
	 * Fill in the keys the template expects, whatever the source provided.
	 *
	 * @param array $lead Raw lead.
	 * @return array<string,mixed>
	 */
	private function normalise( array $lead ): array {
		$defaults = array(
			'type'        => '',
			'name'        => '',
			'phone'       => '',
			'email'       => '',
			'comment'     => '',
			'product'     => '',
			'product_sku' => '',
			'page_url'    => '',
			'form_id'     => 0,
			'form_title'  => '',
			'extra'       => array(),
		);

		$lead = array_merge( $defaults, $lead );

		if ( ! is_array( $lead['extra'] ) ) {
			$lead['extra'] = array();
		}
		if ( '' === $lead['type'] ) {
			$lead['type'] = (string) $lead['form_title'];
		}

		return $lead;
	}

	/**
	 * @param array<string,mixed> $lead Normalised lead.
	 */
	private function dispatch( array $lead ): void {
		if ( 'yes' !== Settings::get( 'notify_forms', 'no' ) || ! Settings::is_configured() ) {
			return;
		}

		$fingerprint = md5( wp_json_encode( $lead ) );
		if ( isset( $this->sent[ $fingerprint ] ) ) {
			return;
		}
		$this->sent[ $fingerprint ] = true;

		$text = Formatter::lead( $lead, (string) Settings::get( 'template_form_lead', '' ) );
		if ( '' === trim( $text ) ) {
			return;
		}

		$silent = 'yes' === Settings::get( 'silent', 'no' );
		$client = new Client();

		foreach ( Settings::chats() as $chat ) {
			$res = $client->send_message( (string) $chat['chat_id'], $text, (int) $chat['thread_id'], $silent );

			Logger::log(
				0,
				'form_lead',
				(string) $chat['chat_id'],
				$res['status'],
				$res['ok'] ? 'OK' : $res['error'],
				$res['ok']
			);

			if ( $res['ok'] ) {
				/** Fires after a message actually reached Telegram. */
				do_action( 'catcode_tgnotify_sent', 'form_lead', (string) $chat['chat_id'] );
			}
		}

		Logger::prune();
	}

	/**
	 * Build a lead out of a CF7 submission: field roles come from the tag types,
	 * so it works with auto-generated field names (text-32, tel-635, …).
	 *
	 * @param \WPCF7_ContactForm $contact_form Form.
	 * @param \WPCF7_Submission  $submission   Submission.
	 * @return array<string,mixed>
	 */
	public static function lead_from_cf7( $contact_form, $submission ): array {
		$posted = (array) $submission->get_posted_data();

		$lead = array(
			'form_id'    => method_exists( $contact_form, 'id' ) ? (int) $contact_form->id() : 0,
			'form_title' => method_exists( $contact_form, 'title' ) ? (string) $contact_form->title() : '',
			'page_url'   => (string) $submission->get_meta( 'url' ),
			'extra'      => array(),
		);

		foreach ( (array) $contact_form->scan_form_tags() as $tag ) {
			$name = (string) $tag->name;
			if ( '' === $name || ! isset( $posted[ $name ] ) ) {
				continue;
			}

			$value = is_array( $posted[ $name ] ) ? implode( ', ', $posted[ $name ] ) : (string) $posted[ $name ];
			$value = trim( $value );
			if ( '' === $value ) {
				continue;
			}

			$base = (string) $tag->basetype;

			if ( in_array( $name, array( 'product', 'product-name', 'product_name' ), true ) ) {
				$lead['product'] = $value;
				continue;
			}
			if ( in_array( $name, array( 'product-sku', 'product_sku' ), true ) ) {
				$lead['product_sku'] = $value;
				continue;
			}

			switch ( $base ) {
				case 'email':
					$lead['email'] = $value;
					break;
				case 'tel':
					$lead['phone'] = $value;
					break;
				case 'textarea':
					$lead['comment'] = '' === (string) ( $lead['comment'] ?? '' )
						? $value
						: $lead['comment'] . "\n" . $value;
					break;
				case 'text':
					if ( empty( $lead['name'] ) ) {
						$lead['name'] = $value;
						break;
					}
					$label                   = self::tag_label( $tag, $name, $contact_form );
					$lead['extra'][ $label ] = $value;
					break;
				case 'acceptance':
				case 'checkbox':
					break;
				default:
					$label                   = self::tag_label( $tag, $name, $contact_form );
					$lead['extra'][ $label ] = $value;
			}
		}

		return $lead;
	}

	/**
	 * Human label for a field: the caption written next to it in the form,
	 * then its placeholder, and only then the field name itself.
	 *
	 * @param \WPCF7_FormTag $tag  Tag.
	 * @param string         $name Field name.
	 * @param mixed          $contact_form Form the tag belongs to.
	 */
	private static function tag_label( $tag, string $name, $contact_form = null ): string {
		$from_markup = self::label_from_markup( $contact_form, $name );
		if ( '' !== $from_markup ) {
			return $from_markup;
		}

		// A placeholder is a real label; the values of a select or radio are its
		// options, and printing the first one as a caption is just confusing.
		if ( is_object( $tag ) && method_exists( $tag, 'has_option' ) && $tag->has_option( 'placeholder' ) ) {
			$values = isset( $tag->values ) ? (array) $tag->values : array();
			$label  = isset( $values[0] ) ? trim( (string) $values[0] ) : '';
			if ( '' !== $label ) {
				return $label;
			}
		}

		return ucfirst( str_replace( array( '-', '_' ), ' ', $name ) );
	}

	/**
	 * The caption an editor actually wrote next to the field: the closest
	 * <label> or .label element before the tag in the form template.
	 *
	 * @param \WPCF7_ContactForm|null $contact_form Form.
	 * @param string                  $name         Field name.
	 */
	private static function label_from_markup( $contact_form, string $name ): string {
		if ( ! is_object( $contact_form ) || ! method_exists( $contact_form, 'prop' ) ) {
			return '';
		}
		$template = (string) $contact_form->prop( 'form' );
		if ( '' === $template ) {
			return '';
		}
		if ( ! preg_match( '/\[[^\]\[]*\b' . preg_quote( $name, '/' ) . '\b[^\]\[]*\]/u', $template, $m, PREG_OFFSET_CAPTURE ) ) {
			return '';
		}
		$before = substr( $template, 0, (int) $m[0][1] );
		if ( ! preg_match_all( '/<(?:label|span)[^>]*>(.*?)<\/(?:label|span)>/su', $before, $labels ) ) {
			return '';
		}
		$last = (string) end( $labels[1] );
		$last = wp_strip_all_tags( $last );
		$last = trim( html_entity_decode( $last, ENT_QUOTES, 'UTF-8' ) );
		$last = trim( preg_replace( '/\s+/u', ' ', $last ) );

		// A caption like "Area: 500 m2" carries a live value after the colon -
		// keep the caption itself and drop the rest.
		$colon = mb_strpos( $last, ':' );
		if ( false !== $colon ) {
			$head = trim( mb_substr( $last, 0, $colon ) );
			if ( '' !== $head ) {
				$last = $head;
			}
		}

		return trim( $last, " \t\n\r\0\x0B:*" );
	}
}
