/**
 * External dependencies
 */
import { registerPlugin } from '@wordpress/plugins';
import { select } from '@wordpress/data';
import { ExperimentalOrderShippingPackages as WC_Slot_ShippingOptions } from '@woocommerce/blocks-checkout';
import { CART_STORE_KEY } from '@woocommerce/block-data';

/**
 * Internal dependencies
 */
import { NovaPostWidgetBlock } from './NovaPostWidgetBlock';
import { bindAnalyticsEventDeliveryMethodSelected } from '../../services/analyticsService/eventBindDom';

const render = () => {
    return (
        <WC_Slot_ShippingOptions>
            <NovaPostWidgetBlock />
        </WC_Slot_ShippingOptions>
    );
};

registerPlugin('nova-post-widget', {
    render,
    scope: 'woocommerce-checkout',
});

bindAnalyticsEventDeliveryMethodSelected(
    () => select(CART_STORE_KEY).getCartData()?.shippingAddress?.country || ''
);
