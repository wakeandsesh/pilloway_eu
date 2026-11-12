/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { __experimentalInputControl as InputControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { FormSection, InfoTooltip } from '../common';
import { createGenericHandler } from '../../utils/fieldHandlers';
import { useSettingsContext } from '../../contexts/SettingsContext';
import { useFieldError } from '../../hooks/useFieldError';

import styles from './styles.module.scss';

const ProductInformation = () => {
    const { settings, isSaving, updateSetting, saveSettings } = useSettingsContext();
    const handleChange = createGenericHandler(updateSetting);

    const defaultWeightError = useFieldError('default_weight');
    const defaultLengthProductError = useFieldError('default_length_product');
    const defaultWidthProductError = useFieldError('default_width_product');
    const defaultHeightProductError = useFieldError('default_height_product');
    const defaultHsTariffCodeError = useFieldError('default_hs_tariff_code');

    return (
        <FormSection
            title={__('Product Information', 'nova-post-for-woocommerce')}
            onSave={saveSettings}
            isSaving={isSaving}
        >
            <InputControl
                className={defaultWeightError.wrapperClass}
                name="default_weight"
                label={
                    <div className={'flex-center'}>
                        {__('Default product weight (kg)', 'nova-post-for-woocommerce')}
                        <InfoTooltip
                            information={__(
                                "This weight will be used for all products that do not have the 'Weight' field filled in on the product page.",
                                'nova-post-for-woocommerce'
                            )}
                        />
                    </div>
                }
                value={settings.default_weight || ''}
                onChange={handleChange('default_weight')}
                type="number"
                step="1"
                min="0"
                __next40pxDefaultSize
                help={defaultWeightError.help}
            />

            <div className={styles.subtitle}>
                <div className={'flex-center'}>
                    <h4>{__('Default product dimensions', 'nova-post-for-woocommerce')}</h4>
                    <InfoTooltip
                        information={__(
                            'Dimensions for each product are first taken from its WooCommerce settings. If they are missing, the default dimensions will be used for the shipping calculation.',
                            'nova-post-for-woocommerce'
                        )}
                    />
                </div>
            </div>
            <div className={styles.dimensionsGrid}>
                <InputControl
                    className={defaultLengthProductError.wrapperClass}
                    name="default_length_product"
                    label={__('Length (cm)', 'nova-post-for-woocommerce')}
                    value={settings.default_length_product || ''}
                    onChange={handleChange('default_length_product')}
                    type="number"
                    step="1"
                    min="0"
                    __next40pxDefaultSize
                    help={defaultLengthProductError.help}
                />

                <InputControl
                    className={defaultWidthProductError.wrapperClass}
                    name="default_width_product"
                    label={__('Width (cm)', 'nova-post-for-woocommerce')}
                    value={settings.default_width_product || ''}
                    onChange={handleChange('default_width_product')}
                    type="number"
                    step="1"
                    min="0"
                    __next40pxDefaultSize
                    help={defaultWidthProductError.help}
                />

                <InputControl
                    className={defaultHeightProductError.wrapperClass}
                    name="default_height_product"
                    label={__('Height (cm)', 'nova-post-for-woocommerce')}
                    value={settings.default_height_product || ''}
                    onChange={handleChange('default_height_product')}
                    type="number"
                    step="1"
                    min="0"
                    __next40pxDefaultSize
                    help={defaultHeightProductError.help}
                />
            </div>

            <InputControl
                className={defaultHsTariffCodeError.wrapperClass}
                name="default_hs_tariff_code"
                label={
                    <div className="flex-center">
                        <span>{__('Default hs code', 'nova-post-for-woocommerce')}</span>
                        <InfoTooltip
                            information={__(
                                'This code will be used for all goods in international shipments that do not have an individual HS-code. ',
                                'nova-post-for-woocommerce'
                            )}
                            example={__(
                                '8517120000 (for mobile phones)',
                                'nova-post-for-woocommerce'
                            )}
                        />
                    </div>
                }
                value={settings.default_hs_tariff_code}
                onChange={handleChange('default_hs_tariff_code')}
                placeholder={__('Enter default HS tariff code', 'nova-post-for-woocommerce')}
                __next40pxDefaultSize
                help={defaultHsTariffCodeError.help}
            />
        </FormSection>
    );
};

export default ProductInformation;
