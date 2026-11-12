/**
 * External dependencies
 */
import { createContext, useContext } from '@wordpress/element';

/**
 * Internal dependencies
 */
import useCountries from '../hooks/useCountries';

/**
 * Context for managing countries data across components
 */
const CountriesContext = createContext();

/**
 * Provider component that wraps components needing countries data
 *
 * @param {Object} props          Component props
 * @param {*}      props.children Child components
 * @return {JSX.Element} Provider component
 */
export const CountriesProvider = ({ children }) => {
    const countriesData = useCountries();

    return <CountriesContext.Provider value={countriesData}>{children}</CountriesContext.Provider>;
};

/**
 * Custom hook to consume countries context
 *
 * @return {Object} Countries data with loading state and error
 */
export const useCountriesContext = () => {
    const context = useContext(CountriesContext);

    if (context === undefined) {
        throw new Error('useCountriesContext must be used within a CountriesProvider');
    }

    return context;
};

export default CountriesContext;
