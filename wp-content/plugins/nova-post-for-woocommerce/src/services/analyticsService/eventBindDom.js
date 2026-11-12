/**
 * Internal dependencies
 */
import analyticsService from './index';

/**
 * Binds DOM listeners that fire the delivery-method-selected analytics event.
 *
 * @param {Function} [getCountry] - Optional. Returns the current shipping country code (ISO 2).
 *                                Provide a context-specific getter:
 *                                - Blocks checkout: read from `wp.data` CART_STORE_KEY
 *                                - Classic checkout: read from the DOM country select
 */
export const bindAnalyticsEventDeliveryMethodSelected = (getCountry) => {
    if (window.__novaPostAnalyticsListenerBound) {
        return;
    }
    window.__novaPostAnalyticsListenerBound = true;

    const DIVISION_PREFIXES = [
        'radio-control-0-nova_post_division',
        'shipping_method_0_nova_post_division',
    ];
    const ADDRESS_PREFIXES = [
        'radio-control-0-nova_post_address',
        'shipping_method_0_nova_post_address',
    ];

    const matchesPrefix = (id, prefixes) => prefixes.some((p) => id && id.startsWith(p));

    const country = () => (typeof getCountry === 'function' ? getCountry() : '') || '';

    const reported = { division: false, address: false };

    const clickHandler = (event) => {
        const target = event.target;
        if (!target || target.tagName !== 'INPUT') {
            return;
        }
        const targetId = target.id;
        if (matchesPrefix(targetId, DIVISION_PREFIXES)) {
            analyticsService.deliveryMethodSelected('division', country());
            reported.division = true;
        }
        if (matchesPrefix(targetId, ADDRESS_PREFIXES)) {
            analyticsService.deliveryMethodSelected('address', country());
            reported.address = true;
        }
    };
    document.addEventListener('click', clickHandler);

    const checkAndReport = () => {
        const divisionEl = Array.from(
            document.querySelectorAll(DIVISION_PREFIXES.map((p) => `[id^="${p}"]`).join(','))
        ).find((el) => el && el.tagName === 'INPUT' && el.checked);
        if (divisionEl && !reported.division) {
            analyticsService.deliveryMethodSelected('division', country());
            reported.division = true;
        }

        const addressEl = Array.from(
            document.querySelectorAll(ADDRESS_PREFIXES.map((p) => `[id^="${p}"]`).join(','))
        ).find((el) => el && el.tagName === 'INPUT' && el.checked);
        if (addressEl && !reported.address) {
            analyticsService.deliveryMethodSelected('address', country());
            reported.address = true;
        }
    };

    checkAndReport();

    const observer = new MutationObserver(() => {
        checkAndReport();
        if (reported.division && reported.address) {
            observer.disconnect();
        }
    });

    observer.observe(document.body, { childList: true, subtree: true });
};
