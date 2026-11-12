/**
 * ParcelsSection Component
 *
 * Displays parcels information in the shipment preview modal.
 */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import styles from '../styles.module.scss';

export const ParcelsSection = ({ parcels }) => {
    if (!parcels || parcels.length === 0) {
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
                {__('Parcels', 'nova-post-for-woocommerce')}
            </h3>
            <div className={styles['shipment-preview-modal__section-content']}>
                {parcels.map((parcel, index) => (
                    <div key={index} className={styles['shipment-preview-modal__parcel']}>
                        <h4 className={styles['shipment-preview-modal__parcel-title']}>
                            {__('Parcel', 'nova-post-for-woocommerce')} #{index + 1}
                        </h4>
                        {renderField(
                            __('Weight (kg)', 'nova-post-for-woocommerce'),
                            parcel.actualWeight
                        )}
                        {renderField(__('Length (cm)', 'nova-post-for-woocommerce'), parcel.length)}
                        {renderField(__('Width (cm)', 'nova-post-for-woocommerce'), parcel.width)}
                        {renderField(__('Height (cm)', 'nova-post-for-woocommerce'), parcel.height)}
                        {renderField(
                            __('Description', 'nova-post-for-woocommerce'),
                            parcel.description
                        )}
                        {renderField(
                            __('Declared Cost', 'nova-post-for-woocommerce'),
                            parcel.declaredCost
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
};
