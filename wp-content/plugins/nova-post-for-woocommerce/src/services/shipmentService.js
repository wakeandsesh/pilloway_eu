/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Shipment Service
 *
 * Handles all shipment-related business logic for Nova Post WooCommerce plugin
 * Follows Single Responsibility Principle and uses composition over inheritance
 */

/**
 * Internal dependencies
 */
import apiService from './ApiService.js';

class ShipmentService {
    constructor(apiClient = apiService) {
        this.apiClient = apiClient;
    }
    /**
     * Get shipment preview data before creation
     *
     * @param {number} orderId - The WooCommerce order ID
     * @return {Promise<Object>} - API response with preview data
     * @throws {Error} Throws error if orderId is missing or API request fails
     */
    async getShipmentPreview(orderId) {
        this._validateOrderId(orderId);

        try {
            const path = `/orders/${orderId}/shipment-preview`;
            const response = await this.apiClient.get(path);
            return response;
        } catch (error) {
            throw new Error(`Failed to get shipment preview: ${error.message}`);
        }
    }

    /**
     * Create a shipment for the given order
     *
     * @param {number} orderId - The WooCommerce order ID
     * @param {Object} data    - Optional data for shipment creation (e.g., note)
     * @return {Promise<Object>} - API response with shipment details
     * @throws {Error} Throws error if orderId is missing or API request fails
     */
    async createShipment(orderId, data = {}) {
        this._validateOrderId(orderId);
        const path = `/orders/${orderId}/create-shipment`;
        const response = await this.apiClient.post(path, data, true);
        return response;
    }

    /**
     * Get shipment information for the given order
     *
     * @param {number} orderId - The WooCommerce order ID
     * @return {Promise<Object>} - API response with shipment details
     * @throws {Error} Throws error if orderId is missing or API request fails
     */
    async getShipment(orderId) {
        this._validateOrderId(orderId);

        try {
            const path = `/orders/${orderId}/shipment`;
            const response = await this.apiClient.get(path);
            return response;
        } catch (error) {
            throw new Error(
                sprintf(
                    /* translators: %s: error message */
                    __('Failed to get shipment information: %s', 'nova-post-for-woocommerce'),
                    error.message
                )
            );
        }
    }

    /**
     * Print label for the given order
     *
     * @param {number} orderId - The WooCommerce order ID
     * @return {Promise<string>} - Base64 encoded PDF data
     * @throws {Error} Throws error if orderId is missing or API request fails
     */
    async getLabelDocument(orderId) {
        this._validateOrderId(orderId);

        try {
            const response = await this._getDocument(orderId, 'marking');
            return response;
        } catch (error) {
            throw new Error(
                sprintf(
                    /* translators: %s: error message */
                    __('Failed to generate label: %s', 'nova-post-for-woocommerce'),
                    error.message
                )
            );
        }
    }

    /**
     * Print invoice for the given order
     *
     * @param {number} orderId - The WooCommerce order ID
     * @return {Promise<string>} - Base64 encoded PDF data
     * @throws {Error} Throws error if orderId is missing or API request fails
     */
    async getInvoiceDocument(orderId) {
        this._validateOrderId(orderId);

        try {
            const response = await this._getDocument(orderId, 'invoice');
            return response;
        } catch (error) {
            throw new Error(
                sprintf(
                    /* translators: %s: error message */
                    __('Failed to generate invoice: %s', 'nova-post-for-woocommerce'),
                    error.message
                )
            );
        }
    }

    /**
     * Print customs declaration for the given order
     *
     * @param {number} orderId - The WooCommerce order ID
     * @return {Promise<string>} - Base64 encoded PDF data
     * @throws {Error} Throws error if orderId is missing or API request fails
     */
    async getCustomsDocument(orderId) {
        this._validateOrderId(orderId);

        try {
            const response = await this._getDocument(orderId, 'international');
            return response;
        } catch (error) {
            throw new Error(
                sprintf(
                    /* translators: %s: error message */
                    __('Failed to generate customs declaration: %s', 'nova-post-for-woocommerce'),
                    error.message
                )
            );
        }
    }

    /**
     * Generic method to print shipment documents
     *
     * @private
     * @param {number} orderId - The WooCommerce order ID
     * @param {string} type    - The document type (marking, invoice, international)
     * @return {Promise<string>} - Base64 encoded PDF data
     * @throws {Error} Throws error if API request fails
     */
    async _getDocument(orderId, type) {
        try {
            const path = `/orders/${orderId}/print-shipment/${type}`;

            const response = await this.apiClient.get(path);

            if (!response || !response.data) {
                throw new Error(
                    __('Invalid PDF response from server', 'nova-post-for-woocommerce')
                );
            }

            return response.data;
        } catch (error) {
            throw new Error(
                error?.message ||
                    __(
                        'Unknown error occurred while printing document',
                        'nova-post-for-woocommerce'
                    )
            );
        }
    }

    /**
     * Delete a shipment for the given order
     *
     * @param {number} orderId - The WooCommerce order ID
     * @return {Promise<Object>} - API response
     * @throws {Error} Throws error if orderId is missing or API request fails
     */
    async deleteShipment(orderId) {
        this._validateOrderId(orderId);

        try {
            const path = `/orders/${orderId}/delete-shipment`;
            const response = await this.apiClient.delete(path);
            return response;
        } catch (error) {
            throw new Error(
                sprintf(
                    /* translators: %s: error message */
                    __('Failed to delete waybill: %s', 'nova-post-for-woocommerce'),
                    error.message
                )
            );
        }
    }

    /**
     * Get order division data
     *
     * @param {number} orderId - The WooCommerce order ID
     * @return {Promise<Object>} - API response with division data
     * @throws {Error} Throws error if orderId is missing or API request fails
     */
    async getOrderDivision(orderId) {
        this._validateOrderId(orderId);

        try {
            const path = `/orders/${orderId}/get-division`;
            const response = await this.apiClient.get(path);
            return response;
        } catch (error) {
            throw new Error(`Failed to get order division: ${error.message}`);
        }
    }

    async updateOrderDivision(orderId, payload) {
        this._validateOrderId(orderId);

        try {
            const path = `/orders/${orderId}/update-division`;
            const response = await this.apiClient.put(path, payload);
            return response;
        } catch (error) {
            throw new Error(`Failed to update order division: ${error.message}`);
        }
    }

    /**
     * Create shipments for multiple orders
     *
     * @param {Array<number>} orderIds - Array of WooCommerce order IDs
     * @return {Promise<Object>} - API response with results
     * @throws {Error} Throws error if orderIds is invalid or API request fails
     */
    async createBulkShipments(orderIds) {
        if (!Array.isArray(orderIds) || orderIds.length === 0) {
            throw new Error(__('Order IDs array is required', 'nova-post-for-woocommerce'));
        }

        try {
            // Add unique batch ID to force parallel processing (bypass browser/proxy serialization)
            const uniqueId = Date.now() + Math.random().toString(36).substring(7);
            const path = `/shipments/bulk-create?batch_id=${uniqueId}`;

            // Check for bulk_delay_ms in URL query parameters
            const urlParams = new URLSearchParams(window.location.search);
            const delayMs = urlParams.get('bulk_delay_ms');

            const payload = { orderIds };
            if (delayMs) {
                payload.delay_ms = parseInt(delayMs, 10);
            }

            const response = await this.apiClient.post(path, payload, true);
            return response;
        } catch (error) {
            throw new Error(
                sprintf(
                    /* translators: %s: error message */
                    __('Failed to create bulk shipments: %s', 'nova-post-for-woocommerce'),
                    error.message
                )
            );
        }
    }

    /**
     * Validate order ID parameter
     *
     * @private
     * @param {number} orderId - The order ID to validate
     * @throws {Error} Throws error if orderId is invalid
     */
    _validateOrderId(orderId) {
        if (!orderId) {
            throw new Error(__('Order ID is required', 'nova-post-for-woocommerce'));
        }

        if (typeof orderId !== 'number' && !Number.isInteger(Number(orderId))) {
            throw new Error(__('Order ID must be a valid number', 'nova-post-for-woocommerce'));
        }
    }
}

export default new ShipmentService();
