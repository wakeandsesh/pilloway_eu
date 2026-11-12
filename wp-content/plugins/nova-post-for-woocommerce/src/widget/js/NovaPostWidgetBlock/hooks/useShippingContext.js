import { useEffect, useMemo, useState } from "@wordpress/element";
import { useSelect } from "@wordpress/data";
import { CART_STORE_KEY } from "@woocommerce/block-data";

const NP_SHIPPING_METHOD_NAME = "nova_post_division";

/**
 * Hook to get shipping method, selected division data, error calculation from the cart store
 */
export const useShippingContext = () => {
  const { shippingAddress, shippingRates, extensions } = useSelect(
    (select) => select(CART_STORE_KEY).getCartData(),
    [],
  );
  const [selectedMethod, setSelectedMethod] = useState(null);

  const isNovaPostDivisionActive = selectedMethod?.method_id === NP_SHIPPING_METHOD_NAME;

  useEffect(() => {
    shippingRates.forEach((shippingPackage) => {
      const foundRate = shippingPackage.shipping_rates.find((rate) => rate.selected);
      if (foundRate) {
        setSelectedMethod(foundRate);
      }
    });
  }, [shippingRates]);

  const { divisionData, divisionFilters, errorCalculation } = useMemo(() => {
    return {
      divisionData: {
        division: extensions?.npforwc_widget_data?.division,
        division_id: extensions?.npforwc_widget_data?.division_id,
        division_type: extensions?.npforwc_widget_data?.division_type,
      },
      divisionFilters: {
        postomat: extensions?.npforwc_widget_data?.divisions_filter_postomat,
        postBranch: extensions?.npforwc_widget_data?.divisions_filter_post_branch,
        cargoBranch: extensions?.npforwc_widget_data?.divisions_filter_cargo_branch,
        pudo: extensions?.npforwc_widget_data?.divisions_filter_pudo,
      },
      errorCalculation: {
        division: Boolean(extensions?.npforwc_widget_data?.error_calculation_division),
        address: Boolean(extensions?.npforwc_widget_data?.error_calculation_address),
      },
    };
  }, [extensions]);

  return {
    shippingRates,
    selectedMethod,
    divisionData,
    divisionFilters,
    errorCalculation,
    shippingAddress,
    isNovaPostDivisionActive,
  };
};
