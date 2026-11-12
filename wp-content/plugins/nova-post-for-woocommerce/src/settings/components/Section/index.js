/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Card, CardBody, CardFooter, CardHeader, Button } from '@wordpress/components';

/**
 * Internal dependencies
 */
import styles from './styles.module.scss';

const Section = ({ children, onSave, title, isSaving, className }) => {
    return (
        <Card className={className}>
            <div className={styles.wrapper}>
                {title && (
                    <CardHeader>
                        <h2 className={styles.head}>{title}</h2>
                    </CardHeader>
                )}

                <CardBody className={styles.body}>{children}</CardBody>

                <CardFooter>
                    <Button variant="primary" isBusy={isSaving} onClick={onSave}>
                        {__('Save', 'nova-post-for-woocommerce')}
                    </Button>
                </CardFooter>
            </div>
        </Card>
    );
};

export default Section;
