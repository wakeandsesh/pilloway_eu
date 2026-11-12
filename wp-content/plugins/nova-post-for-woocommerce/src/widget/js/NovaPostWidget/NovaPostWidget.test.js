/**
 * External dependencies
 */
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { DIVISION_CATEGORIES } from '@/constants';
import analyticsService from '@/services/analyticsService';

/**
 * Internal dependencies
 */
import { NovaPostWidget } from './NovaPostWidget';

jest.mock('@wordpress/api-fetch', () => jest.fn(), { virtual: true });
jest.mock('@wordpress/url', () => ({ addQueryArgs: jest.fn() }), { virtual: true });
jest.mock('@/services/analyticsService', () => ({
    __esModule: true,
    default: {
        viewedWidget: jest.fn(),
        branchSelected: jest.fn(),
    },
}));

global.IS_REACT_ACT_ENVIRONMENT = true;

const division = {
    id: 123,
    name: 'Branch 1',
    divisionCategory: DIVISION_CATEGORIES.POST_BRANCH,
    number: 12,
    maxWeightPlace: 100,
    address: '24/26 Bartycka Street, Warsaw',
    displayAddress: 'Fallback branch address',
};

const postomat = {
    id: 456,
    name: 'Parcel locker 2',
    divisionCategory: DIVISION_CATEGORIES.POSTOMAT,
    number: 2,
    maxWeightPlace: 20,
    address: '7 Khreshchatyk Street, Kyiv',
    countryCode: 'UA',
};

describe('NovaPostWidget', () => {
    let container;
    let root;
    let sdkInstance;
    let SdkWidget;

    beforeEach(() => {
        container = document.createElement('div');
        root = createRoot(container);
        sdkInstance = {
            show: jest.fn(),
            updateParams: jest.fn(),
            destroy: jest.fn(),
        };
        SdkWidget = jest.fn(() => sdkInstance);
        window.NovaPostWidget = { NovaPostWidget: SdkWidget };
        window.navigator.sendBeacon = jest.fn();
        analyticsService.viewedWidget.mockReset();
        analyticsService.branchSelected.mockReset();
    });

    afterEach(() => {
        act(() => root.unmount());
        delete window.NovaPostWidget;
    });

    function render(props = {}) {
        // React DOM roots require act; Testing Library is not used in this test.
        // eslint-disable-next-line testing-library/no-unnecessary-act
        act(() => {
            root.render(
                <NovaPostWidget
                    shippingAddress={{ country: 'UA', city: 'Kyiv' }}
                    senderCountry="PL"
                    divisionsFilters={{ postBranch: true, postomat: true }}
                    currentDivision={division}
                    onSelect={jest.fn()}
                    onClear={jest.fn()}
                    {...props}
                />
            );
        });
    }

    it('passes its native trigger button to the popup SDK without opening it from React', () => {
        render();

        const trigger = container.querySelector('button.nova-post-widget__trigger');

        expect(trigger).not.toBeNull();
        expect(SdkWidget).toHaveBeenCalledTimes(1);
        expect(SdkWidget).toHaveBeenCalledWith(
            expect.objectContaining({
                container: trigger,
                variant: 'widget',
                viewMode: 'popup',
                country: 'UA',
                senderCountry: 'PL',
                city: 'Kyiv',
                divisionId: 123,
                divisionCategories: [DIVISION_CATEGORIES.POST_BRANCH, DIVISION_CATEGORIES.POSTOMAT],
                locale: 'en',
            })
        );

        act(() => trigger.click());

        expect(sdkInstance.show).not.toHaveBeenCalled();
        expect(container.querySelector('[role="tablist"]')).toBeNull();
        expect(container.querySelector('[role="combobox"]')).toBeNull();
        expect(container.querySelector('.nova-post-widget__map-button')).toBeNull();
    });

    it('keeps the same trigger element while projecting selected and unselected content', () => {
        render({ currentDivision: null });

        const trigger = container.querySelector('button.nova-post-widget__trigger');
        const logo = trigger.querySelector('.nova-post-widget__trigger-logo');

        expect(trigger.textContent).toContain('Select branch or parcel locker');
        expect(logo.getAttribute('aria-hidden')).toBe('true');

        render();

        expect(container.querySelector('button.nova-post-widget__trigger')).toBe(trigger);
        expect(trigger.textContent).toContain('Branch #12 (100 kg)');
        expect(trigger.textContent).toContain('24/26 Bartycka Street, Warsaw');
        expect(trigger.textContent).not.toContain('Select branch or parcel locker');
    });

    it('uses displayAddress when a selected division has no address', () => {
        render({ currentDivision: { ...division, address: '' } });

        expect(container.querySelector('button').textContent).toContain('Fallback branch address');
    });

    it('marks saving state as busy without disabling the SDK focus target', () => {
        render({ isSaving: true });

        const trigger = container.querySelector('button.nova-post-widget__trigger');

        expect(trigger.getAttribute('aria-busy')).toBe('true');
        expect(trigger.disabled).toBe(false);
        expect(container.textContent).toContain('Saving division');
    });

    it('forwards SDK select and clear callbacks to the checkout container', () => {
        const onSelect = jest.fn();
        const onClear = jest.fn();

        render({ onSelect, onClear });

        const config = SdkWidget.mock.calls[0]?.[0];
        expect(config).toBeDefined();
        if (!config) {
            return;
        }

        act(() => config.onSelect(division));
        act(() => config.onClear());

        expect(onSelect).toHaveBeenCalledWith(division);
        expect(onClear).toHaveBeenCalledTimes(1);
    });

    it('tracks a view once per mount and a confirmed selection once per selected id', () => {
        const onSelect = jest.fn();
        const onClear = jest.fn();

        render({ currentDivision: division, onSelect, onClear });
        render({
            shippingAddress: { country: 'PL', city: 'Warsaw' },
            currentDivision: division,
            onSelect,
            onClear,
        });

        const config = SdkWidget.mock.calls[0][0];

        act(() => config.onSelect(division));
        act(() => config.onSelect(postomat));
        act(() => config.onSelect(postomat));
        act(() => config.onClear());
        act(() => config.onSelect(postomat));

        expect(analyticsService.viewedWidget).toHaveBeenCalledTimes(1);
        expect(analyticsService.viewedWidget).toHaveBeenCalledWith('UA');
        expect(onSelect).toHaveBeenCalledTimes(4);
    });

    it('continues the persistence callback when selection analytics throws', () => {
        const onSelect = jest.fn();
        analyticsService.branchSelected.mockImplementationOnce(() => {
            throw new Error('Analytics unavailable');
        });

        render({ currentDivision: null, onSelect });

        const config = SdkWidget.mock.calls[0][0];
        act(() => config.onSelect(postomat));

        expect(onSelect).toHaveBeenCalledWith(postomat);
    });

    it('updates SDK params and clears its selection without recreating the widget', () => {
        render();
        render({
            shippingAddress: { country: 'PL', city: 'Warsaw' },
            senderCountry: 'DE',
            currentDivision: null,
            divisionsFilters: { pudo: true },
        });

        expect(SdkWidget).toHaveBeenCalledTimes(1);
        expect(sdkInstance.updateParams).toHaveBeenLastCalledWith(
            expect.objectContaining({
                country: 'PL',
                senderCountry: 'DE',
                city: 'Warsaw',
                divisionId: null,
                divisionCategories: [DIVISION_CATEGORIES.PUDO],
            })
        );
    });

    it('passes a missing sender country as null without using the recipient country', () => {
        render({ senderCountry: null });

        expect(SdkWidget).toHaveBeenCalledWith(
            expect.objectContaining({
                country: 'UA',
                senderCountry: null,
            })
        );
    });

    it('renders a fallback message when the SDK is unavailable', () => {
        delete window.NovaPostWidget;

        render();

        expect(container.textContent).toContain(
            'Could not load the Nova Post division widget. Please reload the page.'
        );
        expect(SdkWidget).not.toHaveBeenCalled();
    });

    it('destroys the SDK instance on unmount', () => {
        render();

        act(() => root.unmount());

        expect(sdkInstance.destroy).toHaveBeenCalledTimes(1);
        root = createRoot(container);
    });
});
