/**
 * External dependencies
 */
import { useCallback, useState } from '@wordpress/element';
import analyticsService from '@/services/analyticsService';

/**
 * Internal dependencies
 */
import { persistDivisionData } from '../CheckoutStateProvider/services/divisionStorageService';
import { useCheckoutActions, useCheckoutState } from '../CheckoutStateProvider/useCheckoutState';

export function useDivisionData() {
    const [isSaving, setIsSaving] = useState(false);
    const [saveError, setSaveError] = useState(null);
    const { checkoutMode } = useCheckoutState();
    const actions = useCheckoutActions();

    const saveDivisionData = useCallback(
        async (payload) => {
            setIsSaving(true);
            setSaveError(null);

            try {
                await persistDivisionData({
                    payload,
                    checkoutMode,
                    actions,
                });

                if (payload?.division) {
                    analyticsService.branchSelected(payload.division, payload.country);
                }
            } catch (error) {
                const errorMessage = error.message || 'Failed to save division data';
                setSaveError(errorMessage);
            } finally {
                setIsSaving(false);
            }
        },
        [actions, checkoutMode]
    );

    return {
        saveDivisionData,
        isSaving,
        saveError,
    };
}
