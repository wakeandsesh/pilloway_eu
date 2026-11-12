/**
 * Shipment deletion action handler.
 *
 * @class DeleteShipmentAction
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

export class DeleteShipmentAction extends BaseAction {
    constructor(eventBus) {
        super(eventBus);
        this.actionName = 'delete_shipment';
    }

    /**
     * Execute shipment deletion
     *
     * @param {HTMLElement} button - The delete shipment button
     * @return {Promise<void>}
     */
    async execute(button) {
        if (!this.validateElement(button)) {
            return;
        }

        const description =
            button.dataset.npwActionDescription ||
            __('Are you sure you want to delete this waybill?', 'nova-post-for-woocommerce');

        const confirmed = await noticeService.showConfirmationDialog(
            description,
            __('Delete waybill', 'nova-post-for-woocommerce')
        );
        if (!confirmed) {
            return;
        }

        const orderId = this.getOrderId(button);

        this.emit('action:start', {
            action: this.actionName,
            orderId,
        });

        this.setLoading(button, true);

        try {
            await shipmentService.deleteShipment(orderId);

            this.emit('shipment:deleted', {
                orderId,
            });

            noticeService.showSuccess(
                __('Shipment deleted successfully!', 'nova-post-for-woocommerce')
            );
            window.location.reload();
        } catch (error) {
            noticeService.showError(
                error.message ||
                    __('Failed to delete waybill. Please try again.', 'nova-post-for-woocommerce')
            );
        } finally {
            this.setLoading(button, false);
        }
    }
}
