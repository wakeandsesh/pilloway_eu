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

/**
 * Normalize mapped API messages, legacy JSON errors, and field error maps.
 *
 * @param {*} errors - Error response or validation messages.
 * @return {string[]} Human-readable messages.
 */
export const getErrorMessages = (errors) => {
    if (typeof errors === 'string') {
        try {
            return getErrorMessages(JSON.parse(errors));
        } catch {
            return [errors];
        }
    }
    if (Array.isArray(errors)) return errors.flatMap(getErrorMessages);
    if (!errors || typeof errors !== 'object') return [];
    const apiErrors = getErrorMessages(errors.data?.api_errors);
    if (apiErrors.length) return apiErrors;
    return getErrorMessages(
        errors.data?.message ||
            errors.message ||
            errors.errorMessage ||
            errors.errors ||
            Object.values(errors)
    );
};

export const ValidationErrors = ({ errors }) => {
    const displayErrors = [...new Set(getErrorMessages(errors))];
    if (!displayErrors.length) return null;

    return (
        <>
            {displayErrors.map((error) => (
                <Notice
                    key={error}
                    status="error"
                    isDismissible={false}
                    className={styles['shipment-preview-modal__validation-error']}
                >
                    <span>{error}</span>
                </Notice>
            ))}
        </>
    );
};
