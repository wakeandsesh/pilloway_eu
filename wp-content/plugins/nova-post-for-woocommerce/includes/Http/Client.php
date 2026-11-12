<?php
/**
 * Custom Http Client.
 *
 * Provides additional headers to track requests coming from WooCommerce.
 *
 * @package NovaPostForWoocommerce\Http
 */

namespace NovaPostForWoocommerce\Http;

use NovaDigital\NovaPost\Http\NovaPostClient;
use NovaPostForWoocommerce\Models\Traits\MerchantIdentifier;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Extended Client class.
 */
class Client extends NovaPostClient {
    use MerchantIdentifier;

    private const VERSION_UNKNOWN = 'unknown';

    /**
     * Methods adds additional tracking headers.
     *
     * @param RequestInterface $request Request object.
     * @return ResponseInterface
     */
    public function sendRequest( RequestInterface $request ): ResponseInterface {
        $new_request = $request->withHeader( 'NovaPost-Ecomm-Platform', 'woocommerce' )
            ->withHeader( 'NovaPost-Ecomm-Platform-Settings', '{}' )
            ->withHeader( 'NovaPost-Ecomm-Platform-Version', $this->get_woo_commerce_version() )
            ->withHeader( 'NovaPost-Ecomm-Plugin-Version', NPFORWC_PLUGIN_VERSION )
            ->withHeader( 'NovaPost-Ecomm-Merchant-Id', $this->get_merchant_id() )
            ->withHeader( 'Client-Referer', $this->get_merchant_id() )
            ->withHeader( 'Accept-Language', substr( get_locale(), 0, 2 ) );

        return parent::sendRequest( $new_request );
    }

    /**
     * Retrieve current WooCommerce Version.
     *
     * @return string
     */
    private function get_woo_commerce_version(): string {
        return defined( 'WC_VERSION' ) ? WC_VERSION : self::VERSION_UNKNOWN;
    }
}
