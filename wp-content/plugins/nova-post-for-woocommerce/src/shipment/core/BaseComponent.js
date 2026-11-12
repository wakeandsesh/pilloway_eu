/**
 * Base class for all UI components.
 * Provides common functionality for subscribing to events and managing lifecycle.
 *
 * @class BaseComponent
 */
export class BaseComponent {
    /**
     * @param {HTMLElement}                   rootElement - Root DOM element for this component
     * @param {import('./EventBus').EventBus} eventBus    - Event bus instance
     */
    constructor(rootElement, eventBus) {
        this.rootElement = rootElement;
        this.eventBus = eventBus;
        this.subscriptions = [];
        this.orderId = this.extractOrderId();
    }

    /**
     * Subscribe to an event
     *
     * @param {string}   event    - Event name
     * @param {Function} callback - Callback function
     */
    subscribe(event, callback) {
        const unsubscribe = this.eventBus.on(event, callback);
        this.subscriptions.push(unsubscribe);
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
     * Extract order ID from the root element
     *
     * @return {string|null} Order ID or null if not found
     */
    extractOrderId() {
        const el = this.rootElement.querySelector('[data-npw-order-id]');
        return el?.dataset.npwOrderId || null;
    }

    /**
     * Check if event is for this component's order
     *
     * @param {Object} eventData - Event data object
     * @return {boolean} Whether event is for this order
     */
    isForThisOrder(eventData) {
        return eventData && eventData.orderId === this.orderId;
    }

    /**
     * Query element within component root
     *
     * @param {string} selector - CSS selector
     * @return {HTMLElement|null} Found element or null
     */
    querySelector(selector) {
        return this.rootElement.querySelector(selector);
    }

    /**
     * Query all elements within component root
     *
     * @param {string} selector - CSS selector
     * @return {NodeList} Found elements
     */
    querySelectorAll(selector) {
        return this.rootElement.querySelectorAll(selector);
    }

    /**
     * Clean up component and unsubscribe from all events
     */
    destroy() {
        this.subscriptions.forEach((unsubscribe) => {
            if (typeof unsubscribe === 'function') {
                unsubscribe();
            }
        });
        this.subscriptions = [];
    }

    /**
     * Set disabled attribute on element and toggle disabled class
     *
     * @param {HTMLElement|null} element  - Element to modify
     * @param {boolean}          disabled - Whether to disable or enable
     */
    setDisabled(element, disabled) {
        if (!element) {
            return;
        }

        if (disabled) {
            element.setAttribute('disabled', '');
            element.classList.add('--disabled');
        } else {
            element.removeAttribute('disabled');
            element.classList.remove('--disabled');
        }
    }

    /**
     * Set hidden attribute on element
     *
     * @param {HTMLElement|null} element - Element to modify
     * @param {boolean}          hidden  - Whether to hide or show
     */
    setHidden(element, hidden) {
        if (!element) {
            return;
        }

        if (hidden) {
            element.setAttribute('hidden', '');
        } else {
            element.removeAttribute('hidden');
        }
    }

    /**
     * Initialize component (can be overridden by subclasses)
     */
    init() {}
}
