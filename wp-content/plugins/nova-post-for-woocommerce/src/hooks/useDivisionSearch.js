/**
 * External dependencies
 */
import { useState, useCallback } from '@wordpress/element';

/**
 * Internal dependencies
 */
import divisionService from '../services/divisionService';

/**
 * Custom hook for searching divisions.
 *
 * @return {Object} An object containing the divisions, loading state, error state, and search function.
 */
export const useDivisionSearch = () => {
    const [divisions, setDivisions] = useState([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState(null);

    const searchDivisions = useCallback(
        async ({
            countryCode,
            city,
            query,
            divisionCategories,
            prohibitedSending,
            prohibitedIssuance,
        }) => {
            setIsLoading(true);
            setError(null);
            setDivisions([]);

            try {
                const divisionsData = await divisionService.searchDivisions({
                    countryCode,
                    city,
                    query,
                    divisionCategories,
                    prohibitedSending,
                    prohibitedIssuance,
                });

                setDivisions(divisionsData);
            } catch (err) {
                setError(err);
                setDivisions([]);
            } finally {
                setIsLoading(false);
            }
        },
        []
    );

    return { divisions, isLoading, error, searchDivisions };
};
