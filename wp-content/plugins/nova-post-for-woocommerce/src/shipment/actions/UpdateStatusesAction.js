/**
 * Update Statuses Action
 *
 * @deprecated Webhook-based status updates replace manual polling.
 *   This action is no longer registered in ShipmentsManager. Keep the file for
 *   reference until the REST endpoint /orders/get-shipment-statuses is removed.
 */

/**
 * Internal dependencies
 */
import { BaseAction } from '../core';
import shipmentService from '../../services/shipmentService.js';

export class UpdateStatusesAction extends BaseAction {
    constructor(eventBus) {
        super(eventBus);
        this.actionName = 'update_statuses';
    }

    /**
     * Execute batch status update for multiple orders
     *
     * @param {Array<number|string>} orderIds - Array of order IDs to update
     * @return {Promise<void>}
     */
    async execute(orderIds = []) {
        if (!orderIds || !Array.isArray(orderIds) || orderIds.length === 0) {
            console.warn('[UpdateStatusesAction] No order IDs provided for status update');
            return;
        }

        try {
            this.emit('action:start', {
                action: this.actionName,
                orderIds,
            });

            // Emit loading start for each order
            orderIds.forEach((orderId) => {
                this.emit('status:loading-start', {
                    orderId: orderId.toString(),
                });
            });

            const response = await shipmentService.getShipmentsStatuses(orderIds);

            if (!response || !response.items) {
                console.warn('[UpdateStatusesAction] Invalid response from API', response);
                // Emit loading end for all orders even on error
                orderIds.forEach((orderId) => {
                    this.emit('status:loading-end', {
                        orderId: orderId.toString(),
                    });
                });
                return;
            }

            // Emit status-updated event for each order
            Object.entries(response.items).forEach(([orderId, statusData]) => {
                if (statusData && statusData.status) {
                    this.emit('shipment:status-updated', {
                        orderId: orderId.toString(),
                        status: statusData.status,
                        statusCode: statusData.status_code,
                    });
                }
                // Emit loading end
                this.emit('status:loading-end', {
                    orderId: orderId.toString(),
                });
            });
        } catch (error) {
            console.error('[UpdateStatusesAction] Failed to update statuses:', error);
            // Emit loading end for all orders on error
            orderIds.forEach((orderId) => {
                this.emit('status:loading-end', {
                    orderId: orderId.toString(),
                });
            });
        }
    }
}
