/**
 * External dependencies
 */
import { useState, useEffect } from '@wordpress/element';

/**
 * Custom hook to handle WooCommerce bulk actions for orders.
 *
 * Listens for bulk action button clicks in the orders table,
 * checks if the selected action matches the provided actionName,
 * collects selected order IDs, and opens a modal if any are selected.
 *
 * @param {string} actionName         - The value of the bulk action to listen for (e.g., 'nova_post_create_pickup_request')
 * @param {string} [inputName='id[]'] - The name attribute of the checkbox inputs for order selection
 * @return {{
 *   selectedOrdersId: string[],
 *   isOpen: boolean,
 *   closeModal: Function
 * }} - Selected order IDs, modal open state, and a function to close the modal
 */

export function useWooBulkAction(actionName, inputName = 'id[]') {
    const [selectedOrdersId, setSelectedOrdersId] = useState([]);
    const [isOpen, setIsOpen] = useState(false);

    useEffect(() => {
        const handler = (e) => {
            if (isOpen) return;

            const form = e.target.closest('form');
            if (!form) return;

            let selector;
            if (e.currentTarget.id === 'doaction') {
                selector = '#bulk-action-selector-top, select[name="action"]';
            } else {
                selector = '#bulk-action-selector-bottom, select[name="action2"]';
            }

            const action = form.querySelector(selector)?.value;

            if (action === actionName) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();

                const selected = Array.from(
                    form.querySelectorAll(`input[name="${inputName}"]:checked`)
                ).map((el) => el.value);

                if (selected.length === 0) {
                    return;
                }

                setSelectedOrdersId(selected);
                setIsOpen(true);
            }
        };

        const btns = document.querySelectorAll('#doaction, #doaction2');
        btns.forEach((btn) => btn.addEventListener('click', handler, { capture: true }));

        return () => {
            btns.forEach((btn) => btn.removeEventListener('click', handler, { capture: true }));
        };
    }, [isOpen]);

    const closeModal = () => {
        setSelectedOrdersId([]);
        setIsOpen(false);
    };

    return { selectedOrdersId, isOpen, closeModal };
}
