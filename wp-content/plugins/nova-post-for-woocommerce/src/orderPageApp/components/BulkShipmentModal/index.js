/**
 * External dependencies
 */
import { __, sprintf, _n } from '@wordpress/i18n';
import { Modal, Button, ProgressBar, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import shipmentService from '@/services/shipmentService';

/**
 * Internal dependencies
 */
import './style.scss';

/**
 * Bulk Shipment Creation Modal Component
 *
 * @param {Object}        props          - Component props
 * @param {Array<number>} props.orderIds - Array of order IDs to create shipments for
 * @param {Function}      props.onClose  - Callback when modal is closed
 */
export function BulkShipmentModal({ orderIds, onClose }) {
    const [isProcessing, setIsProcessing] = useState(false);
    const [isComplete, setIsComplete] = useState(false);
    const [results, setResults] = useState(null);
    const [processedCount, setProcessedCount] = useState(0);

    const handleCreate = async () => {
        setIsProcessing(true);
        setProcessedCount(0);

        const BATCH_SIZE = 5;
        const CONCURRENT_BATCHES = 1; // Process batches serially to respect API rate limit (60 req/min)
        const batches = [];

        for (let i = 0; i < orderIds.length; i += BATCH_SIZE) {
            batches.push(orderIds.slice(i, i + BATCH_SIZE));
        }

        const accumulatedResults = {
            summary: {
                total: orderIds.length,
                success: 0,
                failed: 0,
                skipped: 0,
            },
            results: {
                success: [],
                failed: [],
                skipped: [],
            },
        };

        const queue = [...batches];
        let completedBatches = 0;

        const worker = async () => {
            while (queue.length > 0) {
                const batch = queue.shift();

                try {
                    const idsToProcess = batch || orderIds;
                    const response = await shipmentService.createBulkShipments(idsToProcess);

                    if (response) {
                        if (response.results) {
                            accumulatedResults.results.success.push(
                                ...(response.results.success || [])
                            );
                            accumulatedResults.results.failed.push(
                                ...(response.results.failed || [])
                            );
                            accumulatedResults.results.skipped.push(
                                ...(response.results.skipped || [])
                            );
                        }

                        if (response.summary) {
                            accumulatedResults.summary.success += response.summary.success || 0;
                            accumulatedResults.summary.failed += response.summary.failed || 0;
                            accumulatedResults.summary.skipped += response.summary.skipped || 0;
                        }
                    }
                } catch (error) {
                    batch.forEach((orderId) => {
                        accumulatedResults.results.failed.push({
                            order_id: orderId,
                            error: error?.message || 'Batch processing failed',
                        });
                        accumulatedResults.summary.failed++;
                    });
                } finally {
                    completedBatches++;
                    setProcessedCount(Math.min(completedBatches * BATCH_SIZE, orderIds.length));
                }
            }
        };

        try {
            const workers = [];
            for (let i = 0; i < Math.min(CONCURRENT_BATCHES, batches.length); i++) {
                workers.push(worker());
            }

            await Promise.all(workers);

            setResults(accumulatedResults);
            setIsComplete(true);
        } catch (error) {
            console.error('Fatal error in bulk processing:', error);

            setResults({
                summary: {
                    total: orderIds.length,
                    success: accumulatedResults.summary.success,
                    failed: orderIds.length - accumulatedResults.summary.success,
                    skipped: 0,
                },
                results: accumulatedResults.results,
            });
            setIsComplete(true);
        } finally {
            setIsProcessing(false);
        }
    };

    const handleClose = () => {
        if (isProcessing) {
            return;
        }

        onClose();

        if (isComplete) {
            window.location.reload();
        }
    };

    return (
        <Modal
            onRequestClose={handleClose}
            className="npw-bulk-shipment-modal"
            shouldCloseOnClickOutside={!isProcessing}
        >
            <div className="npw-bulk-shipment-modal__content">
                <h3>
                    {sprintf(
                        /* translators: %d: number of orders */
                        _n(
                            'Create shipment for %d order',
                            'Create shipments for %d orders',
                            orderIds.length,
                            'nova-post-for-woocommerce'
                        ),
                        orderIds.length
                    )}
                </h3>

                {!isProcessing && !isComplete && (
                    <div className="npw-bulk-shipment-modal__confirmation">
                        <p>
                            {sprintf(
                                /* translators: %d: number of orders */
                                _n(
                                    'You are about to create a shipment for %d order.',
                                    'You are about to create shipments for %d orders.',
                                    orderIds.length,
                                    'nova-post-for-woocommerce'
                                ),
                                orderIds.length
                            )}
                        </p>
                        <p>
                            {__(
                                'This action cannot be undone. Do you want to continue?',
                                'nova-post-for-woocommerce'
                            )}
                        </p>
                    </div>
                )}

                {isProcessing && (
                    <div className="npw-bulk-shipment-modal__progress">
                        <p>
                            {sprintf(
                                /* translators: 1: processed count, 2: total count */
                                __('Processing orders: %1$d of %2$d', 'nova-post-for-woocommerce'),
                                Math.min(processedCount, orderIds.length),
                                orderIds.length
                            )}
                        </p>
                        <ProgressBar className="npw-bulk-shipment-modal__progress-bar" />
                    </div>
                )}

                {isComplete && results && (
                    <div className="npw-bulk-shipment-modal__results">
                        <div className="npw-bulk-shipment-modal__summary">
                            {results.summary.success > 0 && (
                                <Notice status="success" isDismissible={false}>
                                    {sprintf(
                                        /* translators: %d: number of successful shipments */
                                        _n(
                                            '%d shipment created successfully',
                                            '%d shipments created successfully',
                                            results.summary.success,
                                            'nova-post-for-woocommerce'
                                        ),
                                        results.summary.success
                                    )}
                                </Notice>
                            )}

                            {results.summary.failed > 0 && (
                                <Notice status="error" isDismissible={false}>
                                    {sprintf(
                                        /* translators: %d: number of failed shipments */
                                        _n(
                                            '%d shipment failed',
                                            '%d shipments failed',
                                            results.summary.failed,
                                            'nova-post-for-woocommerce'
                                        ),
                                        results.summary.failed
                                    )}
                                </Notice>
                            )}

                            {results.summary.skipped > 0 && (
                                <Notice status="warning" isDismissible={false}>
                                    {sprintf(
                                        /* translators: %d: number of skipped shipments */
                                        _n(
                                            '%d order skipped',
                                            '%d orders skipped',
                                            results.summary.skipped,
                                            'nova-post-for-woocommerce'
                                        ),
                                        results.summary.skipped
                                    )}
                                </Notice>
                            )}
                        </div>

                        {results.results.failed.length > 0 && (
                            <div className="npw-bulk-shipment-modal__errors">
                                <h4>{__('Failed Orders:', 'nova-post-for-woocommerce')}</h4>
                                <ul>
                                    {results.results.failed.map((item) => (
                                        <li key={item.order_id}>
                                            {sprintf(
                                                /* translators: 1: order ID, 2: error message */
                                                __(
                                                    'Order #%1$d: %2$s',
                                                    'nova-post-for-woocommerce'
                                                ),
                                                item.order_id,
                                                item.error
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        {results.results.skipped.length > 0 && (
                            <div className="npw-bulk-shipment-modal__skipped">
                                <h4>{__('Skipped Orders:', 'nova-post-for-woocommerce')}</h4>
                                <ul>
                                    {results.results.skipped.map((item) => (
                                        <li key={item.order_id}>
                                            {sprintf(
                                                /* translators: 1: order ID, 2: reason */
                                                __(
                                                    'Order #%1$d: %2$s',
                                                    'nova-post-for-woocommerce'
                                                ),
                                                item.order_id,
                                                item.reason
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </div>
                )}
            </div>

            <div className="npw-bulk-shipment-modal__footer">
                {!isComplete && (
                    <>
                        <Button variant="secondary" onClick={handleClose} disabled={isProcessing}>
                            {__('Cancel', 'nova-post-for-woocommerce')}
                        </Button>
                        <Button
                            variant="primary"
                            onClick={handleCreate}
                            isBusy={isProcessing}
                            disabled={isProcessing}
                        >
                            {__('Create Shipments', 'nova-post-for-woocommerce')}
                        </Button>
                    </>
                )}

                {isComplete && (
                    <Button variant="primary" onClick={handleClose}>
                        {__('Reload Page', 'nova-post-for-woocommerce')}
                    </Button>
                )}
            </div>
        </Modal>
    );
}
