/**
 * External dependencies
 */
import { useEffect, useMemo } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { CART_STORE_KEY } from '@woocommerce/block-data';

/**
 * Internal dependencies
 */
import { useCheckoutActions, useCheckoutState } from '../useCheckoutState';

const NP_SHIPPING_METHOD_NAME = 'nova_post_division';

function getSelectedShippingRate(shippingRates = []) {
    for (const shippingPackage of shippingRates) {
        const foundRate = shippingPackage.shipping_rates.find((rate) => rate.selected);

        if (foundRate) {
            return foundRate;
        }
    }

    return null;
}

export function BlocksCheckoutAdapter() {
    const { hydrateFromBlocks } = useCheckoutActions();
    const checkoutState = useCheckoutState();
    const { shippingAddress, shippingRates, extensions } = useSelect(
        (select) => select(CART_STORE_KEY).getCartData(),
        []
    );

    const normalizedState = useMemo(() => {
        const selectedMethod = getSelectedShippingRate(shippingRates);
        const extensionData = extensions?.npforwc_widget_data || {};

        return {
            selectedShippingRateId: selectedMethod?.rate_id || null,
            selectedShippingMethodId: selectedMethod?.method_id || null,
            isNovaPostDivisionActive: selectedMethod?.method_id === NP_SHIPPING_METHOD_NAME,
            senderCountry: extensionData?.sender_country || null,
            shippingAddress: {
                country: shippingAddress?.country || '',
                state: shippingAddress?.state || '',
                postcode: shippingAddress?.postcode || '',
                city: shippingAddress?.city || '',
                address_1: shippingAddress?.address_1 || '',
                address_2: shippingAddress?.address_2 || '',
            },
            divisionData: {
                division: extensionData?.division || null,
                division_id: extensionData?.division_id || null,
                division_type: extensionData?.division_type || null,
            },
            divisionFilters: {
                postomat: Boolean(extensionData?.divisions_filter_postomat),
                postBranch: Boolean(extensionData?.divisions_filter_post_branch),
                cargoBranch: Boolean(extensionData?.divisions_filter_cargo_branch),
                pudo: Boolean(extensionData?.divisions_filter_pudo),
            },
            errorCalculation: {
                division: Boolean(extensionData?.error_calculation_division),
                address: Boolean(extensionData?.error_calculation_address),
            },
        };
    }, [extensions, shippingAddress, shippingRates]);

    const serializedNormalizedState = useMemo(
        () => JSON.stringify(normalizedState),
        [normalizedState]
    );

    const serializedCurrentState = useMemo(
        () =>
            JSON.stringify({
                selectedShippingRateId: checkoutState.selectedShippingRateId,
                selectedShippingMethodId: checkoutState.selectedShippingMethodId,
                isNovaPostDivisionActive: checkoutState.isNovaPostDivisionActive,
                senderCountry: checkoutState.senderCountry,
                shippingAddress: checkoutState.shippingAddress,
                divisionData: checkoutState.divisionData,
                divisionFilters: checkoutState.divisionFilters,
                errorCalculation: checkoutState.errorCalculation,
            }),
        [checkoutState]
    );

    useEffect(() => {
        if (serializedNormalizedState !== serializedCurrentState) {
            hydrateFromBlocks(normalizedState);
        }
    }, [hydrateFromBlocks, normalizedState, serializedCurrentState, serializedNormalizedState]);

    return null;
}
