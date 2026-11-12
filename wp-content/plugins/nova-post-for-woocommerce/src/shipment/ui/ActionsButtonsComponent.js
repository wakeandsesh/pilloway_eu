/**
 * Actions Buttons Component
 * Manages action buttons visibility and state.
 *
 * @class ActionsButtonsComponent
 */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { BaseComponent } from '../core';

export class ActionsButtonsComponent extends BaseComponent {
    constructor(rootElement, eventBus) {
        super(rootElement, eventBus);

        this.elements = {
            createButton: this.querySelector('[data-npw-action="create_waybill"]'),
            deleteButton: this.querySelector('[data-npw-action="delete_shipment"]'),
            printLabelButton: this.querySelector('[data-npw-action="print_label"]'),
            printInvoiceButton: this.querySelector('[data-npw-action="print_invoice"]'),
            printCustomsButton: this.querySelector('[data-npw-action="print_customs"]'),
        };

        this.init();
    }

    init() {
        this.initSubscriptions();
    }

    /**
     * Initialize event subscriptions
     */
    initSubscriptions() {
        this.subscribe('waybill:created', (data) => {
            if (this.isForThisOrder(data)) {
                this.updateDeleteButtonState(data.statusCode);
            }
        });

        this.subscribe('shipment:status-updated', (data) => {
            if (this.isForThisOrder(data)) {
                this.updateDeleteButtonState(data.statusCode);
            }
        });
    }

    /**
     * Show action buttons (delete, print)
     */
    showActionButtons() {
        const actionButtons = [
            this.elements.deleteButton,
            this.elements.printLabelButton,
            this.elements.printInvoiceButton,
            this.elements.printCustomsButton,
        ];

        actionButtons.forEach((element) => {
            if (element) {
                this.setHidden(element, false);
            }
        });
    }

    /**
     * Hide action buttons (delete, print)
     *
     * @param {HTMLElement[]} actionButtons - Buttons to hide
     */
    hideActionButtons(actionButtons) {
        actionButtons.forEach((element) => {
            if (element) {
                this.setHidden(element, true);
            }
        });
    }

    /**
     * Update delete button state based on status code
     *
     * @param {string|number} statusCode - Status code from shipment
     */
    updateDeleteButtonState(statusCode) {
        if (!this.elements.deleteButton) {
            return;
        }

        const shouldDisable = statusCode && String(statusCode) !== '1';

        this.setDisabled(this.elements.deleteButton, shouldDisable);

        this.elements.deleteButton.title = shouldDisable
            ? __('Cannot delete waybill with this status', 'nova-post-for-woocommerce')
            : __('Delete waybill', 'nova-post-for-woocommerce');
    }
}
