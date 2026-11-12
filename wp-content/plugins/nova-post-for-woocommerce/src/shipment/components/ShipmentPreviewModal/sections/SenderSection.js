/**
 * SenderSection Component
 *
 * Displays sender information in the shipment preview modal.
 */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import styles from '../styles.module.scss';

export const SenderSection = ({ sender }) => {
    const renderField = (label, value) => {
        if (!value) return null;

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
                {__('Sender', 'nova-post-for-woocommerce')}
            </h3>
            <div className={styles['shipment-preview-modal__section-content']}>
                {renderField(__('Name', 'nova-post-for-woocommerce'), sender.name)}
                {renderField(__('Phone', 'nova-post-for-woocommerce'), sender.phone)}
                {renderField(__('Email', 'nova-post-for-woocommerce'), sender.email)}
                {sender.divisionId && (
                    <>
                        {renderField(
                            __('Division', 'nova-post-for-woocommerce'),
                            sender.divisionData?.name
                        )}
                        {renderField(
                            __('Division Address', 'nova-post-for-woocommerce'),
                            sender.divisionData?.address
                        )}
                    </>
                )}
                {sender.addressParts && (
                    <>
                        {renderField(
                            __('Country', 'nova-post-for-woocommerce'),
                            sender.addressParts.countryCode
                        )}
                        {renderField(
                            __('Region', 'nova-post-for-woocommerce'),
                            sender.addressParts.region
                        )}
                        {renderField(
                            __('City', 'nova-post-for-woocommerce'),
                            sender.addressParts.city
                        )}
                        {renderField(
                            __('Street', 'nova-post-for-woocommerce'),
                            sender.addressParts.street
                        )}
                        {renderField(
                            __('Building', 'nova-post-for-woocommerce'),
                            sender.addressParts.building
                        )}
                        {renderField(
                            __('Apartment', 'nova-post-for-woocommerce'),
                            sender.addressParts.apartment
                        )}
                        {renderField(
                            __('Postal Code', 'nova-post-for-woocommerce'),
                            sender.addressParts.postalCode
                        )}
                    </>
                )}
            </div>
        </div>
    );
};
