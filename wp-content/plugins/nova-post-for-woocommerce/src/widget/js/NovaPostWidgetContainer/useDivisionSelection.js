/**
 * External dependencies
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { DIVISION_CATEGORIES, DIVISION_TYPE } from '@/constants';

const DIVISION_TYPES_BY_CATEGORY = Object.freeze({
    [DIVISION_CATEGORIES.POST_BRANCH]: DIVISION_TYPE.BRANCH,
    [DIVISION_CATEGORIES.CARGO_BRANCH]: DIVISION_TYPE.BRANCH,
    [DIVISION_CATEGORIES.POSTOMAT]: DIVISION_TYPE.POSTMAT,
    [DIVISION_CATEGORIES.PUDO]: DIVISION_TYPE.BRANCH,
});

function getSelectionKey(divisionId) {
    if (divisionId === null || divisionId === undefined) {
        return null;
    }

    return String(divisionId);
}

/**
 * Manage persisted checkout selection around the SDK widget.
 *
 * @param {Object}   params                  Hook parameters.
 * @param {Object}   params.shippingAddress  Current WooCommerce shipping address.
 * @param {Object}   params.divisionData     Persisted division data.
 * @param {Function} params.saveDivisionData Persistence callback.
 * @return {{
 *   currentDivision: (Object|null),
 *   handleChangeDivision: (function(Object): void),
 *   handleClearDivision: (function(): void)
 * }} SDK selection state and handlers.
 */
export function useDivisionSelection({ shippingAddress, divisionData, saveDivisionData }) {
    const persistedDivision = divisionData?.division || null;
    const persistedDivisionId = divisionData?.division_id ?? persistedDivision?.id ?? null;
    const [currentDivision, setCurrentDivision] = useState(persistedDivision);
    const currentSelectionKeyRef = useRef(getSelectionKey(persistedDivisionId));
    const lastUserSelectionKeyRef = useRef(undefined);
    const currentAddressKey = `${shippingAddress.country || ''}\u0000${shippingAddress.city || ''}`;
    const previousAddressKeyRef = useRef(currentAddressKey);

    const handleClearDivision = useCallback(() => {
        if (currentSelectionKeyRef.current === null) {
            return;
        }

        currentSelectionKeyRef.current = null;
        lastUserSelectionKeyRef.current = null;
        setCurrentDivision(null);

        saveDivisionData?.({
            division: null,
            division_id: null,
            division_type: null,
            country: shippingAddress.country || null,
            needRefreshCart: false,
        });
    }, [saveDivisionData, shippingAddress.country]);

    const handleChangeDivision = useCallback(
        (division) => {
            const nextSelectionKey = getSelectionKey(division?.id);

            if (nextSelectionKey === currentSelectionKeyRef.current) {
                return;
            }

            currentSelectionKeyRef.current = nextSelectionKey;
            lastUserSelectionKeyRef.current = nextSelectionKey;
            setCurrentDivision(division);

            saveDivisionData?.({
                division,
                division_id: division?.id ?? null,
                division_type: DIVISION_TYPES_BY_CATEGORY[division?.divisionCategory],
                country: division?.countryCode || shippingAddress.country || null,
                needRefreshCart: true,
            });
        },
        [saveDivisionData, shippingAddress.country]
    );

    useEffect(() => {
        const persistedSelectionKey = getSelectionKey(persistedDivisionId);

        if (
            lastUserSelectionKeyRef.current !== undefined &&
            persistedSelectionKey !== lastUserSelectionKeyRef.current
        ) {
            return;
        }

        currentSelectionKeyRef.current = persistedSelectionKey;
        setCurrentDivision(persistedDivision);
    }, [persistedDivision, persistedDivisionId]);

    useEffect(() => {
        const addressChanged = previousAddressKeyRef.current !== currentAddressKey;
        previousAddressKeyRef.current = currentAddressKey;

        if (addressChanged && currentDivision) {
            handleClearDivision();
        }
    }, [currentAddressKey, currentDivision, handleClearDivision]);

    return {
        currentDivision,
        handleChangeDivision,
        handleClearDivision,
    };
}
