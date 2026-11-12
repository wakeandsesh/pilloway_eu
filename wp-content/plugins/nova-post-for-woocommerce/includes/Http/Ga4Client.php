<?php
/**
 * Custom GA4 Http Client.
 *
 * Provides Http Client to send requests to Google Analytics 4 using Measurement Protocol.
 *
 * @package NovaPostForWoocommerce\Http
 */

namespace NovaPostForWoocommerce\Http;

use GuzzleHttp\Client;
use Throwable;
use GuzzleHttp\Exception\GuzzleException;
use NovaPostForWoocommerce\Logger\Logger;

/**
 * Measurement Protocol Client
 */
class Ga4Client {

    private const MEASUREMENT_ID = 'G-PMH31F9C1L';
    private const BASE_URL       = 'https://www.google-analytics.com/mp/collect';
    private const BASE_DEBUG_URL = 'https://www.google-analytics.com/debug/mp/collect';
    private const API_SECRET     = '-isUfMOdR9mzPiFUMtMolg';

    /**
     * Guzzle HTTP client.
     *
     * @var Client
     */
    private Client $http_client;

    /**
     * Logger instance.
     *
     * @var Logger
     */
    private Logger $logger;

    /**
     * Constructor.
     *
     * @param Client|null $client Guzzle HTTP client instance.
     */
    public function __construct( ?Client $client = null ) {
        $this->http_client = $client ?? new Client( array( 'timeout' => 3.0 ) );
        $this->logger      = new Logger();
    }

    /**
     * Methods sends GA4 Event.
     *
     * @param array $payload  The event payload.
     * @param bool  $is_debug Whether to send to debug endpoint.
     * @return array
     */
    public function send_request( array $payload, bool $is_debug = false ): array {
        try {
            $response = $this->http_client->post(
                $this->build_url( $is_debug ),
                array(
                    'json' => $payload,
                )
            );

            $result   = array();
            $contents = $response->getBody()->getContents();
            if ( $is_debug ) {
                $result = json_decode( $contents, true );
            }
            return $result;
        } catch ( GuzzleException $e ) {
            $this->logger->error( 'GA4 sendEvent error: ' . $e->getMessage() );
            return array();
        } catch ( Throwable $e ) {
            $this->logger->error( 'GA4 sendEvent unexpected error: ' . $e->getMessage() );
            return array();
        }
    }

    /**
     * Build URL for GA4 Measurement Protocol.
     *
     * @param bool $is_debug Whether to use the debug endpoint.
     * @return string
     */
    private function build_url( bool $is_debug = false ): string {
        return sprintf(
            '%s?measurement_id=%s&api_secret=%s',
            $is_debug ? self::BASE_DEBUG_URL : self::BASE_URL,
            self::MEASUREMENT_ID,
            self::API_SECRET
        );
    }
}
