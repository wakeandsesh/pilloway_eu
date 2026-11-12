/**
 * External dependencies
 */
import { useMemo } from '@wordpress/element';
import { ALL_DIVISION_CATEGORIES, DIVISION_CATEGORIES } from '@/constants';

/**
 * Map checkout division filters to SDK division categories.
 *
 * @param {Object} divisionsFilters Checkout division filters.
 * @return {{divisionCategories: string[]}} SDK division categories.
 */
export const useDivisionCategories = (divisionsFilters = {}) => {
    const {
        postBranch = false,
        cargoBranch = false,
        postomat = false,
        pudo = false,
    } = divisionsFilters;

    const divisionCategories = useMemo(() => {
        if (!postBranch && !cargoBranch && !postomat && !pudo) {
            return [...ALL_DIVISION_CATEGORIES];
        }

        return [
            postBranch && DIVISION_CATEGORIES.POST_BRANCH,
            postomat && DIVISION_CATEGORIES.POSTOMAT,
            cargoBranch && DIVISION_CATEGORIES.CARGO_BRANCH,
            pudo && DIVISION_CATEGORIES.PUDO,
        ].filter(Boolean);
    }, [postBranch, cargoBranch, postomat, pudo]);

    return {
        divisionCategories,
    };
};
