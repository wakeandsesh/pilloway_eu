<?php
/**
 * ShipmentSettingsResolverService class
 *
 * @package NovaPostForWoocommerce\Services
 */

namespace NovaPostForWoocommerce\Services;

use Exception;
use NovaPostForWoocommerce\Logger\Logger;
use NovaPostForWoocommerce\Services\Cache\CachedCountriesService;
use NovaPostForWoocommerce\Services\Cache\CachedCustomsFeesService;
use NovaPostForWoocommerce\Services\Cache\CachedExchangeRatesService;

/**
 * Validates and resolves customs_fee_payer and delivery_tariff based on Nova Post rules.
 */
class ShipmentSettingsResolverService {

    /**
     * Singleton instance.
     *
     * @var ShipmentSettingsResolverService|null
     */
    private static ?ShipmentSettingsResolverService $instance = null;

    /**
     * Cached customs fees service.
     *
     * @var CachedCustomsFeesService
     */
    private CachedCustomsFeesService $customs_fees_cache_service;

    /**
     * Cached countries service.
     *
     * @var CachedCountriesService
     */
    private CachedCountriesService $countries_cache_service;

    /**
     * Express delivery tariff.
     */
    public const TARIFF_EXPRESS = 'express';

    /**
     * Standard delivery tariff.
     */
    public const TARIFF_STANDARD = 'standard';

    /**
     * United States country code.
     */
    public const COUNTRY_US = 'US';

    /**
     * Ukraine country code.
     */
    public const COUNTRY_FEE = 'UA';

    /**
     * Sender payer type.
     */
    public const PAYER_SENDER = 'Sender';

    /**
     * Recipient payer type.
     */
    public const PAYER_RECIPIENT = 'Recipient';

    /**
     * Incoterm DDP.
     */
    public const INCOTERM_DDP = 'DDP';

    /**
     * Incoterm DAP.
     */
    public const INCOTERM_DAP = 'DAP';

    /**
     * Currency for customs fee calculation.
     */
    public const CUSTOM_FEE_CURRENCY = 'UAH';

    /**
     * Exchange rate conversion service.
     *
     * @var CachedExchangeRatesService
     */
    private CachedExchangeRatesService $exchange_rate_service;

    /**
     * Gets the singleton instance of the service.
     *
     * @return self
     */
    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->customs_fees_cache_service = CachedCustomsFeesService::get_instance();
        $this->countries_cache_service    = CachedCountriesService::get_instance();
        $this->exchange_rate_service      = CachedExchangeRatesService::get_instance();
    }

    /**
     * Prevent cloning.
     */
    private function __clone() {}

    /**
     * Prevent unserialization.
     */
    private function __wakeup() {}

    /**
     * Resolves valid settings for customs fee payer and delivery tariff.
     *
     * @param string $receiver_country  ISO 3166-1 alpha-2 receiver country code.
     * @param float  $declared_cost     Declared cost in UAH.
     * @param string $customs_fee_payer Current customs fee payer setting ('Sender' or 'Recipient').
     * @param string $delivery_tariff   Current delivery tariff setting ('standard' or 'express').
     * @param string $sender_country    ISO 3166-1 alpha-2 sender country code. Defaults to 'UA'.
     * @param string $store_currency    Store currency.
     *
     * @return array Validated ['customs_fee_payer' => string, 'delivery_tariff' => string, 'incoterm' => string].
     */
    public function resolve(
        string $receiver_country,
        float $declared_cost,
        string $customs_fee_payer,
        string $delivery_tariff,
        string $sender_country = self::COUNTRY_FEE,
        string $store_currency = self::CUSTOM_FEE_CURRENCY
    ): array {
        // Rules apply only for UA-origin shipments.
        if ( self::COUNTRY_FEE !== $sender_country ) {
            $resolved_incoterm = self::PAYER_SENDER === $customs_fee_payer ? self::INCOTERM_DDP : self::INCOTERM_DAP;

            return array(
                'customs_fee_payer' => $customs_fee_payer,
                'delivery_tariff'   => $delivery_tariff,
                'incoterm'          => $resolved_incoterm,
            );
        }

        $resolved_cf_payer        = $this->resolve_customs_fee_payer( $customs_fee_payer, $receiver_country, $declared_cost, $store_currency, $sender_country );
        $resolved_delivery_tariff = $this->resolve_delivery_tariff( $delivery_tariff, $receiver_country, $resolved_cf_payer );
        $resolved_incoterm        = self::PAYER_SENDER === $resolved_cf_payer ? self::INCOTERM_DDP : self::INCOTERM_DAP;

        return array(
            'customs_fee_payer' => $resolved_cf_payer,
            'delivery_tariff'   => $resolved_delivery_tariff,
            'incoterm'          => $resolved_incoterm,
        );
    }

    /**
     * Resolves the delivery tariff based on destination country.
     *
     * @param string $delivery_tariff   The current tariff.
     * @param string $receiver_country  The destination country.
     * @param string $customs_fee_payer The resolved customs fee payer.
     *
     * @return string Validated tariff.
     */
    private function resolve_delivery_tariff( string $delivery_tariff, string $receiver_country, string $customs_fee_payer = '' ): string {
        if ( self::COUNTRY_US === $receiver_country && self::PAYER_RECIPIENT === $customs_fee_payer ) {
            return self::TARIFF_EXPRESS;
        }

        if ( self::TARIFF_EXPRESS === $delivery_tariff && self::COUNTRY_US !== $receiver_country ) {
            return self::TARIFF_STANDARD;
        }

        return $delivery_tariff;
    }

    /**
     * Resolves the customs fee payer based on Nova Post dictionary constraints.
     *
     * @param string $customs_fee_payer Current payer.
     * @param string $receiver_country  Destination country.
     * @param float  $declared_cost     Declared cost.
     * @param string $store_currency    Store currency.
     * @param string $sender_country    Sender country code. Defaults to 'UA'.
     *
     * @return string Validated payer.
     */
    private function resolve_customs_fee_payer( string $customs_fee_payer, string $receiver_country, float $declared_cost, string $store_currency, string $sender_country = self::COUNTRY_FEE ): string {
        // We only enforce limits when the sender attempts to pay customs fees.
        if ( self::PAYER_SENDER !== $customs_fee_payer ) {
            return $customs_fee_payer;
        }

        return $this->validate_sender_as_customs_payer( $customs_fee_payer, $receiver_country, $declared_cost, $store_currency, $sender_country );
    }

    /**
     * Validates if the sender can be the customs fee payer according to Nova Post dictionary.
     *
     * @param string $customs_fee_payer The requested customs fee payer.
     * @param string $receiver_country  Destination country.
     * @param float  $declared_cost     Declared cost.
     * @param string $store_currency    Store currency.
     * @param string $sender_country    Sender country code. Defaults to 'UA'.
     *
     * @return string The validated customs fee payer.
     */
    private function validate_sender_as_customs_payer( string $customs_fee_payer, string $receiver_country, float $declared_cost, string $store_currency, string $sender_country = self::COUNTRY_FEE ): string {
        try {
            $receiver_currency = $this->countries_cache_service->get_country_currency( $receiver_country );

            if ( empty( $receiver_currency ) ) {
                return self::PAYER_RECIPIENT;
            }

            $converted_cost = null;
            try {
                $converted_cost = $this->exchange_rate_service->get_exchange_price(
                    $declared_cost,
                    $sender_country,
                    $store_currency,
                    $receiver_currency
                );
            } catch ( \Exception $e ) {
                Logger::write_log(
                    'warning',
                    'No exchange rate fallback available for country.',
                    array(
                        'sender_country'   => $sender_country,
                        'receiver_country' => $receiver_country,
                        'declared_cost'    => $declared_cost,
                        'store_currency'   => $store_currency,
                        'target_currency'  => $receiver_currency,
                        'error'            => $e->getMessage(),
                    )
                );
            }

            if ( null === $converted_cost ) {
                return self::PAYER_RECIPIENT;
            }

            $customs_fees = $this->customs_fees_cache_service->get_customs_fees( $receiver_country );

            // If dictionary is empty or unavailable, leave it as is.
            if ( empty( $customs_fees ) ) {
                return $customs_fee_payer;
            }

            $is_active         = ! empty( $customs_fees['customFeeActive'] );
            $min_declared_cost = $customs_fees['minDeclaredCost'] ?? 0;
            $max_declared_cost = $customs_fees['maxDeclaredCost'] ?? PHP_INT_MAX;

            $is_in_range = ( $converted_cost >= $min_declared_cost ) && ( $converted_cost <= $max_declared_cost );

            if ( $is_active && $is_in_range ) {
                return $customs_fee_payer;
            }

            return self::PAYER_RECIPIENT;
        } catch ( Exception $e ) {
            Logger::write_log(
                'error',
                'Exception while getting customs fees.',
                array(
                    'error' => $e->getMessage(),
                )
            );
        }

        return self::PAYER_RECIPIENT;
    }
}
