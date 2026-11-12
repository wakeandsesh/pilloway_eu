/**
 * RecipientSection Component
 *
 * Displays recipient information in the shipment preview modal.
 */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import styles from '../styles.module.scss';

export const RecipientSection = ({ recipient }) => {
    const renderField = (label, value) => {
        if (!value || value === '-') return null;

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
                {__('Recipient', 'nova-post-for-woocommerce')}
            </h3>
            <div className={styles['shipment-preview-modal__section-content']}>
                {renderField(__('Name', 'nova-post-for-woocommerce'), recipient.name)}
                {renderField(__('Phone', 'nova-post-for-woocommerce'), recipient.phone)}
                {renderField(__('Email', 'nova-post-for-woocommerce'), recipient.email)}
                {recipient.divisionId && (
                    <>
                        {renderField(
                            __('Division', 'nova-post-for-woocommerce'),
                            recipient.divisionData?.name
                        )}
                        {renderField(
                            __('Division Address', 'nova-post-for-woocommerce'),
                            recipient.divisionData?.address
                        )}
                    </>
                )}
                {recipient.addressParts && (
                    <>
                        {renderField(
                            __('Country', 'nova-post-for-woocommerce'),
                            recipient.addressParts.countryCode
                        )}
                        {renderField(
                            __('Region', 'nova-post-for-woocommerce'),
                            recipient.addressParts.region
                        )}
                        {renderField(
                            __('City', 'nova-post-for-woocommerce'),
                            recipient.addressParts.city
                        )}
                        {renderField(
                            __('Street', 'nova-post-for-woocommerce'),
                            recipient.addressParts.street
                        )}
                        {renderField(
                            __('Building', 'nova-post-for-woocommerce'),
                            recipient.addressParts.building
                        )}
                        {renderField(
                            __('Apartment', 'nova-post-for-woocommerce'),
                            recipient.addressParts.apartment
                        )}
                        {renderField(
                            __('Postal Code', 'nova-post-for-woocommerce'),
                            recipient.addressParts.postalCode
                        )}
                    </>
                )}
            </div>
        </div>
    );
};
