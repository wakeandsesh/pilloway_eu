<?php
/**
 * Calculation service for Nova Post shipping costs.
 *
 * @package   NovaPostForWoocommerce
 */

namespace NovaPostForWoocommerce\Services;

use NovaDigital\NovaPost\NovaPostApi;
use NovaPostForWoocommerce\Factories\NovaPost;
use NovaPostForWoocommerce\Logger\Logger;
use Exception;

/**
 * Handles shipment cost calculations and request building.
 */
class CalculationService {
    /**
     * NovaPosApi client.
     *
     * @var NovaPostApi|null
     */
    private ?NovaPostApi $nova_post_client = null;

    /**
     * Construct.
     */
    public function __construct() {
        try {
            $this->nova_post_client = NovaPost::get_client();
        } catch ( Exception $e ) {
            Logger::write_log( \WC_Log_Levels::ERROR, 'NovaPost Client initialization failed', $e->getMessage() );
        }
    }

    /**
     * Calculate delivery cost.
     *
     * @param array $calculation_params to recalculate cost.
     */
    public function calculate( array $calculation_params ): ?array {
        try {
            $client = $this->nova_post_client ?? NovaPost::get_client();
            return $client->shipments()->calculate( $calculation_params );
        } catch ( Exception $e ) {
            Logger::write_log( \WC_Log_Levels::ERROR, 'Calculation failed', $e->getMessage() );
            return null;
        }
    }
}
