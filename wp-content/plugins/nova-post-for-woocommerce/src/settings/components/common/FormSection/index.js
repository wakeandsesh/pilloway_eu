/**
 * External dependencies
 */
import { Card, CardBody, CardFooter, CardHeader, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import styles from './styles.module.scss';

/**
 * Enhanced FormSection component with unified interface
 * Replaces the old Section component with better standardization
 *
 * @param {Object}   props                  - Component props
 * @param {*}        props.children         - Content to render inside the section
 * @param {Function} [props.onSave]         - Save handler function
 * @param {string}   [props.title]          - Section title
 * @param {boolean}  [props.isSaving]       - Whether save operation is in progress
 * @param {string}   [props.className]      - Additional CSS classes
 * @param {boolean}  [props.hideFooter]     - Whether to hide the footer with save button
 * @param {string}   [props.saveButtonText] - Custom text for save button
 * @param {boolean}  [props.disabled]       - Whether the section is disabled
 */
const FormSection = ({
    children,
    onSave,
    title,
    isSaving = false,
    className = '',
    hideFooter = true,
    saveButtonText,
    disabled = false,
}) => {
    const handleSave = () => {
        if (onSave && !disabled && !isSaving) {
            onSave();
        }
    };

    return (
        <Card className={className}>
            <div className={styles.wrapper}>
                {title && (
                    <CardHeader>
                        <h2 className={styles.head}>{title}</h2>
                    </CardHeader>
                )}

                <CardBody className={`${styles.body} ${disabled ? styles.disabled : ''}`}>
                    {children}
                </CardBody>

                {!hideFooter && onSave && (
                    <CardFooter>
                        <Button
                            variant="primary"
                            isBusy={isSaving}
                            disabled={disabled}
                            onClick={handleSave}
                        >
                            {saveButtonText || __('Save', 'nova-post-for-woocommerce')}
                        </Button>
                    </CardFooter>
                )}
            </div>
        </Card>
    );
};

export default FormSection;
