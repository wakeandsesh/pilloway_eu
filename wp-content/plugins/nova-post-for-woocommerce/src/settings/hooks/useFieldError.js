/**
 * Internal dependencies
 */
import { useSettingsContext } from '../contexts/SettingsContext';

/**
 * @typedef {Object} FieldErrorResult
 * @property {boolean}     hasError     - Whether the field has a validation error.
 * @property {string|null} message      - First error message, or null if no error.
 * @property {string}      wrapperClass - CSS class to apply to the field wrapper ('npfwc-field-error' or '').
 * @property {*|string}    help         - JSX help node to pass to the component, or undefined.
 */

/**
 * Hook to retrieve validation error state for a single settings field.
 *
 * @param {string} name - Field name as it appears in the server `errors` map (e.g. 'sender_email').
 * @return {FieldErrorResult} Derived error state for the field.
 */
export const useFieldError = (name) => {
    const { fieldErrors } = useSettingsContext();

    const hasError = Array.isArray(fieldErrors[name]) && fieldErrors[name].length > 0;
    const message = hasError ? fieldErrors[name][0] : null;
    const wrapperClass = hasError ? 'npfwc-field-error' : '';
    const help = hasError ? <span className="text-error">{message}</span> : undefined;

    return { hasError, message, wrapperClass, help };
};

/**
 * Hook to retrieve validation error state for a field inside an array setting.
 *
 * Composes the dotted key `${arrayName}.${index}.${subField}` and delegates to
 * {@link useFieldError}. Useful for `np_custom_boxes` rows where the server
 * returns keys such as `np_custom_boxes.0.width`.
 *
 * @param {string} arrayName - Top-level array field name (e.g. 'np_custom_boxes').
 * @param {number} index     - Zero-based index of the array item.
 * @param {string} subField  - Sub-field name within the item (e.g. 'width').
 * @return {FieldErrorResult} Derived error state for the indexed field.
 */
export const useFieldErrorIndexed = (arrayName, index, subField) => {
    return useFieldError(`${arrayName}.${index}.${subField}`);
};

export default useFieldError;
