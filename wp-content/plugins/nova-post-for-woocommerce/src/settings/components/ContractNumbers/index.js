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

const ContractNumbers = () => {
    const { settings, isSaving, updateSetting, saveSettings } = useSettingsContext();
    const handleChange = createGenericHandler(updateSetting);
    const domesticCnError = useFieldError('domestic_cn');
    const internationalCnError = useFieldError('international_cn');

    return (
        <FormSection
            title={__('Contract Numbers', 'nova-post-for-woocommerce')}
            onSave={saveSettings}
            isSaving={isSaving}
        >
            <InputControl
                className={domesticCnError.wrapperClass}
                value={settings.domestic_cn || ''}
                name="domestic_cn"
                label={
                    <div className="flex-center">
                        <span>{__('Domestic Contract Number', 'nova-post-for-woocommerce')}</span>
                        <InfoTooltip
                            information={__(
                                'Enter your contract number for shipments within one country.',
                                'nova-post-for-woocommerce'
                            )}
                            example={'CNPCZ-0000'}
                        />
                    </div>
                }
                placeholder={__('Enter domestic contract number', 'nova-post-for-woocommerce')}
                onChange={handleChange('domestic_cn')}
                __next40pxDefaultSize
                help={domesticCnError.help}
            />

            <InputControl
                className={internationalCnError.wrapperClass}
                value={settings.international_cn || ''}
                name="international_cn"
                label={
                    <div className="flex-center">
                        <span>
                            {__('International Contract Number', 'nova-post-for-woocommerce')}
                        </span>
                        <InfoTooltip
                            information={__(
                                'Enter your contract number for international shipments outside the country of departure and the EU.',
                                'nova-post-for-woocommerce'
                            )}
                            example={'GNPCZ-0000'}
                        />
                    </div>
                }
                placeholder={__('Enter international contract number', 'nova-post-for-woocommerce')}
                onChange={handleChange('international_cn')}
                __next40pxDefaultSize
                help={internationalCnError.help}
            />
        </FormSection>
    );
};

export default ContractNumbers;
