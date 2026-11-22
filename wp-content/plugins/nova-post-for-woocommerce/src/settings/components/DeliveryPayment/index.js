/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { SelectControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { FormSection, InfoTooltip } from '../common';
import { createGenericHandler } from '../../utils/fieldHandlers';
import { useSettingsContext } from '../../contexts/SettingsContext';
import { useFieldError } from '../../hooks/useFieldError';

/**
 * Delivery payment settings section.
 *
 * @return {JSX.Element} Delivery payment settings.
 */
const DeliveryPayment = () => {
    const { settings, isSaving, updateSetting, saveSettings } = useSettingsContext();
    const handleChange = createGenericHandler(updateSetting);
    const deliveryPayerError = useFieldError('delivery_payer');

    return (
        <FormSection
            title={__('Delivery payment', 'nova-post-for-woocommerce')}
            onSave={saveSettings}
            isSaving={isSaving}
        >
            <SelectControl
                className={deliveryPayerError.wrapperClass}
                name="delivery_payer"
                label={
                    <div className="flex-center">
                        <span>{__('Delivery payer', 'nova-post-for-woocommerce')}</span>
                        <InfoTooltip
                            information={[
                                __(
                                    'Choose who pays for Nova Post delivery. This applies to all shipments, including prepaid orders. If you select Recipient, both Nova Post shipping methods are shown at checkout with zero cost and a note that delivery is paid on receipt. Order totals in WooCommerce will not include delivery for these orders.',
                                    'nova-post-for-woocommerce'
                                ),
                                __(
                                    'If the recipient refuses the parcel or does not collect it, you pay for delivery in both directions. The customer pays the Nova Post tariff at the public price list, and your contract discounts do not apply to it.',
                                    'nova-post-for-woocommerce'
                                ),
                            ].join('\n\n')}
                        />
                    </div>
                }
                value={settings.delivery_payer || 'Sender'}
                options={[
                    {
                        label: __('Sender', 'nova-post-for-woocommerce'),
                        value: 'Sender',
                    },
                    {
                        label: __('Recipient', 'nova-post-for-woocommerce'),
                        value: 'Recipient',
                    },
                ]}
                onChange={handleChange('delivery_payer')}
                __next40pxDefaultSize
                __nextHasNoMarginBottom
                help={deliveryPayerError.help}
            />
        </FormSection>
    );
};

export default DeliveryPayment;
