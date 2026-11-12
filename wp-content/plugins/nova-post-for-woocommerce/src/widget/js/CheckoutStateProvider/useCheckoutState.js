/**
 * External dependencies
 */
import { useContext } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { CheckoutStateContext } from './context';

function useCheckoutContext() {
    const value = useContext(CheckoutStateContext);

    if (!value) {
        throw new Error('useCheckoutState must be used within CheckoutStateProvider');
    }

    return value;
}

export function useCheckoutState() {
    return useCheckoutContext().state;
}

export function useCheckoutActions() {
    return useCheckoutContext().actions;
}
