/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Base class for all action handlers.
 * Provides common functionality for executing actions and managing state.
 *
 * @class BaseAction
 */
export class BaseAction {
    /**
     * @param {import('./EventBus').EventBus} eventBus - Event bus instance
     */
    constructor(eventBus) {
        this.eventBus = eventBus;
        this.actionName = '';
    }

    /**
     * Emit an event through the event bus
     *
     * @param {string} event - Event name
     * @param {*}      data  - Event data
     */
    emit(event, data) {
        this.eventBus.emit(event, data);
    }

    /**
     * Subscribe to an event
     *
     * @param {string}   event    - Event name
     * @param {Function} callback - Callback function
     * @return {Function} Unsubscribe function
     */
    subscribe(event, callback) {
        return this.eventBus.on(event, callback);
    }

    /**
     * Extract order ID from element element
     *
     * @param {HTMLElement} element - element element
     * @return {string|null} Order ID or null if not found
     */
    getOrderId(element) {
        return element.dataset.npwOrderId || null;
    }

    /**
     * Set loading state on a element
     *
     * @param {HTMLElement} element   - element element
     * @param {boolean}     isLoading - Loading state
     */
    setLoading(element, isLoading) {
        element.disabled = isLoading;
        if (isLoading) {
            element.setAttribute('aria-busy', 'true');
            element.setAttribute('loading', '');
        } else {
            element.removeAttribute('aria-busy');
            element.removeAttribute('loading');
        }
    }

    /**
     * Handle error state and emit error event
     *
     * @param {HTMLElement} element         - element that triggered the action
     * @param {Error}       error           - Error object
     * @param {string}      fallbackMessage - Default error message
     */
    handleError(element, error, fallbackMessage) {
        console.error(`[${this.actionName}] Error:`, error);

        const errorMessage = error.message || fallbackMessage;

        this.emit('action:error', {
            action: this.actionName,
            error: errorMessage,
            orderId: this.getOrderId(element),
        });
    }

    /**
     * Execute the action (must be implemented by subclasses)
     *
     * @param {HTMLElement} _element - element that triggered the action
     * @return {Promise<void>}
     * @abstract
     */
    async execute(_element) {
        throw new Error(`execute() must be implemented in ${this.constructor.name}`);
    }

    /**
     * Validate element element before execution
     *
     * @param {HTMLElement} element - element element
     * @return {boolean} Whether element is valid
     */
    validateElement(element) {
        if (!element) {
            console.error(`[${this.actionName}] Invalid element element`);
            return false;
        }

        const orderId = this.getOrderId(element);
        if (!orderId) {
            console.error(`[${this.actionName}] Order ID not found`);
            this.emit('action:error', {
                action: this.actionName,
                error: __('Order ID not found', 'nova-post-for-woocommerce'),
                orderId: null,
            });
            return false;
        }

        return true;
    }
}
