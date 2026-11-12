/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { CheckboxControl, __experimentalInputControl as InputControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { FormSection, InfoTooltip } from '../common';
import { createGenericHandler } from '../../utils/fieldHandlers';
import { useSettingsContext } from '../../contexts/SettingsContext';
import { useFieldError } from '../../hooks/useFieldError';

const ApiConfiguration = () => {
    const { settings, isSaving, updateSetting, saveApiConfiguration } = useSettingsContext();
    const handleChange = createGenericHandler(updateSetting);
    const apiKeyError = useFieldError('api_key');
    const stageApiKeyError = useFieldError('stage_api_key');

    return (
        <FormSection
            title={__('API Configuration', 'nova-post-for-woocommerce')}
            onSave={saveApiConfiguration}
            isSaving={isSaving}
        >
            <InputControl
                className={apiKeyError.wrapperClass}
                value={settings.api_key}
                name="api_key"
                label={
                    <div className="flex-center">
                        <span>{__('Production API Key', 'nova-post-for-woocommerce')}</span>
                        <InfoTooltip
                            information={__(
                                'Enter your live API key obtained from your NovaPost account. This key is used for creating actual shipments.',
                                'nova-post-for-woocommerce'
                            )}
                        />
                    </div>
                }
                placeholder={__('Enter your Nova Post API key', 'nova-post-for-woocommerce')}
                onChange={handleChange('api_key')}
                __next40pxDefaultSize
                help={apiKeyError.help}
            />

            <CheckboxControl
                name="debug"
                checked={settings.debug}
                label={
                    <div className="flex-center">
                        <span>{__('Testing API', 'nova-post-for-woocommerce')}</span>
                        <InfoTooltip
                            information={__(
                                "Enable to use the NovaPost testing environment. This allows you to test the plugin's functionality without creating real shipments. Remember to disable this mode before launching your store for actual sales.",
                                'nova-post-for-woocommerce'
                            )}
                        />
                    </div>
                }
                help={__('Enable testing mode to use stage API key', 'nova-post-for-woocommerce')}
                onChange={handleChange('debug')}
                __nextHasNoMarginBottom
            />

            {settings.debug && (
                <InputControl
                    className={stageApiKeyError.wrapperClass}
                    value={settings.stage_api_key}
                    name="stage_api_key"
                    label={
                        <div className="flex-center">
                            <span>{__('Stage API Key', 'nova-post-for-woocommerce')}</span>
                            <InfoTooltip
                                information={__(
                                    "Enter your test API key obtained from the NovaPost Sandbox environment. Use it with the 'Testing API' option to safely test the plugin's functionality.",
                                    'nova-post-for-woocommerce'
                                )}
                            />
                        </div>
                    }
                    placeholder={__(
                        'Enter your Stage Nova Post API key',
                        'nova-post-for-woocommerce'
                    )}
                    onChange={handleChange('stage_api_key')}
                    __next40pxDefaultSize
                    help={stageApiKeyError.help}
                />
            )}
        </FormSection>
    );
};

export default ApiConfiguration;
