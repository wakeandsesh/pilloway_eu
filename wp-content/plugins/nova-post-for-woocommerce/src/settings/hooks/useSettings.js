/**
 * External dependencies
 */
import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import apiService from '../../services/ApiService.js';
import noticeService from '../../services/noticeService';

/**
 * Default settings structure
 */
const DEFAULT_SETTINGS = {
    // API Configuration
    api_key: '',
    stage_api_key: '',
    debug: false,

    // Contract Numbers
    domestic_cn: '',
    international_cn: '',

    // Sender Data
    sender_first_name: '',
    sender_last_name: '',
    sender_email: '',
    sender_phone: '',
    sender_country: '',
    sender_city: '',
    sender_postal_code: '',
    sender_street: '',
    sender_building: '',
    sender_division_id: '',
    sender_division_latitude: null,
    sender_division_longitude: null,
    customs_fee_payer: 'Sender',
    delivery_tariff: 'standard',

    // Company Information
    company_tin: '',
    company_name: '',
    eori_code: '',
    ioss: '',

    // Address Configuration
    address_type: '',

    // HS Tariff Code
    default_hs_tariff_code: '',

    // Cash on Delivery
    cod_bank_account_id: '',
    cod_bank_account_name: '',
    cod_fee_paid_seller: false,

    // Carrier Service
    shipping_division_flat_rate: false,
    shipping_division_flat_rate_amount: '',
    shipping_division_free_delivery: false,
    shipping_division_free_delivery_amount: '',
    shipping_address_flat_rate: false,
    shipping_address_flat_rate_amount: '',
    shipping_address_free_delivery: false,
    shipping_address_free_delivery_amount: '',

    // Default Package Parameters
    default_weight: '',
    default_height: '',
    default_width: '',
    default_length: '',
    np_custom_boxes: [],
    packaging_type: 'novapost_boxes',
    default_height_product: '',
    default_width_product: '',
    default_length_product: '',
};

/**
 * Custom hook for managing settings state and operations
 * Centralizes all settings-related logic including API calls
 *
 * @return {Object} Settings state and operations
 */
const useSettings = () => {
    const [settings, setSettings] = useState(DEFAULT_SETTINGS);
    const [fieldErrors, setFieldErrors] = useState({});
    const [isLoading, setIsLoading] = useState(true);
    const [isSaving, setIsSaving] = useState(false);
    const [isApiConfigured, setIsApiConfigured] = useState(false);
    const initialSettings = useRef(DEFAULT_SETTINGS);

    const isSettingsDirty = useCallback(() => {
        return JSON.stringify(settings) !== JSON.stringify(initialSettings.current);
    }, [settings]);

    const isSettingsApiConfigDirty = useCallback(() => {
        const apiConfigSettings = {
            api_key: settings.api_key,
            stage_api_key: settings.stage_api_key,
            debug: settings.debug,
        };

        const initialApiConfigSettings = {
            api_key: initialSettings.current.api_key,
            stage_api_key: initialSettings.current.stage_api_key,
            debug: initialSettings.current.debug,
        };

        return JSON.stringify(apiConfigSettings) !== JSON.stringify(initialApiConfigSettings);
    }, [settings]);

    const handleErrors = (error) => {
        let errors = [];

        if (error?.errors) {
            if (Array.isArray(error.errors)) {
                errors = error.errors;
            } else if (typeof error.errors === 'object') {
                for (const key in error.errors) {
                    const err = error.errors[key];
                    if (Array.isArray(err)) {
                        errors = [...errors, ...err];
                    } else if (typeof err === 'string') {
                        errors.push(err);
                    }
                }
            }
        }

        if (errors.length === 0 && error?.message) {
            errors = [error.message];
        }

        for (const msg of errors) {
            noticeService.showError(msg);
        }

        setFieldErrors(error.errors ?? {});
    };

    /**
     * Load settings from API
     */
    const loadSettings = useCallback(async () => {
        try {
            setIsLoading(true);

            const response = await apiService.get('/settings');

            const settingsData = response.data || response;

            const mergedSettings = {
                ...DEFAULT_SETTINGS,
                ...settingsData,
            };

            setSettings(mergedSettings);
            initialSettings.current = mergedSettings;

            setIsApiConfigured(
                settingsData?.debug ? !!settingsData?.stage_api_key : !!settingsData?.api_key
            );
        } catch (error) {
            handleErrors(error);
            console.error('Error loading settings:', error);
        } finally {
            setIsLoading(false);
        }
    }, []);

    /**
     * Save settings to API
     */
    const saveSettings = useCallback(async () => {
        try {
            setIsSaving(true);
            setFieldErrors({});
            noticeService.clearNotices('error');

            const response = await apiService.post('/settings', settings, true);

            const data = response?.data || response;
            setSettings((prev) => ({ ...prev, ...data }));
            initialSettings.current = { ...initialSettings.current, ...data };

            noticeService.showSuccess(
                response.message || __('Settings saved successfully!', 'nova-post-for-woocommerce')
            );
        } catch (error) {
            handleErrors(error);
            console.error('Error saving settings:', error);
        } finally {
            setIsSaving(false);
        }
    }, [settings]);

    /**
     * Save API configuration (API keys) to dedicated endpoint
     */
    const saveApiConfiguration = useCallback(async () => {
        try {
            setIsSaving(true);
            setFieldErrors({});
            noticeService.clearNotices('error');

            const apiConfigData = {
                api_key: settings.api_key,
                stage_api_key: settings.stage_api_key,
                debug: settings.debug,
            };

            const response = await apiService.post('/settings/api-keys', apiConfigData);

            const data = response?.data || response;
            setSettings((prev) => ({ ...prev, ...data }));
            initialSettings.current = { ...initialSettings.current, ...data };

            noticeService.showSuccess(
                response.message ||
                    __('API configuration saved successfully!', 'nova-post-for-woocommerce')
            );

            const phpAlerts = document.querySelectorAll('.nova-post-alert-api-key-missing');
            phpAlerts.forEach((alert) => alert.remove());

            // Update isApiConfigured state based on the save response
            setIsApiConfigured(data.debug ? !!data.stage_api_key : !!data.api_key);

            return true;
        } catch (error) {
            handleErrors(error);
            console.error('Error saving API configuration:', error);
            return false;
        } finally {
            setIsSaving(false);
        }
    }, [settings.api_key, settings.stage_api_key, settings.debug]);

    /**
     * Update a single setting field
     *
     * @param {string} field - Field name to update
     * @param {any}    value - New value for the field
     */
    const updateSetting = useCallback((field, value) => {
        setSettings((prevSettings) => ({
            ...prevSettings,
            [field]: value,
        }));
        setFieldErrors((prev) => {
            const next = { ...prev };
            delete next[field];
            for (const key of Object.keys(next)) {
                if (key.startsWith(`${field}.`)) {
                    delete next[key];
                }
            }
            return next;
        });
    }, []);

    /**
     * Update multiple settings at once
     *
     * @param {Object} newSettings - Object with settings to update
     */
    const updateSettings = useCallback((newSettings) => {
        setSettings((prevSettings) => ({
            ...prevSettings,
            ...newSettings,
        }));
        setFieldErrors((prev) => {
            const next = { ...prev };
            for (const field of Object.keys(newSettings)) {
                delete next[field];
                for (const key of Object.keys(next)) {
                    if (key.startsWith(`${field}.`)) {
                        delete next[key];
                    }
                }
            }
            return next;
        });
    }, []);

    /**
     * Reset settings to defaults
     */
    const resetSettings = useCallback(() => {
        setSettings(DEFAULT_SETTINGS);
    }, []);

    // Load settings on mount
    useEffect(() => {
        loadSettings();
    }, [loadSettings]);

    return {
        // State
        settings,
        fieldErrors,
        isApiConfigured,
        isLoading,
        isSaving,
        isDirty: isSettingsDirty(),
        isApiConfigDirty: isSettingsApiConfigDirty(),

        // Operations
        updateSetting,
        updateSettings,
        saveSettings,
        saveApiConfiguration,
        loadSettings,
        resetSettings,
    };
};

export default useSettings;
