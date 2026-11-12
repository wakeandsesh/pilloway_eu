/**
 * External dependencies
 */
import { useMemo, useCallback } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { localStorageService } from '../../services/localStorageService';
import { createLocalStorageKey } from '../../utils/localStorage';

/**
 * Hook to manage a dismissible UI flag with pluggable storage.
 *
 * @param {string}  id                         Unique id for the dismissible entity
 * @param {Object}  options
 * @param {boolean} [options.defaultShow=true] Initial show if no record exists
 */
export default function useDismissibleFlag(id, { defaultShow = true } = {}) {
    const PREFIX = 'npw_hidded_settings_info:';
    const createKey = (entryId) => createLocalStorageKey(PREFIX, entryId);

    const isDismissed = useMemo(() => localStorageService.get(createKey(id)) === 1, [id]);
    const isShown = useMemo(() => (defaultShow ? !isDismissed : false), [defaultShow, isDismissed]);

    const dismiss = useCallback(() => localStorageService.set(createKey(id), 1), [id]);
    const reset = useCallback(() => localStorageService.remove(createKey(id)), [id]);

    return { isShown, isDismissed, dismiss, reset };
}
