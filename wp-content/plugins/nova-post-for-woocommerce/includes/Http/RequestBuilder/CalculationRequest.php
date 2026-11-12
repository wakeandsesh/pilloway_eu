<?php
/**
 * Calculation Request builder class.
 *
 * Builds request for calculation.
 *
 * @package NovaPostForWoocommerce\Http
 */

namespace NovaPostForWoocommerce\Http\RequestBuilder;

use NovaPostForWoocommerce\Adapters\ProductAdapter;
use NovaPostForWoocommerce\Http\RequestBuilder\Calculation\Package;
use NovaPostForWoocommerce\Http\RequestBuilder\Calculation\Settings;
use NovaPostForWoocommerce\Models\Cache\DivisionCache;
use NovaPostForWoocommerce\Services\BoxPackerService;
use NovaPostForWoocommerce\Services\DivisionsService;
use NovaPostForWoocommerce\Services\ShipmentInvoiceService;
use NovaPostForWoocommerce\Services\ShipmentSettingsResolverService;
use NovaPostForWoocommerce\Models\NovaPostSettings;

/**
 * Calculation Request builder class.
 */
class CalculationRequest {
    private const PAYER_TYPE            = 'Sender';
    private const TYPE_ADDRESS          = 'address';
    private const TYPE_DIVISION         = 'division';
    private const COUNTRY_UA            = 'UA';
    private const DEFAULT_TARIFF        = 'standard';
    private const INVOICE_INCOTERM      = 'DAP';
    private const INVOICE_PAYER_CUSTOMS = 'Recipient';

    /**
     * Used for calculation request.
     *
     * @var Package
     */
    private Package $package;

    /**
     * Used for calculation request.
     *
     * @var Settings.
     */
    private Settings $calc_settings;

    /**
     * Cached Division search.
     *
     * @var DivisionCache
     */
    private DivisionCache $division_cache;

    /**
     * Box packer service class.
     *
     * @var BoxPackerService
     */
    private BoxPackerService $box_packer;

    /**
     * Construct.
     *
     * @param array $package params for calculation.
     */
    public function __construct( array $package ) {
        $this->package        = new Package( $package );
        $this->calc_settings  = new Settings();
        $this->division_cache = new DivisionCache();
        $this->box_packer     = new BoxPackerService();
    }

    /**
     * Method builds Shipping Calculation request.
     */
    public function build(): array {
        $payload = array(
            'payerType'           => self::PAYER_TYPE,
            'payerContractNumber' => $this->get_payer_contract_number(),
            'sender'              => $this->build_sender_data(),
            'recipient'           => $this->build_recipient_data(),
            'parcels'             => $this->build_parcels(),
        );

        $payload = $this->apply_cross_border_invoice_data( $payload );

        return $payload;
    }

    /**
     * Adds invoice and deliveryType to payload for international shipments from UA.
     *
     * @param array $payload The calculation payload.
     * @return array Modified payload.
     */
    private function apply_cross_border_invoice_data( array $payload ): array {
        $sender_country    = $this->calc_settings->get_sender_country();
        $recipient_country = $this->package->get_destination_country();

        if ( self::COUNTRY_UA === $sender_country && ShipmentInvoiceService::is_invoice_required( $sender_country, $recipient_country ) ) {
            $cost     = $this->package->get_contents_cost();
            $currency = get_woocommerce_currency();
            $settings = NovaPostSettings::get_instance();

            $resolver          = ShipmentSettingsResolverService::get_instance();
            $resolved_settings = $resolver->resolve(
                $recipient_country,
                $cost,
                $settings->customs_fee_payer ?? self::PAYER_TYPE,
                $settings->delivery_tariff ?? self::DEFAULT_TARIFF,
                $sender_country,
                $currency
            );

            $payload['deliveryTypes'] = array( $resolved_settings['delivery_tariff'] );
            $payload['invoice']       = array(
                'incoterm'         => self::INVOICE_INCOTERM,
                'currency'         => $currency,
                'payerFeesCustoms' => self::INVOICE_PAYER_CUSTOMS,
                'cost'             => $cost,
            );
        }

        return $payload;
    }

    /**
     * Determine payer contract number (domestic/international).
     */
    private function get_payer_contract_number(): ?string {
        $payer_contract_number = $this->calc_settings->get_sender_country() === $this->package->get_destination_country()
            ? $this->calc_settings->get_domestic_cn()
            : $this->calc_settings->get_international_cn();

        return $payer_contract_number;
    }

    /**
     * Method builds sender information for calculation request.
     *
     * @return array
     */
    private function build_sender_data(): array {
        $company_tin = $this->calc_settings->get_company_tin();
        $company_tin = trim( $company_tin ) ? $company_tin : '';

        $sender_data = array(
            'companyTin'  => $company_tin,
            'companyName' => '',
            'countryCode' => $this->calc_settings->get_sender_country(),
        );

        if ( self::TYPE_ADDRESS === $this->calc_settings->get_address_type() ) {
            $sender_data['addressParts'] = array(
                'city'         => $this->calc_settings->get_sender_city(),
                'region'       => $this->calc_settings->get_sender_country(),
                'addressLine1' => $this->calc_settings->get_sender_street() . ', ' . $this->calc_settings->get_sender_building(),
                'postCode'     => $this->calc_settings->get_sender_postal_code(),
                'flat'         => '',
                'block'        => '',
                'note'         => '',
            );
        } else {
            $sender_data['divisionId'] = $this->calc_settings->get_sender_division_id();
        }

        return $sender_data;
    }

    /**
     * Method to build recipient data.
     */
    private function build_recipient_data(): array {
        $recipient = array( 'countryCode' => $this->package->get_destination_country() );
        if ( self::TYPE_DIVISION === $this->package->get_destination_type() ) {
            $division_info             = $this->resolve_division();
            $recipient['divisionId']   = $division_info['id'];
            $recipient['divisionType'] = $division_info['divisionCategory'];
        } else {
            $recipient['addressParts'] = $this->format_address_parts(
                array(
                    'city'     => $this->package->get_destination_city(),
                    'street'   => $this->package->get_destination_address1(),
                    'building' => $this->package->get_destination_address2(),
                    'postCode' => $this->package->get_destination_postcode(),
                )
            );
        }

        return $recipient;
    }

    /**
     * Method resolves division data either from Cache or from API call.
     */
    private function resolve_division(): array {
        $division_search_params = array(
            $this->package->get_destination_country(),
            $this->package->get_destination_city(),
            $this->package->get_destination_postcode(),
        );

        $division_info = $this->division_cache->get( $division_search_params );
        if ( false === $division_info ) {
            $division_info = DivisionsService::get_full_division_by_country_city_or_postcode(
                $this->package->get_destination_country(),
                $this->package->get_destination_city(),
                $this->package->get_destination_postcode()
            );
            $this->division_cache->set(
                $division_search_params,
                array(
                    'id'               => $division_info['id'] ?? '',
                    'divisionCategory' => $division_info['divisionCategory'] ?? '',
                )
            );
        }

        return $division_info;
    }

    /**
     * Method format address parts.
     *
     * @param array $data Address data.
     */
    private function format_address_parts( array $data ): array {
        return array_merge(
            array(
                'city'     => '',
                'region'   => '',
                'street'   => '',
                'building' => '',
                'postCode' => '',
                'flat'     => '',
                'block'    => '',
                'note'     => '',
            ),
            $data
        );
    }

    /**
     * Method builds parcel data for calculation.
     */
    private function build_parcels(): array {
        $boxes = $this->box_packer->pack_from_woocommerce_items( $this->package->get_contents() );

        if ( empty( $boxes ) ) {
            return $this->build_parcels_old();
        }

        return BoxPackerService::map_boxes_to_api_parcels( $boxes, false );
    }

    /**
     * Old logic for forming parcels for each product separately
     * Used only if box packing is disabled
     */
    private function build_parcels_old(): array {

        $row     = 1;
        $parcels = array();

        foreach ( $this->package->get_contents() as $item ) {
            $product_adapter = new ProductAdapter( $item['data'] );

            for ( $i = 0; $i < $item['quantity']; $i++ ) {
                $parcels[] = $this->build_parcel_item(
                    $product_adapter,
                    $item['data']->get_price(),
                    $row++
                );
            }
        }

        return $parcels;
    }

    /**
     * Method to build parcel item data.
     *
     * @param ProductAdapter $product Product object.
     * @param float          $price Item Price.
     * @param int            $row Row number.
     * @return array
     */
    private function build_parcel_item( ProductAdapter $product, float $price, int $row ): array {
        return array(
            'cargoCategory' => 'parcel',
            'pricingType'   => 'parcel',
            'insuranceCost' => 0.0 === $price ? 1 : $price,
            'rowNumber'     => $row,
            'width'         => $product->get_width_in_mm(),
            'length'        => $product->get_length_in_mm(),
            'height'        => $product->get_height_in_mm(),
            'actualWeight'  => $product->get_weight_in_grams(),
        );
    }
}
