/**
 * External dependencies
 */
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { NovaPostWidgetClassicCheckout } from './NovaPostWidgetClassicCheckout';
import { bindAnalyticsEventDeliveryMethodSelected } from '../../services/analyticsService/eventBindDom';

document.addEventListener('DOMContentLoaded', () => {
    const anchorEl = document.querySelector('.shop_table.woocommerce-checkout-review-order-table');
    const rootId = 'nova-post-widget-root';

    if (anchorEl) {
        let el = document.getElementById(rootId);

        if (!el) {
            el = document.createElement('div');
            el.id = rootId;
            el.style.marginBottom = '20px';
            anchorEl.insertAdjacentElement('afterend', el);
        }

        const root = createRoot(el);
        root.render(<NovaPostWidgetClassicCheckout />);
    }
});

bindAnalyticsEventDeliveryMethodSelected(
    () => document.querySelector('#shipping_country')?.value || ''
);
