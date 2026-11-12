/**
 * Waybill creation action handler.
 *
 * @class CreateWaybillAction
 */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { BaseAction } from '../core';
import shipmentService from '../../services/shipmentService.js';
import noticeService from '../../services/noticeService.js';
import { ShipmentPreviewModal } from '../components/ShipmentPreviewModal';
import { renderModal } from '../../utils';

export class CreateWaybillAction extends BaseAction {
    constructor(eventBus) {
        super(eventBus);
        this.actionName = 'create_waybill';
    }

    /**
     * Execute waybill creation
     *
     * @param {HTMLElement} button - The create waybill button
     * @return {Promise<void>}
     */
    async execute(button) {
        if (!this.validateElement(button)) {
            return;
        }

        const orderId = this.getOrderId(button);

        this.emit('action:start', {
            action: this.actionName,
            orderId,
        });

        this.setLoading(button, true);

        try {
            const previewData = await shipmentService.getShipmentPreview(orderId);

            const modalResult = await this.showPreviewModal(previewData, orderId);

            if (!modalResult.confirmed) {
                this.setLoading(button, false);
                return;
            }

            noticeService.showSuccess(
                __('Waybill created successfully!', 'nova-post-for-woocommerce')
            );
            window.location.reload();
        } catch (error) {
            const message =
                error?.message ||
                __('Failed to create waybill. Please try again.', 'nova-post-for-woocommerce');
            noticeService.showError(message);
        } finally {
            this.setLoading(button, false);
        }
    }

    /**
     * Show preview modal and handle creation
     *
     * @param {Object} previewData - Preview data from API
     * @param {number} orderId     - Order ID
     * @return {Promise<Object>} - Modal result
     */
    async showPreviewModal(previewData, orderId) {
        return renderModal(ShipmentPreviewModal, {
            previewData,
            onAction: async (data) => {
                const response = await shipmentService.createShipment(orderId, { note: data.note });

                if (!response.track_number) {
                    throw new Error('No tracking number received from API');
                }

                this.emit('waybill:created', {
                    orderId,
                    trackNumber: response.track_number,
                    trackingUrl: response.tracking_url || '',
                    shipmentData: response,
                    status: response.last_status,
                    statusCode: response.last_status_code,
                });
            },
        });
    }
}
