/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import {
    SelectControl,
    ToggleControl,
    __experimentalInputControl as InputControl,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import { FormSection, InfoTooltip } from '../common';
import { createGenericHandler } from '../../utils/fieldHandlers';
import { useSettingsContext } from '../../contexts/SettingsContext';
import { useFieldError } from '../../hooks/useFieldError';

const COD_CURRENCIES = ['CZK', 'EUR', 'GBP', 'HUF', 'MDL', 'PLN', 'RON', 'UAH', 'USD', 'CAD'];

const CashOnDelivery = () => {
    const { settings, isSaving, updateSetting, saveSettings } = useSettingsContext();
    const handleChange = createGenericHandler(updateSetting);
    const bankAccountCurrencyError = useFieldError('cod_bank_account_currency');
    const bankAccountIdError = useFieldError('cod_bank_account_id');
    const bankAccountNameError = useFieldError('cod_bank_account_name');

    return (
        <FormSection
            title={__('Cash on Delivery', 'nova-post-for-woocommerce')}
            tooltip={[
                __(
                    'Shipments with cash on delivery cannot be created to Pick Up Drop Off points, because they do not accept payments on behalf of Nova Post. If you use cash on delivery, uncheck Pick Up Drop Off in Divisions Filters. Prepaid orders to these points work without restrictions.',
                    'nova-post-for-woocommerce'
                ),
                __(
                    'Parcel lockers work with cash on delivery: the customer pays online through a link from Nova Post before collecting the parcel.',
                    'nova-post-for-woocommerce'
                ),
            ].join('\n\n')}
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
                            information={__(
                                'Choose who pays the Nova Post commission for the cash on delivery service. The commission is charged in addition to the COD amount and shown to the payer separately. If off, the recipient pays the commission. If on, you pay it.',
                                'nova-post-for-woocommerce'
                            )}
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
                                'The tax identification number of your company registered with Nova Post.',
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
                        <span>{__('Bank Account Name (IBAN)', 'nova-post-for-woocommerce')}</span>
                        <InfoTooltip
                            information={__(
                                'Your bank account number in IBAN format. It must be linked to your company profile at Nova Post, otherwise the shipment will not be created.',
                                'nova-post-for-woocommerce'
                            )}
                            example={'PL54995871000006740000004282341'}
                        />
                    </div>
                }
                value={settings.cod_bank_account_name}
                onChange={handleChange('cod_bank_account_name')}
                placeholder={__('Enter your IBAN', 'nova-post-for-woocommerce')}
                __next40pxDefaultSize
                help={bankAccountNameError.help}
            />

            <SelectControl
                className={bankAccountCurrencyError.wrapperClass}
                name="cod_bank_account_currency"
                label={
                    <div className="flex-center">
                        <span>{__('Bank Account Currency', 'nova-post-for-woocommerce')}</span>
                        <InfoTooltip
                            information={__(
                                'Select the currency of the bank account (IBAN) specified in your cash on delivery contract with Nova Post. If your contract has several accounts, select the currency of the IBAN entered above. Nova Post does not check the currency against your contract when creating the shipment, so you are responsible for selecting the correct one. If the order currency is different, the cash on delivery amount is converted to this currency at the Nova Post exchange rate.',
                                'nova-post-for-woocommerce'
                            )}
                        />
                    </div>
                }
                value={settings.cod_bank_account_currency || ''}
                options={[
                    { label: __('Select currency', 'nova-post-for-woocommerce'), value: '' },
                    ...COD_CURRENCIES.map((currency) => ({ label: currency, value: currency })),
                ]}
                onChange={handleChange('cod_bank_account_currency')}
                __next40pxDefaultSize
                help={bankAccountCurrencyError.help}
            />
        </FormSection>
    );
};

export default CashOnDelivery;
