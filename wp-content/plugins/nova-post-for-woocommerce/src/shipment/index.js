/**
 * Shipments functionality for Nova Post WooCommerce plugin with order
 * Refactored to use Event-Driven Architecture with actions and components
 */

/**
 * External dependencies
 */
import '@/styles/shipment.scss';

import { EventBus } from '@/shipment/core';

/**
 * Internal dependencies
 */
import {
    CreateWaybillAction,
    DeleteShipmentAction,
    PrintLabelAction,
    PrintInvoiceAction,
    PrintCustomsAction,
} from './actions';

import {
    SectionManager,
    TrackingLinkComponent,
    StatusBadgeComponent,
    ActionsButtonsComponent,
} from './ui';

/**
 * Main Shipments Manager class
 * Orchestrates actions and UI components via event bus
 */
class ShipmentsManager {
    constructor() {
        this.eventBus = new EventBus();
        this.actions = new Map();
        this.components = new Map();

        this.init();
    }

    /**
     * Initialize the manager
     */
    init() {
        this.initActions();
        this.initComponents();
        this.bindEvents();
        this.setupDebugMode();
    }

    /**
     * Initialize all action handlers
     */
    initActions() {
        const actionClasses = [
            CreateWaybillAction,
            DeleteShipmentAction,
            PrintLabelAction,
            PrintInvoiceAction,
            PrintCustomsAction,
        ];

        actionClasses.forEach((ActionClass) => {
            const action = new ActionClass(this.eventBus);
            this.actions.set(action.actionName, action);
        });
    }

    /**
     * Initialize UI components for all .npw-content blocks
     */
    initComponents() {
        const roots = document.querySelectorAll('.npw-content');

        roots.forEach((root) => {
            const orderId = this.getOrderIdFromRoot(root);
            if (!orderId) {
                console.warn('[ShipmentsManager] Order ID not found for root element', root);
                return;
            }

            // Create unique component instances for each order block
            const componentKey = `order-${orderId}`;

            this.components.set(`${componentKey}-section`, new SectionManager(root, this.eventBus));
            this.components.set(
                `${componentKey}-tracking`,
                new TrackingLinkComponent(root, this.eventBus)
            );
            this.components.set(
                `${componentKey}-status`,
                new StatusBadgeComponent(root, this.eventBus)
            );
            this.components.set(
                `${componentKey}-actions`,
                new ActionsButtonsComponent(root, this.eventBus)
            );
        });
    }

    /**
     * Bind DOM event listeners
     */
    bindEvents() {
        document.addEventListener('click', this.handleButtonClick.bind(this), true);
    }

    /**
     * Handle button clicks
     *
     * @param {Event} event - Click event
     */
    handleButtonClick(event) {
        const containerEl = event.target.closest('.npw-content');

        if (!containerEl) {
            return;
        }

        const button = event.target.closest('[data-npw-action]');
        if (!button) {
            event.stopImmediatePropagation();
            return false;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        const actionName = button.dataset.npwAction;
        const action = this.actions.get(actionName);

        if (!action) {
            console.warn(`[ShipmentsManager] Unknown action: ${actionName}`);
            return false;
        }

        action.execute(button);
    }

    /**
     * Extract order ID from root element
     *
     * @param {HTMLElement} root - Root element
     * @return {string|null} Order ID or null
     */
    getOrderIdFromRoot(root) {
        const button = root.querySelector('[data-npw-order-id]');
        return button?.dataset.npwOrderId || null;
    }

    /**
     * Setup debug mode if URL parameter is present
     */
    setupDebugMode() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('npw_debug') === '1') {
            this.eventBus.setDebugMode(true);
            console.log('[ShipmentsManager] Debug mode enabled');
        }
    }

    /**
     * Cleanup and destroy all components
     */
    destroy() {
        // Cleanup all components
        this.components.forEach((component) => {
            if (component && typeof component.destroy === 'function') {
                component.destroy();
            }
        });
        this.components.clear();

        // Clear event bus
        this.eventBus.clear();

        console.log('[ShipmentsManager] Destroyed');
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.npwforwcShipmentsManager = new ShipmentsManager();
});
