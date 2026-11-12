/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { __experimentalInputControl as InputControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { useSettingsContext } from '../../contexts/SettingsContext';
import { createGenericHandler } from '../../utils/fieldHandlers';
import { useFieldError } from '../../hooks/useFieldError';

import styles from './styles.module.scss';

const Address = () => {
    const { settings, updateSetting } = useSettingsContext();
    const handleChange = createGenericHandler(updateSetting);
    const postalCodeError = useFieldError('sender_postal_code');
    const streetError = useFieldError('sender_street');
    const buildingError = useFieldError('sender_building');

    return (
        <div className={styles['address-container']}>
            <InputControl
                className={postalCodeError.wrapperClass}
                name="sender_postal_code"
                __next40pxDefaultSize
                label={__('Postal Code', 'nova-post-for-woocommerce')}
                value={settings?.sender_postal_code || ''}
                onChange={handleChange('sender_postal_code')}
                help={postalCodeError.help}
            />

            <InputControl
                className={streetError.wrapperClass}
                name="sender_street"
                __next40pxDefaultSize
                label={__('Street', 'nova-post-for-woocommerce')}
                value={settings?.sender_street || ''}
                onChange={handleChange('sender_street')}
                help={streetError.help}
            />

            <InputControl
                className={buildingError.wrapperClass}
                name="sender_building"
                __next40pxDefaultSize
                label={__('Building', 'nova-post-for-woocommerce')}
                value={settings?.sender_building || ''}
                onChange={handleChange('sender_building')}
                help={buildingError.help}
            />
        </div>
    );
};

export default Address;
