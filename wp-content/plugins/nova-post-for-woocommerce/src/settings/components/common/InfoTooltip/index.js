/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Icon, Tooltip } from '@wordpress/components';
import { help } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import styles from './styles.module.scss';

const InfoTooltip = ({ information, example, size = 20 }) => {
    if (!information && !example) {
        return null;
    }

    const tooltipContent = (
        <div className={styles.tooltipContent}>
            {information && (
                <div className={styles.information}>
                    <h4 className={styles.title}>
                        {__('Information', 'nova-post-for-woocommerce')}
                    </h4>
                    <p className={styles.text}>{information}</p>
                </div>
            )}

            {example && (
                <div className={styles.example}>
                    <h4 className={styles.title}>{__('Example', 'nova-post-for-woocommerce')}</h4>
                    <div className={styles.exampleContent}>{example}</div>
                </div>
            )}
        </div>
    );

    return (
        <Tooltip text={tooltipContent} className={styles.componentTooltip} placement="top-center">
            <span>
                <Icon
                    icon={help}
                    size={size}
                    className={styles.icon}
                    role="img"
                    aria-label={__('More information', 'nova-post-for-woocommerce')}
                />
            </span>
        </Tooltip>
    );
};

export default InfoTooltip;
