/**
 * ValidationErrors Component
 *
 * Displays validation errors in the shipment preview modal.
 */

/**
 * External dependencies
 */
import { Notice } from '@wordpress/components';

/**
 * Internal dependencies
 */
import styles from '../styles.module.scss';

export const ValidationErrors = ({ errors }) => {
    if (!errors) {
        return null;
    }

    const displayErrors = Object.entries(typeof errors === 'object' ? errors : [errors]);

    return (
        <>
            {displayErrors.map((error) => (
                <Notice
                    key={error[0]}
                    status="error"
                    isDismissible={false}
                    className={styles['shipment-preview-modal__validation-error']}
                >
                    <strong>{error[0]}</strong>:&nbsp;
                    <span>{error[1]}</span>
                </Notice>
            ))}
        </>
    );
};
