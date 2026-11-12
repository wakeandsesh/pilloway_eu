/**
 * External dependencies
 */
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { DIVISION_CATEGORIES, DIVISION_TYPE } from '@/constants';

/**
 * Internal dependencies
 */
import { useDivisionSelection } from './useDivisionSelection';

global.IS_REACT_ACT_ENVIRONMENT = true;

const branch = {
    id: 101,
    name: 'Branch 1',
    divisionCategory: DIVISION_CATEGORIES.POST_BRANCH,
};

const postomat = {
    id: 202,
    name: 'Postomat 2',
    divisionCategory: DIVISION_CATEGORIES.POSTOMAT,
};

function HookHarness({ hookProps, onRender }) {
    onRender(useDivisionSelection(hookProps));

    return null;
}

describe('useDivisionSelection', () => {
    let container;
    let root;
    let result;
    let saveDivisionData;

    beforeEach(() => {
        container = document.createElement('div');
        root = createRoot(container);
        result = null;
        saveDivisionData = jest.fn().mockResolvedValue(undefined);
    });

    afterEach(() => {
        act(() => root.unmount());
    });

    function render({
        shippingAddress = { country: 'UA', city: 'Kyiv' },
        divisionData = { division: null, division_id: null, division_type: null },
    } = {}) {
        // React DOM roots require act; Testing Library is not used in this test.
        // eslint-disable-next-line testing-library/no-unnecessary-act
        act(() => {
            root.render(
                <HookHarness
                    hookProps={{ shippingAddress, divisionData, saveDivisionData }}
                    onRender={(nextResult) => {
                        result = nextResult;
                    }}
                />
            );
        });
    }

    it('persists an SDK selection with a derived type and with refreshing the cart', () => {
        render();

        act(() => result.handleChangeDivision(postomat));

        expect(result.currentDivision).toEqual(postomat);
        expect(saveDivisionData).toHaveBeenCalledWith({
            division: postomat,
            division_id: 202,
            division_type: DIVISION_TYPE.POSTMAT,
            country: 'UA',
            needRefreshCart: true,
        });
    });

    it('persists SDK deselect with a null division type and without refreshing the cart', () => {
        render({
            divisionData: {
                division: postomat,
                division_id: postomat.id,
                division_type: DIVISION_TYPE.POSTMAT,
            },
        });

        act(() => result.handleClearDivision());

        expect(result.currentDivision).toBeNull();
        expect(saveDivisionData).toHaveBeenCalledWith({
            division: null,
            division_id: null,
            division_type: null,
            country: 'UA',
            needRefreshCart: false,
        });
    });

    it('clears persisted and local selection when the WooCommerce address changes', () => {
        render({
            divisionData: {
                division: branch,
                division_id: branch.id,
                division_type: DIVISION_TYPE.BRANCH,
            },
        });

        expect(saveDivisionData).not.toHaveBeenCalled();

        render({
            shippingAddress: { country: 'UA', city: 'Lviv' },
            divisionData: {
                division: branch,
                division_id: branch.id,
                division_type: DIVISION_TYPE.BRANCH,
            },
        });

        expect(result.currentDivision).toBeNull();
        expect(saveDivisionData).toHaveBeenCalledWith({
            division: null,
            division_id: null,
            division_type: null,
            country: 'UA',
            needRefreshCart: false,
        });
    });

    it('does not treat initial checkout hydration as an address change', () => {
        render({ shippingAddress: { country: '', city: '' } });
        render({
            shippingAddress: { country: 'UA', city: 'Kyiv' },
            divisionData: {
                division: branch,
                division_id: branch.id,
                division_type: DIVISION_TYPE.BRANCH,
            },
        });

        expect(result.currentDivision).toEqual(branch);
        expect(saveDivisionData).not.toHaveBeenCalled();
    });

    it('ignores duplicate SDK selection and stale persisted echoes', () => {
        render({
            divisionData: {
                division: branch,
                division_id: branch.id,
                division_type: DIVISION_TYPE.BRANCH,
            },
        });

        act(() => result.handleChangeDivision(postomat));

        render({
            divisionData: {
                division: branch,
                division_id: branch.id,
                division_type: DIVISION_TYPE.BRANCH,
            },
        });

        act(() => result.handleChangeDivision(postomat));

        expect(result.currentDivision).toEqual(postomat);
        expect(saveDivisionData).toHaveBeenCalledTimes(1);
    });
});
