/**
 * External dependencies
 */
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import analyticsService from '@/services/analyticsService';

/**
 * Internal dependencies
 */
import { persistDivisionData } from '../CheckoutStateProvider/services/divisionStorageService';
import { useCheckoutActions, useCheckoutState } from '../CheckoutStateProvider/useCheckoutState';
import { useDivisionData } from './useDivisionData';

jest.mock('../CheckoutStateProvider/services/divisionStorageService', () => ({
    persistDivisionData: jest.fn(),
}));
jest.mock('../CheckoutStateProvider/useCheckoutState', () => ({
    useCheckoutActions: jest.fn(),
    useCheckoutState: jest.fn(),
}));
jest.mock('@/services/analyticsService', () => ({
    __esModule: true,
    default: {
        branchSelected: jest.fn(),
    },
}));

global.IS_REACT_ACT_ENVIRONMENT = true;

function HookHarness({ onRender }) {
    onRender(useDivisionData());

    return null;
}

describe('useDivisionData', () => {
    let container;
    let root;
    let result;
    let checkoutActions;

    beforeEach(() => {
        container = document.createElement('div');
        root = createRoot(container);
        checkoutActions = { setDivisionData: jest.fn() };
        persistDivisionData.mockReset().mockResolvedValue(undefined);
        useCheckoutState.mockReset().mockReturnValue({ checkoutMode: 'blocks' });
        useCheckoutActions.mockReset().mockReturnValue(checkoutActions);
        analyticsService.branchSelected.mockReset();

        // React DOM roots require act; Testing Library is not used in this test.
        // eslint-disable-next-line testing-library/no-unnecessary-act
        act(() => {
            root.render(
                <HookHarness
                    onRender={(nextResult) => {
                        result = nextResult;
                    }}
                />
            );
        });
    });

    afterEach(() => {
        act(() => root.unmount());
    });

    it('persists checkout selection without sending duplicate branch analytics', async () => {
        const payload = {
            division: { id: 123, divisionCategory: 'PostBranch' },
            country: 'UA',
            needRefreshCart: false,
        };

        await act(async () => {
            await result.saveDivisionData(payload);
        });

        expect(persistDivisionData).toHaveBeenCalledWith({
            payload,
            checkoutMode: 'blocks',
            actions: checkoutActions,
        });
        expect(analyticsService.branchSelected).not.toHaveBeenCalled();
    });
});
