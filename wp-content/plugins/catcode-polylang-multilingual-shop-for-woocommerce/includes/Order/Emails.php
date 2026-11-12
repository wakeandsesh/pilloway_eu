<?php
/**
 * Customer e-mails go out in the language of the order — whoever triggers them
 * (checkout, a status change in the admin, a cron, a payment callback). Admin
 * e-mails sent during a shopper's request use the store's default language.
 *
 * The switch starts when WooCommerce asks for the recipient (the order is known
 * by then, subject and body are not built yet) and ends once the e-mail is sent.
 * The stock notifications (low stock, out of stock, backorder) are plain wp_mail()
 * calls without an e-mail object: they are wrapped around their own actions.
 *
 * @package CatCodePolylangShop
 */

namespace CatCode\PolylangShop\Order;

use CatCode\PolylangShop\Core\Lang;

defined( 'ABSPATH' ) || exit;

class Emails {

	/** @var array<int,string> spl_object_id( email ) => language switched to. */
	private $active = array();

	/** @var array<string,bool> E-mail IDs already hooked. */
	private $hooked = array();

	/** @var array<int,bool> One entry per stock notification in progress: true when it switched the language. */
	private $stock_mail = array();

	/** Admin notifications WooCommerce sends straight from these actions (WC_Emails, priority 10). */
	private const STOCK_ACTIONS = array( 'woocommerce_low_stock', 'woocommerce_no_stock', 'woocommerce_product_on_backorder' );

	public function register(): void {
		add_filter( 'woocommerce_email_classes', array( $this, 'hook_emails' ), 9999 );
		add_action( 'woocommerce_email_sent', array( $this, 'sent' ), 1, 3 );
		add_filter( 'woocommerce_allow_restoring_email_locale', array( $this, 'before_restore' ), 1, 2 );
		foreach ( self::STOCK_ACTIONS as $action ) {
			add_action( $action, array( $this, 'stock_mail_start' ), 1 );
			add_action( $action, array( $this, 'stock_mail_end' ), 1000 );
		}
	}

	/** A stock notification raised during a shopper's visit goes out in the store's default language. */
	public function stock_mail_start(): void {
		$current = Lang::ready() ? Lang::current() : '';
		$switch  = '' !== $current && $current !== Lang::default() && Lang::switch_to( Lang::default() );
		$this->stock_mail[] = $switch;
	}

	public function stock_mail_end(): void {
		if ( array_pop( $this->stock_mail ) ) {
			Lang::restore();
		}
	}

	/**
	 * @param array<string,\WC_Email> $emails E-mail objects.
	 * @return array<string,\WC_Email>
	 */
	public function hook_emails( $emails ) {
		if ( ! is_array( $emails ) ) {
			return $emails;
		}
		foreach ( $emails as $email ) {
			if ( $email instanceof \WC_Email && '' !== (string) $email->id && empty( $this->hooked[ $email->id ] ) ) {
				add_filter( 'woocommerce_email_recipient_' . $email->id, array( $this, 'recipient' ), 1000, 3 );
				$this->hooked[ $email->id ] = true;
			}
		}
		return $emails;
	}

	/**
	 * @param string    $recipient Recipient list.
	 * @param mixed     $object    Order, refund, user…
	 * @param \WC_Email $email     E-mail.
	 * @return string
	 */
	public function recipient( $recipient, $object = null, $email = null ) {
		if ( ! $email instanceof \WC_Email || '' === trim( (string) $recipient ) || ! Lang::ready() ) {
			return $recipient;
		}
		$key = spl_object_id( $email );
		if ( isset( $this->active[ $key ] ) ) {
			return $recipient;
		}

		if ( $email->is_customer_email() ) {
			$order = self::order_of( $object );
			$lang  = $order ? OrderLanguage::get( $order ) : '';
		} else {
			// Only when a shopper's language is active — in the admin the admin's own language stays.
			$current = Lang::current();
			$lang    = ( '' !== $current && $current !== Lang::default() ) ? Lang::default() : '';
		}

		/**
		 * Language of a WooCommerce e-mail; '' leaves it as WooCommerce builds it.
		 *
		 * @param string    $lang   Language slug.
		 * @param \WC_Email $email  E-mail.
		 * @param mixed     $object Order, refund, user…
		 */
		$lang = (string) apply_filters( 'catcode_plms_email_language', $lang, $email, $object );
		if ( '' === $lang || ! Lang::is_valid( $lang ) ) {
			return $recipient;
		}
		if ( Lang::switch_to( $lang ) ) {
			$this->active[ $key ] = $lang;
			self::reload( $email );
		}
		return $recipient;
	}

	/**
	 * WooCommerce keeps the default subject / heading / extra text it once read in the
	 * e-mail object (and builds the form defaults in the language of the moment the
	 * object was created). Re-read both in the current language, so one request that
	 * sends e-mails in several languages does not reuse the first one's texts.
	 */
	private static function reload( \WC_Email $email ): void {
		if ( is_callable( array( $email, 'init_form_fields' ) ) ) {
			$email->init_form_fields();
		}
		$email->init_settings();
	}

	/**
	 * @param bool      $ok    Sent.
	 * @param string    $id    E-mail ID.
	 * @param \WC_Email $email E-mail.
	 */
	public function sent( $ok, $id = '', $email = null ): void {
		unset( $ok, $id );
		if ( $email instanceof \WC_Email ) {
			$this->finish( $email );
		}
	}

	/**
	 * Safety net for e-mails that end without sending: undo the switch before
	 * WooCommerce restores its own locale.
	 *
	 * @param bool      $allow Allow WooCommerce to restore.
	 * @param \WC_Email $email E-mail.
	 * @return bool
	 */
	public function before_restore( $allow, $email = null ) {
		if ( $email instanceof \WC_Email ) {
			$this->finish( $email );
		}
		return $allow;
	}

	private function finish( \WC_Email $email ): void {
		$key = spl_object_id( $email );
		if ( isset( $this->active[ $key ] ) ) {
			unset( $this->active[ $key ] );
			Lang::restore();
			self::reload( $email );
		}
	}

	/**
	 * @param mixed $object E-mail object.
	 */
	private static function order_of( $object ): ?\WC_Order {
		if ( $object instanceof \WC_Order_Refund ) {
			$parent = wc_get_order( $object->get_parent_id() );
			return $parent instanceof \WC_Order ? $parent : null;
		}
		return $object instanceof \WC_Order ? $object : null;
	}
}
