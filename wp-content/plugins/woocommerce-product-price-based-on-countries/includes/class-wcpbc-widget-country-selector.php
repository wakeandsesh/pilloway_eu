<?php
/**
 * WooCommerce Price Based Country Selector Widget.
 *
 * @version 1.8.10
 * @package WCPBC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


/**
 * WCPBC_Widget_Country_Selector Class
 */
class WCPBC_Widget_Country_Selector extends WC_Widget {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->widget_description = __( 'Display a country switcher using a dropdown.', 'woocommerce-product-price-based-on-countries' );
		$this->widget_id          = 'wcpbc_country_selector';
		$this->widget_name        = __( 'Country Switcher', 'woocommerce-product-price-based-on-countries' );
		$this->settings           = [
			'title'                  => [
				'type'  => 'text',
				'std'   => __( 'Country', 'woocommerce-product-price-based-on-countries' ),
				'label' => __( 'Title', 'woocommerce-product-price-based-on-countries' ),
			],

			'flag'                   => [
				'type'  => 'checkbox',
				'std'   => 0,
				'label' => __( 'Display flags in supported devices', 'woocommerce-product-price-based-on-countries' ),
			],

			'other_countries_text'   => [
				'type'  => 'text',
				'std'   => __( 'Other countries', 'woocommerce-product-price-based-on-countries' ),
				'label' => __( 'Other countries text', 'woocommerce-product-price-based-on-countries' ),
			],
			'remove_other_countries' => [
				'type'  => 'checkbox',
				'std'   => 0,
				'label' => __( 'Remove "Other countries" from switcher.', 'woocommerce-product-price-based-on-countries' ),
			],
		];
		parent::__construct();
		add_action( 'update_option_wc_price_based_country_regions', [ $this, 'flush_widget_cache' ], 20 );
	}

	/**
	 * Outputs the settings update form.
	 *
	 * @param string $key Field key.
	 * @param string $value Field value.
	 * @param array  $setting An array of settings.
	 * @param array  $instance Widget instance.
	 */
	public static function remove_other_countries_field( $key, $value, $setting, $instance ) {
		?>
		<p>
			<input class="checkbox" disabled="disabled" name="remove_other_countries_pro" type="checkbox" />
			<label style="opacity: 0.7;" for="remove_other_countries_pro"><?php echo esc_html( $setting['label'] ); ?></label>
			<span style="display: block; font-size: 12px; font-style: italic; margin-left: 22px;">
				<?php
					// Translators: HTML tags.
					printf( esc_html__( '%1$sUpgrade to Pro to remove Other countries%2$s', 'woocommerce-product-price-based-on-countries' ), '<a target="_blank" rel="noopener noreferrer" class="cta-button" href="' . esc_url( wcpbc_home_url( 'widget' ) ) . '">', '</a>' );
				?>
			</span>
		</p>
		<?php
	}

	/**
	 * Flush the cache.
	 */
	public function flush_widget_cache() {
		WC_Cache_Helper::invalidate_cache_group( __CLASS__ );
	}

	/**
	 * Parse widget args.
	 *
	 * @param array $instance Widget instance values.
	 */
	protected function parse_widget_args( $instance ) {
		$selected_country = wcpbc_get_woocommerce_country();
		$widget_data      = wp_json_encode(
			array(
				'instance' => $instance,
				'id'       => $this->widget_id,
			)
		);

		$cache_key  = WC_Cache_Helper::get_cache_prefix( __CLASS__ ) . __FUNCTION__ . $widget_data . $selected_country . wcpbc()->version;
		$cache_data = wp_cache_get( $cache_key, 'widget' );
		if ( $cache_data && is_array( $cache_data ) ) {
			return $cache_data;
		}

		$rest_all_world_name    = empty( $instance['other_countries_text'] ) ? apply_filters( 'wcpbc_other_countries_text', __( 'Other countries', 'woocommerce-product-price-based-on-countries' ) ) : $instance['other_countries_text'];
		$display_rest_all_world = empty( $instance['remove_other_countries'] ) || ! wc_string_to_bool( $instance['remove_other_countries'] );
		$classname              = ! empty( $instance['className'] ) ? " {$instance['className']}" : '';
		$data                   = (object) self::get_data();
		$rest_all_world_key     = $display_rest_all_world ? $data->rest_all_world_key : false;
		$base_country           = $data->base_country;

		if ( $rest_all_world_key && $display_rest_all_world ) {

			$data->data[] = [
				'code'       => $rest_all_world_key,
				'name'       => $rest_all_world_name,
				'emoji_flag' => false,
			];
		}

		$countries = wp_list_pluck( $data->data, 'name', 'code' );

		/**
		 * Allow developers filter the list of countries
		 */
		do_action_ref_array( 'wc_price_based_country_widget_before_selected', array( &$rest_all_world_key, &$countries, $base_country, $instance ) );

		if ( is_string( $selected_country ) && ! isset( $countries[ $selected_country ] ) ) {
			$selected_country = $rest_all_world_key ? $rest_all_world_key : $base_country;
		}

		$cache_data = [
			'widget_data'            => $widget_data,
			'classname'              => $classname,
			'show_flags'             => empty( $instance['flag'] ) ? 0 : 1,
			'other_country_id'       => $rest_all_world_key,
			'remove_other_countries' => ! $display_rest_all_world,
			'countries'              => $countries,
			'selected_country'       => $selected_country,
			'label'                  => empty( $instance['title'] ) ? __( 'Country', 'woocommerce-product-price-based-on-countries' ) : $instance['title'],
		];

		wp_cache_set( $cache_key, $cache_data, 'widget' );

		return $cache_data;
	}

	/**
	 * Widget function.
	 *
	 * @see WP_Widget
	 * @version 1.9 Check the countries of the widget are in the allowed countries.
	 * @param array $args Array of arguments.
	 * @param array $instance Widget instance.
	 * @return void
	 */
	public function widget( $args, $instance ) {

		$params = $this->parse_widget_args( $instance );

		$this->widget_start( $args, $instance );

		printf(
			'<div class="wc-price-based-country wc-price-based-country-refresh-area%1$s" data-area="widget" data-id="%2$s" data-options="%3$s">',
			esc_attr( $params['classname'] ),
			esc_attr( md5( $params['widget_data'] ) ),
			esc_attr( $params['widget_data'] )
		);

		wc_get_template(
			'country-selector.php',
			$params,
			'woocommerce-product-price-based-on-countries/',
			wcpbc()->plugin_path() . '/templates/'
		);
		echo '</div>';

		$this->widget_end( $args );
	}

	/**
	 * Output a form to handle the country/currency switcher.
	 */
	public static function country_switcher_form() {
		static $form_added = false;
		if ( $form_added ) {
			// Do only once.
			return;
		}
		$form_added = true;
		?>
		<form method="post" id="wcpbc-widget-country-switcher-form" class="wcpbc-widget-country-switcher" style="display:none;">
			<input type="hidden" id="wcpbc-widget-country-switcher-input" name="wcpbc-manual-country" />
			<input type="hidden" name="redirect" value="1" />
		</form>
		<?php
	}

	/**
	 * Returns the countries for the country switcher.
	 *
	 * @return array
	 */
	public static function get_data() {

		$data          = [];
		$raw_countries = WC()->countries->get_countries();
		$base_country  = wc_get_base_location()['country'];
		$all_countries = [ $base_country ];

		foreach ( WCPBC_Pricing_Zones::get_zones() as $zone ) {
			if ( ! $zone->get_enabled() ) {
				continue;
			}
			$all_countries = array_merge( $all_countries, $zone->get_countries() );
		}

		$rest_all_world     = array_diff( array_keys( $raw_countries ), $all_countries );
		$rest_all_world_key = $rest_all_world ? array_shift( $rest_all_world ) : false;

		foreach ( array_unique( $all_countries ) as $country_code ) {
			$data[] = [
				'code'       => $country_code,
				'name'       => $raw_countries[ $country_code ],
				'emoji_flag' => WCPBC_Country_Flags::get_by_country( $country_code ),
			];
		}

		array_multisort( array_column( $data, 'name' ), SORT_LOCALE_STRING, $data );

		return [
			'data'               => $data,
			'base_country'       => $base_country,
			'rest_all_world_key' => $rest_all_world_key,
		];
	}
}

