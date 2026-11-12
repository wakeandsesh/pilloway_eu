/**
 * Internal dependencies
 */
import { localStorageIsAvailable } from '../utils';

export const localStorageService = {
    get(key) {
        if (localStorageIsAvailable()) {
            const item = window.localStorage.getItem(key);
            if (item === null) {
                return null;
            }
            try {
                return JSON.parse(item);
            } catch (error) {
                // Return as string if JSON parsing fails
                return item;
            }
        }
        return null;
    },
    set(key, value) {
        if (localStorageIsAvailable()) {
            try {
                const serializedValue = JSON.stringify(value);
                window.localStorage.setItem(key, serializedValue);
            } catch (error) {
                // Fallback to string conversion if JSON.stringify fails
                window.localStorage.setItem(key, String(value));
            }
        }
    },
    remove(key) {
        if (localStorageIsAvailable()) {
            window.localStorage.removeItem(key);
        }
    },
};
