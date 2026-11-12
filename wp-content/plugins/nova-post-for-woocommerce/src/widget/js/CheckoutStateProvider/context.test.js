/**
 * External dependencies
 */
import { act } from 'react';
import { createRoot } from 'react-dom/client';

/**
 * Internal dependencies
 */
import { CheckoutStateProvider } from './context';
import { useCheckoutActions, useCheckoutState } from './useCheckoutState';

global.IS_REACT_ACT_ENVIRONMENT = true;

describe('CheckoutStateProvider', () => {
    let container;
    let root;
    let checkoutActions;

    beforeEach(() => {
        container = document.createElement('div');
        root = createRoot(container);
    });

    afterEach(() => {
        act(() => root.unmount());
    });

    function StateProbe() {
        checkoutActions = useCheckoutActions();
        const { senderCountry } = useCheckoutState();

        return <span>{senderCountry ?? 'null'}</span>;
    }

    it('hydrates sender country without falling back to the recipient country', () => {
        // React DOM roots require act; Testing Library is not used in this test.
        // eslint-disable-next-line testing-library/no-unnecessary-act
        act(() => {
            root.render(
                <CheckoutStateProvider>
                    <StateProbe />
                </CheckoutStateProvider>
            );
        });

        act(() => {
            checkoutActions.hydrateFromClassic({
                senderCountry: 'PL',
                shippingAddress: { country: 'UA' },
            });
        });

        expect(container.textContent).toBe('PL');

        act(() => {
            checkoutActions.hydrateFromClassic({
                senderCountry: '',
                shippingAddress: { country: 'UA' },
            });
        });

        expect(container.textContent).toBe('null');
    });
});
