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

export const ServicesSection = ({ services }) => {
    if (!services || services.length === 0) {
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

    return (
        <div className={styles['shipment-preview-modal__section']}>
            <h3 className={styles['shipment-preview-modal__section-title']}>
                {__('Additional Services', 'nova-post-for-woocommerce')}
            </h3>
            <div className={styles['shipment-preview-modal__section-content']}>
                {services.map((service, index) => (
                    <div key={index} className={styles['shipment-preview-modal__service']}>
                        <h4 className={styles['shipment-preview-modal__service-title']}>
                            {service.type || __('Service', 'nova-post-for-woocommerce')} #
                            {index + 1}
                        </h4>
                        {renderField(__('Type', 'nova-post-for-woocommerce'), service.type)}
                        {service.amount &&
                            renderField(
                                __('Amount', 'nova-post-for-woocommerce'),
                                `${service.amount} ${service.currency || ''}`
                            )}
                        {renderField(
                            __('Card Number', 'nova-post-for-woocommerce'),
                            service.cardNumber
                        )}
                        {renderField(
                            __('Account Number', 'nova-post-for-woocommerce'),
                            service.accountNumber
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
};
