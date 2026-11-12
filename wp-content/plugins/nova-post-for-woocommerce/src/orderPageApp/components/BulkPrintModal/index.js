/**
 * External dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { Modal, Button, Spinner, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import bulkPrintService from '@/services/bulkPrintService';

/**
 * Internal dependencies
 */
import './style.scss';

/**
 * Document type labels
 */
const DOCUMENT_LABELS = {
    marking: __('Markings (100x100)', 'nova-post-for-woocommerce'),
    invoice: __('Commercial Invoice', 'nova-post-for-woocommerce'),
    international: __('Customs Declaration', 'nova-post-for-woocommerce'),
};

/**
 * Bulk Print Modal Component
 *
 * @param {Object}        props              - Component props
 * @param {Array<number>} props.orderIds     - Array of order IDs
 * @param {string}        props.documentType - Document type: 'marking', 'invoice', or 'international'
 * @param {Function}      props.onClose      - Callback when modal is closed
 */
export function BulkPrintModal({ orderIds, documentType, onClose }) {
    const [phase, setPhase] = useState('confirmation'); // confirmation, processing, complete
    const [pdfData, setPdfData] = useState(null);
    const [summary, setSummary] = useState(null);
    const [error, setError] = useState(null);
    const [nonce, setNonce] = useState(null);

    const documentLabel = DOCUMENT_LABELS[documentType] || documentType;

    const handleConfirm = async () => {
        setPhase('processing');
        setError(null);

        try {
            const response = await bulkPrintService.printBulkDocuments(orderIds, documentType);

            if (response.success && response.pdf_url) {
                setPdfData(response.pdf_url);
                setSummary(response.summary);
                if (response.nonce) {
                    setNonce(response.nonce);
                }
                console.log('[BulkPrintModal] Summary:', response.summary);
                setPhase('complete');

                // Auto-trigger print dialog
                setTimeout(() => {
                    const filename = bulkPrintService.getFilename(documentType);
                    bulkPrintService.printPDF(response.pdf_url, filename, response.nonce);
                }, 500);
            } else {
                throw new Error(__('Failed to generate documents', 'nova-post-for-woocommerce'));
            }
        } catch (err) {
            console.error('[BulkPrintModal] Error:', err);
            setError(
                err.message ||
                    __('An error occurred while generating documents', 'nova-post-for-woocommerce')
            );
            setPhase('confirmation');
        }
    };

    const handleDownload = () => {
        if (pdfData) {
            const filename = bulkPrintService.getFilename(documentType);
            bulkPrintService.downloadPDF(pdfData, filename, nonce);
        }
    };

    const renderConfirmation = () => (
        <>
            <p>
                {sprintf(
                    /* translators: 1: document type, 2: number of selected orders */
                    _n(
                        'You are about to print %1$s for %2$d selected order.',
                        'You are about to print %1$s for %2$d selected orders.',
                        orderIds.length,
                        'nova-post-for-woocommerce'
                    ),
                    documentLabel,
                    orderIds.length
                )}
            </p>
            <div className="bulk-print-modal__actions">
                <Button variant="secondary" onClick={onClose}>
                    {__('Cancel', 'nova-post-for-woocommerce')}
                </Button>
                <Button variant="primary" onClick={handleConfirm}>
                    {__('Confirm', 'nova-post-for-woocommerce')}
                </Button>
            </div>
        </>
    );

    const renderProcessing = () => (
        <div className="bulk-print-modal__processing">
            <Spinner />
            <p>{__('Processing…', 'nova-post-for-woocommerce')}</p>
            <p className="bulk-print-modal__wait-message">
                {__('Please wait, do not close this window…', 'nova-post-for-woocommerce')}
            </p>
        </div>
    );

    const renderComplete = () => (
        <>
            {summary && (
                <div className="bulk-print-modal__stats">
                    <Notice status="success" isDismissible={false}>
                        {sprintf(
                            /* translators: 1: number of successful, 2: total number */
                            __(
                                'Successfully generated %1$d of %2$d documents',
                                'nova-post-for-woocommerce'
                            ),
                            summary.successful,
                            summary.found_shipments
                        )}
                    </Notice>

                    {summary.skipped > 0 && (
                        <Notice status="warning" isDismissible={false}>
                            {sprintf(
                                /* translators: %d: number of skipped orders */
                                _n(
                                    'Skipped: %d order (no shipment found)',
                                    'Skipped: %d orders (no shipments found)',
                                    summary.skipped,
                                    'nova-post-for-woocommerce'
                                ),
                                summary.skipped
                            )}
                            {summary.skipped_orders && summary.skipped_orders.length > 0 && (
                                <div className="bulk-print-modal__order-list">
                                    {__('Order IDs:', 'nova-post-for-woocommerce')}{' '}
                                    {summary.skipped_orders.join(', ')}
                                </div>
                            )}
                        </Notice>
                    )}

                    {summary.skipped_print > 0 && (
                        <Notice status="warning" isDismissible={false}>
                            {sprintf(
                                /* translators: %d: number of skipped_print orders */
                                _n(
                                    'Skipped: %d order (document is only available for international shipments)',
                                    'Skipped: %d orders (document is only available for international shipments)',
                                    summary.skipped_print,
                                    'nova-post-for-woocommerce'
                                ),
                                summary.skipped_print
                            )}
                            {summary.skipped_print_orders &&
                                summary.skipped_print_orders.length > 0 && (
                                    <div className="bulk-print-modal__order-list">
                                        {__('Order IDs:', 'nova-post-for-woocommerce')}{' '}
                                        {summary.skipped_print_orders.join(', ')}
                                    </div>
                                )}
                        </Notice>
                    )}

                    {summary.failed > 0 && (
                        <Notice status="error" isDismissible={false}>
                            {sprintf(
                                /* translators: %d: number of failed orders */
                                _n(
                                    'Failed: %d order (invalid tracking number or API error)',
                                    'Failed: %d orders (invalid tracking numbers or API errors)',
                                    summary.failed,
                                    'nova-post-for-woocommerce'
                                ),
                                summary.failed
                            )}
                            {summary.failed_orders && summary.failed_orders.length > 0 && (
                                <div className="bulk-print-modal__failed-list">
                                    {summary.failed_orders.map((failed, index) => (
                                        <div key={index} className="bulk-print-modal__failed-item">
                                            <strong>
                                                {sprintf(
                                                    /* translators: %d: order ID */
                                                    __('Order #%d:', 'nova-post-for-woocommerce'),
                                                    failed.order_id
                                                )}
                                            </strong>{' '}
                                            {failed.error}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </Notice>
                    )}
                </div>
            )}

            <div className="bulk-print-modal__actions">
                <Button variant="secondary" onClick={onClose}>
                    {__('Close', 'nova-post-for-woocommerce')}
                </Button>
                <Button variant="primary" onClick={handleDownload}>
                    {__('Download PDF', 'nova-post-for-woocommerce')}
                </Button>
            </div>
        </>
    );

    const getTitle = () => {
        switch (phase) {
            case 'processing':
                return __('Processing…', 'nova-post-for-woocommerce');
            case 'complete':
                return __('✓ Documents Ready', 'nova-post-for-woocommerce');
            default:
                return sprintf(
                    /* translators: %s: document type */
                    __('Print %s', 'nova-post-for-woocommerce'),
                    documentLabel
                );
        }
    };

    return (
        <Modal
            onRequestClose={phase !== 'processing' ? onClose : undefined}
            className="bulk-print-modal"
            isDismissible={phase !== 'processing'}
        >
            <div className="bulk-print-modal__content">
                <h3>{getTitle()}</h3>

                {error && (
                    <Notice status="error" isDismissible={false}>
                        {error}
                    </Notice>
                )}

                {phase === 'confirmation' && renderConfirmation()}
                {phase === 'processing' && renderProcessing()}
                {phase === 'complete' && renderComplete()}
            </div>
        </Modal>
    );
}
