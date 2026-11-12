/**
 * PickupService
 *
 * Service for interacting with Nova Post pickup API endpoints.
 * Provides methods to fetch available pickup time intervals,
 * create new pickup intervals, and create pickup requests for orders.
 *
 * All methods return Promises and throw descriptive Errors on failure.
 */

/**
 * Internal dependencies
 */
import apiService from './ApiService.js';

class PickupService {
    constructor(apiClient = apiService) {
        this.apiClient = apiClient;
        this.API_BASE_PATH = '/np-shipping/v1/pickups';
    }

    /**
     * Fetch available pickup time intervals from the API for given order IDs.
     *
     * @param {Object} payload - Request payload, typically { orderIds: number[] }.
     * @return {Promise<Object>} API response object containing time intervals.
     * @throws {Error} If the API request fails.
     */
    async getTimeIntervals(payload) {
        const path = `${this.API_BASE_PATH}/time-intervals`;
        return await this.apiClient.post(path, payload).then((response) => response || {});
    }

    /**
     * Create a new pickup request for the provided orders.
     *
     * @param {Object} payload - Pickup payload, e.g. { orderIds: number[], windowStart: string, windowEnd: string }.
     * @return {Promise<Object>} API response object containing created pickup data.
     * @throws {Error} If the API request fails.
     */
    async createPickup(payload) {
        const path = `${this.API_BASE_PATH}`;
        return await this.apiClient.post(path, payload).then((response) => response || {});
    }

    /**
     * Add shipments (orders) to an existing pickup.
     *
     * @param {number|string} pickupId - ID of the pickup to add shipments to.
     * @param {Object}        payload  - Payload containing orderIds array, e.g. { orderIds: number[] }.
     * @return {Promise<Object>} API response object.
     * @throws {Error} If the API request fails.
     */
    async addShimpentToPickup(pickupId, payload) {
        const path = `${this.API_BASE_PATH}/${pickupId}/shipments`;
        return await this.apiClient.post(path, payload, true).then((response) => response || {});
    }

    /**
     * Update status of an existing pickup.
     *
     * @param {number|string} pickupId - ID of the pickup to update.
     * @param {Object}        payload  - Status payload, e.g. { status: 'Created', note?: string }.
     * @return {Promise<Object>} API response object.
     * @throws {Error} If the API request fails.
     */
    async updateStatus(pickupId, payload) {
        const path = `${this.API_BASE_PATH}/${pickupId}/status`;
        return await this.apiClient.post(path, payload).then((response) => response || {});
    }
}

export default new PickupService();
