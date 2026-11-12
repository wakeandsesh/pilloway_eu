/**
 * External dependencies
 */
import { useCallback, useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { getClassicCheckoutState } from '../services/classicCheckoutStateService';
import { useCheckoutActions } from '../useCheckoutState';

function attachUpdatedCheckoutHandler(handler) {
    if (typeof jQuery !== 'undefined' && jQuery(document.body).on) {
        jQuery(document.body).on('updated_checkout', handler);
        return () => jQuery(document.body).off('updated_checkout', handler);
    }

    return () => {};
}

export function ClassicCheckoutAdapter() {
    const { hydrateFromClassic, setLoading, setFetchError } = useCheckoutActions();

  const refreshClassicState = useCallback(
    async (event, data) => {
      // Use state from WooCommerce update_order_review fragments if available
      if (data?.fragments?.nova_post_state) {
        hydrateFromClassic(data.fragments.nova_post_state);
        setFetchError(null);
        return;
      }

      setLoading(true);

      try {
        const response = await getClassicCheckoutState();
        hydrateFromClassic(response);
        setFetchError(null);
      } catch (error) {
        setFetchError(error.message || 'Failed to load classic checkout state');
      } finally {
        setLoading(false);
      }
    },
    [hydrateFromClassic, setFetchError, setLoading]
  );

    useEffect(() => {
        void refreshClassicState();

        return attachUpdatedCheckoutHandler(refreshClassicState);
    }, [refreshClassicState]);

    return null;
}
