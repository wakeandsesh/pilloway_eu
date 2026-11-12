/**
 * External dependencies
 */
import { ALL_DIVISION_CATEGORIES } from '@/constants.js';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import apiService from './ApiService.js';

/**
 * Service for handling division-related business logic.
 * This service can be used both in React hooks and standalone JavaScript modules.
 * Follows Single Responsibility Principle and uses composition over inheritance.
 */
class DivisionService {
    constructor(apiClient = apiService) {
        this.apiClient = apiClient;
    }

    /**
     * Search for divisions based on country, city, and query parameters.
     *
     * @param {Object}        params                      - Search parameters
     * @param {string}        params.countryCode          - The country code to search in
     * @param {string}        params.city                 - The city to search in
     * @param {string}        params.query                - The search query string
     * @param {Array<string>} params.divisionCategories   - The types of divisions ('PostBranch', 'Postomat', 'CargoBranch', 'PUDO')
     * @param {boolean}       [params.prohibitedSending]  - Whether sending is prohibited
     * @param {boolean}       [params.prohibitedIssuance] - Whether issuance is prohibited
     * @return {Promise<Array>} Promise that resolves to an array of divisions
     * @throws {Error} Throws error if API request fails
     */
    async searchDivisions({
        countryCode,
        city,
        query,
        divisionCategories,
        prohibitedSending,
        prohibitedIssuance,
    }) {
        this._validateSearchParams({ countryCode, city, query, divisionCategories });

        try {
            const searchParams = {
                countryCode,
                city,
                query,
                divisionCategories,
                ...(typeof prohibitedSending === 'boolean' && { prohibitedSending }),
                ...(typeof prohibitedIssuance === 'boolean' && { prohibitedIssuance }),
            };

            const path = `/checkout-widget/find-divisions`;
            const response = await this.apiClient.get(path, { params: searchParams });

            return response?.data || [];
        } catch (error) {
            throw new Error(
                sprintf(
                    // translators: %s: error message
                    __('Failed to search divisions: %s', 'nova-post-for-woocommerce'),
                    error.message
                )
            );
        }
    }

    /**
     * Validate search parameters
     *
     * @private
     * @param {Object}   params                    - Search parameters to validate
     * @param {string}   params.countryCode        - Country code to search within
     * @param {string}   params.city               - City name to search within
     * @param {string}   params.query              - Search query string
     * @param {string[]} params.divisionCategories - Division categories to filter by
     * @throws {Error} Throws error if required parameters are missing
     */
    _validateSearchParams({ countryCode, city, query, divisionCategories }) {
        if (!countryCode) {
            throw new Error(__('Country code is required', 'nova-post-for-woocommerce'));
        }

        if (!city) {
            throw new Error(__('City is required', 'nova-post-for-woocommerce'));
        }

        if (!query) {
            throw new Error(__('Search query is required', 'nova-post-for-woocommerce'));
        }

        if (
            !divisionCategories ||
            !Array.isArray(divisionCategories) ||
            !divisionCategories.every((t) => ALL_DIVISION_CATEGORIES.includes(t))
        ) {
            throw new Error(
                __('Type must be either "branch" or "postomat"', 'nova-post-for-woocommerce')
            );
        }
    }
}

export default new DivisionService();
