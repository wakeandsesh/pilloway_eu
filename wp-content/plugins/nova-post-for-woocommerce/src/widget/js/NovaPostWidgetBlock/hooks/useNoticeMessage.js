/**
 * External dependencies
 */
import { useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Hook to determine the notice message based on error calculation
 *
 * @param {Object}  errorCalculationShipping          - Error calculation object
 * @param {boolean} errorCalculationShipping.division - Division calculation error
 * @param {boolean} errorCalculationShipping.address  - Address calculation error
 * @return {string|null} Notice message or null
 */
export const useNoticeMessage = (errorCalculationShipping) => {
    return useMemo(() => {
        const { division, address } = errorCalculationShipping || {};

        if (address && division) {
            return __(
                'Nova Post shipping methods cannot be applied to this shipment.',
                'nova-post-for-woocommerce'
            );
        }

        if (address && !division) {
            return __(
                'If you wish to choose address delivery, please enter a valid address or select delivery to a branch.',
                'nova-post-for-woocommerce'
            );
        }

        if (division && !address) {
            return __(
                'If you wish to choose warehouse delivery, please enter a valid zip code or select delivery to address.',
                'nova-post-for-woocommerce'
            );
        }

        return null;
    }, [errorCalculationShipping]);
};
