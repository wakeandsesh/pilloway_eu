/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import {
    Panel,
    PanelBody,
    RadioControl,
    CheckboxControl,
    __experimentalInputControl as InputControl,
    Flex,
    FlexItem,
} from '@wordpress/components';
import { SITE_CURRENCY } from '@woocommerce/settings';

/**
 * Internal dependencies
 */
import { FormSection, InfoTooltip } from '../common';
import { createGenericHandler, createGenericAmountHandler } from '../../utils/fieldHandlers';
import { useSettingsContext } from '../../contexts/SettingsContext';
import { useFieldError } from '../../hooks/useFieldError';
import styles from './styles.module.scss';

const CURRENCY = SITE_CURRENCY?.symbol || '';

const CarrierShippingCalculation = () => {
    const { settings, isSaving, updateSetting, saveSettings } = useSettingsContext();
    const handleChange = createGenericHandler(updateSetting);
    const handleAmountChange = createGenericAmountHandler(updateSetting);

    const divFlatRateAmountError = useFieldError('shipping_division_flat_rate_amount');
    const divFreeDeliveryAmountError = useFieldError('shipping_division_free_delivery_amount');
    const addrFlatRateAmountError = useFieldError('shipping_address_flat_rate_amount');
    const addrFreeDeliveryAmountError = useFieldError('shipping_address_free_delivery_amount');

    const adapterStrToBoolean = (v) => v === 'true';
    const adapterBooleanToStr = (v) => (v ? 'true' : 'false');

    return (
        <FormSection
            title={__('Carrier shipping calculation', 'nova-post-for-woocommerce')}
            onSave={saveSettings}
            isSaving={isSaving}
        >
            <Panel>
                <PanelBody title={__('Division', 'nova-post-for-woocommerce')} initialOpen={true}>
                    <Flex justify="space-between" align="end">
                        <FlexItem>
                            <RadioControl
                                name="shipping_division_flat_rate"
                                selected={adapterBooleanToStr(settings.shipping_division_flat_rate)}
                                options={[
                                    {
                                        label: __(
                                            'Carrier calculation',
                                            'nova-post-for-woocommerce'
                                        ),
                                        value: 'false',
                                    },
                                    {
                                        label: (
                                            <div className="flex-center">
                                                <span>
                                                    {__(
                                                        'Shipping Flat Rate',
                                                        'nova-post-for-woocommerce'
                                                    )}
                                                </span>
                                                <InfoTooltip
                                                    information={__(
                                                        'Enter a fixed cost that will be displayed to the buyer. The actual shipping costs will be calculated via the API and charged to your account.',
                                                        'nova-post-for-woocommerce'
                                                    )}
                                                />
                                            </div>
                                        ),
                                        value: 'true',
                                    },
                                ]}
                                onChange={(v) =>
                                    handleChange('shipping_division_flat_rate')(
                                        adapterStrToBoolean(v)
                                    )
                                }
                            />
                        </FlexItem>
                        <FlexItem>
                            <InputControl
                                className={`w-full ${divFlatRateAmountError.wrapperClass}`}
                                name="shipping_division_flat_rate_amount"
                                value={settings.shipping_division_flat_rate_amount || ''}
                                onChange={handleAmountChange('shipping_division_flat_rate_amount')}
                                __next40pxDefaultSize
                                type="number"
                                step="0.1"
                                min="0"
                                disabled={!settings.shipping_division_flat_rate}
                                prefix={
                                    <span className={styles['input-prefix-currency']}>
                                        {CURRENCY}
                                    </span>
                                }
                                help={divFlatRateAmountError.help}
                            />
                        </FlexItem>
                    </Flex>

                    <br />

                    <Flex justify="space-between" align="center">
                        <FlexItem>
                            <CheckboxControl
                                name="shipping_division_free_delivery"
                                label={
                                    <div className="flex-center">
                                        <span>
                                            {__(
                                                'Free Delivery for orders above',
                                                'nova-post-for-woocommerce'
                                            )}
                                        </span>
                                        <InfoTooltip
                                            information={__(
                                                'Specify the order amount above which shipping will be free for the buyer. The actual shipping costs will be charged to your account',
                                                'nova-post-for-woocommerce'
                                            )}
                                        />
                                    </div>
                                }
                                checked={settings.shipping_division_free_delivery || false}
                                onChange={handleChange('shipping_division_free_delivery')}
                                __nextHasNoMarginBottom
                            />
                        </FlexItem>
                        <FlexItem>
                            <InputControl
                                className={`w-full ${divFreeDeliveryAmountError.wrapperClass}`}
                                name="shipping_division_free_delivery_amount"
                                value={settings.shipping_division_free_delivery_amount || ''}
                                onChange={handleAmountChange(
                                    'shipping_division_free_delivery_amount'
                                )}
                                __next40pxDefaultSize
                                type="number"
                                step="0.1"
                                min="0"
                                disabled={!settings.shipping_division_free_delivery}
                                prefix={
                                    <span className={styles['input-prefix-currency']}>
                                        {CURRENCY}
                                    </span>
                                }
                                help={divFreeDeliveryAmountError.help}
                            />
                        </FlexItem>
                    </Flex>
                </PanelBody>

                <PanelBody title={__('Address', 'nova-post-for-woocommerce')} initialOpen={true}>
                    <Flex justify="space-between" align="end">
                        <FlexItem>
                            <RadioControl
                                name="shipping_address_flat_rate"
                                selected={adapterBooleanToStr(settings.shipping_address_flat_rate)}
                                options={[
                                    {
                                        label: __(
                                            'Carrier calculation',
                                            'nova-post-for-woocommerce'
                                        ),
                                        value: 'false',
                                    },
                                    {
                                        label: (
                                            <div className="flex-center">
                                                <span>
                                                    {__(
                                                        'Shipping Flat Rate',
                                                        'nova-post-for-woocommerce'
                                                    )}
                                                </span>
                                                <InfoTooltip
                                                    information={__(
                                                        'Enter a fixed cost that will be displayed to the buyer. The actual shipping costs will be calculated via the API and charged to your account.',
                                                        'nova-post-for-woocommerce'
                                                    )}
                                                />
                                            </div>
                                        ),
                                        value: 'true',
                                    },
                                ]}
                                onChange={(v) =>
                                    handleChange('shipping_address_flat_rate')(
                                        adapterStrToBoolean(v)
                                    )
                                }
                            />
                        </FlexItem>
                        <FlexItem>
                            <InputControl
                                className={`w-full ${addrFlatRateAmountError.wrapperClass}`}
                                name="shipping_address_flat_rate_amount"
                                value={settings.shipping_address_flat_rate_amount || ''}
                                onChange={handleAmountChange('shipping_address_flat_rate_amount')}
                                __next40pxDefaultSize
                                type="number"
                                step="0.1"
                                min="0"
                                disabled={!settings.shipping_address_flat_rate}
                                prefix={
                                    <span className={styles['input-prefix-currency']}>
                                        {CURRENCY}
                                    </span>
                                }
                                help={addrFlatRateAmountError.help}
                            />
                        </FlexItem>
                    </Flex>

                    <br />

                    <Flex justify="space-between" align="center">
                        <FlexItem>
                            <CheckboxControl
                                name="shipping_address_free_delivery"
                                label={
                                    <div className="flex-center">
                                        <span>
                                            {__(
                                                'Free Delivery for orders above',
                                                'nova-post-for-woocommerce'
                                            )}
                                        </span>
                                        <InfoTooltip
                                            information={__(
                                                'Specify the order amount above which shipping will be free for the buyer. The actual shipping costs will be charged to your account',
                                                'nova-post-for-woocommerce'
                                            )}
                                        />
                                    </div>
                                }
                                checked={settings.shipping_address_free_delivery || false}
                                onChange={handleChange('shipping_address_free_delivery')}
                                __nextHasNoMarginBottom
                            />
                        </FlexItem>
                        <FlexItem>
                            <InputControl
                                className={`w-full ${addrFreeDeliveryAmountError.wrapperClass}`}
                                name="shipping_address_free_delivery_amount"
                                value={settings.shipping_address_free_delivery_amount || ''}
                                onChange={handleAmountChange(
                                    'shipping_address_free_delivery_amount'
                                )}
                                __next40pxDefaultSize
                                type="number"
                                step="0.1"
                                min="0"
                                disabled={!settings.shipping_address_free_delivery}
                                prefix={
                                    <span className={styles['input-prefix-currency']}>
                                        {CURRENCY}
                                    </span>
                                }
                                help={addrFreeDeliveryAmountError.help}
                            />
                        </FlexItem>
                    </Flex>
                </PanelBody>
            </Panel>
        </FormSection>
    );
};

export default CarrierShippingCalculation;
