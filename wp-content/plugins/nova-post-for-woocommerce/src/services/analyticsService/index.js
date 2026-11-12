/**
 *  ANALYTICS SERVICE MODULE
 *
 * Purpose:
 * A modular and lightweight system for sending analytics events
 * to a backend endpoint.
 *
 * Architecture:
 * 1. API Layer (`sendEventAnalytics`) — handles event transmission.
 * 2. Modules (`moduleMain`) — define specific analytics events.
 * 3. Service (`analyticsService`) — central entry point combining all modules.
 *
 * Features:
 * - Uses `navigator.sendBeacon` for non-blocking analytics delivery.
 * - Falls back to `fetch` when `sendBeacon` is not available.
 * - Extensible structure for additional event modules.
 *
 * Example:
 * ```js
 * import analyticsService from '.service/analyticsService';
 * analyticsService.viewedWidget('US');
 * ```
 */

/**
 * Sends an analytics event to the backend.
 *
 * @param {Object} item       - Event payload.
 * @param {string} item.event - Event name.
 */
const sendEventAnalytics = (item) => {
    const API_BASE_PATH = `${window.location.origin}/index.php?rest_route=/np-shipping/v1/ga-event`;
    const jsonString = JSON.stringify(item);

    if (navigator.sendBeacon) {
        const blob = new Blob([jsonString], { type: 'application/json' });
        navigator.sendBeacon(API_BASE_PATH, blob);
    } else {
        fetch(API_BASE_PATH, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: jsonString,
        }).catch((err) => console.error('Analytics error:', err));
    }
};

/**
 * Analytics module containing base event definitions.
 *
 * @return {Object} Object with event methods.
 */
function moduleMain() {
    const sendEvent = (item) => sendEventAnalytics(item);

    return {
        viewedWidget(country) {
            sendEvent({
                event: 'np_widget_checkout_viewed',
                params: { country },
            });
        },
        deliveryMethodSelected(method, country) {
            sendEvent({
                event: 'np_delivery_method_selected',
                params: {
                    shipping_method: method,
                    country,
                },
            });
        },
        branchSelected(item, country) {
            const { divisionCategory, id, address } = item;
            const payload = {
                division_type: divisionCategory,
                division_id: id,
                address,
                country,
            };

            sendEvent({
                event: 'np_branch_selected',
                params: { ...payload },
            });
        },
    };
}

/**
 * Central analytics service combining all event modules.
 *
 * @return {Object} All available analytics methods.
 */
function analyticsService() {
    return {
        ...moduleMain(),
    };
}

export default new analyticsService();
