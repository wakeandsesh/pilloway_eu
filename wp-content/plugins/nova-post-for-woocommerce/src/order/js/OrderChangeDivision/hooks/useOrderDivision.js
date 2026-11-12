/**
 * External dependencies
 */
import { useState, useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import shipmentService from '../../../../services/shipmentService.js';

/**
 * Custom hook for managing order division data via REST API.
 *
 * @param {number} orderId Order ID
 * @return {Object} Hook state and methods
 */
export const useOrderDivision = (orderId) => {
    const [divisionData, setDivisionData] = useState(null);
    const [shippingAddress, setShippingAddress] = useState({ country: '', city: '' });
    const [isInitializing, setIsInitializing] = useState(true);
    const [isSaving, setIsSaving] = useState(false);
    const [saveError, setSaveError] = useState(null);

    // Load initial division data from order
    useEffect(() => {
        const loadOrderDivision = async () => {
            if (!orderId) {
                setIsInitializing(false);
                return;
            }

            try {
                const response = await shipmentService.getOrderDivision(orderId);

                // Set division data in format expected by NovaPostWidget
                setDivisionData({
                    division: response.division || null,
                    division_id: response.division_id || null,
                    division_type: response.division_type || 'branch',
                });

                // Set shipping address
                if (response.shipping_address) {
                    setShippingAddress(response.shipping_address);
                }
            } catch (error) {
                console.error('Error loading order division:', error);
                setSaveError(error.message || 'Failed to load division data');
            } finally {
                setIsInitializing(false);
            }
        };

        loadOrderDivision();
    }, [orderId]);

    /**
     * Save division data to order meta.
     *
     * @param {Object} payload Division data to save
     * @return {Promise<Object>} API response with updated division data
     */
    const saveDivision = async (payload) => {
        setIsSaving(true);
        setSaveError(null);

        try {
            const response = await shipmentService.updateOrderDivision(orderId, payload);

            const updatedData = {
                division: payload.division,
                division_id: payload.division_id,
                division_type: payload.division_type,
            };

            setDivisionData(updatedData);

            return {
                ...response,
                divisionData: updatedData,
            };
        } catch (error) {
            const errorMessage = error.message || 'Unknown error occurred';
            setSaveError(errorMessage);
            throw error;
        } finally {
            setIsSaving(false);
        }
    };

    return {
        divisionData,
        setDivisionData,
        shippingAddress,
        isInitializing,
        saveDivision,
        isSaving,
        saveError,
    };
};
