/**
 * External dependencies
 */
import { dispatch, select } from '@wordpress/data';
import { CART_STORE_KEY } from '@woocommerce/block-data';

import apiService from '@/services/ApiService';

function triggerClassicCheckoutRefresh() {
    if (typeof jQuery !== 'undefined' && jQuery(document.body).trigger) {
        jQuery(document.body).trigger('update_checkout');
    }
}

export async function persistDivisionData({ payload, checkoutMode, actions }) {
    const divisionData = {
        division: payload?.division || null,
        division_id: payload?.division_id || null,
        division_type: payload?.division_type || null,
    };

    await apiService.post('/user-storage', divisionData);

    actions.updateDivisionData(divisionData);

    if (!payload?.needRefreshCart) {
        return divisionData;
    }

    if (checkoutMode === 'blocks') {
        const { shippingAddress } = select(CART_STORE_KEY).getCartData();
        await dispatch(CART_STORE_KEY).updateCustomerData(
            { shipping_address: shippingAddress },
            false,
            true
        );
    }

    if (checkoutMode === 'classic') {
        triggerClassicCheckoutRefresh();
    }

    return divisionData;
}
