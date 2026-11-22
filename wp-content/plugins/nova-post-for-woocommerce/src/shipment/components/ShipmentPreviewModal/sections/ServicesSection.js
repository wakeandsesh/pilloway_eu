/**
 * ServicesSection Component
 *
 * Displays additional services (e.g., COD) in the shipment preview modal.
 */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import styles from '../styles.module.scss';

export const ServicesSection = ({ payerType, services, compact = false }) => {
    const codService = Array.isArray(services)
        ? services.find((service) => service?.serviceCode === 'COD')
        : null;

    if (!payerType && !codService) {
        return null;
    }

    const renderField = (label, value) => {
        if (!value && value !== 0) return null;

        return (
            <div className={styles['shipment-preview-modal__field']}>
                <span className={styles['shipment-preview-modal__field-label']}>{label}:</span>
                <span className={styles['shipment-preview-modal__field-value']}>{value}</span>
            </div>
        );
    };

    const formatPayer = (payer) => {
        if (payer === 'Sender') {
            return __('Sender', 'nova-post-for-woocommerce');
        }

        if (payer === 'Recipient') {
            return __('Recipient', 'nova-post-for-woocommerce');
        }

        return payer;
    };

    const bankAccount = codService?.additionalParameters?.cod?.bankAccount || {};
    const codAmount = bankAccount.amount ?? codService?.amount;
    const codCurrency = bankAccount.currencyCode || '';
    const codFeePayer = bankAccount.commissionPayer;

    return (
        <div
            className={`${styles['shipment-preview-modal__section']} ${compact ? styles['shipment-preview-modal__section--compact'] : ''}`}
        >
            {!compact && (
                <h3 className={styles['shipment-preview-modal__section-title']}>
                    {__('Delivery payment', 'nova-post-for-woocommerce')}
                </h3>
            )}
            <div className={styles['shipment-preview-modal__section-content']}>
                {renderField(
                    __('Delivery payer', 'nova-post-for-woocommerce'),
                    formatPayer(payerType)
                )}

                {codService && (
                    <div className={styles['shipment-preview-modal__service']}>
                        <h4 className={styles['shipment-preview-modal__service-title']}>
                            {__('Cash on Delivery', 'nova-post-for-woocommerce')}
                        </h4>
                        {codAmount !== undefined &&
                            renderField(
                                __('COD amount', 'nova-post-for-woocommerce'),
                                `${codAmount} ${codCurrency}`.trim()
                            )}
                        {renderField(
                            __('COD commission payer', 'nova-post-for-woocommerce'),
                            formatPayer(codFeePayer)
                        )}
                    </div>
                )}
            </div>
        </div>
    );
};
