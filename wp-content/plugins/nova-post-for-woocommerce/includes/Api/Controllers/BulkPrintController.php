<?php
/**
 * Bulk Print Controller
 *
 * Handles REST API requests for bulk document printing.
 *
 * @package    NovaPostForWoocommerce
 * @subpackage Api\Controllers
 */

namespace NovaPostForWoocommerce\Api\Controllers;

use NovaPostForWoocommerce\Exceptions\ShipmentException;
use NovaPostForWoocommerce\Models\Traits\TrackEvent;
use NovaPostForWoocommerce\Services\BulkPrintService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use NovaPostForWoocommerce\Logger\Logger;

/**
 * Bulk Print Controller
 *
 * Handles bulk printing of shipment documents.
 */
class BulkPrintController {

    use TrackEvent;

    /**
     * Bulk print service instance.
     *
     * @var BulkPrintService
     */
    private $bulk_print_service;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->bulk_print_service = new BulkPrintService();
    }

    /**
     * Print documents for multiple orders.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error Response with merged PDF or error.
     */
    public function print_bulk_documents( WP_REST_Request $request ) {
        $order_ids = $request->get_param( 'orderIds' );
        $type      = $request->get_param( 'type' );

        $print_size_type = ( 'marking' === $type ) ? 'size_100_100' : 'size_A4';

        try {
            // Print documents.
            $result  = $this->bulk_print_service->print_bulk_documents( $order_ids, $type );
            $numbers = $result['numbers'] ?? array();

            $this->track_bulk_prints_created(
                array(
                    'numbers'       => $numbers,
                    'type'          => $type,
                    'printSizeType' => $print_size_type,
                )
            );

            return new WP_REST_Response(
                array(
                    'success' => true,
                    'pdf_url' => $result['pdf_url'],
                    'summary' => $result['summary'],
                    'nonce'   => wp_create_nonce( 'wp_rest' ),
                ),
                200
            );

        } catch ( ShipmentException $e ) {
            $this->track_bulk_prints_failed(
                array(
                    'error_message' => $e->getMessage(),
                    'numbers'       => array(),
                    'type'          => $type,
                    'printSizeType' => $print_size_type,
                )
            );

            return new WP_Error( 'shipment_exception', $e->getMessage(), $e->get_data() );
        } catch ( \Exception $e ) {
            Logger::write_log(
                \WC_Log_Levels::ERROR,
                'Bulk print unexpected error',
                array(
                    'error' => $e->getMessage(),
                )
            );

            return new WP_Error(
                'unexpected_error',
                sprintf(
                    /* translators: %s: error message */
                    __( 'An unexpected error occurred: %s', 'nova-post-for-woocommerce' ),
                    $e->getMessage()
                ),
                array( 'status' => 500 )
            );
        }
    }

    /**
     * Download merged PDF file.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return void|WP_Error
     */
    public function download_pdf( WP_REST_Request $request ) {
        $filename = $request->get_param( 'filename' );

        $validation = $this->validate_filename( $filename );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        $file_path = $this->get_file_path( $filename );
        if ( is_wp_error( $file_path ) ) {
            return $file_path;
        }

        $this->stream_file( $file_path, $request );
        exit;
    }

    /**
     * Validate the requested filename.
     *
     * @param mixed $filename The requested filename.
     * @return bool|WP_Error True if valid, WP_Error otherwise.
     */
    private function validate_filename( $filename ) {
        if ( empty( $filename ) || ! preg_match( '/^npw_print_[a-f0-9]{32}\.pdf$/', $filename ) ) {
            return new WP_Error( 'invalid_filename', __( 'Invalid filename.', 'nova-post-for-woocommerce' ), array( 'status' => 400 ) );
        }
        return true;
    }

    /**
     * Get the absolute path to the requested file.
     *
     * @param string $filename The requested filename.
     * @return string|WP_Error Path to file, or WP_Error if not found.
     */
    private function get_file_path( string $filename ) {
        $upload_dir = wp_upload_dir();
        $file_path  = $upload_dir['basedir'] . '/nova-post-woocommerce/bulk-print/' . $filename;

        if ( ! file_exists( $file_path ) ) {
            return new WP_Error( 'file_not_found', __( 'File not found or expired.', 'nova-post-for-woocommerce' ), array( 'status' => 404 ) );
        }
        return $file_path;
    }

    /**
     * Stream the file to the browser with appropriate headers.
     *
     * @param string          $file_path Absolute path to the file.
     * @param WP_REST_Request $request   The REST request object.
     * @return void
     */
    private function stream_file( string $file_path, WP_REST_Request $request ): void {
        $is_download       = $request->get_param( 'download' );
        $custom_name       = $request->get_param( 'name' );
        $disposition       = ! empty( $is_download ) ? 'attachment' : 'inline';
        $download_filename = ! empty( $custom_name ) ? sanitize_file_name( $custom_name ) : basename( $file_path );

        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: ' . $disposition . '; filename="' . esc_attr( $download_filename ) . '"' );
        header( 'Content-Length: ' . filesize( $file_path ) );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        // Clean output buffer.
        if ( ob_get_length() ) {
            ob_clean(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        }
        flush();

        readfile( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
    }
}
