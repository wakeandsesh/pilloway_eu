import { useEffect, useCallback } from '@wordpress/element';
import { dispatch } from '@wordpress/data';
import { CART_STORE_KEY } from '@woocommerce/block-data';

function attachUpdatedCheckoutHandler(handler) {
  if (typeof jQuery !== 'undefined' && jQuery(document.body).on) {
    jQuery(document.body).on('updated_checkout', handler);
    return () => jQuery(document.body).off('updated_checkout', handler);
  }

  return () => {};
}

export const useClassicCheckoutCart = () => {
  const handler = useCallback(() => {
    dispatch(CART_STORE_KEY).invalidateResolutionForStore();
  }, []);

  useEffect(() => attachUpdatedCheckoutHandler(handler), [handler]);
};

