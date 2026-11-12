/**
 * External dependencies
 */
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { OrderChangeDivision } from './OrderChangeDivision';

/**
 * Initialize Division Toggle functionality
 */
function initDivisionToggle() {
    const toggleButton = document.querySelector('.npw-division-toggle');
    const divisionData = document.querySelector('.npw-division-data');

    if (!toggleButton || !divisionData) {
        return;
    }

    toggleButton.addEventListener('click', () => {
        // Toggle the editing state
        divisionData.classList.toggle('is-editing');
    });
}

/**
 * Initialize Nova Post Widget on Order Edit page.
 * Finds the widget container and mounts the React component.
 */
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('npforwc-order-widget');

    if (!container) {
        return;
    }

    // Get orderId from data attribute
    const orderId = parseInt(container.dataset.orderId, 10);

    if (!orderId) {
        console.error('Nova Post Widget: orderId is required');
        return;
    }

    const root = createRoot(container);
    root.render(<OrderChangeDivision orderId={orderId} />);

    // Initialize division toggle
    initDivisionToggle();
});
