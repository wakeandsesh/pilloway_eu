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

const OrderWorkflow = () => {
    const { settings, isSaving, updateSetting, saveSettings } = useSettingsContext();
    const handleChange = createGenericHandler(updateSetting);

    return (
        <FormSection
            title={__('Order Workflow', 'nova-post-for-woocommerce')}
            onSave={saveSettings}
            isSaving={isSaving}
        >
            <CheckboxControl
                name="order_workflow_auto_complete_orders"
                checked={settings.order_workflow_auto_complete_orders}
                label={
                    <div className="flex-center">
                        <span>{__('Auto-complete orders', 'nova-post-for-woocommerce')}</span>
                        <InfoTooltip
                            information={__(
                                `Automatically change order status to ‘Completed' after creating a waybill. This triggers the standard email notification with tracking details to the customer.`,
                                'nova-post-for-woocommerce'
                            )}
                        />
                    </div>
                }
                onChange={handleChange('order_workflow_auto_complete_orders')}
                __nextHasNoMarginBottom
            />
        </FormSection>
    );
};

export default OrderWorkflow;
