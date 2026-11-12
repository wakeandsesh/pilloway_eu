<?php
/**
 * Calculation service for Nova Post shipping costs.
 *
 * @package   NovaPostForWoocommerce
 */

namespace NovaPostForWoocommerce\Services\Analytics;

use NovaPostForWoocommerce\Http\Ga4Client;
use NovaPostForWoocommerce\Models\Ga4Event;
use NovaPostForWoocommerce\Models\NovaPostSettings;
use NovaPostForWoocommerce\Models\Traits\MerchantIdentifier;

/**
 * Google Analytics 4 Service.
 *
 * Handles sending events to Google Analytics 4.
 *
 * @package   NovaPostForWoocommerce
 */
class Ga4Service {

    use MerchantIdentifier;

    /**
     * GA4 HTTP client.
     *
     * @var Ga4Client
     */
    private Ga4Client $client;

    /**
     * GA4 Event object.
     *
     * @var Ga4Event
     */
    private Ga4Event $ga4_event;

    /**
     * List of allowed languages and their strict locale requirements.
     *
     * @var array
     */
    private array $allowed_languages = array(
        'en' => '*',
        'cs' => '*',
        'de' => 'de_DE',
    );

    /**
     * Default language code to fall back to.
     *
     * @var string
     */
    private string $default_lang = 'en';

    /**
     * Constructor.
     *
     * Initializes the GA4 client and event objects.
     */
    public function __construct() {
        $this->client    = new Ga4Client();
        $this->ga4_event = new Ga4Event();
    }

    /**
     * Sends a GA4 event.
     *
     * @param string $event_name The name of the event to send.
     * @param array  $params     Optional. Additional parameters for the event.
     * @param bool   $is_debug   Optional. Whether to send the event in debug mode.
     * @param bool   $is_frontend_event   Optional. Whether to send the event is from frontend.
     * @return array The API response from the GA4 Measurement Protocol.
     */
    public function send_event( string $event_name, array $params = array(), bool $is_debug = false, bool $is_frontend_event = false ): array {
        $payload = array(
            'client_id' => $this->ga4_event->get_client_id(),
            'user_id'   => $this->ga4_event->get_user_id(),
            'events'    => array(
                array(
                    'name'   => $event_name,
                    'params' => $this->build_common_params( $params, $is_frontend_event ),
                ),
            ),
        );

        return $this->client->send_request( $payload, $is_debug );
    }

    /**
     * Building common params that will be passed for every GA event.
     *
     * @param array $params            Specific event params.
     * @param bool  $is_frontend_event Whether the event is triggered from the frontend.
     */
    private function build_common_params( array $params, $is_frontend_event ): array {

        $settings = NovaPostSettings::get_instance();

        $common_params = array(
            'session_id'    => sanitize_text_field( $this->ga4_event->get_session_id() ),
            'store_domain'  => sanitize_text_field( $this->get_merchant_id() ),
            'platform_type' => 'woocommerce',
            'app_version'   => sanitize_text_field( NPFORWC_PLUGIN_VERSION ),
            'language'      => sanitize_text_field( strtok( get_locale() ?? 'en', '_' ) ),
            'user_type'     => is_user_logged_in() ? 'logged_in' : 'guest',
            'timestamp'     => time(),
        );

        $country = $is_frontend_event ? ( $params['country'] ?? '' ) : ( ! empty( $params['country'] ) ? $params['country'] : $settings->sender_country );

        if ( ! empty( $country ) ) {
            $common_params['country'] = sanitize_text_field( $country );
        }

        unset( $params['country'] ); // For no sending invalid value to ga.

        return array_merge( $common_params, $params );
    }

    /**
     * Get and validate language based on user locale or site settings.
     *
     * @return string Validated short language code.
     */
    public function get_validated_language(): string {
        $full_locale = get_user_locale();
        $short_code  = strtok( $full_locale, '_' );

        if ( isset( $this->allowed_languages[ $short_code ] ) ) {
            $requirement = $this->allowed_languages[ $short_code ];

            if ( '*' === $requirement || $full_locale === $requirement ) {
                return $short_code;
            }
        }

        return $this->default_lang;
    }
}
