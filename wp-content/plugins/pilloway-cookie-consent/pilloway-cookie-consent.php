<?php
/**
 * Plugin Name: Pilloway Cookie Consent
 * Description: Discreet GDPR/CCPA cookie-consent notice. Remembers the visitor's choice in a first-party cookie and hides itself afterwards.
 * Version:     1.2.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Text Domain: pilloway-cookie-consent
 */

defined( 'ABSPATH' ) || exit;

final class Pilloway_Cookie_Consent {

	const COOKIE   = 'pilloway_consent';
	const LIFETIME = YEAR_IN_SECONDS;

	public function __construct() {
		add_action( 'wp_footer', array( $this, 'render_banner' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function enqueue_assets() {
		$asset_url = trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/';
		wp_enqueue_style( 'pilloway-cookie-consent', $asset_url . 'consent.css', array(), '1.2.0' );
		wp_enqueue_script( 'pilloway-cookie-consent', $asset_url . 'consent.js', array(), '1.2.0', true );

	}

	/**
	 * Translations for the notice, keyed by Polylang language slug. Falls back to English.
	 *
	 * @return array
	 */
	public static function strings() {
		$map = array(
			'en' => array(
				'text'       => 'We respect your privacy. Cookies keep your cart working and remember your choices; analytics cookies help us improve the site.',
				'accept'     => 'Allow all',
				'essential'  => 'Essential only',
				'link_label' => 'Privacy Policy',
				'reopen'     => 'Cookies',
			),
			'uk' => array(
				'text'       => 'Ми поважаємо вашу приватність. Cookies забезпечують роботу кошика та запам\'ятовують ваші вибори; аналітичні cookies допомагають нам покращувати сайт.',
				'accept'     => 'Дозволити все',
				'essential'  => 'Лише необхідні',
				'link_label' => 'Політика конфіденційності',
				'reopen'     => 'Cookies',
			),
			'ru' => array(
				'text'       => 'Мы уважаем вашу конфиденциальность. Cookies обеспечивают работу корзины и запоминают ваш выбор; аналитические cookies помогают нам улучшать сайт.',
				'accept'     => 'Разрешить всё',
				'essential'  => 'Только необходимые',
				'link_label' => 'Политика конфиденциальности',
				'reopen'     => 'Cookies',
			),
			'fr' => array(
				'text'       => 'Nous respectons votre vie privée. Les cookies font fonctionner votre panier et mémorisent vos choix; les cookies d\'analyse nous aident à améliorer le site.',
				'accept'     => 'Tout accepter',
				'essential'  => 'Essentiels uniquement',
				'link_label' => 'Politique de confidentialité',
				'reopen'     => 'Cookies',
			),
			'pl' => array(
				'text'       => 'Szanujemy Twoją prywatność. Cookies zapewniają działanie koszyka i zapamiętują Twoje wybory; cookies analityczne pomagają nam ulepszać witrynę.',
				'accept'     => 'Zezwól na wszystko',
				'essential'  => 'Tylko niezbędne',
				'link_label' => 'Polityka prywatności',
				'reopen'     => 'Cookies',
			),
			'de' => array(
				'text'       => 'Wir respektieren Ihre Privatsphäre. Cookies ermöglichen die Funktion Ihres Warenkorbs und merken Ihre Auswahl; Analyse-Cookies helfen uns, die Seite zu verbessern.',
				'accept'     => 'Alle erlauben',
				'essential'  => 'Nur erforderliche',
				'link_label' => 'Datenschutzrichtlinie',
				'reopen'     => 'Cookies',
			),
		);

		$slug = 'en';
		if ( function_exists( 'pll_current_language' ) ) {
			$cur = pll_current_language( 'slug' );
			if ( $cur && isset( $map[ $cur ] ) ) {
				$slug = $cur;
			}
		}

		return $map[ $slug ];
	}

	/**
	 * Find a privacy-policy page URL: WooCommerce's setting first, then common slugs.
	 *
	 * @return string
	 */
	private function privacy_url() {
		$woo_id = (int) get_option( 'wp_page_for_privacy_policy', get_option( 'woocommerce_privacy_policy_page_id', 0 ) );
		if ( $woo_id && function_exists( 'pll_get_post' ) ) { $woo_id = pll_get_post( $woo_id ) ?: $woo_id; }
		if ( $woo_id ) {
			$url = get_permalink( $woo_id );
			if ( $url ) {
				return $url;
			}
		}

		$needles = array( 'privacy', 'konfidents', 'confiden', 'datenschutz', 'privac', 'polityka' );
		$pages   = get_pages( array( 'sort' => 'title', 'sort_order' => 'ASC' ) );
		foreach ( $pages as $page ) {
			$slug = strtolower( $page->post_name );
			foreach ( $needles as $n ) {
				if ( 0 === strpos( $slug, $n ) ) {
					return (string) get_permalink( $page->ID );
				}
			}
		}

		return '';
	}

	/**
	 * Print the banner when the visitor has not chosen yet; otherwise a small re-open control.
	 *
	 * @return void
	 */
	public function render_banner() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() ) {
			return;
		}

		$strings = self::strings();

		wp_localize_script(
			'pilloway-cookie-consent',
			'PillowayConsent',
			array(
				'cookie'     => self::COOKIE,
				'privacyUrl' => $this->privacy_url(),
				'strings'    => $strings,
			)
		);

		?>
		<div id="pilloway-consent-banner" class="pilloway-consent" hidden tabindex="-1" role="dialog" aria-live="polite" aria-label="<?php echo esc_attr( $strings['link_label'] ); ?>">
			<span class="pilloway-consent__text"><?php echo esc_html( $strings['text'] ); ?></span>
			<span class="pilloway-consent__actions">
				<button type="button" class="pilloway-consent__btn pilloway-consent__btn--accept" data-consent="all"><?php echo esc_html( $strings['accept'] ); ?></button>
				<button type="button" class="pilloway-consent__btn" data-consent="essential"><?php echo esc_html( $strings['essential'] ); ?></button>
			</span>
			<?php $privacy_url = $this->privacy_url(); ?>
			<?php if ( $privacy_url ) : ?>
				<a class="pilloway-consent__link" href="<?php echo esc_url( $privacy_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $strings['link_label'] ); ?></a>
			<?php endif; ?>
		</div>

		<?php
	}
}

new Pilloway_Cookie_Consent();

/**
 * Expose the stored consent choice to other code.
 *
 * @return string|null 'all' | 'essential' | null when no choice recorded yet.
 */
function pilloway_consent_choice() {
	$raw = isset( $_COOKIE[ 'pilloway_consent' ] ) ? (string) $_COOKIE[ 'pilloway_consent' ] : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return in_array( $raw, array( 'all', 'essential' ), true ) ? $raw : null;
}
