/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { CheckboxControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { FormSection, InfoTooltip } from '../common';
import { createGenericHandler } from '../../utils/fieldHandlers';
import { useSettingsContext } from '../../contexts/SettingsContext';

const DivisionsFilters = () => {
    const { settings, isSaving, updateSetting, saveSettings } = useSettingsContext();
    const handleChange = createGenericHandler(updateSetting);

    return (
        <FormSection
            title={__('Divisions Filters', 'nova-post-for-woocommerce')}
            onSave={saveSettings}
            isSaving={isSaving}
        >
            <div className="flex-center">
                <span style={{ textTransform: 'uppercase', fontSize: '11px', fontWeight: '500' }}>
                    {__('Allowed Divisions Types', 'nova-post-for-woocommerce')}
                </span>
                <InfoTooltip
                    information={__(
                        'Select the branch types to display in the Nova Post widget on the checkout page. Customers will only see the branch types you have checked (Post Branch, Freight branch, Parcel locker, or Pick Up Drop Off).',
                        'nova-post-for-woocommerce'
                    )}
                />
            </div>

            <CheckboxControl
                name="divisions_filter_post_branch"
                checked={settings.divisions_filter_post_branch}
                label={__('Post Branch', 'nova-post-for-woocommerce')}
                onChange={handleChange('divisions_filter_post_branch')}
                __nextHasNoMarginBottom
            />

            <CheckboxControl
                name="divisions_filter_cargo_branch"
                checked={settings.divisions_filter_cargo_branch}
                label={__('Freight branch', 'nova-post-for-woocommerce')}
                onChange={handleChange('divisions_filter_cargo_branch')}
                __nextHasNoMarginBottom
            />

            <CheckboxControl
                name="divisions_filter_postomat"
                checked={settings.divisions_filter_postomat}
                label={__('Parcel locker', 'nova-post-for-woocommerce')}
                onChange={handleChange('divisions_filter_postomat')}
                __nextHasNoMarginBottom
            />

            <CheckboxControl
                name="divisions_filter_pudo"
                checked={settings.divisions_filter_pudo}
                label={__('Pick Up Drop Off', 'nova-post-for-woocommerce')}
                onChange={handleChange('divisions_filter_pudo')}
                __nextHasNoMarginBottom
            />
        </FormSection>
    );
};

export default DivisionsFilters;
