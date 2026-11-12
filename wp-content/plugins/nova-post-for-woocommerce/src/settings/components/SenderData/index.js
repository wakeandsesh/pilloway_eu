/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';
import {
    ComboboxControl,
    SelectControl,
    __experimentalInputControl as InputControl,
    TabPanel,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import { FormSection, InfoTooltip } from '../common';
import { createGenericHandler } from '../../utils/fieldHandlers';
import { useSettingsContext } from '../../contexts/SettingsContext';
import { useCountriesContext } from '../../contexts/CountriesContext';
import { useFieldError } from '../../hooks/useFieldError';
import Address from '../SenderAddress';
import Division from '../SenderDivision';
import styles from './style.module.scss';

const SenderData = ({ className }) => {
    const { settings, isSaving, updateSetting, saveSettings } = useSettingsContext();
    const { countries, isLoading: isLoadingCountries, error } = useCountriesContext();
    const handleChange = createGenericHandler(updateSetting);

    const firstNameError = useFieldError('sender_first_name');
    const lastNameError = useFieldError('sender_last_name');
    const emailError = useFieldError('sender_email');
    const phoneError = useFieldError('sender_phone');
    const companyTinError = useFieldError('company_tin');
    const companyNameError = useFieldError('company_name');
    const eoriCodeError = useFieldError('eori_code');
    const iossError = useFieldError('ioss');
    const countryError = useFieldError('sender_country');
    const senderCityError = useFieldError('sender_city');
    const customsFeePayerError = useFieldError('customs_fee_payer');
    const deliveryTariffError = useFieldError('delivery_tariff');

    const isUkraineSender = settings.sender_country === 'UA';
    const isRecipientCustomsFeePayer = settings.customs_fee_payer === 'Recipient';
    const deliveryTariff = isRecipientCustomsFeePayer
        ? 'express'
        : settings.delivery_tariff || 'standard';

    useEffect(() => {
        if (
            isUkraineSender &&
            isRecipientCustomsFeePayer &&
            settings.delivery_tariff !== 'express'
        ) {
            updateSetting('delivery_tariff', 'express');
        }
    }, [isUkraineSender, isRecipientCustomsFeePayer, settings.delivery_tariff, updateSetting]);

    return (
        <FormSection
            className={className}
            title={__('Sender Data', 'nova-post-for-woocommerce')}
            onSave={saveSettings}
            isSaving={isSaving}
        >
            <InputControl
                className={firstNameError.wrapperClass}
                value={settings.sender_first_name || ''}
                name="sender_first_name"
                label={__('First Name', 'nova-post-for-woocommerce')}
                placeholder={__('Enter first name', 'nova-post-for-woocommerce')}
                onChange={handleChange('sender_first_name')}
                __next40pxDefaultSize
                help={firstNameError.help}
            />

            <InputControl
                className={lastNameError.wrapperClass}
                value={settings.sender_last_name || ''}
                name="sender_last_name"
                label={__('Last Name', 'nova-post-for-woocommerce')}
                placeholder={__('Enter last name', 'nova-post-for-woocommerce')}
                onChange={handleChange('sender_last_name')}
                __next40pxDefaultSize
                help={lastNameError.help}
            />

            <InputControl
                className={emailError.wrapperClass}
                value={settings.sender_email || ''}
                name="sender_email"
                label={__('Email', 'nova-post-for-woocommerce')}
                placeholder={__('Enter email address', 'nova-post-for-woocommerce')}
                type="email"
                onChange={handleChange('sender_email')}
                __next40pxDefaultSize
                help={emailError.help}
            />

            <InputControl
                className={phoneError.wrapperClass}
                value={settings.sender_phone || ''}
                name="sender_phone"
                label={__('Phone', 'nova-post-for-woocommerce')}
                placeholder={__('Enter phone number', 'nova-post-for-woocommerce')}
                onChange={handleChange('sender_phone')}
                __next40pxDefaultSize
                help={phoneError.help}
            />

            <InputControl
                className={companyTinError.wrapperClass}
                name="company_tin"
                label={
                    <div className="flex-center">
                        <span>{__('Company TIN', 'nova-post-for-woocommerce')}</span>
                        <InfoTooltip
                            information={__(
                                "Enter your company's Taxpayer Identification Number.",
                                'nova-post-for-woocommerce'
                            )}
                        />
                    </div>
                }
                value={settings.company_tin || ''}
                onChange={handleChange('company_tin')}
                placeholder={__('Enter company TIN', 'nova-post-for-woocommerce')}
                __next40pxDefaultSize
                help={companyTinError.help}
            />

            <InputControl
                className={companyNameError.wrapperClass}
                name="company_name"
                label={
                    <div className="flex-center">
                        <span>{__('Company Name', 'nova-post-for-woocommerce')}</span>
                        <InfoTooltip
                            information={__(
                                'Enter the full legal name of your company as specified in the registration documents.',
                                'nova-post-for-woocommerce'
                            )}
                        />
                    </div>
                }
                value={settings.company_name || ''}
                onChange={handleChange('company_name')}
                placeholder={__('Enter company name', 'nova-post-for-woocommerce')}
                __next40pxDefaultSize
                help={companyNameError.help}
            />

            <InputControl
                className={eoriCodeError.wrapperClass}
                name="eori_code"
                label={
                    <div className="flex-center">
                        <span>{__('EORI Code', 'nova-post-for-woocommerce')}</span>
                        <InfoTooltip
                            information={__(
                                'Enter your EORI (Economic Operators Registration and Identification) number. It is mandatory for all companies engaged in importing or exporting goods within the European Union.',
                                'nova-post-for-woocommerce'
                            )}
                        />
                    </div>
                }
                value={settings.eori_code || ''}
                onChange={handleChange('eori_code')}
                placeholder={__('Enter EORI code', 'nova-post-for-woocommerce')}
                __next40pxDefaultSize
                help={eoriCodeError.help}
            />

            <InputControl
                className={iossError.wrapperClass}
                name="ioss"
                label={
                    <div className="flex-center">
                        <span>{__('IOSS', 'nova-post-for-woocommerce')}</span>
                        <InfoTooltip
                            information={__(
                                'Enter your IOSS (Import One-Stop Shop) number if you are registered in this system. It is used to simplify VAT payments on sales of goods up to 150 EUR to buyers in the EU.',
                                'nova-post-for-woocommerce'
                            )}
                        />
                    </div>
                }
                value={settings.ioss || ''}
                onChange={handleChange('ioss')}
                placeholder={__('Enter IOSS', 'nova-post-for-woocommerce')}
                __next40pxDefaultSize
                help={iossError.help}
            />

            <div className={styles.departureMethodContainer}>
                <div className={styles.departureMethodHeader}>
                    <span className={styles.departureMethodLabel}>
                        {__('Shipment Departure Method', 'nova-post-for-woocommerce')}
                    </span>
                    <InfoTooltip
                        information={__(
                            'Configure your shipment departure method. Fill in the address fields if you want a courier to pick up shipments from your address (Pickup service). Select a division in the widget if you will bring the parcels yourself.',
                            'nova-post-for-woocommerce'
                        )}
                    />
                </div>
                <TabPanel
                    tabs={[
                        { name: 'address', title: __('Address', 'nova-post-for-woocommerce') },
                        { name: 'division', title: __('Division', 'nova-post-for-woocommerce') },
                    ]}
                    initialTabName={settings.address_type || 'address'}
                    onSelect={handleChange('address_type')}
                >
                    {() => {}}
                </TabPanel>
            </div>

            <ComboboxControl
                className={countryError.wrapperClass}
                __next40pxDefaultSize
                __nextHasNoMarginBottom
                name="country"
                label={__('Country', 'nova-post-for-woocommerce')}
                value={settings.sender_country || ''}
                isLoading={isLoadingCountries}
                expandOnFocus={false}
                options={countries}
                onChange={handleChange('sender_country')}
                placeholder={
                    error
                        ? __('Error loading countries', 'nova-post-for-woocommerce')
                        : __('Enter Country', 'nova-post-for-woocommerce')
                }
                help={countryError.help}
            />

            <InputControl
                className={senderCityError.wrapperClass}
                name="sender_city"
                __next40pxDefaultSize
                label={__('City', 'nova-post-for-woocommerce')}
                value={settings?.sender_city || ''}
                onChange={handleChange('sender_city')}
                help={senderCityError.help}
            />

            {settings.address_type === 'address' && <Address />}

            {settings.address_type === 'division' && <Division />}

            {isUkraineSender && (
                <>
                    <div className={styles.departureMethodHeader}>
                        <span className={styles.departureMethodLabel}>
                            {__('International Shipment Settings', 'nova-post-for-woocommerce')}
                        </span>
                        <InfoTooltip
                            information={__(
                                'Configure customs fee payer and delivery tariff for shipments from Ukraine.',
                                'nova-post-for-woocommerce'
                            )}
                        />
                    </div>

                    <SelectControl
                        className={customsFeePayerError.wrapperClass}
                        name="customs_fee_payer"
                        label={
                            <div className="flex-center">
                                <span>{__('Customs Fee Payer', 'nova-post-for-woocommerce')}</span>
                                <InfoTooltip
                                    information={__(
                                        "Who pays customs fees for shipments. The sender may be automatically switched to the recipient as the customs fee payer if the declared parcel value exceeds the limit set for the recipient's country. If the value is below that country's minimum threshold, customs fees do not apply at all.",
                                        'nova-post-for-woocommerce'
                                    )}
                                />
                            </div>
                        }
                        value={settings.customs_fee_payer || 'Sender'}
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
                        onChange={handleChange('customs_fee_payer')}
                        __next40pxDefaultSize
                        help={customsFeePayerError.help}
                    />

                    <SelectControl
                        className={deliveryTariffError.wrapperClass}
                        name="delivery_tariff"
                        label={
                            <div className="flex-center">
                                <span>{__('Delivery Tariff', 'nova-post-for-woocommerce')}</span>
                                <InfoTooltip
                                    information={__(
                                        'Field for selecting the type of delivery. Available values: "Standard" - regular delivery for all countries, and "Express" - fast international delivery available only for shipments to the USA. For other countries, "Standard" will be applied automatically. For shipments to the USA, when the recipient pays customs fees, only "Express" is applied.',
                                        'nova-post-for-woocommerce'
                                    )}
                                />
                            </div>
                        }
                        value={deliveryTariff}
                        options={[
                            {
                                label: __('Standard', 'nova-post-for-woocommerce'),
                                value: 'standard',
                            },
                            {
                                label: __('Express', 'nova-post-for-woocommerce'),
                                value: 'express',
                            },
                        ]}
                        onChange={handleChange('delivery_tariff')}
                        disabled={isRecipientCustomsFeePayer}
                        __next40pxDefaultSize
                        __nextHasNoMarginBottom
                        help={deliveryTariffError.help}
                    />
                </>
            )}
        </FormSection>
    );
};

export default SenderData;
