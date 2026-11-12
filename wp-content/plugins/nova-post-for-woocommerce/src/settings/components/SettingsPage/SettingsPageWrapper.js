/**
 * External dependencies
 */
import { Fragment } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import styles from './styles.module.scss';

export const SettingsPageWrapper = ({ children }) => {
    return (
        <Fragment>
            <h1 className="wp-heading-inline">
                {__('Nova Post Settings', 'nova-post-for-woocommerce')}
            </h1>

            <hr className="wp-header-end" />

            <div className="npw-notice-insert-point"></div>

            <div className={styles['np-settings-page']}>{children}</div>
        </Fragment>
    );
};
