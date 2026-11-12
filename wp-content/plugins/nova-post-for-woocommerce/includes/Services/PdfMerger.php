<?php
/**
 * PDF Merger Utility
 *
 * Merges multiple PDF documents into a single PDF file using FPDI.
 *
 * @package    NovaPostForWoocommerce
 * @subpackage Services
 */

namespace NovaPostForWoocommerce\Services;

use setasign\Fpdi\Fpdi;

/**
 * PDF Merger Service
 *
 * Provides functionality to merge multiple PDF documents.
 */
class PdfMerger {

    /**
     * Merge multiple PDF documents into one.
     *
     * @param array $pdf_binaries Array of PDF binary data strings.
     * @return string Merged PDF as binary string.
     * @throws \Exception If PDF merging fails.
     */
    public function merge( array $pdf_binaries ): string {
        if ( empty( $pdf_binaries ) ) {
            throw new \Exception( 'No PDF documents provided for merging' );
        }

        try {
            $pdf = new Fpdi();

            foreach ( $pdf_binaries as $index => $pdf_binary ) {
                if ( empty( $pdf_binary ) ) {
                    continue;
                }

                // Save binary to temporary file (FPDI requires file path).
                $temp_file = $this->create_temp_file( $pdf_binary, $index );

                try {
                    // Get page count.
                    $page_count = $pdf->setSourceFile( $temp_file );

                    // Import all pages from this PDF.
                    for ( $page_no = 1; $page_no <= $page_count; $page_no++ ) {
                        // Import page.
                        $template_id = $pdf->importPage( $page_no );

                        // Get page dimensions.
                        $size = $pdf->getTemplateSize( $template_id );

                        // Add page with same orientation and size.
                        $orientation = $size['width'] > $size['height'] ? 'L' : 'P';
                        $pdf->AddPage( $orientation, array( $size['width'], $size['height'] ) );

                        // Use the imported page.
                        $pdf->useTemplate( $template_id );
                    }
                } finally {
                    // Clean up temporary file.
                    $this->delete_temp_file( $temp_file );
                }
            }

            // Return merged PDF as string.
            return $pdf->Output( 'S' );

        } catch ( \Exception $e ) {
            throw new \Exception( 'Failed to merge PDF documents: ' . esc_html( $e->getMessage() ) );
        }
    }

    /**
     * Create temporary file for PDF binary data.
     *
     * @param string $pdf_binary PDF binary data.
     * @param int    $index      File index for unique naming.
     * @return string Path to temporary file.
     * @throws \Exception If file creation fails.
     */
    private function create_temp_file( string $pdf_binary, int $index ): string {
        $temp_dir  = get_temp_dir();
        $temp_file = $temp_dir . 'npw_pdf_' . time() . '_' . $index . '.pdf';

        $result = file_put_contents( $temp_file, $pdf_binary ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

        if ( false === $result ) {
            throw new \Exception( 'Failed to create temporary PDF file' );
        }

        return $temp_file;
    }

    /**
     * Delete temporary file.
     *
     * @param string $file_path Path to temporary file.
     * @return void
     */
    private function delete_temp_file( string $file_path ): void {
        if ( file_exists( $file_path ) ) {
            unlink( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
        }
    }

    /**
     * Split a PDF binary into individual pages.
     *
     * @param string $pdf_content PDF binary data.
     * @return array Array of single-page PDF binaries.
     * @throws \Exception If splitting fails.
     */
    public function split_to_pages( string $pdf_content ): array {
        if ( empty( $pdf_content ) ) {
            throw new \Exception( 'No PDF content provided for splitting' );
        }

        $temp_file = $this->create_temp_file( $pdf_content, wp_rand() );
        $pages     = array();

        try {
            $source_pdf = new Fpdi();
            $page_count = $source_pdf->setSourceFile( $temp_file );

            for ( $page_no = 1; $page_no <= $page_count; $page_no++ ) {
                $single_page_pdf = new Fpdi();
                $single_page_pdf->setSourceFile( $temp_file );
                $imported_template = $single_page_pdf->importPage( $page_no );
                $size              = $single_page_pdf->getTemplateSize( $imported_template );

                $orientation = $size['width'] > $size['height'] ? 'L' : 'P';
                $single_page_pdf->AddPage( $orientation, array( $size['width'], $size['height'] ) );
                $single_page_pdf->useTemplate( $imported_template );
                $pages[] = $single_page_pdf->Output( 'S' );
            }

            return $pages;
        } catch ( \Exception $e ) {
            throw new \Exception( 'Failed to split PDF: ' . esc_html( $e->getMessage() ) );
        } finally {
            $this->delete_temp_file( $temp_file );
        }
    }
}
