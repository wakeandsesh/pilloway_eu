/**
 * Utility functions for form field handling
 * Eliminates code duplication across form components
 */

/**
 * Generic field change handler that can be used directly in components
 *
 * @param {Function} onChange - The onChange callback from parent component
 * @return {Function} Handler function that takes field name as first parameter
 *
 * @example
 * const handleChange = createGenericHandler(onChange);
 * <InputControl onChange={handleChange('sender_email')} />
 */
export const createGenericHandler = (onChange) => {
    return (fieldName) => (value) => {
        onChange(fieldName, value);
    };
};

/**
 * Generic amount change handler that sanitizes and validates numeric input
 *
 * @param {Function} onChange - The onChange callback from parent component
 * @return {Function} Handler function that takes field name as first parameter
 *
 * @example
 * const handleNumericChange = createGenericNumberHandler(onChange);
 * <InputControl onChange={handleNumericChange('sender_email')} />
 */
export const createGenericAmountHandler = (onChange) => {
    return (fieldName) => (value) => {
        let sanitized = value.replace(',', '.');

        sanitized = sanitized.replace(/[^\d.]/g, '');

        const parts = sanitized.split('.');

        if (parts.length === 2 && parts[1].length > 2) {
            sanitized = parts[0] + '.' + parts[1].substring(0, 2);
        }

        onChange(fieldName, sanitized);
    };
};

/**
 * Validates if a field handler is properly configured
 *
 * @param {Function} onChange  - The onChange function to validate
 * @param {string}   fieldName - The field name to validate
 * @return {boolean} True if valid, false otherwise
 */
export const validateFieldHandler = (onChange, fieldName) => {
    if (typeof onChange !== 'function') {
        console.warn(
            `Field handler validation failed: onChange is not a function for field "${fieldName}"`
        );
        return false;
    }

    if (!fieldName || typeof fieldName !== 'string') {
        console.warn(`Field handler validation failed: fieldName must be a non-empty string`);
        return false;
    }

    return true;
};
