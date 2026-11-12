/**
 * External dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { useWooBulkAction } from '../../../hooks/useWooBulkAction';
import { BulkPrintModal } from '../BulkPrintModal';
import { BulkShipmentModal } from '../BulkShipmentModal';
import { CreatePickupModal } from '../CreatePickupModal';

export default function BulkManagerApp() {
    const [activeAction, setActiveAction] = useState(null);

    const shipmentAction = useWooBulkAction('npforwc_create_shipments');
    const markingAction = useWooBulkAction('npforwc_print_markings');
    const invoiceAction = useWooBulkAction('npforwc_print_invoice');
    const customsAction = useWooBulkAction('npforwc_print_customs');
    const pickupAction = useWooBulkAction('npforwc_create_pickup_request');

    useEffect(() => {
        if (shipmentAction.isOpen) setActiveAction('shipment');
        else if (markingAction.isOpen) setActiveAction('marking');
        else if (invoiceAction.isOpen) setActiveAction('invoice');
        else if (customsAction.isOpen) setActiveAction('customs');
        else if (pickupAction.isOpen) setActiveAction('pickup');
        else setActiveAction(null);
    }, [
        shipmentAction.isOpen,
        markingAction.isOpen,
        invoiceAction.isOpen,
        customsAction.isOpen,
        pickupAction.isOpen,
    ]);

    const handleClose = () => {
        if (activeAction === 'shipment') shipmentAction.closeModal();
        if (activeAction === 'marking') markingAction.closeModal();
        if (activeAction === 'invoice') invoiceAction.closeModal();
        if (activeAction === 'customs') customsAction.closeModal();
        if (activeAction === 'pickup') pickupAction.closeModal();
        setActiveAction(null);
    };

    if (!activeAction) return null;

    if (activeAction === 'shipment') {
        const orderIds = shipmentAction.selectedOrdersId.map((id) => parseInt(id, 10));

        if (orderIds.length > 100) {
            alert(
                sprintf(
                    /* translators: %d: maximum number of orders */
                    __(
                        'You can create shipments for maximum %d orders at once',
                        'nova-post-for-woocommerce'
                    ),
                    100
                )
            );
            handleClose();
            return null;
        }

        return <BulkShipmentModal orderIds={orderIds} onClose={handleClose} />;
    }

    const getPrintProps = (v = activeAction) => {
        const variants = {
            marking: {
                orderIds: markingAction.selectedOrdersId.map((id) => parseInt(id, 10)),
                documentType: 'marking',
            },
            invoice: {
                orderIds: invoiceAction.selectedOrdersId.map((id) => parseInt(id, 10)),
                documentType: 'invoice',
            },
            customs: {
                orderIds: customsAction.selectedOrdersId.map((id) => parseInt(id, 10)),
                documentType: 'international',
            },
        };

        return variants[v] || null;
    };

    const printProps = getPrintProps();

    if (printProps) {
        if (printProps.orderIds.length > 200) {
            alert(
                sprintf(
                    /* translators: %d: maximum number of orders */
                    __('Maximum %d orders allowed for bulk printing', 'nova-post-for-woocommerce'),
                    200
                )
            );
            handleClose();
            return null;
        }

        return (
            <BulkPrintModal
                orderIds={printProps.orderIds}
                documentType={printProps.documentType}
                onClose={handleClose}
            />
        );
    }

    if (activeAction === 'pickup') {
        const orderIds = pickupAction.selectedOrdersId.map((id) => parseInt(id, 10));
        return <CreatePickupModal orderIds={orderIds} onClose={handleClose} />;
    }

    return null;
}
