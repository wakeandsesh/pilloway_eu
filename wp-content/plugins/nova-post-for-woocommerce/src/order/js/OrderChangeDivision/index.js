/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { RadioControl } from '@wordpress/components';

import { DIVISION_TYPE } from '@/constants';
import SelectDivision from '@/components/SelectDivision';

/**
 * Internal dependencies
 */
import { useOrderDivision } from './hooks/useOrderDivision';
import { updateDivisionInfo } from '../utils/updateDivisionInfo';
import styles from './styles.module.scss';
/**
 * Nova Post Widget wrapper for Order Edit page in WordPress Admin.
 * Loads division data from order and saves changes via order API.
 *
 * @param {Object} props         Component props
 * @param {number} props.orderId Order ID
 */
export const OrderChangeDivision = ({ orderId }) => {
    const {
        divisionData,
        shippingAddress,
        isInitializing,
        saveDivision,
        setDivisionData,
        isSaving,
    } = useOrderDivision(orderId);

    // Show loading state during initialization
    if (isInitializing) {
        return (
            <div className={'nova-post-widget nova-post-widget__container'}>
                <div className="nova-post-widget__loading">
                    <p>{__('Loading division data…', 'nova-post-for-woocommerce')}</p>
                </div>
            </div>
        );
    }

    /**
     * Handle division change and save to order.
     *
     * @return {Promise<void>}
     */
    const handleSave = async () => {
        try {
            const response = await saveDivision(divisionData);

            if (response?.success) {
                updateDivisionInfo(divisionData);
            }

            const divisionDataContainer = document.querySelector('.npw-division-data');
            if (divisionDataContainer) {
                divisionDataContainer.classList.remove('is-editing');
            }
        } catch (error) {
            console.error('Error saving division:', error);
        }
    };

    /**
     * Handle division selection change.
     *
     * @param {Object} data Selected division object
     */
    const handleChangeDivision = (data) => {
        setDivisionData({
            division_type: divisionData?.division_type || DIVISION_TYPE.BRANCH,
            division: data,
            division_id: data?.id,
        });
    };

    /**
     * Handle change of division type (branch/parcel)
     *
     * @param {string} newType New division type
     */
    const handlerChangeType = (newType) => {
        console.log('Change type to:', divisionData, newType);
        setDivisionData({
            ...divisionData,
            division_type: newType,
        });
    };

    return (
        <div className={styles['npwforwc-order-edit-widget-wrapper']}>
            <RadioControl
                onChange={handlerChangeType}
                label={__('Division Type', 'nova-post-for-woocommerce')}
                selected={divisionData.division_type}
                options={[
                    {
                        label: __('Branch', 'nova-post-for-woocommerce'),
                        value: DIVISION_TYPE.BRANCH,
                    },
                    {
                        label: __('Parcel locker', 'nova-post-for-woocommerce'),
                        value: DIVISION_TYPE.POSTMAT,
                    },
                ]}
            />

            <SelectDivision
                countryCode={shippingAddress?.country}
                city={shippingAddress?.city}
                type={divisionData?.division_type}
                prohibitedIssuance={false}
                presetDivision={divisionData?.division}
                onDivisionSelect={handleChangeDivision}
                placeholder={__('Enter division name', 'nova-post-for-woocommerce')}
            />

            <button
                className={styles['npwforwc-save-button'] + ' button button-primary'}
                onClick={handleSave}
                disabled={isSaving}
            >
                {__('Save Changes', 'nova-post-for-woocommerce')}
            </button>
        </div>
    );
};
