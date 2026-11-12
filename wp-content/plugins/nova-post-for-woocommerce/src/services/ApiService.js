/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

/**
 * API Service
 *
 * Handles HTTP requests and response processing for Nova Post WooCommerce plugin
 * Follows Single Responsibility Principle - responsible only for API communication
 */
class ApiService {
    /**
     * @param {string} basePath - Base REST API namespace.
     */
    constructor(basePath = '/np-shipping/v1') {
        this.basePath = basePath.replace(/\/$/, '');
    }

    /**
     * Execute a GET request returning JSON.
     *
     * @param {string}  path            - API endpoint path.
     * @param {Object}  options         - Additional fetch options (params, headers, ...).
     * @param {boolean} fullErrorObject - Whether to reject with the full error object instead of a message.
     * @return {Promise<Object>} JSON-decoded response.
     */
    async get(path, options = {}, fullErrorObject = false) {
        try {
            const { params = {}, ...fetchOptions } = options;
            const requestPath = this._getFullPath(path);
            const fullPath = addQueryArgs(requestPath, params);

            return await apiFetch({
                path: fullPath,
                method: 'GET',
                ...fetchOptions,
            });
        } catch (error) {
            throw this._handleApiError(error, 'GET request failed', fullErrorObject);
        }
    }

    /**
     * Execute a GET request expecting a binary Blob (e.g., PDF).
     *
     * @param {string}  path            - API endpoint path.
     * @param {Object}  options         - Additional fetch options (params, headers...).
     * @param {boolean} fullErrorObject - Whether to reject with the full error object instead of a message.
     * @return {Promise<Blob>} Response body as Blob.
     */
    async getBlob(path, options = {}, fullErrorObject = false) {
        try {
            const { params = {}, ...fetchOptions } = options;
            const requestPath = this._getFullPath(path);
            const fullPath = addQueryArgs(requestPath, params);

            const response = await apiFetch({
                path: fullPath,
                method: 'GET',
                parse: false, // prevent apiFetch from JSON parsing
                ...fetchOptions,
            });

            if (response instanceof Response) {
                const blob = await response.blob();

                return blob;
            }

            throw new Error('Unexpected blob response format');
        } catch (error) {
            throw this._handleApiError(error, 'GET blob request failed', fullErrorObject);
        }
    }

    /**
     * Execute a POST request
     *
     * @param {string}  path            - API endpoint path
     * @param {Object}  data            - Request body data
     * @param {boolean} fullErrorObject - Whether to reject with the full error object instead of a message.
     * @return {Promise<Object>} - API response
     */
    async post(path, data = {}, fullErrorObject = false) {
        try {
            const fullPath = this._getFullPath(path);

            const response = await apiFetch({
                path: fullPath,
                method: 'POST',
                data,
            });

            return response;
        } catch (error) {
            throw this._handleApiError(error, 'POST request failed', fullErrorObject);
        }
    }

    /**
     * Execute a DELETE request
     *
     * @param {string}  path            - API endpoint path
     * @param {Object}  data            - Request body data
     * @param {boolean} fullErrorObject - Whether to reject with the full error object instead of a message.
     * @return {Promise<Object>} - API response
     */
    async delete(path, data = {}, fullErrorObject = false) {
        try {
            const fullPath = this._getFullPath(path);

            const response = await apiFetch({
                path: fullPath,
                method: 'DELETE',
                data,
            });

            return response;
        } catch (error) {
            throw this._handleApiError(error, 'DELETE request failed', fullErrorObject);
        }
    }

    /**
     * Execute a PUT request
     *
     * @param {string}  path            - API endpoint path
     * @param {Object}  data            - Request body data
     * @param {boolean} fullErrorObject - Whether to reject with the full error object instead of a message.
     * @return {Promise<Object>} - API response
     */
    async put(path, data = {}, fullErrorObject = false) {
        try {
            const fullPath = this._getFullPath(path);

            const response = await apiFetch({
                path: fullPath,
                method: 'PUT',
                data,
            });

            return response;
        } catch (error) {
            throw this._handleApiError(error, 'PUT request failed', fullErrorObject);
        }
    }

    /**
     * Build full API path including namespace base path.
     *
     * @private
     * @param {string} path - Endpoint path relative to the base namespace.
     * @return {string} - Normalized API path.
     */
    _getFullPath(path = '') {
        const normalizedBase = this.basePath.replace(/\/$/, '');

        if (!path) {
            return normalizedBase;
        }

        if (typeof path !== 'string') {
            throw new Error('API path should be a string');
        }

        if (path.startsWith('http://') || path.startsWith('https://')) {
            return path;
        }

        const trimmedPath = path.replace(/^\//, '');
        const baseWithoutLeadingSlash = normalizedBase.replace(/^\//, '');

        if (trimmedPath.startsWith(baseWithoutLeadingSlash)) {
            return `/${trimmedPath}`;
        }

        return `${normalizedBase}/${trimmedPath}`;
    }

    /**
     * Handle and format API errors
     *
     * @private
     * @param {Object}  error           - The error object from API call
     * @param {string}  defaultMessage  - Default error message if no specific error found
     * @param {boolean} fullErrorObject - Whether to return the full error object instead of a formatted Error.
     * @return {Error} - Formatted error object
     */
    _handleApiError(
        error,
        defaultMessage = 'Operation failed. Please try again.',
        fullErrorObject = false
    ) {
        console.error('API Error:', error);

        if (fullErrorObject) {
            return error;
        }

        let errorMessage = defaultMessage;

        if (error && typeof error === 'object') {
            if (error.message) {
                errorMessage = error.message;
            } else if (error.api_errors && error.api_errors.errorMessage) {
                errorMessage = error.api_errors.errorMessage;
            } else if (error.errors && Array.isArray(error.errors) && error.errors.length > 0) {
                errorMessage = error.errors[0].message || error.errors[0];
            } else if (error.data && error.data.message) {
                errorMessage = error.data.message;
            } else if (error.code && error.data) {
                errorMessage = `Error ${error.code}: ${error.data}`;
            }
        }

        return new Error(errorMessage);
    }
}

export default new ApiService();
