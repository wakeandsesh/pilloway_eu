/**
 * NoteSection Component
 *
 * Displays and allows editing of the note field in the shipment preview modal.
 */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { TextareaControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import styles from '../styles.module.scss';

export const NoteSection = ({ note, onChange }) => {
    return (
        <div className={styles['shipment-preview-modal__section']}>
            <h3 className={styles['shipment-preview-modal__section-title']}>
                {__('Note', 'nova-post-for-woocommerce')}
            </h3>
            <div className={styles['shipment-preview-modal__section-content']}>
                <TextareaControl
                    label={__('Add a note for this shipment', 'nova-post-for-woocommerce')}
                    value={note}
                    onChange={onChange}
                    rows={4}
                    help={__(
                        'Optional note that will be included with the shipment',
                        'nova-post-for-woocommerce'
                    )}
                />
            </div>
        </div>
    );
};
