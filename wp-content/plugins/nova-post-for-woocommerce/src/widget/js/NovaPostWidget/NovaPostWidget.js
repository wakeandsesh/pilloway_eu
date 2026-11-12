/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { DIVISION_CATEGORIES } from '@/constants';
import { useDivisionCategories } from '@/hooks/useDivisionCategories';
import analyticsService from '@/services/analyticsService';
import { getLocale } from '@/utils';

function getDivisionCategoryLabel(divisionCategory) {
    switch (divisionCategory) {
        case DIVISION_CATEGORIES.POSTOMAT:
            return __('Parcel locker', 'nova-post-for-woocommerce');
        case DIVISION_CATEGORIES.CARGO_BRANCH:
            return __('Freight branch', 'nova-post-for-woocommerce');
        case DIVISION_CATEGORIES.PUDO:
            return __('Pick Up Drop Off', 'nova-post-for-woocommerce');
        case DIVISION_CATEGORIES.POST_BRANCH:
            return __('Branch', 'nova-post-for-woocommerce');
        default:
            return divisionCategory || '';
    }
}

/**
 * Render the official Nova Post Division Widget for checkout.
 *
 * @param {Object}      props                  Component props.
 * @param {Object}      props.shippingAddress  Current WooCommerce shipping address.
 * @param {string|null} props.senderCountry    Configured Nova Post sender country.
 * @param {Object}      props.divisionsFilters Enabled division filters.
 * @param {Object|null} props.currentDivision  Persisted current division.
 * @param {Function}    props.onSelect         SDK selection callback.
 * @param {Function}    props.onClear          SDK deselection callback.
 * @param {boolean}     props.isSaving         Whether selection is being persisted.
 * @param {string|null} props.saveError        Selection persistence error.
 * @return {JSX.Element} SDK widget container and persistence notices.
 */
export const NovaPostWidget = ({
    shippingAddress,
    senderCountry,
    divisionsFilters = {},
    currentDivision,
    onSelect,
    onClear,
    isSaving,
    saveError,
}) => {
    const containerRef = useRef(null);
    const widgetRef = useRef(null);
    const onSelectRef = useRef(onSelect);
    const onClearRef = useRef(onClear);
    const initialParamsRef = useRef(null);
    const skipNextParamsUpdateRef = useRef(true);
    const [sdkError, setSdkError] = useState(false);
    const { divisionCategories } = useDivisionCategories(divisionsFilters);
    const locale = getLocale().slice(0, 2).toLowerCase();
    const country = shippingAddress?.country || null;
    const city = shippingAddress?.city || null;
    const divisionId = currentDivision?.id ?? null;
    const SdkWidget =
        typeof window !== 'undefined' ? window.NovaPostWidget?.NovaPostWidget : undefined;
    const divisionAddress = currentDivision?.address || currentDivision?.displayAddress;
    const selectedTitle = currentDivision
        ? [
              getDivisionCategoryLabel(currentDivision.divisionCategory),
              currentDivision.number !== null && currentDivision.number !== undefined
                  ? `#${currentDivision.number}`
                  : null,
              currentDivision.maxWeightPlace !== null &&
              currentDivision.maxWeightPlace !== undefined &&
              currentDivision.maxWeightPlace !== ''
                  ? `(${currentDivision.maxWeightPlace} kg)`
                  : null,
          ]
              .filter(Boolean)
              .join(' ')
        : null;

    initialParamsRef.current = {
        country,
        senderCountry,
        city,
        divisionId,
        divisionCategories,
        locale,
    };

    useEffect(() => {
        onSelectRef.current = onSelect;
    }, [onSelect]);

    useEffect(() => {
        onClearRef.current = onClear;
    }, [onClear]);

    useEffect(() => {
        try {
            analyticsService.viewedWidget(country);
        } catch {}
    }, []);

    const handleSelect = useCallback((division) => {
        onSelectRef.current?.(division);
    }, []);

    const handleClear = useCallback(() => {
        onClearRef.current?.();
    }, []);

    useEffect(() => {
        if (!SdkWidget || !containerRef.current) {
            return undefined;
        }

        let widget;
        skipNextParamsUpdateRef.current = true;

        try {
            widget = new SdkWidget({
                container: containerRef.current,
                variant: 'widget',
                viewMode: 'popup',
                ...initialParamsRef.current,
                onSelect: handleSelect,
                onClear: handleClear,
                onError: () => {
                    setSdkError(true);
                },
            });
            widgetRef.current = widget;
        } catch {
            widget?.destroy?.();
            widgetRef.current = null;
            setSdkError(true);

            return undefined;
        }

        return () => {
            widget.destroy();

            if (widgetRef.current === widget) {
                widgetRef.current = null;
            }
        };
    }, [SdkWidget, handleClear, handleSelect, sdkError]);

    useEffect(() => {
        if (!widgetRef.current) {
            return;
        }

        if (skipNextParamsUpdateRef.current) {
            skipNextParamsUpdateRef.current = false;
            return;
        }

        widgetRef.current.updateParams({
            country,
            senderCountry,
            city,
            divisionId,
            divisionCategories,
            locale,
        });
    }, [city, country, divisionCategories, divisionId, locale, senderCountry]);

    if (!SdkWidget || sdkError) {
        return (
            <div className="nova-post-widget nova-post-widget__error-message">
                {__(
                    'Could not load the Nova Post division widget. Please reload the page.',
                    'nova-post-for-woocommerce'
                )}
            </div>
        );
    }

    return (
        <div className="nova-post-widget">
            <button
                ref={containerRef}
                type="button"
                className="nova-post-widget__trigger"
                aria-busy={isSaving || undefined}
            >
                <span className="nova-post-widget__trigger-logo" aria-hidden="true" />

                {currentDivision ? (
                    <span className="nova-post-widget__trigger-content">
                        <span className="nova-post-widget__trigger-title">{selectedTitle}</span>
                        {divisionAddress && (
                            <span className="nova-post-widget__trigger-address">
                                {divisionAddress}
                            </span>
                        )}
                    </span>
                ) : (
                    <span className="nova-post-widget__trigger-placeholder">
                        {__('Select branch or parcel locker', 'nova-post-for-woocommerce')}
                    </span>
                )}
            </button>

            {isSaving && (
                <small style={{ color: 'var(--color-info)' }}>
                    {__('Saving division', 'nova-post-for-woocommerce')}...
                </small>
            )}

            {saveError && (
                <small style={{ color: 'var(--color-error)' }}>
                    {__('Error saving division. Clear and try again.', 'nova-post-for-woocommerce')}
                </small>
            )}
        </div>
    );
};
