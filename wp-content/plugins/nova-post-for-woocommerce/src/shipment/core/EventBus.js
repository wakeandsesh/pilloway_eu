/**
 * Event Bus for pub-sub pattern implementation.
 * Provides centralized event management for decoupled communication between components.
 *
 * @class EventBus
 */
export class EventBus {
    constructor() {
        this.listeners = new Map();
        this.debugMode = false;
    }

    /**
     * Subscribe to an event
     *
     * @param {string}   event    - Event name to listen for
     * @param {Function} callback - Function to call when event is emitted
     * @return {Function} Unsubscribe function
     */
    on(event, callback) {
        if (!this.listeners.has(event)) {
            this.listeners.set(event, []);
        }
        this.listeners.get(event).push(callback);

        // Return unsubscribe function for cleanup
        return () => this.off(event, callback);
    }

    /**
     * Subscribe to an event that will be called only once
     *
     * @param {string}   event    - Event name to listen for
     * @param {Function} callback - Function to call when event is emitted
     * @return {Function} Unsubscribe function
     */
    once(event, callback) {
        const onceCallback = (data) => {
            callback(data);
            this.off(event, onceCallback);
        };
        return this.on(event, onceCallback);
    }

    /**
     * Unsubscribe from an event
     *
     * @param {string}   event    - Event name
     * @param {Function} callback - Callback to remove
     */
    off(event, callback) {
        if (!this.listeners.has(event)) return;

        const callbacks = this.listeners.get(event);
        const index = callbacks.indexOf(callback);
        if (index > -1) {
            callbacks.splice(index, 1);
        }

        // Clean up empty listener arrays
        if (callbacks.length === 0) {
            this.listeners.delete(event);
        }
    }

    /**
     * Emit an event with data
     *
     * @param {string} event - Event name to emit
     * @param {*}      data  - Data to pass to listeners
     */
    emit(event, data) {
        if (this.debugMode) {
            console.log(`[EventBus] Emitting: ${event}`, data);
        }

        if (!this.listeners.has(event)) {
            if (this.debugMode) {
                console.log(`[EventBus] No listeners for: ${event}`);
            }
            return;
        }

        const callbacks = this.listeners.get(event);
        callbacks.forEach((callback) => {
            try {
                callback(data);
            } catch (error) {
                console.error(`[EventBus] Error in event handler for "${event}":`, error);
            }
        });
    }

    /**
     * Enable debug mode for logging all events
     *
     * @param {boolean} enabled - Whether to enable debug mode
     */
    setDebugMode(enabled) {
        this.debugMode = enabled;
    }

    /**
     * Clear all listeners (useful for cleanup/testing)
     */
    clear() {
        this.listeners.clear();
    }

    /**
     * Get all registered event names
     *
     * @return {string[]} Array of event names
     */
    getEventNames() {
        return Array.from(this.listeners.keys());
    }

    /**
     * Get listener count for an event
     *
     * @param {string} event - Event name
     * @return {number} Number of listeners
     */
    listenerCount(event) {
        return this.listeners.has(event) ? this.listeners.get(event).length : 0;
    }
}
