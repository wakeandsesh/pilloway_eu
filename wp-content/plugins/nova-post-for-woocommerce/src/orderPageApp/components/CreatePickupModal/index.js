/**
 * External dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import { Modal, Button, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import pickupService from '@/services/pickupService';
import { formatToISO, formatDayMonthWeekday, getLocale } from '@/utils';

/**
 * Internal dependencies
 */
import { PickupTimeIntervals } from '../PickupTimeIntervals';
import './style.scss';

export function CreatePickupModal({ orderIds, onClose }) {
    const [listIntervals, setListIntervals] = useState({});
    const [selectedDay, setSelectedDay] = useState(null);
    const [selectedIntervalIndex, setSelectedIntervalIndex] = useState(null);
    const [selectedIntervalTime, setSelectedIntervalTime] = useState(null);
    const [isLoading, setIsLoading] = useState(false);
    const [errorList, setErrorList] = useState([]);

    useEffect(() => {
        if (orderIds && orderIds.length > 0) {
            fetchAndSetTimeIntervals(orderIds);
        }
    }, [orderIds]);

    const timeSelected = (day = selectedDay, IntervalTime = selectedIntervalTime) => {
        const locale = getLocale();
        const dayLabel = day ? formatDayMonthWeekday(day, locale) : '';
        const timeLabel = IntervalTime ? `${IntervalTime.from} – ${IntervalTime.to}` : '';

        return dayLabel && timeLabel ? `${dayLabel} / ${timeLabel}` : '';
    };

    const formatToUtcWithMicroseconds = (dateStr, timeStr) => {
        const formatted = formatToISO(dateStr);
        return `${formatted}T${timeStr}:00.000000Z`;
    };

    const modelByTimeInterval = (obj) => {
        const intervals = { ...obj };

        delete intervals.id;
        delete intervals.type;
        delete intervals.createdAt;
        delete intervals.updatedAt;
        delete intervals.deletedAt;
        delete intervals.deliveryPartner;
        delete intervals.divisionId;
        delete intervals.ignoreNonWorkingDays;
        delete intervals.countryCode;

        return intervals;
    };

    const formatErrorMessage = (error) => {
        let msg = error?.message || String(error);

        if (error?.data && typeof error.data === 'object') {
            msg += `: ${JSON.stringify(error.data)}`;
        }

        return msg;
    };

    const pushError = (error) => {
        let errors = [];

        if (error?.data?.errors && Array.isArray(error.data.errors)) {
            errors = error.data.errors;
        } else {
            errors = [formatErrorMessage(error)];
        }

        setErrorList((prev) => [...(prev || []), ...errors]);
    };

    const closeModalAndResetVal = () => {
        onClose();

        setSelectedDay(null);
        setSelectedIntervalIndex(null);
        setSelectedIntervalTime(null);
        setErrorList([]);
    };

    const fetchAndSetTimeIntervals = async (ids = orderIds) => {
        setIsLoading(true);
        setErrorList([]);

        try {
            const result = await pickupService.getTimeIntervals({
                orderIds: ids,
            });

            const intervals = modelByTimeInterval(result);
            setListIntervals(intervals);
        } catch (error) {
            pushError(error);
        } finally {
            setIsLoading(false);
        }
    };

    const createPickup = async (ids = orderIds) => {
        const windowStart = formatToUtcWithMicroseconds(selectedDay, selectedIntervalTime.from);
        const windowEnd = formatToUtcWithMicroseconds(selectedDay, selectedIntervalTime.to);

        const payload = {
            windowStart,
            windowEnd,
            orderIds: ids,
        };

        try {
            const result = await pickupService.createPickup(payload);
            return result;
        } catch (error) {
            pushError(error);
        }
    };

    const addShimpentToPickup = async (pickupId, ids = orderIds) => {
        try {
            const result = await pickupService.addShimpentToPickup(pickupId, { orderIds: ids });
            return result;
        } catch (error) {
            pushError(error);
        }
    };

    const createStatusPickup = async (pickupId) => {
        const payload = {
            status: 'Created',
            note: '#',
        };

        try {
            const result = await pickupService.updateStatus(pickupId, payload);
            return result;
        } catch (error) {
            pushError(error);
        }
    };

    const createTimeInterval = async () => {
        setIsLoading(true);
        setErrorList([]);

        try {
            const { id } = await createPickup();

            if (!id) {
                throw new Error('Failed to create pickup');
            }

            const added = await addShimpentToPickup(id);
            if (!added) {
                throw new Error('Failed to add shipment to pickup');
            }

            const status = await createStatusPickup(id);
            if (!status) {
                throw new Error('Failed to update pickup status');
            }

            closeModalAndResetVal();
            window.location.reload();
        } catch (error) {
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <Modal onRequestClose={() => onClose()}>
            {errorList.length > 0 && (
                <Notice
                    status="error"
                    isDismissible={false}
                    className="nova-post-pickup-notice-wrapper"
                >
                    <ul>
                        {errorList.map((err, idx) => (
                            <li key={idx}>{err}</li>
                        ))}
                    </ul>
                </Notice>
            )}

            <PickupTimeIntervals
                listIntervals={listIntervals}
                selectedDay={selectedDay}
                setSelectedDay={setSelectedDay}
                selectedIntervalIndex={selectedIntervalIndex}
                setSelectedIntervalIndex={setSelectedIntervalIndex}
                setSelectedIntervalTime={setSelectedIntervalTime}
            />

            <div className="nova-post-pickup-button-row">
                <Button
                    variant="primary"
                    disabled={!timeSelected() || isLoading}
                    isBusy={isLoading}
                    onClick={() => createTimeInterval()}
                >
                    {__('Confirm', 'nova-post-for-woocommerce')}
                    {timeSelected() && `: ${timeSelected()}`}
                </Button>
            </div>
        </Modal>
    );
}
