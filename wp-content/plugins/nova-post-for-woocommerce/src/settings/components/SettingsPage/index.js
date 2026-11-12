/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Spinner, Button } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { useSettingsContext } from '../../contexts/SettingsContext';
import ApiConfiguration from '../ApiConfiguration';
import ContractNumbers from '../ContractNumbers';
import SenderData from '../SenderData';
import CarrierShippingCalculation from '../CarrierShippingCalculation';
import ProductInformation from '../ProductInformation';
import PackagingSettings from '../PackagingSettings';
import CashOnDelivery from '../CashOnDelivery';
import OrderWorkflow from '../OrderWorkflow';
import DivisionsFilters from '../DivisionsFilters';

import styles from './styles.module.scss';
import { SettingsPageWrapper } from './SettingsPageWrapper';

const NovaPostSettingsPage = () => {
    // Use centralized settings context instead of local state
    const {
        isLoading,
        isApiConfigured,
        isSaving,
        saveSettings,
        saveApiConfiguration,
        isDirty,
        isApiConfigDirty,
    } = useSettingsContext();

    const handlerClick = async () => {
        try {
            if (!isApiConfigured) {
                await saveApiConfiguration();
            } else {
                if (isApiConfigDirty) {
                    const isApiSaved = await saveApiConfiguration();

                    if (!isApiSaved) {
                        return;
                    }
                }

                saveSettings();
            }
        } catch (error) {
            console.error('Error in handlerClick:', error);
        }
    };

    if (isLoading) {
        return (
            <SettingsPageWrapper>
                <Spinner />

                <p>{__('Loading data…', 'nova-post-for-woocommerce')}</p>
            </SettingsPageWrapper>
        );
    }

    return (
        <SettingsPageWrapper>
            <ApiConfiguration />

            {isApiConfigured && (
                <>
                    <ContractNumbers />

                    <SenderData className={styles['np-settings-page__sender-data']} />

                    <ProductInformation />

                    <PackagingSettings />

                    <CarrierShippingCalculation />

                    <CashOnDelivery />

                    <OrderWorkflow />

                    <DivisionsFilters />
                </>
            )}

            <div className={styles['np-settings-page__footer']}>
                <Button
                    variant="primary"
                    isBusy={isSaving}
                    disabled={!isDirty || isLoading || isSaving}
                    onClick={handlerClick}
                >
                    {__('Save', 'nova-post-for-woocommerce')}
                </Button>
            </div>
        </SettingsPageWrapper>
    );
};

export default NovaPostSettingsPage;
