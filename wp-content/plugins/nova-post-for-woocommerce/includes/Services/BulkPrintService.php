<?php
/**
 * Bulk Print Service
 *
 * Handles bulk printing of shipment documents with PDF merging.
 *
 * @package    NovaPostForWoocommerce
 * @subpackage Services
 */

namespace NovaPostForWoocommerce\Services;

use NovaPostForWoocommerce\Database\Repositories\OrderRepository;
use NovaPostForWoocommerce\Logger\Logger;
use NovaPostForWoocommerce\Exceptions\ShipmentException;

/**
 * Bulk Print Service
 *
 * Provides functionality for bulk document printing.
 */
class BulkPrintService {

    /**
     * Maximum number of orders per bulk print request.
     */
    const MAX_ORDERS = 100;

    /**
     * Maximum number of documents per Nova Post API request.
     */
    const API_BATCH_SIZE = 15;

    /**
     * Temporary PDF file lifetime in seconds (30 minutes).
     */
    const TEMP_PDF_LIFETIME = 1800;

    /**
     * PDF Merger instance.
     *
     * @var PdfMerger
     */
    private $pdf_merger;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->pdf_merger = new PdfMerger();
    }

    /**
     * Print documents for multiple orders and merge into single PDF.
     *
     * @param array  $order_ids Array of WooCommerce order IDs.
     * @param string $type      Document type (marking, invoice, international).
     * @return array {
     *     @type string $pdf_data Base64 encoded merged PDF.
     *     @type array  $stats    Statistics about the operation.
     * }
     * @throws ShipmentException If printing fails.
     */
    public function print_bulk_documents( array $order_ids, string $type ): array {
        // Validate input.
        $this->validate_inputs( $order_ids, $type );

        // Get tracking numbers from orders.
        $tracking_data = $this->get_tracking_numbers( $order_ids );

        if ( empty( $tracking_data['numbers'] ) ) {
            throw new ShipmentException(
                esc_html__( 'No shipments found for selected orders', 'nova-post-for-woocommerce' ),
                404
            );
        }

        // Fetch PDFs with pre-validation and batch processing.
        $fetch_result = $this->fetch_pdfs_validated( $tracking_data, $type );

        // Check if we have any successful PDFs.
        if ( empty( $fetch_result['pdf_binaries'] ) ) {
            throw new ShipmentException(
                esc_html__( 'Failed to generate any documents. All tracking numbers are invalid.', 'nova-post-for-woocommerce' ),
                500
            );
        }

        // Merge all successful PDFs.
        $merged_pdf = $this->pdf_merger->merge( $fetch_result['pdf_binaries'] );

        $pdf_filename = $this->save_temp_pdf( $merged_pdf );
        $pdf_url      = get_rest_url( null, 'np-shipping/v1/shipments/bulk-print/download/' . $pdf_filename );

        // Calculate successful count: found_shipments - failed - skipped.
        $successful_count = count( $tracking_data['numbers'] ) - count( $fetch_result['failed'] ) - count( $fetch_result['skipped_print'] );

        return array(
            'pdf_url' => $pdf_url,
            'numbers' => $tracking_data['numbers'] ?? array(),
            'summary' => array(
                'total_orders'         => count( $order_ids ),
                'found_shipments'      => count( $tracking_data['numbers'] ),
                'skipped'              => count( $tracking_data['skipped'] ),
                'failed'               => count( $fetch_result['failed'] ),
                'skipped_print'        => count( $fetch_result['skipped_print'] ),
                'successful'           => $successful_count,
                'skipped_orders'       => $tracking_data['skipped'],
                'failed_orders'        => $fetch_result['failed'],
                'skipped_print_orders' => array_values( array_unique( array_column( $fetch_result['skipped_print'], 'order_id' ) ) ),
            ),
        );
    }

    /**
     * Get or create the protected temporary directory for PDFs.
     *
     * @return string The directory path.
     * @throws ShipmentException If directory cannot be created.
     */
    private function get_temp_pdf_dir(): string {
        $upload_dir = wp_upload_dir();
        $base_dir   = $upload_dir['basedir'] . '/nova-post-woocommerce/bulk-print';

        if ( ! file_exists( $base_dir ) ) {
            if ( ! wp_mkdir_p( $base_dir ) ) {
                Logger::write_log( \WC_Log_Levels::ERROR, 'Failed to create temporary PDF directory.', array( 'dir' => $base_dir ) );

                // translators: %s: Directory path that failed to be created.
                throw new ShipmentException( esc_html( sprintf( __( 'Failed to create directory: %s', 'nova-post-for-woocommerce' ), $base_dir ) ), 500 );
            }
        }

        // Protect directory from direct public access.
        // This ensures PDFs (which contain sensitive customer data) can only be downloaded.
        // securely via our REST API, preventing unauthorized access or directory listing.
        $htaccess_file = $base_dir . '/.htaccess';
        if ( ! file_exists( $htaccess_file ) ) {
            $result = file_put_contents( $htaccess_file, 'Deny from all' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            if ( false === $result ) {
                Logger::write_log( \WC_Log_Levels::ERROR, 'Failed to create .htaccess file in temporary PDF directory.', array( 'file' => $htaccess_file ) );
            }
        }

        $index_file = $base_dir . '/index.php';
        if ( ! file_exists( $index_file ) ) {
            $result = file_put_contents( $index_file, '<?php' . PHP_EOL . '// Silence is golden.' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            if ( false === $result ) {
                Logger::write_log( \WC_Log_Levels::ERROR, 'Failed to create index.php file in temporary PDF directory.', array( 'file' => $index_file ) );
            }
        }

        return $base_dir;
    }

    /**
     * Clean up old temporary PDF files.
     *
     * @param string $base_dir The directory path.
     * @return void
     */
    private function cleanup_temp_pdfs( string $base_dir ): void {
        $files = glob( $base_dir . '/npw_print_*.pdf' );
        if ( is_array( $files ) ) {
            $now = time();
            foreach ( $files as $file ) {
                if ( is_file( $file ) && ( $now - filemtime( $file ) ) > self::TEMP_PDF_LIFETIME ) {
                    try {
                        // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
                        if ( ! @unlink( $file ) ) {
                            Logger::write_log( \WC_Log_Levels::ERROR, 'Failed to delete temporary PDF file.', array( 'file' => $file ) );
                        }
                    } catch ( \Exception $e ) {
                        Logger::write_log(
                            \WC_Log_Levels::ERROR,
                            'Exception while deleting temporary PDF file.',
                            array(
                                'file'  => $file,
                                'error' => $e->getMessage(),
                            )
                        );
                    }
                }
            }
        }
    }

    /**
     * Save merged PDF to a protected temporary directory and clean up old files.
     *
     * @param string $pdf_content The PDF binary content.
     * @return string The generated filename.
     * @throws ShipmentException If file cannot be saved.
     */
    private function save_temp_pdf( string $pdf_content ): string {
        $base_dir = $this->get_temp_pdf_dir();

        $this->cleanup_temp_pdfs( $base_dir );

        $filename  = 'npw_print_' . md5( uniqid( (string) wp_rand(), true ) ) . '.pdf';
        $file_path = $base_dir . '/' . $filename;

        $result = file_put_contents( $file_path, $pdf_content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

        if ( false === $result ) {
            Logger::write_log( \WC_Log_Levels::ERROR, 'Failed to save temporary PDF file.', array( 'file_path' => $file_path ) );
            throw new ShipmentException( esc_html__( 'Failed to save temporary PDF file.', 'nova-post-for-woocommerce' ), 500 );
        }

        return $filename;
    }

    /**
     * Get tracking numbers from orders.
     *
     * @param array $order_ids Array of order IDs.
     * @return array {
     *     @type array $numbers   Array of tracking numbers.
     *     @type array $skipped   Array of skipped order IDs.
     *     @type array $order_map Map of tracking_number => order_id.
     * }
     */
    public function get_tracking_numbers( array $order_ids ): array {
        $tracking_numbers = array();
        $skipped          = array();
        $order_map        = array();

        foreach ( $order_ids as $order_id ) {
            try {
                $order = OrderRepository::get_order_or_fail( $order_id );

                $tracking_number = $order->get_meta( '_nova_post_shipment_number' );

                if ( $tracking_number ) {
                    $tracking_numbers[]            = $tracking_number;
                    $order_map[ $tracking_number ] = $order_id;
                } else {
                    $skipped[] = $order_id;
                }
            } catch ( \Exception $e ) {
                $skipped[] = $order_id;
            }
        }

        return array(
            'numbers'   => $tracking_numbers,
            'skipped'   => $skipped,
            'order_map' => $order_map,
        );
    }

    /**
     * Fetch PDFs from Nova Post API with pre-validation and batch processing.
     *
     * @param array  $tracking_data Array with 'numbers' and 'order_map'.
     * @param string $type          Document type.
     * @return array {
     *     @type array $pdf_binaries Array of PDF binary data.
     *     @type array $failed       Array of failed tracking numbers with errors.
     * }
     * @throws \Exception When missing binary data.
     */
    private function fetch_pdfs_validated( array $tracking_data, string $type ): array {
        $pdf_binaries  = array();
        $failed        = array();
        $skipped_print = array();

        // 1. Pre-validate tracking numbers with pagination support.
        $found_numbers = array();
        try {
            // API has limit of 100, so we need to paginate if we have more tracking numbers.
            $all_numbers = $tracking_data['numbers'];
            $chunks      = array_chunk( $all_numbers, 100 );

            foreach ( $chunks as $chunk ) {
                $validation_response = ShipmentApiService::get_shipments(
                    array(
                        'numbers' => $chunk,
                        'limit'   => 100,
                    )
                );

                if ( is_array( $validation_response['items'] ) ) {
                    foreach ( $validation_response['items'] as $shipment ) {
                        $found_numbers[] = $shipment['number'];
                    }
                }
            }

            // Identify valid and missing numbers.
            $valid_numbers = array();
            foreach ( $tracking_data['numbers'] as $requested_number ) {
                if ( in_array( $requested_number, $found_numbers, true ) ) {
                    $valid_numbers[] = $requested_number;
                } else {
                    $order_id   = $tracking_data['order_map'][ $requested_number ] ?? null;
                    $error_data = array(
                        'tracking_number' => $requested_number,
                        'order_id'        => $order_id,
                        'error'           => __( 'Shipment not found in Nova Post', 'nova-post-for-woocommerce' ),
                    );
                    $failed[]   = $error_data;
                    Logger::write_log( \WC_Log_Levels::ERROR, 'Bulk print validation failed', $error_data );
                }
            }
        } catch ( \Exception $e ) {
            // If validation fails entirely, mark all as failed (or fallback to individual? No, fail safe).
            foreach ( $tracking_data['numbers'] as $number ) {
                $order_id = $tracking_data['order_map'][ $number ] ?? null;
                $failed[] = array(
                    'tracking_number' => $number,
                    'order_id'        => $order_id,
                    'error'           => __( 'Validation failed: ', 'nova-post-for-woocommerce' ) . $e->getMessage(),
                );
            }

            Logger::write_log(
                \WC_Log_Levels::ERROR,
                'Bulk print validation failed',
                array(
                    'error'   => $e->getMessage(),
                    'numbers' => $tracking_data['numbers'],
                )
            );

            return array(
                'pdf_binaries'  => $pdf_binaries,
                'failed'        => $failed,
                'skipped_print' => $skipped_print,
            );
        }

        // 2. Fetch PDFs for valid numbers in batches.
        if ( ! empty( $valid_numbers ) ) {
            $batches = array_chunk( $valid_numbers, self::API_BATCH_SIZE );

            foreach ( $batches as $batch_numbers ) {
                $params = array(
                    'type'    => $type,
                    'numbers' => $batch_numbers,
                );

                try {
                    $chunk_result = $this->process_batch_chunk( $batch_numbers, $params, $type, $tracking_data );

                    if ( ! empty( $chunk_result['pdf_binaries'] ) ) {
                        $pdf_binaries = array_merge( $pdf_binaries, $chunk_result['pdf_binaries'] );
                    }
                    if ( ! empty( $chunk_result['failed'] ) ) {
                        $skipped_print = array_merge( $skipped_print, $chunk_result['failed'] );
                    }
                } catch ( \Exception $e ) {
                    // If batch fails, mark all documents in this batch as failed.
                    $error_msg = $e->getMessage();
                    if ( $e instanceof ShipmentException ) {
                        $data = $e->get_data();
                        if ( ! empty( $data['errors'] ) ) {
                            $error_msg .= ': ' . $this->format_api_error( $data['errors'] );
                        }
                    }

                    foreach ( $batch_numbers as $number ) {
                        $order_id        = $tracking_data['order_map'][ $number ] ?? null;
                        $skipped_print[] = array(
                            'tracking_number' => $number,
                            'order_id'        => $order_id,
                            'error'           => $error_msg,
                        );
                    }

                    Logger::write_log(
                        \WC_Log_Levels::ERROR,
                        'Bulk print batch failed',
                        array(
                            'error'   => $error_msg,
                            'numbers' => $batch_numbers,
                        )
                    );
                }
            }
        }

        return array(
            'pdf_binaries'  => $pdf_binaries,
            'failed'        => $failed,
            'skipped_print' => $skipped_print,
        );
    }

    /**
     * Process a chunk of tracking numbers and handle PDF splitting validation.
     *
     * @param array  $batch_numbers  Tracking numbers in this batch.
     * @param array  $params         API parameters for printing.
     * @param string $type           Document type.
     * @param array  $tracking_data  Full tracking data for order mapping.
     * @return array {
     *     @type array $pdf_binaries
     *     @type array $failed
     * }
     * @throws \Exception If binary data is empty or API fails.
     */
    private function process_batch_chunk( array $batch_numbers, array $params, string $type, array $tracking_data ): array {
        $pdf_binaries = array();
        $failed       = array();

        $pdf_data = ShipmentApiService::get_shipment_print( $params );

        if ( empty( $pdf_data ) ) {
            throw new \Exception( 'Empty binary data received' );
        }

        $expected_count = count( $batch_numbers );

        if ( 1 === $expected_count ) {
            $pdf_binaries[] = $pdf_data;
        } else {
            $split_pages = $this->pdf_merger->split_to_pages( $pdf_data );
            $pages_count = count( $split_pages );

            if ( $pages_count !== $expected_count ) {
                Logger::write_log(
                    \WC_Log_Levels::WARNING,
                    'Chunk page count mismatch. Fallback to single-file processing.',
                    array(
                        'expected_pages'   => $expected_count,
                        'received_pages'   => $pages_count,
                        'tracking_numbers' => $batch_numbers,
                    )
                );

                return $this->process_chunk_individually( $batch_numbers, $type, $tracking_data );
            } else {
                $pdf_binaries[] = $pdf_data;
            }
        }

        return array(
            'pdf_binaries' => $pdf_binaries,
            'failed'       => $failed,
        );
    }

    /**
     * Format API error response into readable message.
     *
     * @param array|string $errors Error data from API.
     * @return string Formatted error message.
     */
    private function format_api_error( $errors ): string {
        if ( is_string( $errors ) ) {
            return $errors;
        }

        $error_msg = esc_html__( 'Unknown error', 'nova-post-for-woocommerce' );

        if ( is_array( $errors ) ) {
            return implode( ', ', $errors ?? array( $error_msg ) );
        }

        return $error_msg;
    }

    /**
     * Process a chunk of tracking numbers individually to isolate errors.
     *
     * @param array  $tracking_numbers Tracking numbers to process.
     * @param string $type             Document type.
     * @param array  $tracking_data    Full tracking data for order mapping.
     * @return array {
     *     @type array $pdf_binaries
     *     @type array $failed
     * }
     * @throws \Exception When missing binary data.
     */
    private function process_chunk_individually( array $tracking_numbers, string $type, array $tracking_data ): array {
        $pdf_binaries = array();
        $failed       = array();

        foreach ( $tracking_numbers as $tracking_number ) {
            $params = array(
                'type'    => $type,
                'numbers' => array( $tracking_number ),
            );

            try {
                $pdf_data = ShipmentApiService::get_shipment_print( $params );

                if ( ! empty( $pdf_data ) ) {
                    $pdf_binaries[] = $pdf_data;
                } else {
                    throw new \Exception( 'Empty binary data received' );
                }
            } catch ( \Exception $e ) {
                $error_msg = $e->getMessage();
                if ( $e instanceof ShipmentException ) {
                    $data = $e->get_data();
                    if ( ! empty( $data['errors'] ) ) {
                        $error_msg .= ': ' . $this->format_api_error( $data['errors'] );
                    }
                }

                $order_id = $tracking_data['order_map'][ $tracking_number ] ?? null;
                $failed[] = array(
                    'tracking_number' => $tracking_number,
                    'order_id'        => $order_id,
                    'error'           => $error_msg,
                );
            }
        }

        return array(
            'pdf_binaries' => $pdf_binaries,
            'failed'       => $failed,
        );
    }

    /**
     * Validate request input.
     *
     * @param array  $order_ids Order IDs.
     * @param string $type      Document type.
     * @throws ShipmentException If validation fails.
     */
    private function validate_inputs( array $order_ids, string $type ) {
        // Validate order IDs.
        if ( empty( $order_ids ) ) {
            throw new ShipmentException(
                esc_html__( 'Order IDs must be a non-empty array', 'nova-post-for-woocommerce' ),
                422
            );
        }

        if ( count( $order_ids ) > self::MAX_ORDERS ) {
            throw new ShipmentException(
                sprintf(
                    /* translators: %d: maximum number of orders */
                    esc_html__( 'Maximum %d orders allowed for bulk printing', 'nova-post-for-woocommerce' ),
                    self::MAX_ORDERS // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                ),
                422
            );
        }

        // Validate document type.
        $allowed_types = array( 'marking', 'invoice', 'international' );
        if ( ! in_array( $type, $allowed_types, true ) ) {
            throw new ShipmentException(
                sprintf(
                    /* translators: %s: allowed types */
                    esc_html__( 'Invalid document type. Allowed types: %s', 'nova-post-for-woocommerce' ),
                    esc_html( implode( ', ', $allowed_types ) )
                ),
                422
            );
        }
    }
}
