<?php
/**
 * WooCommerce → Polylang Shop: what is set up, what is missing (languages,
 * translated WooCommerce pages, products per language) and the few settings.
 *
 * @package CatCodePolylangShop
 */

namespace CatCode\PolylangShop\Admin;

use CatCode\PolylangShop\Core\Environment;
use CatCode\PolylangShop\Core\Lang;
use CatCode\PolylangShop\Core\Settings;
use CatCode\PolylangShop\Product\Fields;
use CatCode\PolylangShop\Pro\License;

defined( 'ABSPATH' ) || exit;

class SettingsPage {

	public const SLUG = 'catcode-polylang-shop';

	/** @var string */
	private $hook_suffix = '';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ), 60 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_catcode_plms_settings_save', array( $this, 'save_settings' ) );
	}

	public function menu(): void {
		$this->hook_suffix = (string) add_submenu_page(
			'woocommerce',
			__( 'Polylang Multilingual Shop', 'catcode-polylang-multilingual-shop-for-woocommerce' ),
			__( 'Polylang Shop', 'catcode-polylang-multilingual-shop-for-woocommerce' ),
			'manage_woocommerce',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * @param array<string,mixed> $args Extra query args.
	 */
	public static function url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * @param string $hook Admin page hook.
	 */
	public function assets( $hook ): void {
		if ( '' === $this->hook_suffix || $hook !== $this->hook_suffix ) {
			return;
		}
		wp_enqueue_style( 'catcode-plms-admin', CATCODE_PLMS_URL . 'assets/css/admin.css', array(), CATCODE_PLMS_VERSION );
		wp_enqueue_script( 'catcode-plms-license', CATCODE_PLMS_URL . 'assets/js/admin-license.js', array(), CATCODE_PLMS_VERSION, true );
		wp_localize_script(
			'catcode-plms-license',
			'catcodePlmsLicense',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( Ajax::NONCE ),
				'settingsUrl' => self::url(),
				'i18n'        => array(
					'network'           => __( 'Could not reach the server. Try again in a moment.', 'catcode-polylang-multilingual-shop-for-woocommerce' ),
					'confirmDeactivate' => __( 'Release the licence from this site? The Pro features switch off here and the key becomes free for another store.', 'catcode-polylang-multilingual-shop-for-woocommerce' ),
				),
			)
		);
		wp_enqueue_script( 'catcode-plms-bulk', CATCODE_PLMS_URL . 'assets/js/admin-bulk.js', array(), CATCODE_PLMS_VERSION, true );
		wp_localize_script(
			'catcode-plms-bulk',
			'catcodePlmsBulk',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( Ajax::NONCE ),
				'i18n'    => array(
					'confirm' => __( 'Create the missing translations of all categories, tags, attribute terms and products in this language? Existing translations are not touched.', 'catcode-polylang-multilingual-shop-for-woocommerce' ),
					/* translators: %d: number of created translations. */
					'done'    => __( 'Done. Translations created: %d.', 'catcode-polylang-multilingual-shop-for-woocommerce' ),
					'failed'  => __( 'The step failed. Reload the page and start again — finished items are skipped.', 'catcode-polylang-multilingual-shop-for-woocommerce' ),
				),
			)
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		echo '<div class="wrap catcode-plms-wrap">';
		echo '<h1>' . esc_html__( 'Polylang Multilingual Shop', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</h1>';

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display-only flash messages.
		if ( isset( $_GET['catcode_plms_msg'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</p></div>';
		}
		if ( isset( $_GET['catcode_plms_pages'] ) ) {
			$created = absint( wp_unslash( $_GET['catcode_plms_pages'] ) );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(
				sprintf(
					/* translators: %d: number of pages created. */
					_n( '%d page translation created.', '%d page translations created.', $created, 'catcode-polylang-multilingual-shop-for-woocommerce' ),
					$created
				)
			) . '</p></div>';
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$problems = Environment::problems();
		if ( ! empty( $problems ) ) {
			echo '<div class="catcode-plms-card"><h2>' . esc_html__( 'Not active yet', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</h2><ul class="catcode-plms-problems ul-disc">';
			foreach ( $problems as $problem ) {
				echo '<li>' . esc_html( $problem ) . '</li>';
			}
			echo '</ul></div></div>';
			return;
		}
		if ( ! Lang::ready() ) {
			echo '<div class="catcode-plms-card"><h2>' . esc_html__( 'Add your languages', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</h2><p>' . esc_html__( 'Polylang has no languages yet. Add the store languages in Languages → Languages, then come back here.', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</p>';
			echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=mlang' ) ) . '">' . esc_html__( 'Open Languages', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</a></p></div></div>';
			return;
		}

		$this->section_status();
		$this->section_pages();
		$this->section_howto();
		$this->section_settings();
		$this->section_bulk();
		$this->section_license();
		echo '</div>';
	}

	private function section_status(): void {
		$default = Lang::default();
		$counts  = array();
		foreach ( Lang::slugs() as $slug ) {
			$counts[ $slug ] = self::count_products( $slug );
		}
		$no_lang = self::count_products( '' );

		echo '<div class="catcode-plms-card"><h2>' . esc_html__( 'Languages and products', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</h2>';
		echo '<table class="catcode-plms-table"><thead><tr><th>' . esc_html__( 'Language', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</th><th>' . esc_html__( 'Products', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</th><th>' . esc_html__( 'Not translated yet', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</th></tr></thead><tbody>';
		foreach ( $counts as $slug => $count ) {
			$gap = $slug === $default ? 0 : max( 0, $counts[ $default ] - $count );
			echo '<tr><td>' . esc_html( Lang::name( $slug ) ) . ( $slug === $default ? ' <em>(' . esc_html__( 'default', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . ')</em>' : '' ) . '</td>';
			echo '<td>' . esc_html( (string) $count ) . '</td>';
			echo '<td>' . ( $gap > 0 ? '<span class="catcode-plms-missing">' . esc_html( (string) $gap ) . '</span>' : '<span class="catcode-plms-ok">0</span>' ) . '</td></tr>';
		}
		echo '</tbody></table>';
		if ( $no_lang > 0 ) {
			echo '<p class="catcode-plms-missing">' . esc_html(
				sprintf(
					/* translators: %d: number of products. */
					_n( '%d product has no language yet — Polylang offers to put such content in the default language on its Languages screen.', '%d products have no language yet — Polylang offers to put such content in the default language on its Languages screen.', $no_lang, 'catcode-polylang-multilingual-shop-for-woocommerce' ),
					$no_lang
				)
			) . '</p>';
		}
		echo '<p class="description">' . esc_html__( 'To translate a product, open Products and click "+" in the column of the language: a full copy is created in that language, you translate the texts.', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</p>';
		echo '</div>';
	}

	private function section_pages(): void {
		$status = PagesTool::status();
		$labels = PagesTool::pages();
		echo '<div class="catcode-plms-card"><h2>' . esc_html__( 'WooCommerce pages', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</h2>';
		if ( empty( $status ) ) {
			echo '<p>' . esc_html__( 'WooCommerce has no shop, cart, checkout or account page set (WooCommerce → Settings → Advanced).', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</p></div>';
			return;
		}
		echo '<p class="description">' . esc_html__( 'Each language needs its own shop, cart, checkout and account page. They are found automatically once they are translations of the pages set in WooCommerce → Settings → Advanced.', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</p>';
		echo '<table class="catcode-plms-table"><thead><tr><th>' . esc_html__( 'Page', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</th>';
		foreach ( Lang::slugs() as $slug ) {
			echo '<th>' . esc_html( Lang::name( $slug ) ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $status as $key => $row ) {
			echo '<tr><td>' . esc_html( isset( $labels[ $key ] ) ? $labels[ $key ] : $key ) . '</td>';
			foreach ( Lang::slugs() as $slug ) {
				$id = isset( $row[ $slug ] ) ? (int) $row[ $slug ] : 0;
				if ( $id > 0 ) {
					echo '<td><a href="' . esc_url( (string) get_edit_post_link( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a>' . ( 'publish' !== get_post_status( $id ) ? ' <em>(' . esc_html( (string) get_post_status( $id ) ) . ')</em>' : '' ) . '</td>';
				} else {
					echo '<td><span class="catcode-plms-missing">' . esc_html__( 'missing', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</span></td>';
				}
			}
			echo '</tr>';
		}
		echo '</tbody></table>';
		if ( PagesTool::missing_count() > 0 ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo '<input type="hidden" name="action" value="' . esc_attr( PagesTool::ACTION ) . '"/>';
			wp_nonce_field( PagesTool::ACTION );
			submit_button( __( 'Create the missing page translations', 'catcode-polylang-multilingual-shop-for-woocommerce' ), 'primary', 'submit', false );
			echo '<p class="description">' . esc_html__( 'Copies of the original pages (with the cart / checkout / account block or shortcode) are published in each missing language. Translate their titles and slugs afterwards.', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</p>';
			echo '</form>';
		}
		echo '</div>';
	}

	private function section_howto(): void {
		echo '<div class="catcode-plms-card"><h2>' . esc_html__( 'What happens in the shop', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</h2><ul class="ul-disc">';
		echo '<li>' . esc_html__( 'Products, categories, tags and attribute terms have a language; each language shows its own.', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</li>';
		echo '<li>' . esc_html__( 'Prices, stock, SKU and the other shared data stay the same in every translation — edit any language, the others follow. An order in one language takes the stock of all.', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</li>';
		echo '<li>' . esc_html__( 'When a shopper switches language, the cart switches with them. The order keeps its language (see the Language column in Orders) and customer e-mails are sent in it.', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</li>';
		echo '<li>' . esc_html__( 'Attribute names and the e-mail subjects, headings and texts you typed in WooCommerce are translated in Languages → Translations.', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</li>';
		echo '<li>' . esc_html__( 'A coupon limited to a product or category covers it in every language.', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</li>';
		echo '</ul></div>';
	}

	private function section_settings(): void {
		$cfg = Settings::all();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="catcode-plms-card">';
		echo '<input type="hidden" name="action" value="catcode_plms_settings_save"/>';
		wp_nonce_field( 'catcode_plms_settings_save' );
		echo '<h2>' . esc_html__( 'Settings', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</h2>';
		echo '<table class="form-table" role="presentation">';

		echo '<tr><th scope="row"><label for="catcode-plms-new-status">' . esc_html__( 'New product translation', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</label></th><td>';
		echo '<select id="catcode-plms-new-status" name="new_status">';
		echo '<option value="draft"' . selected( 'draft', $cfg['new_status'], false ) . '>' . esc_html__( 'Draft — publish it after translating the texts', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</option>';
		echo '<option value="source"' . selected( 'source', $cfg['new_status'], false ) . '>' . esc_html__( 'Same status as the original', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</option>';
		echo '</select></td></tr>';

		$this->checkbox_row( 'copy_terms', __( 'Missing categories and attribute values', 'catcode-polylang-multilingual-shop-for-woocommerce' ), __( 'When a product is translated, create its categories, tags and attribute values in the target language as copies if they are not translated yet (rename them later in Products → Categories / Attributes).', 'catcode-polylang-multilingual-shop-for-woocommerce' ), $cfg );
		$this->checkbox_row( 'delete_data', __( 'Delete data on uninstall', 'catcode-polylang-multilingual-shop-for-woocommerce' ), __( 'When the plugin is deleted, also remove its settings, the links between variations and the language saved in orders. Products, translations and pages stay.', 'catcode-polylang-multilingual-shop-for-woocommerce' ), $cfg );

		$pro    = License::is_pro();
		$chosen = is_array( $cfg['sync_groups'] ) ? $cfg['sync_groups'] : array();
		echo '<tr class="' . ( $pro ? '' : 'catcode-plms-locked' ) . '"><th scope="row">' . esc_html__( 'Shared by all translations', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</th><td class="catcode-plms-groups">';
		foreach ( Fields::groups() as $group => $label ) {
			$on = ! isset( $chosen[ $group ] ) || 'no' !== $chosen[ $group ];
			echo '<label><input type="checkbox" name="sync_groups[]" value="' . esc_attr( $group ) . '"' . checked( $on, true, false ) . disabled( $pro, false, false ) . '/> ' . esc_html( $label ) . '</label>';
		}
		echo '<p class="description">' . esc_html__( 'Untick what each language should keep separately — e.g. its own images, its own price or its own stock. Pro feature; without a licence everything in this list is shared.', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</p></td></tr>';
		echo '<tr class="' . ( $pro ? '' : 'catcode-plms-locked' ) . '"><th scope="row">' . esc_html__( 'Payment and shipping texts', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</th><td><label><input type="checkbox" name="texts" value="1"' . checked( 'yes', $cfg['texts'], false ) . disabled( $pro, false, false ) . '/> ' . esc_html__( 'Translate payment method titles, descriptions and instructions, shipping method titles, the store notice and the checkout privacy / terms texts in Languages → Translations. Pro feature.', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</label></td></tr>';

		echo '</table>';
		submit_button( __( 'Save settings', 'catcode-polylang-multilingual-shop-for-woocommerce' ) );
		echo '</form>';
	}

	/**
	 * @param string              $key   Setting.
	 * @param string              $label Label.
	 * @param string              $help  Help text.
	 * @param array<string,mixed> $cfg   Settings.
	 */
	private function checkbox_row( string $key, string $label, string $help, array $cfg ): void {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td><label><input type="checkbox" name="' . esc_attr( $key ) . '" value="1"' . checked( 'yes', $cfg[ $key ], false ) . '/> ' . esc_html( $help ) . '</label></td></tr>';
	}

	public function save_settings(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'catcode-polylang-multilingual-shop-for-woocommerce' ) );
		}
		check_admin_referer( 'catcode_plms_settings_save' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- check_admin_referer() ran above.
		$patch = array(
			'new_status'  => ( isset( $_POST['new_status'] ) && 'source' === sanitize_key( wp_unslash( $_POST['new_status'] ) ) ) ? 'source' : 'draft',
			'copy_terms'  => ! empty( $_POST['copy_terms'] ) ? 'yes' : 'no',
			'delete_data' => ! empty( $_POST['delete_data'] ) ? 'yes' : 'no',
		);
		if ( License::is_pro() ) {
			$posted = isset( $_POST['sync_groups'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['sync_groups'] ) ) : array();
			$groups = array();
			foreach ( array_keys( Fields::groups() ) as $group ) {
				$groups[ $group ] = in_array( $group, $posted, true ) ? 'yes' : 'no';
			}
			$patch['sync_groups'] = $groups;
			$patch['texts']       = ! empty( $_POST['texts'] ) ? 'yes' : 'no';
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		Settings::update( $patch );
		wp_safe_redirect( self::url( array( 'catcode_plms_msg' => 1 ) ) );
		exit;
	}

	/** Products (any status except trash) in a language; '' = products with no language. */
	private static function count_products( string $lang ): int {
		$args = array(
			'post_type'              => 'product',
			'post_status'            => array( 'publish', 'private', 'draft', 'pending', 'future' ),
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'lang'                   => $lang,
		);
		if ( '' === $lang ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- settings screen only.
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'language',
					'operator' => 'NOT EXISTS',
				),
			);
		}
		$query = new \WP_Query( $args );
		return (int) $query->found_posts;
	}


	private function section_bulk(): void {
		$pro     = License::is_pro();
		$default = Lang::default();
		echo '<div class="catcode-plms-card' . ( $pro ? '' : ' catcode-plms-locked' ) . '" id="bulk"><h2>' . esc_html__( 'Translate the whole catalogue', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Creates, in one run, the missing translations of every category, tag, attribute term and product of the default language — full copies, the same as the "+" button. Existing translations are not touched; the run can be stopped and started again. Pro feature.', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</p>';
		echo '<p><label for="catcode-plms-bulk-lang">' . esc_html__( 'Language', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</label> <select id="catcode-plms-bulk-lang"' . disabled( $pro, false, false ) . '>';
		foreach ( Lang::slugs() as $slug ) {
			if ( $slug !== $default ) {
				echo '<option value="' . esc_attr( $slug ) . '">' . esc_html( Lang::name( $slug ) ) . '</option>';
			}
		}
		echo '</select> <label for="catcode-plms-bulk-status">' . esc_html__( 'Status of new products', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</label> <select id="catcode-plms-bulk-status"' . disabled( $pro, false, false ) . '>';
		echo '<option value="draft">' . esc_html__( 'Draft', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</option>';
		echo '<option value="source">' . esc_html__( 'Same as the original', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</option>';
		echo '</select> <button type="button" class="button button-primary" id="catcode-plms-bulk-start"' . disabled( $pro, false, false ) . '>' . esc_html__( 'Create missing translations', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</button></p>';
		echo '<pre id="catcode-plms-bulk-log" class="catcode-plms-log" hidden></pre>';
		echo '</div>';
	}

	private function section_license(): void {
		$cfg       = Settings::all();
		$is_pro    = License::is_pro();
		$on_trial  = License::on_trial();
		$can_trial = License::trial_available();
		$owned     = License::is_owned();
		$updates   = License::updates_active();
		$key       = (string) $cfg['license_key'];

		echo '<div class="catcode-plms-card" id="licence">';
		echo '<h2>' . esc_html__( 'Pro licence', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</h2>';

		echo '<p class="description">';
		if ( $is_pro ) {
			if ( $on_trial ) {
				$line = __( 'Pro is on — trial.', 'catcode-polylang-multilingual-shop-for-woocommerce' );
			} elseif ( $owned ) {
				$line = __( 'Pro is on — licence purchased, the features stay on for good.', 'catcode-polylang-multilingual-shop-for-woocommerce' );
			} else {
				$line = __( 'Pro is on — licence active.', 'catcode-polylang-multilingual-shop-for-woocommerce' );
			}
			echo '<strong>' . esc_html( $line ) . '</strong> ';
		} else {
			echo '<strong>' . esc_html__( 'Free version.', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</strong> ';
			echo esc_html__( 'Translatable products, categories and attributes, one-click translation, shared price / stock / SKU, pages per language, the cart following the language and e-mails in the order language work without a licence. Pro adds translating the whole catalogue in one run, choosing what each language keeps separately, payment / shipping / checkout texts per language and CSV export / import with languages.', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . ' ';
		}
		if ( ! $owned ) {
			echo esc_html(
				sprintf(
					/* translators: %s: human-readable licence status. */
					__( 'Status: %s.', 'catcode-polylang-multilingual-shop-for-woocommerce' ),
					License::describe()
				)
			);
		}
		$expires = License::expires_at();
		if ( '' !== $expires && $owned ) {
			echo '</p><p class="description">' . esc_html(
				$updates
					? sprintf(
						/* translators: %s: date the updates and support term ends, YYYY-MM-DD. */
						__( 'Updates and support until %s.', 'catcode-polylang-multilingual-shop-for-woocommerce' ),
						$expires
					)
					: sprintf(
						/* translators: %s: date the updates and support term ended, YYYY-MM-DD. */
						__( 'The updates and support term ended on %s. Renew the licence to keep receiving new versions — the Pro features keep working either way.', 'catcode-polylang-multilingual-shop-for-woocommerce' ),
						$expires
					)
			);
			if ( ! $updates ) {
				echo ' <a class="button button-small" href="' . esc_url( License::MODULE_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'Renew the licence', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</a>';
			}
		} elseif ( '' !== $expires ) {
			echo ' ' . esc_html(
				sprintf(
					/* translators: %s: expiry date, YYYY-MM-DD. */
					__( 'Valid until %s.', 'catcode-polylang-multilingual-shop-for-woocommerce' ),
					$expires
				)
			);
		}
		echo '</p>';

		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row"><label for="catcode-plms-license">' . esc_html__( 'Licence key', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</label></th><td>';
		echo '<input type="text" class="regular-text" id="catcode-plms-license" value="' . esc_attr( $key ) . '" autocomplete="off" spellcheck="false"/> ';
		// AJAX buttons only: the key never rides along with "Save settings".
		echo '<button type="button" class="button button-secondary" id="catcode-plms-activate">' . esc_html__( 'Activate key', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</button> ';
		if ( '' !== $key ) {
			echo '<button type="button" class="button" id="catcode-plms-deactivate">' . esc_html__( 'Release licence', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</button>';
		}
		echo '<p class="description">' . esc_html__( 'The key arrives by e-mail after purchase and is checked against catcode.com.ua. Once a purchased key is confirmed, Pro stays on for good — the licence term covers updates and support. A trial key switches Pro off when its 7 days are over.', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</p>';
		echo '<p id="catcode-plms-license-msg" class="catcode-plms-msg"></p>';
		echo '</td></tr>';

		if ( $owned ) {
			echo '</table></div>';
			return;
		}

		echo '<tr><th scope="row">' . esc_html__( 'No licence yet?', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</th><td>';
		if ( $can_trial ) {
			echo '<button type="button" class="button button-primary" id="catcode-plms-trial-open">' . esc_html__( 'Try Pro for 7 days', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</button> ';
			echo '<a class="button" href="' . esc_url( License::MODULE_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'Buy a licence', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</a>';
			echo '<p class="description">' . esc_html__( 'The trial issues a real 7-day key to your e-mail. No card, nothing is charged, and it never starts by itself.', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</p>';
		} else {
			echo '<a class="button" href="' . esc_url( License::MODULE_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'Buy a licence', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</a>';
			echo '<p class="description">' . esc_html__( 'The trial has already been used on this site — one per install.', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</p>';
		}
		echo '</td></tr></table>';

		if ( $can_trial ) {
			$user  = wp_get_current_user();
			$email = ( $user && ! empty( $user->user_email ) ) ? (string) $user->user_email : '';
			echo '<div class="catcode-plms-modal" id="catcode-plms-modal" hidden>';
			echo '<div class="catcode-plms-modal__backdrop catcode-plms-modal-close"></div>';
			echo '<div class="catcode-plms-modal__box" role="dialog" aria-modal="true" aria-labelledby="catcode-plms-modal-title">';
			echo '<h2 id="catcode-plms-modal-title">' . esc_html__( 'Try Pro for 7 days', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</h2>';
			echo '<p>' . esc_html__( 'Whole-catalogue translation, per-language data, payment / shipping / checkout texts per language and CSV with languages — free for 7 days. We e-mail you the key and switch Pro on right away.', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</p>';
			echo '<p><label for="catcode-plms-trial-email">' . esc_html__( 'Your e-mail', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</label><br/>';
			echo '<input type="email" class="regular-text" id="catcode-plms-trial-email" value="' . esc_attr( $email ) . '" autocomplete="email"/></p>';
			echo '<p><button type="button" class="button button-primary" id="catcode-plms-trial-start">' . esc_html__( 'Get the key', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</button> ';
			echo '<button type="button" class="button catcode-plms-modal-close">' . esc_html__( 'Cancel', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . '</button></p>';
			echo '<p id="catcode-plms-trial-msg" class="catcode-plms-msg"></p>';
			echo '<p id="catcode-plms-trial-key" class="catcode-plms-modal__key" hidden>' . esc_html__( 'Your key:', 'catcode-polylang-multilingual-shop-for-woocommerce' ) . ' <code></code></p>';
			echo '</div></div>';
		}
		echo '</div>';
	}

}
