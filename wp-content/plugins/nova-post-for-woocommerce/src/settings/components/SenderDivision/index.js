/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { useCallback, useMemo } from '@wordpress/element';

import SelectDivision from '@/components/SelectDivision';

import { useSettingsContext } from '@/settings/contexts/SettingsContext';
import { useFieldError } from '@/settings/hooks/useFieldError';
import analyticsService from '@/services/analyticsService';

const Division = () => {
    const { settings, updateSetting } = useSettingsContext();

    const divisionError = useFieldError('sender_division_id');

    const presetDivision = useMemo(() => {
        if (!settings.sender_division_name) {
            return null;
        }

        return {
            id: settings.sender_division_id,
            name: settings.sender_division_name,
            address: settings.sender_division_address,
            latitude: settings.sender_division_latitude,
            longitude: settings.sender_division_longitude,
        };
    }, [
        settings.sender_division_address,
        settings.sender_division_id,
        settings.sender_division_latitude,
        settings.sender_division_longitude,
        settings.sender_division_name,
    ]);

    const handleDivisionSelect = useCallback(
        (division) => {
            updateSetting('sender_division_name', division.name);
            updateSetting('sender_division_id', division.id);
            updateSetting('sender_division_address', division.address);
            updateSetting('sender_division_latitude', division.latitude);
            updateSetting('sender_division_longitude', division.longitude);

            analyticsService.branchSelected(division, settings.sender_country);
        },
        [updateSetting, settings.sender_country]
    );

    return (
        <SelectDivision
            countryCode={settings.sender_country}
            city={settings.sender_city}
            type="branch"
            prohibitedSending={false}
            presetDivision={presetDivision}
            onDivisionSelect={handleDivisionSelect}
            inputClasses={divisionError.wrapperClass}
            label={__('Division', 'nova-post-for-woocommerce')}
            placeholder={__('Enter division name', 'nova-post-for-woocommerce')}
            help={divisionError.help}
        />
    );
};

export default Division;
