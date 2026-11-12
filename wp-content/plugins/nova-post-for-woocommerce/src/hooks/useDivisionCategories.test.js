/**
 * External dependencies
 */
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { DIVISION_CATEGORIES } from '@/constants';

/**
 * Internal dependencies
 */
import { useDivisionCategories } from './useDivisionCategories';

global.IS_REACT_ACT_ENVIRONMENT = true;

function HookHarness({ divisionsFilters, onRender }) {
    onRender(useDivisionCategories(divisionsFilters));

    return null;
}

describe('useDivisionCategories', () => {
    let container;
    let root;
    let result;

    beforeEach(() => {
        container = document.createElement('div');
        root = createRoot(container);
        result = null;
    });

    afterEach(() => {
        act(() => root.unmount());
    });

    function render(divisionsFilters) {
        // React DOM roots require act; Testing Library is not used in this test.
        // eslint-disable-next-line testing-library/no-unnecessary-act
        act(() => {
            root.render(
                <HookHarness
                    divisionsFilters={divisionsFilters}
                    onRender={(nextResult) => {
                        result = nextResult;
                    }}
                />
            );
        });

        return result.divisionCategories;
    }

    it('maps enabled checkout filters to SDK division categories', () => {
        expect(
            render({
                postBranch: true,
                cargoBranch: false,
                postomat: true,
                pudo: false,
            })
        ).toEqual([DIVISION_CATEGORIES.POST_BRANCH, DIVISION_CATEGORIES.POSTOMAT]);
    });

    it('returns every SDK division category when all checkout filters are disabled', () => {
        expect(render({})).toEqual(Object.values(DIVISION_CATEGORIES));
    });
});
