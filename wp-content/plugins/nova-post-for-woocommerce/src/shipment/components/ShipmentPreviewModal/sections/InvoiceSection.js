/**
 * InvoiceSection Component
 *
 * Displays invoice information for international shipments in the preview modal.
 */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import styles from '../styles.module.scss';

export const InvoiceSection = ({ invoice }) => {
    if (!invoice || !invoice.items || invoice.items.length === 0) {
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
                {__('Invoice (International)', 'nova-post-for-woocommerce')}
            </h3>
            <div className={styles['shipment-preview-modal__section-content']}>
                {renderField(__('Incoterm', 'nova-post-for-woocommerce'), invoice.incoterm)}
                {renderField(
                    __('Export Reason', 'nova-post-for-woocommerce'),
                    invoice.exportReason
                )}
                {renderField(
                    __('Customer Number', 'nova-post-for-woocommerce'),
                    invoice.customerNumber
                )}

                <div className={styles['shipment-preview-modal__invoice-items']}>
                    <h4 className={styles['shipment-preview-modal__subsection-title']}>
                        {__('Items', 'nova-post-for-woocommerce')}
                    </h4>
                    {invoice.items.map((item, index) => (
                        <div key={index} className={styles['shipment-preview-modal__invoice-item']}>
                            <h5 className={styles['shipment-preview-modal__invoice-item-title']}>
                                {item.name || item.nameEng} (x{item.amount})
                            </h5>
                            {renderField(__('HS Code', 'nova-post-for-woocommerce'), item.hsCode)}
                            {renderField(
                                __('Weight (g)', 'nova-post-for-woocommerce'),
                                item.actualWeight
                            )}
                            {renderField(
                                __('Unit Cost', 'nova-post-for-woocommerce'),
                                `${item.cost} ${invoice.currency}`
                            )}
                        </div>
                    ))}
                </div>

                <div className={styles['shipment-preview-modal__invoice-totals']}>
                    <h4 className={styles['shipment-preview-modal__subsection-title']}>
                        {__('Totals', 'nova-post-for-woocommerce')}
                    </h4>
                    {renderField(
                        __('Total Cost', 'nova-post-for-woocommerce'),
                        `${invoice.cost} ${invoice.currency}`
                    )}
                </div>
            </div>
        </div>
    );
};
