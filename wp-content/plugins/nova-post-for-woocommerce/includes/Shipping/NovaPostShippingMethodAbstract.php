<?php
/**
 * NovaPostShippingMethod class
 *
 * @package NovaPostForWoocommerce\Shipping
 */

namespace NovaPostForWoocommerce\Shipping;

use NovaPostForWoocommerce\Http\RequestBuilder\CalculationRequest;
use NovaPostForWoocommerce\Logger\Logger;
use NovaPostForWoocommerce\Models\Cache\CalculationCache;
use NovaPostForWoocommerce\Models\NovaPostSettings;
use NovaPostForWoocommerce\Models\Traits\TrackEvent;
use NovaPostForWoocommerce\Models\UserStorage;
use NovaPostForWoocommerce\Services\Cache\CachedExchangeRatesService;
use NovaPostForWoocommerce\Services\CalculationService;
use NovaPostForWoocommerce\Services\DivisionsService;
use WC_Shipping_Method;

defined( 'ABSPATH' ) || exit;

/**
 * Nova Post shipping method abstract base class.
 */
abstract class NovaPostShippingMethodAbstract extends WC_Shipping_Method {

    use TrackEvent;

    /**
     * Title of shipping method.
     *
     * @var string
     */
    public $title;

    /**
     * Calculation cache.
     *
     * @var CalculationCache
     */
    protected CalculationCache $rate_cache;

    /**
     * Get the destination type (e.g., 'division' or 'address').
     *
     * @return string
     */
    abstract protected function get_destination_type(): string;

    /**
     * Get the prefix for settings keys (e.g., 'shipping_division_' or 'shipping_address_').
     *
     * @return string
     */
    abstract protected function get_settings_prefix(): string;

    /**
     * Calculation service.
     *
     * @var CalculationService
     */
    private CalculationService $calculation_service;

    /**
     * NovaPostSettings object.
     *
     * @var NovaPostSettings
     */
    private NovaPostSettings $np_settings;

    /**
     * Get the concrete METHOD_ID for the child class.
     *
     * @return string
     */
    abstract public static function get_method_id(): string;

    /**
     * Constructor for the shipping class.
     *
     * @param int $instance_id Instance ID for the shipping method.
     */
    public function __construct( $instance_id = 0 ) {
        $this->id                  = static::get_method_id();
        $this->instance_id         = absint( $instance_id );
        $this->rate_cache          = new CalculationCache();
        $this->calculation_service = new CalculationService();
        $this->np_settings         = NovaPostSettings::get_instance();
        $this->supports            = array(
            'shipping-zones',
            'instance-settings-modal',
        );
        $this->init();
    }

    /**
     * Initialize settings for the shipping method.
     *
     * @return void
     */
    public function init() {
        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option( 'title' );

        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    /**
     * Define settings field for this shipping.
     *
     * @return void
     */
    public function init_form_fields() {
        $this->instance_form_fields = array(
            'title' => array(
                'title'       => __( 'Title', 'nova-post-for-woocommerce' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'nova-post-for-woocommerce' ),
                'default'     => $this->method_title,
                'desc_tip'    => true,
            ),
        );
    }

    /**
     * Determines if the shipping method is available for the given package.
     *
     * @param array $package The package details.
     * @return bool True if the method is available, false otherwise.
     */
    public function is_available( $package ) {
        if ( ! parent::is_available( $package ) ) {
            return false;
        }

        $settings = NovaPostSettings::get_instance();
        return $settings->is_available();
    }

    /**
     * Calculate the shipping cost.
     *
     * @param array $package Package.
     * @return void
     */
    public function calculate_shipping( $package = array() ) {
        if ( $this->is_free_delivery( (float) $package['contents_cost'] ) ) {
            $rate = $this->create_rate( 0 );
            $this->add_rate( $rate );
            return;
        }

        if ( $this->is_flat_rate() ) {
            $rate = $this->create_rate(
                (float) $this->np_settings->{$this->get_settings_prefix() . 'flat_rate_amount'}
            );
            $this->add_rate( $rate );
            return;
        }

        $package['destination_type'] = $this->get_destination_type();
        $user_storage                = UserStorage::get_instance();

        $calculation_request = new CalculationRequest( $package );
        $calculation_params  = $calculation_request->build();

        $cached_rate = $this->rate_cache->get( $calculation_params );
        if ( false !== $cached_rate ) {
            $user_storage->set( $this->get_error_key(), null );
            $this->set_rate( $calculation_params, $cached_rate );
            return;
        }

        try {
            $total_cost = $this->calculate_cost( $calculation_params, $package );

            if ( $total_cost > 0 ) {
                $this->set_rate( $calculation_params, $this->create_rate( $total_cost ) );
                $user_storage->set( $this->get_error_key(), null );
            } else {
                $user_storage->set( $this->get_error_key(), true );
            }
        } catch ( \Throwable $e ) {
            Logger::write_log( \WC_Log_Levels::ERROR, 'Calculate Shipping Error: ' . $e->getMessage(), array( 'exception' => $e ) );
            $user_storage->set( $this->get_error_key(), true );

            if ( function_exists( 'wc_add_notice' ) && function_exists( 'wc_has_notice' ) ) {
                $notice_message = __( 'Exchange rate unavailable.', 'nova-post-for-woocommerce' );
                if ( ! wc_has_notice( $notice_message, 'error' ) ) {
                    wc_add_notice( $notice_message, 'error' );
                }
            }
        }
    }

    /**
     * Add rate and store it in cache.
     *
     * @param array $calculation_params Calculation params.
     * @param array $rate Rate data.
     * @return void
     */
    protected function set_rate( array $calculation_params, array $rate ): void {
        $this->add_rate( $rate );
        $this->rate_cache->set( $calculation_params, $rate );
    }

    /**
     * Build a WC shipping rate array.
     *
     * @param float $cost Cost amount.
     * @return array
     */
    protected function create_rate( float $cost ): array {
        return array(
            'id'    => $this->get_rate_id(),
            'label' => $this->title,
            'cost'  => $cost,
        );
    }

    /**
     * Perform API cost calculation with fallback.
     *
     * @param array $calculation_params Calculation parameters.
     * @param array $package WooCommerce package.
     * @return float
     */
    private function calculate_cost( array $calculation_params, array $package ): float {
        $result = $this->calculation_service->calculate( $calculation_params );

        if ( ! isset( $result['services'] )
            && 'address' === $this->get_destination_type()
            && ! empty( $package['destination']['postcode'] )
        ) {
            $result = $this->recalculate_to_division( $calculation_params );
        }

        $total_cost = $this->get_total_cost_from_result( $result, $package );

        $this->track_cost_calculated(
            array(
                'delivery_cost' => $total_cost,
                'currency_code' => get_woocommerce_currency(),
                'country_code'  => $package['destination']['country'] ?? '',
                'division_id'   => $result['recipient']['divisionId'] ?? ( $calculation_params['recipient']['divisionId'] ?? '' ),
                'division_type' => $result['recipient']['divisionType'] ?? ( $calculation_params['recipient']['divisionType'] ?? '' ),
                'city'          => $package['destination']['city'] ?? '',
                'postcode'      => $package['destination']['postcode'] ?? '',
                'address'       => $package['destination']['address'] ?? '',
            )
        );

        return $total_cost;
    }

    /**
     * Recalculate delivery cost to the closest division when direct address lookup fails.
     *
     * @param array $calculation_params to recalculate cost.
     * @return array
     */
    private function recalculate_to_division( array $calculation_params ): array {
        $division = DivisionsService::get_address_by_country_city_or_postcode(
            $calculation_params['recipient']['countryCode'],
            $calculation_params['recipient']['addressParts']['city'],
            $calculation_params['recipient']['addressParts']['postCode']
        );

        $calculation_params['recipient']['addressParts'] = array(
            'city'     => $division->city,
            'region'   => $division->region,
            'street'   => $division->street,
            'building' => $division->building,
            'postCode' => $division->post_code,
            'flat'     => '',
            'block'    => '',
            'note'     => '',
        );

        return $this->calculation_service->calculate( $calculation_params );
    }

    /**
     * Extract total cost from API result and convert currency if needed.
     *
     * @param array $result  API result data.
     * @param array $package WooCommerce package.
     * @return float
     * @throws \InvalidArgumentException When exchange rate is unavailable.
     * @throws \RuntimeException         When exchange rate is unavailable.
     */
    private function get_total_cost_from_result( array $result, array $package ): float {
        if ( empty( $result['services'] ) ) {
            Logger::write_log( \WC_Log_Levels::ERROR, 'Cost Calculation', $result );
            return 0;
        }

        $total_cost = 0;
        $currency   = '';

        foreach ( $result['services'] as $service ) {
            $total_cost += $service['cost'];
            $currency    = $service['currencyCode'];
        }

        $woocommerce_currency = get_woocommerce_currency();
        if ( $woocommerce_currency !== $currency ) {
            $sender_country  = $this->np_settings->sender_country;
            $cached_rate_obj = CachedExchangeRatesService::get_instance();

            if ( empty( $currency ) ) {
                $exchange_data = $cached_rate_obj->get_exchange_rates(
                    $total_cost,
                    $sender_country,
                    $woocommerce_currency
                );

                $currency = $exchange_data['originalCurrency'];
            }

            $total_cost = $cached_rate_obj->get_exchange_price(
                $total_cost,
                $sender_country,
                $currency,
                $woocommerce_currency
            );
        }

        return (float) $total_cost;
    }

    /**
     * Method checks if free shipping is enabled.
     *
     * @param float $package_cost Package cost.
     * @return bool
     */
    private function is_free_delivery( float $package_cost ): bool {
        $prefix               = $this->get_settings_prefix();
        $is_free_delivery     = (bool) $this->np_settings->{$prefix . 'free_delivery'};
        $free_delivery_amount = (float) $this->np_settings->{$prefix . 'free_delivery_amount'};

        return $is_free_delivery && $package_cost >= $free_delivery_amount;
    }

    /**
     * Method checks if flat rate is configured.
     */
    private function is_flat_rate(): bool {
        return (bool) $this->np_settings->{$this->get_settings_prefix() . 'flat_rate'};
    }

    /**
     * Method gets error key.
     */
    private function get_error_key(): string {
        return 'error_calculation_' . $this->get_destination_type();
    }
}
