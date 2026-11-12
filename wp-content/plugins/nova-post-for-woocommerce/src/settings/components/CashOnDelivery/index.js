/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { __experimentalInputControl as InputControl, ToggleControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { FormSection, InfoTooltip } from '../common';
import { createGenericHandler } from '../../utils/fieldHandlers';
import { useSettingsContext } from '../../contexts/SettingsContext';
import { useFieldError } from '../../hooks/useFieldError';

const CashOnDelivery = () => {
    const { settings, isSaving, updateSetting, saveSettings } = useSettingsContext();
    const handleChange = createGenericHandler(updateSetting);
    const bankAccountIdError = useFieldError('cod_bank_account_id');
    const bankAccountNameError = useFieldError('cod_bank_account_name');

    return (
        <FormSection
            title={__('Cash on Delivery', 'nova-post-for-woocommerce')}
            onSave={saveSettings}
            isSaving={isSaving}
        >
            <ToggleControl
                name="cod_fee_paid_seller"
                __nextHasNoMarginBottom
                label={
                    <div className="flex-center">
                        <span>
                            {__('COD fee is paid by the seller', 'nova-post-for-woocommerce')}
                        </span>
                        <InfoTooltip
                            information={
                                <>
                                    <span>
                                        {__(
                                            'Choose who pays the commission for the cash on delivery service. If off, the commission will be added to the total amount the buyer has to pay. If on, the buyer will only pay for the goods and their delivery.',
                                            'nova-post-for-woocommerce'
                                        )}
                                    </span>
                                    <br />
                                    <strong>
                                        {__(
                                            'This setting must be agreed at the contract level.',
                                            'nova-post-for-woocommerce'
                                        )}
                                    </strong>
                                </>
                            }
                        />
                    </div>
                }
                checked={settings.cod_fee_paid_seller}
                onChange={handleChange('cod_fee_paid_seller')}
            />

            <InputControl
                className={bankAccountIdError.wrapperClass}
                name="cod_bank_account_id"
                label={
                    <div className="flex-center">
                        <span>{__('Bank Account Id', 'nova-post-for-woocommerce')}</span>
                        <InfoTooltip
                            information={__(
                                "Enter your bank account's unique identifier provided by your bank for crediting funds for cash on delivery.",
                                'nova-post-for-woocommerce'
                            )}
                            example={'3449614907'}
                        />
                    </div>
                }
                value={settings.cod_bank_account_id}
                onChange={handleChange('cod_bank_account_id')}
                placeholder={__('Enter your Bank Account Id', 'nova-post-for-woocommerce')}
                __next40pxDefaultSize
                help={bankAccountIdError.help}
            />

            <InputControl
                className={bankAccountNameError.wrapperClass}
                name="cod_bank_account_name"
                label={
                    <div className="flex-center">
                        <span>{__('Bank Account Name or IBAN', 'nova-post-for-woocommerce')}</span>
                        <InfoTooltip
                            information={__(
                                'Enter your bank name or account number in IBAN format to transfer funds for cash on delivery.',
                                'nova-post-for-woocommerce'
                            )}
                            example={'PL54995871000006740000004282341'}
                        />
                    </div>
                }
                value={settings.cod_bank_account_name}
                onChange={handleChange('cod_bank_account_name')}
                placeholder={__('Enter your Bank Account Name', 'nova-post-for-woocommerce')}
                __next40pxDefaultSize
                help={bankAccountNameError.help}
            />
        </FormSection>
    );
};

export default CashOnDelivery;
