/**
 * ShipmentPreviewModal Component
 *
 * Main modal component for displaying shipment preview before creation.
 */

/**
 * External dependencies
 */
import { Modal, Button } from '@wordpress/components';
import { useState, useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import styles from './styles.module.scss';

import { ValidationErrors } from './sections/ValidationErrors';
import { SenderSection } from './sections/SenderSection';
import { RecipientSection } from './sections/RecipientSection';
import { ParcelsSection } from './sections/ParcelsSection';
import { InvoiceSection } from './sections/InvoiceSection';
import { ServicesSection } from './sections/ServicesSection';
import { NoteSection } from './sections/NoteSection';

export const ShipmentPreviewModal = ({ previewData, onClose, onAction }) => {
    const [note, setNote] = useState(previewData.shipment_data.note || '');
    const [isCreating, setIsCreating] = useState(false);
    const [creationError, setCreationError] = useState(null);
    const contentRef = useRef(null);

    useEffect(() => {
        if (creationError && contentRef.current) {
            const modalContent = contentRef.current.closest('.components-modal__content');
            if (modalContent) {
                modalContent.scrollTo({
                    top: 0,
                    behavior: 'smooth',
                });
            }
        }
    }, [creationError]);

    const handleCreate = async () => {
        const errorMsg = (err) =>
            err?.data?.api_errors ||
            err?.message ||
            __('Failed to create shipment', 'nova-post-for-woocommerce');

        setIsCreating(true);
        setCreationError(null);

        try {
            await onAction({ note });
        } catch (error) {
            setCreationError(errorMsg(error));
            setIsCreating(false);
        }
    };

    const {
        shipment_data: shipmentData,
        validation_error: validationError,
        is_valid: isValid,
        delivery_type: deliveryType,
    } = previewData;

    const displayError = creationError || validationError;

    const formatParcels = (parcels) => {
        if (!parcels) return [];

        return parcels.map((parcel) => ({
            ...parcel,
            length: parcel.length / 10,
            width: parcel.width / 10,
            height: parcel.height / 10,
            actualWeight: parcel.actualWeight / 1000,
        }));
    };

    return (
        <Modal
            title={__('Create Waybill - Preview', 'nova-post-for-woocommerce')}
            onRequestClose={onClose}
            className={styles['shipment-preview-modal']}
            shouldCloseOnClickOutside={false}
            isDismissible={!isCreating}
            size={'large'}
        >
            <div ref={contentRef} className={styles['shipment-preview-modal__content']}>
                <ValidationErrors errors={displayError} />

                <SenderSection sender={shipmentData.sender} />

                <RecipientSection recipient={shipmentData.recipient} />

                <ParcelsSection parcels={formatParcels(shipmentData.parcels)} />

                {deliveryType.is_international && shipmentData.invoice && (
                    <InvoiceSection invoice={shipmentData.invoice} />
                )}

                {shipmentData.services && shipmentData.services.length > 0 && (
                    <ServicesSection services={shipmentData.services} />
                )}

                <NoteSection note={note} onChange={setNote} />
            </div>

            <div className={styles['shipment-preview-modal__footer']}>
                <Button variant="secondary" onClick={onClose} disabled={isCreating}>
                    {__('Cancel', 'nova-post-for-woocommerce')}
                </Button>
                <Button
                    variant="primary"
                    onClick={handleCreate}
                    isBusy={isCreating}
                    disabled={!isValid || isCreating}
                >
                    {__('Create Waybill', 'nova-post-for-woocommerce')}
                </Button>
            </div>
        </Modal>
    );
};
