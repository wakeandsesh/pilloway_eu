/**
 * External dependencies
 */
import { createContext, useContext } from '@wordpress/element';

/**
 * Internal dependencies
 */
import useSettings from '../hooks/useSettings';

/**
 * Context for managing settings data across components
 * Provides centralized access to settings state and operations
 */
const SettingsContext = createContext();

/**
 * Provider component that wraps components needing settings data
 * Should be placed at the root level of the admin interface
 *
 * @param {Object} props          Component props
 * @param {*}      props.children Child components
 * @return {JSX.Element} Provider component
 */
export const SettingsProvider = ({ children }) => {
    const settingsData = useSettings();

    return <SettingsContext.Provider value={settingsData}>{children}</SettingsContext.Provider>;
};

/**
 * @typedef {Object} SettingsContextValue
 * @property {Object}   settings             - Current settings object
 * @property {Object}   fieldErrors          - Map of field name → string[] of error messages from last failed save
 * @property {Function} updateSetting        - Function to update a single setting
 * @property {Function} saveSettings         - Function to save settings to API
 * @property {Function} saveApiConfiguration - Function to save API configuration to dedicated endpoint
 * @property {Function} loadSettings         - Function to reload settings from API
 * @property {boolean}  isLoading            - Whether settings are currently loading
 * @property {boolean}  isSaving             - Whether settings are currently saving
 * @property {Function} clearNotice          - Function to clear current notices
 */

/**
 * Custom hook to consume settings context
 * Provides access to settings state and operations
 *
 * @return {SettingsContextValue} Settings data with state and operations
 */
export const useSettingsContext = () => {
    const context = useContext(SettingsContext);

    if (context === undefined) {
        throw new Error('useSettingsContext must be used within a SettingsProvider');
    }

    return context;
};

export default SettingsContext;
