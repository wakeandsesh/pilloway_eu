/**
 * External dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import apiService from '../../services/ApiService.js';

/**
 * Custom hook for managing countries data from Nova Post API
 *
 * @return {Object} Object containing countries data, loading state, and error state
 */
const useCountries = () => {
    const [countries, setCountries] = useState([]);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        const fetchCountries = async () => {
            try {
                setIsLoading(true);
                setError(null);

                const response = await apiService.get('/available-countries');

                const { countries: fetchedCountries } = response;

                // Transform data to format suitable for ComboboxControl
                // Expected format: countries: [{<country_code>: <country_title>}, ... ]
                let formattedCountries = [];

                if (Array.isArray(fetchedCountries)) {
                    formattedCountries = fetchedCountries.map((country) => {
                        const code = Object.keys(country)[0];
                        const title = country[code];

                        return {
                            value: code,
                            label: title,
                        };
                    });
                }

                setCountries(formattedCountries);
            } catch (err) {
                console.error('Error loading countries:', err);
                setError(
                    err.message || __('Failed to load countries', 'nova-post-for-woocommerce')
                );
                setCountries([]);
            } finally {
                setIsLoading(false);
            }
        };

        fetchCountries();
    }, []);

    return {
        countries,
        isLoading,
        error,
    };
};

export default useCountries;
