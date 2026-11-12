/**
 * Internal dependencies
 */
import apiService from './ApiService.js';
import divisionService from './divisionService.js';

jest.mock('./ApiService.js', () => ({
    __esModule: true,
    default: {
        get: jest.fn(),
    },
}));

const baseSearchParams = {
    countryCode: 'PL',
    city: 'Warsaw',
    query: 'Bartycka',
    divisionCategories: ['PostBranch'],
};

describe('divisionService', () => {
    beforeEach(() => {
        apiService.get.mockReset();
        apiService.get.mockResolvedValue({ data: [] });
    });

    it.each(['prohibitedSending', 'prohibitedIssuance'])(
        'preserves false for the %s availability filter',
        async (filterName) => {
            await divisionService.searchDivisions({
                ...baseSearchParams,
                [filterName]: false,
            });

            expect(apiService.get).toHaveBeenCalledWith('/checkout-widget/find-divisions', {
                params: {
                    ...baseSearchParams,
                    [filterName]: false,
                },
            });
        }
    );

    it('omits availability filters when the caller does not provide them', async () => {
        await divisionService.searchDivisions(baseSearchParams);

        expect(apiService.get).toHaveBeenCalledWith('/checkout-widget/find-divisions', {
            params: baseSearchParams,
        });
    });
});
