/**
 * External dependencies
 */
import { Card, CardBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { addDays, getWeekdayName, formatToISO, formatDayMonth, getLocale } from '@/utils';

/**
 * Internal dependencies
 */
import './style.scss';

export function PickupTimeIntervals({
    listIntervals,
    selectedDay,
    setSelectedDay,
    selectedIntervalIndex,
    setSelectedIntervalIndex,
    setSelectedIntervalTime,
}) {
    const days = Array.from({ length: 7 }).map((_, i) => addDays(new Date(), i + 1));
    const locale = getLocale();

    return (
        <div className="nova-post-pickup-days-row">
            {days.map((day, dayIdx) => {
                const key = getWeekdayName(day, 'long', 'en');
                const entry = listIntervals[key] || {};
                let intervals = [];
                if (entry.timeIntervals?.length) {
                    intervals = entry.timeIntervals;
                } else if (entry.from) {
                    intervals = [{ from: entry.from, to: entry.to, orderTo: entry.orderTo }];
                }
                const isDaySelected = selectedDay && formatToISO(selectedDay) === formatToISO(day);

                return (
                    <Card
                        key={dayIdx}
                        isSelected={isDaySelected}
                        className={[
                            'nova-post-pickup-day-card',
                            isDaySelected ? 'nova-post-pickup-selected-day' : '',
                            !intervals.length ? 'nova-post-pickup-day-disabled' : '',
                        ].join(' ')}
                    >
                        <CardBody>
                            <div className="nova-post-pickup-day-date">
                                {formatDayMonth(day, locale)}
                            </div>
                            <div className="nova-post-pickup-day-week">
                                {getWeekdayName(day, 'short', locale)}
                            </div>
                            <div className="nova-post-pickup-intervals-list">
                                {intervals.length ? (
                                    intervals.map((itv, i) => {
                                        const isIntervalSelectedIndex =
                                            isDaySelected && selectedIntervalIndex === i;
                                        return (
                                            <div
                                                key={i}
                                                role="button"
                                                tabIndex={0}
                                                onClick={() => {
                                                    setSelectedDay(day);
                                                    setSelectedIntervalIndex(i);
                                                    setSelectedIntervalTime({
                                                        from: itv.from,
                                                        to: itv.to,
                                                    });
                                                }}
                                                onKeyDown={(event) => {
                                                    if (
                                                        event.key === 'Enter' ||
                                                        event.key === ' '
                                                    ) {
                                                        event.preventDefault();
                                                        setSelectedDay(day);
                                                        setSelectedIntervalIndex(i);
                                                        setSelectedIntervalTime({
                                                            from: itv.from,
                                                            to: itv.to,
                                                        });
                                                    }
                                                }}
                                                className={[
                                                    'nova-post-pickup-interval',
                                                    isIntervalSelectedIndex
                                                        ? 'nova-post-pickup-selected-interval'
                                                        : '',
                                                ].join(' ')}
                                            >
                                                {itv.from}–{itv.to}
                                                {itv.orderTo && (
                                                    <div className="nova-post-pickup-order-to">
                                                        {__(
                                                            'Order to',
                                                            'nova-post-for-woocommerce'
                                                        )}{' '}
                                                        {itv.orderTo}
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })
                                ) : (
                                    <span className="nova-post-pickup-day-off">-</span>
                                )}
                            </div>
                        </CardBody>
                    </Card>
                );
            })}
        </div>
    );
}
