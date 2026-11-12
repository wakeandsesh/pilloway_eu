<?php
/**
 * Shipment Sender Service for preparing sender data.
 *
 * @package    NovaPostForWoocommerce
 * @subpackage Services
 */

namespace NovaPostForWoocommerce\Services;

/**
 * Class ShipmentSenderService.
 */
class ShipmentSenderService {

    /**
     * Country code for Ukraine.
     */
    public const COUNTRY_UA = 'UA';

    /**
     * Prepare sender data.
     *
     * @param NovaPostSettings $settings Settings of Nova Post.
     * @return array
     */
    public static function prepare_sender_data( $settings ): array {
        $sender_data = array(
            'companyTin'  => $settings->company_tin ?? '',
            'companyName' => $settings->company_name,
            'phone'       => $settings->sender_phone,
            'email'       => $settings->sender_email,
            'name'        => $settings->sender_first_name . ' ' . $settings->sender_last_name,
            'countryCode' => $settings->sender_country,
            'ioss'        => $settings->ioss,
            'eoriCode'    => $settings->eori_code,
        );

        if ( 'address' === $settings->address_type ) {
            $sender_data['addressParts'] = array(
                'postCode' => $settings->sender_postal_code,
                'region'   => $settings->sender_country,
                'city'     => $settings->sender_city,
                'street'   => $settings->sender_street,
                'building' => $settings->sender_building,
                'block'    => '',
                'flat'     => '',
                'note'     => '',
            );

            if ( self::COUNTRY_UA === $settings->sender_country && ! isset( $sender_data['registrationAddressParts'] ) ) {
                $sender_data['registrationAddressParts'] = array(
                    'postCode' => $settings->sender_postal_code,
                    'region'   => $settings->sender_country,
                    'city'     => $settings->sender_city,
                    'street'   => $settings->sender_street,
                    'building' => $settings->sender_building,
                );
            }
        } else {
            $sender_data['divisionId'] = $settings->sender_division_id;
        }

        return $sender_data;
    }
}
