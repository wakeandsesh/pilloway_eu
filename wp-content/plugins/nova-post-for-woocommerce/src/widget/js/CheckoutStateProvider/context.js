/**
 * External dependencies
 */
import { createContext, useMemo, useReducer } from '@wordpress/element';

const emptyShippingAddress = {
    country: '',
    state: '',
    postcode: '',
    city: '',
    address_1: '',
    address_2: '',
};

const emptyDivisionData = {
    division: null,
    division_id: null,
    division_type: null,
};

const emptyDivisionFilters = {
    postomat: false,
    postBranch: false,
    cargoBranch: false,
    pudo: false,
};

const emptyErrorCalculation = {
    division: false,
    address: false,
};

const initialState = {
    checkoutMode: null,
    selectedShippingRateId: null,
    selectedShippingMethodId: null,
    isNovaPostDivisionActive: false,
    shippingAddress: emptyShippingAddress,
    senderCountry: null,
    divisionData: emptyDivisionData,
    divisionFilters: emptyDivisionFilters,
    errorCalculation: emptyErrorCalculation,
    isLoading: false,
    fetchError: null,
    lastUpdatedAt: null,
};

export const CheckoutStateContext = createContext(null);

function normalizePayload(payload = {}) {
    return {
        checkoutMode: payload.checkoutMode ?? null,
        selectedShippingRateId: payload.selectedShippingRateId ?? null,
        selectedShippingMethodId: payload.selectedShippingMethodId ?? null,
        isNovaPostDivisionActive: Boolean(payload.isNovaPostDivisionActive),
        senderCountry:
            typeof payload.senderCountry === 'string' && payload.senderCountry
                ? payload.senderCountry
                : null,
        shippingAddress: {
            ...emptyShippingAddress,
            ...(payload.shippingAddress || {}),
        },
        divisionData: {
            ...emptyDivisionData,
            ...(payload.divisionData || {}),
        },
        divisionFilters: {
            ...emptyDivisionFilters,
            ...(payload.divisionFilters || {}),
        },
        errorCalculation: {
            ...emptyErrorCalculation,
            ...(payload.errorCalculation || {}),
        },
        lastUpdatedAt: Date.now(),
    };
}

function reducer(state, action) {
    switch (action.type) {
        case 'HYDRATE':
            return {
                ...state,
                ...normalizePayload(action.payload),
            };
        case 'UPDATE_DIVISION_DATA':
            return {
                ...state,
                divisionData: {
                    ...state.divisionData,
                    ...action.payload,
                },
                lastUpdatedAt: Date.now(),
            };
        case 'SET_LOADING':
            return {
                ...state,
                isLoading: action.payload,
            };
        case 'SET_FETCH_ERROR':
            return {
                ...state,
                fetchError: action.payload,
            };
        default:
            return state;
    }
}

export const CheckoutStateProvider = ({ children }) => {
    const [state, dispatch] = useReducer(reducer, initialState);

    const actions = useMemo(
        () => ({
            hydrateFromBlocks(payload) {
                dispatch({ type: 'HYDRATE', payload: { ...payload, checkoutMode: 'blocks' } });
            },
            hydrateFromClassic(payload) {
                dispatch({ type: 'HYDRATE', payload: { ...payload, checkoutMode: 'classic' } });
            },
            updateDivisionData(payload) {
                dispatch({ type: 'UPDATE_DIVISION_DATA', payload });
            },
            setLoading(isLoading) {
                dispatch({ type: 'SET_LOADING', payload: Boolean(isLoading) });
            },
            setFetchError(error) {
                dispatch({ type: 'SET_FETCH_ERROR', payload: error });
            },
        }),
        []
    );

    const value = useMemo(() => ({ state, actions }), [actions, state]);

    return <CheckoutStateContext.Provider value={value}>{children}</CheckoutStateContext.Provider>;
};
