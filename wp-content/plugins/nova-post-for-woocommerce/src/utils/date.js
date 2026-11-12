export const addDays = (date, days) => {
    const result = new Date(date);
    result.setDate(result.getDate() + days);
    return result;
};

/**
 * Format date to ISO string (YYYY-MM-DD)
 *
 * @param {Date} date - Date to format
 * @return {string} ISO formatted date (YYYY-MM-DD)
 */
export const formatToISO = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

export const getWeekdayName = (date, format, locale) => {
    return date.toLocaleDateString(locale, { weekday: format }).toLowerCase();
};

export const formatDayMonth = (date, locale) => {
    return date.toLocaleDateString(locale, { day: 'numeric', month: 'long' });
};

export const formatDayMonthWeekday = (date, locale) => {
    return date.toLocaleDateString(locale, { day: 'numeric', month: 'long', weekday: 'long' });
};
