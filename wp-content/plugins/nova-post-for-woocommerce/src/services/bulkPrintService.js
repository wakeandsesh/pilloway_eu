/**
 * Internal dependencies
 */
import apiClient from './ApiService';

/**
 * Bulk Print Service
 * Handles bulk document printing operations
 */
class BulkPrintService {
    /**
     * Print documents for multiple orders
     *
     * @param {number[]} orderIds     - Array of order IDs
     * @param {string}   documentType - Document type: 'marking', 'invoice', or 'international'
     * @return {Promise<Object>} Response with success, data (base64) and summary
     */
    async printBulkDocuments(orderIds, documentType) {
        try {
            const response = await apiClient.post(
                '/shipments/bulk-print',
                {
                    orderIds,
                    type: documentType,
                },
                true
            );

            return response;
        } catch (error) {
            console.error('[BulkPrintService] Error printing documents:', error);
            throw error;
        }
    }

    /**
     * Convert base64 string to Blob
     *
     * @param {string} base64      - Base64 encoded data
     * @param {string} contentType - MIME type
     * @return {Blob} Decoded Blob object
     */
    base64ToBlob(base64, contentType = 'application/pdf') {
        const byteCharacters = atob(base64);
        const byteArrays = [];

        for (let offset = 0; offset < byteCharacters.length; offset += 512) {
            const slice = byteCharacters.slice(offset, offset + 512);
            const byteNumbers = new Array(slice.length);

            for (let i = 0; i < slice.length; i++) {
                byteNumbers[i] = slice.charCodeAt(i);
            }

            const byteArray = new Uint8Array(byteNumbers);
            byteArrays.push(byteArray);
        }

        return new Blob(byteArrays, { type: contentType });
    }

    /**
     * Trigger browser print dialog for PDF
     *
     * @param {string} pdfUrl   - URL of the PDF file
     * @param {string} filename - Filename to display in the print dialog
     * @param {string} nonce    - Optional nonce to append to URL
     */
    printPDF(pdfUrl, filename, nonce) {
        const url = new URL(pdfUrl, window.location.origin);
        if (filename) {
            url.searchParams.append('name', filename);
        }
        const finalNonce = nonce || this._getNonce();
        if (finalNonce) {
            url.searchParams.append('_wpnonce', finalNonce);
        }
        const iframeUrl = url.toString();

        const iframe = document.createElement('iframe');
        iframe.style.position = 'fixed';
        iframe.style.right = '0';
        iframe.style.bottom = '0';
        iframe.style.width = '0';
        iframe.style.height = '0';
        iframe.style.border = 'none';
        iframe.src = iframeUrl;
        document.body.appendChild(iframe);

        iframe.onload = () => {
            setTimeout(() => {
                try {
                    iframe.contentWindow.print();

                    // Listen for afterprint event to clean up
                    const cleanup = () => {
                        setTimeout(() => {
                            if (iframe.parentNode) {
                                document.body.removeChild(iframe);
                            }
                        }, 100);
                    };

                    // Try to listen for afterprint event
                    if (iframe.contentWindow.matchMedia) {
                        const mediaQueryList = iframe.contentWindow.matchMedia('print');
                        mediaQueryList.addListener((mql) => {
                            if (!mql.matches) {
                                cleanup();
                            }
                        });
                    }

                    // Fallback: clean up after 60 seconds if user doesn't print
                    setTimeout(cleanup, 60000);
                } catch (e) {
                    console.error('[BulkPrintService] Print error:', e);
                    // Clean up on error
                    if (iframe.parentNode) {
                        document.body.removeChild(iframe);
                    }
                }
            }, 500);
        };
    }

    /**
     * Download PDF file
     *
     * @param {string} pdfUrl   - URL of the PDF file
     * @param {string} filename - Filename for download
     * @param {string} nonce    - Optional nonce to append to URL
     */
    downloadPDF(pdfUrl, filename, nonce) {
        const url = new URL(pdfUrl, window.location.origin);
        url.searchParams.append('download', '1');
        if (filename) {
            url.searchParams.append('name', filename);
        }
        const finalNonce = nonce || this._getNonce();
        if (finalNonce) {
            url.searchParams.append('_wpnonce', finalNonce);
        }
        const downloadUrl = url.toString();

        const a = document.createElement('a');
        a.href = downloadUrl;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    /**
     * Get security nonce
     *
     * @return {string} Security nonce, or an empty string if none is available
     */
    _getNonce() {
        if (window.wpApiSettings && window.wpApiSettings.nonce) {
            return window.wpApiSettings.nonce;
        }
        if (window.npForWcSettings && window.npForWcSettings.nonce) {
            return window.npForWcSettings.nonce;
        }
        if (window.wcSettings && window.wcSettings.admin && window.wcSettings.admin.nonce) {
            return window.wcSettings.admin.nonce;
        }
        return '';
    }

    /**
     * Get filename for document type
     *
     * @param {string} documentType - Document type
     * @return {string} Generated filename
     */
    getFilename(documentType) {
        const now = new Date();
        const date = now.toISOString().split('T')[0];
        const time = now.toTimeString().split(' ')[0].substring(0, 5).replace(':', '-');

        const typeMap = {
            marking: 'markings',
            invoice: 'invoices',
            international: 'customs',
        };

        const typeName = typeMap[documentType] || documentType;
        return `nova-post-${typeName}-${date}-${time}.pdf`;
    }
}

export default new BulkPrintService();
