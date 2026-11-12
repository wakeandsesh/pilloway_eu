/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { __experimentalInputControl as InputControl, RadioControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { FormSection, InfoTooltip } from '../common';
import { createGenericHandler } from '../../utils/fieldHandlers';
import { useSettingsContext } from '../../contexts/SettingsContext';
import { useFieldError } from '../../hooks/useFieldError';

import styles from './styles.module.scss';

import CustomBoxManager from './CustomBoxManager';

const PackagingSettings = () => {
    const { settings, isSaving, updateSetting, saveSettings } = useSettingsContext();
    const handleChange = createGenericHandler(updateSetting);

    const packagingTypeError = useFieldError('packaging_type');
    const defaultLengthError = useFieldError('default_length');
    const defaultWidthError = useFieldError('default_width');
    const defaultHeightError = useFieldError('default_height');

    return (
        <FormSection
            title={__('Packaging Settings', 'nova-post-for-woocommerce')}
            onSave={saveSettings}
            isSaving={isSaving}
        >
            <RadioControl
                className={packagingTypeError.wrapperClass}
                label={
                    <div className="flex-center">
                        <span>{__('Packaging type', 'nova-post-for-woocommerce')}</span>
                        <InfoTooltip
                            information={__(
                                'Nova Post boxes uses predefined standard sizes with optimal packing. One box uses fixed dimensions for entire order. Custom boxes allows you to define up to 10 box sizes.',
                                'nova-post-for-woocommerce'
                            )}
                        />
                    </div>
                }
                selected={settings.packaging_type}
                options={[
                    {
                        label: (
                            <div className="flex-center">
                                <span>
                                    {__('Use Nova Post boxes', 'nova-post-for-woocommerce')}
                                </span>
                            </div>
                        ),
                        value: 'novapost_boxes',
                    },
                    {
                        label: __('One box per order', 'nova-post-for-woocommerce'),
                        value: 'one_box',
                    },
                    {
                        label: (
                            <div className="flex-center">
                                <span>{__('Use custom boxes', 'nova-post-for-woocommerce')}</span>
                            </div>
                        ),
                        value: 'custom_boxes',
                    },
                ]}
                onChange={handleChange('packaging_type')}
                help={packagingTypeError.help}
            />

            {settings.packaging_type === 'one_box' && (
                <>
                    <div className={styles.dimensionsGrid}>
                        <InputControl
                            className={defaultLengthError.wrapperClass}
                            name="default_length"
                            label={__('Length (cm)', 'nova-post-for-woocommerce')}
                            value={settings.default_length || ''}
                            onChange={handleChange('default_length')}
                            type="number"
                            step="1"
                            min="0"
                            __next40pxDefaultSize
                            help={defaultLengthError.help}
                        />

                        <InputControl
                            className={defaultWidthError.wrapperClass}
                            name="default_width"
                            label={__('Width (cm)', 'nova-post-for-woocommerce')}
                            value={settings.default_width || ''}
                            onChange={handleChange('default_width')}
                            type="number"
                            step="1"
                            min="0"
                            __next40pxDefaultSize
                            help={defaultWidthError.help}
                        />

                        <InputControl
                            className={defaultHeightError.wrapperClass}
                            name="default_height"
                            label={__('Height (cm)', 'nova-post-for-woocommerce')}
                            value={settings.default_height || ''}
                            onChange={handleChange('default_height')}
                            type="number"
                            step="1"
                            min="0"
                            __next40pxDefaultSize
                            help={defaultHeightError.help}
                        />
                    </div>
                </>
            )}

            {settings.packaging_type === 'custom_boxes' && (
                <>
                    <CustomBoxManager
                        value={settings.np_custom_boxes}
                        onChange={handleChange('np_custom_boxes')}
                    />
                </>
            )}
        </FormSection>
    );
};

export default PackagingSettings;
