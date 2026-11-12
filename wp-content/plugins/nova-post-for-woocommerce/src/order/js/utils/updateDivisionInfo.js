/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Update division info in the DOM after saving changes.
 * Performs targeted updates to specific elements using their classes.
 *
 * @param {Object} divisionData - Division data containing division object with name and address
 */
export function updateDivisionInfo(divisionData) {
    const divisionBlock = document.querySelector('.npw-division-data .division');

    if (!divisionBlock) {
        console.warn('Division block not found in DOM');
        return;
    }

    const nameElement = divisionBlock.querySelector('.division-name');
    if (nameElement) {
        const { name, shortName } = divisionData.division;
        const divisionName =
            shortName || name || __('No division selected', 'nova-post-for-woocommerce');

        nameElement.textContent = divisionName;
    }

    const addressElement = divisionBlock.querySelector('.division-address');
    if (divisionData?.division?.address) {
        if (addressElement) {
            addressElement.textContent = divisionData.division.address;
        }
    } else if (addressElement) {
        addressElement.textContent = '';
    }
}
