<?php
/**
 * Admin Settings Controller
 *
 * Handles admin settings related API endpoints for the Nova Post WooCommerce plugin.
 *
 * @package NovaPostForWoocommerce\Api\Controllers
 */

namespace NovaPostForWoocommerce\Api\Controllers;

use NovaPostForWoocommerce\Helpers\CountriesHelper;
use NovaPostForWoocommerce\Models\NovaPostSettings;
use NovaPostForWoocommerce\Models\Traits\TrackEvent;
use NovaPostForWoocommerce\Normalization\SettingsPageNormalizer;
use NovaPostForWoocommerce\Services\OpensslService as Openssl;
use NovaPostForWoocommerce\Services\SettingsPageService;
use NovaPostForWoocommerce\Services\WebhookService;
use NovaPostForWoocommerce\Validation\SettingsPageApiKeysValidator;
use NovaPostForWoocommerce\Validation\SettingsPageValidator;
use WC_Countries;
use WP_REST_Response;

/**
 * Class AdminSettingsController
 *
 * Handles admin settings operations including retrieving and updating plugin settings
 * and managing country-specific configurations.
 */
class AdminSettingsController {

    use TrackEvent;

    /**
     * Retrieves all plugin settings.
     *
     * @return WP_REST_Response Response containing all plugin settings.
     */
    public function get_settings() {
        $settings = NovaPostSettings::all();

        $settings['api_key'] = Openssl::decrypt( $settings['api_key'] ?? '' );

        $settings['stage_api_key'] = Openssl::decrypt( $settings['stage_api_key'] ?? '' );

        return new WP_REST_Response( $settings );
    }

    /**
     * Updates plugin settings.
     *
     * @param \WP_REST_Request $request The request containing settings to update.
     * @return WP_REST_Response Response containing updated settings or validation errors.
     */
    public function update_settings( $request ) {
        $settings = $request->get_params();

        $settings  = SettingsPageNormalizer::normalize( $settings );
        $settings  = SettingsPageNormalizer::normalize_custom_boxes( $settings );
        $validator = SettingsPageValidator::validate( $settings );

        if ( $validator->fails() ) {
            $this->track_settings_save_failed( $settings, $validator->errors()->firstMessage() );

            return new WP_REST_Response(
                array(
                    'errors'  => $validator->errors()->allMessages(),
                    'message' => $validator->errors()->firstMessage(),
                ),
                400
            );
        }

        $response_payload = array_filter(
            $settings,
            static fn( $key ) => '_' !== ( $key[0] ?? '' ),
            ARRAY_FILTER_USE_KEY
        );

        if ( isset( $settings['api_key'] ) ) {
            $settings['api_key'] = Openssl::encrypt( $settings['api_key'] );
        }
        if ( isset( $settings['stage_api_key'] ) ) {
            $settings['stage_api_key'] = Openssl::encrypt( $settings['stage_api_key'] );
        }

        update_option( 'npforwc_shipping_api_key', $settings['api_key'] );
        update_option( 'npforwc_shipping_stage_api_key', $settings['stage_api_key'] );
        update_option( 'npforwc_shipping_debug', $settings['debug'] );

        $all_settings     = NovaPostSettings::all();
        $updated_settings = array_merge( $all_settings, $settings );

        NovaPostSettings::update( $updated_settings );

        WebhookService::subscribe();

        $this->track_settings_saved( $settings );

        return new WP_REST_Response( $response_payload );
    }

    /**
     * Updates plugin api keys settings.
     *
     * @param \WP_REST_Request $request The request containing settings to update.
     * @return WP_REST_Response Response containing updated settings or validation errors.
     */
    public function update_settings_api_keys( $request ) {
        $settings = $request->get_params();

        $validator = SettingsPageApiKeysValidator::validate( $settings );

        if ( $validator->fails() ) {
            return new WP_REST_Response(
                array(
                    'errors'  => $validator->errors()->allMessages(),
                    'message' => $validator->errors()->firstMessage(),
                ),
                400
            );
        }

        $response_payload = array(
            'api_key'       => $settings['api_key'] ?? '',
            'stage_api_key' => $settings['stage_api_key'] ?? '',
            'debug'         => $settings['debug'] ?? false,
        );

        if ( isset( $settings['api_key'] ) ) {
            $settings['api_key'] = Openssl::encrypt( $settings['api_key'] );
        }
        if ( isset( $settings['stage_api_key'] ) ) {
            $settings['stage_api_key'] = Openssl::encrypt( $settings['stage_api_key'] );
        }

        update_option( 'npforwc_shipping_api_key', $settings['api_key'] );
        update_option( 'npforwc_shipping_stage_api_key', $settings['stage_api_key'] );
        update_option( 'npforwc_shipping_debug', $settings['debug'] );

        $all_settings     = NovaPostSettings::all();
        $updated_settings = array_merge( $all_settings, $settings );
        NovaPostSettings::update( $updated_settings );

        WebhookService::subscribe();

        return new WP_REST_Response( $response_payload );
    }

    /**
     * Retrieves a list of countries where Nova Post operates.
     *
     * @return WP_REST_Response Response containing the list of countries.
     */
    public function get_countries() {
        $countries_obj = new WC_Countries();

        $countries = CountriesHelper::get_countries_of_presence();

        $countries_labels = $countries_obj->get_countries();

        $filtered_countries_label = array_filter(
            $countries_labels,
            function ( $country, $country_code ) use ( $countries ) {
                return in_array( $country_code, $countries, true );
            },
            ARRAY_FILTER_USE_BOTH
        );

        // Sort countries by their display names.
        uasort(
            $filtered_countries_label,
            function ( $a, $b ) {
                return strcmp( $a, $b );
            }
        );

        $countries_result = array_map(
            function ( $country, $country_code ) {
                return array(
                    $country_code => $country,
                );
            },
            $filtered_countries_label,
            array_keys( $filtered_countries_label )
        );

        return new WP_REST_Response(
            array(
                'countries' => $countries_result,
            )
        );
    }

    /**
     * Retrieves the state for the settings page.
     *
     * @return WP_REST_Response Response containing the settings page state.
     */
    public function get_settings_page_state() { // phpcs:ignore
        return new WP_REST_Response(
            array(
                'show_phone_required_warning'       => ! SettingsPageService::is_checkout_phone_required(),
                'show_unsupported_currency_warning' => ! SettingsPageService::is_supported_currency( get_woocommerce_currency() ),
                'show_no_shipping_zone_warning'     => ! SettingsPageService::check_nova_post_methods_in_zones(),
            )
        );
    }
}
