/**
 * External dependencies
 */
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { useCheckoutActions, useCheckoutState } from '../useCheckoutState';
import { BlocksCheckoutAdapter } from './blocks';

jest.mock('@wordpress/data', () => ({ useSelect: jest.fn() }));
jest.mock('@woocommerce/block-data', () => ({ CART_STORE_KEY: 'wc/store/cart' }), {
    virtual: true,
});
jest.mock('../useCheckoutState', () => ({
    useCheckoutActions: jest.fn(),
    useCheckoutState: jest.fn(),
}));

global.IS_REACT_ACT_ENVIRONMENT = true;

describe('BlocksCheckoutAdapter', () => {
    let container;
    let root;
    let hydrateFromBlocks;

    beforeEach(() => {
        container = document.createElement('div');
        root = createRoot(container);
        hydrateFromBlocks = jest.fn();

        useCheckoutActions.mockReturnValue({ hydrateFromBlocks });
        useCheckoutState.mockReturnValue({
            senderCountry: null,
            selectedShippingRateId: 'nova_post_division:3',
            selectedShippingMethodId: 'nova_post_division',
            isNovaPostDivisionActive: true,
            shippingAddress: {
                country: 'UA',
                state: '',
                postcode: '',
                city: 'Kyiv',
                address_1: '',
                address_2: '',
            },
            divisionData: {
                division: null,
                division_id: null,
                division_type: null,
            },
            divisionFilters: {
                postomat: true,
                postBranch: true,
                cargoBranch: true,
                pudo: true,
            },
            errorCalculation: {
                division: false,
                address: false,
            },
        });
        useSelect.mockReturnValue({
            shippingAddress: {
                country: 'UA',
                state: '',
                postcode: '',
                city: 'Kyiv',
                address_1: '',
                address_2: '',
            },
            shippingRates: [
                {
                    shipping_rates: [
                        {
                            selected: true,
                            rate_id: 'nova_post_division:3',
                            method_id: 'nova_post_division',
                        },
                    ],
                },
            ],
            extensions: {
                npforwc_widget_data: {
                    sender_country: 'PL',
                    division: null,
                    division_id: null,
                    division_type: null,
                    divisions_filter_postomat: true,
                    divisions_filter_post_branch: true,
                    divisions_filter_cargo_branch: true,
                    divisions_filter_pudo: true,
                    error_calculation_division: false,
                    error_calculation_address: false,
                },
            },
        });
    });

    afterEach(() => {
        act(() => root.unmount());
        jest.clearAllMocks();
    });

    it('hydrates sender country when it differs from the current checkout state', () => {
        // React DOM roots require act; Testing Library is not used in this test.
        // eslint-disable-next-line testing-library/no-unnecessary-act
        act(() => {
            root.render(<BlocksCheckoutAdapter />);
        });

        expect(hydrateFromBlocks).toHaveBeenCalledWith(
            expect.objectContaining({
                senderCountry: 'PL',
                shippingAddress: expect.objectContaining({ country: 'UA' }),
            })
        );
    });
});
