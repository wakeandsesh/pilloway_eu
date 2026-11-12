<?php
/**
 * Google Analytics 4 Event defined constants.
 *
 * @package    NovaPostForWoocommerce
 * @subpackage Models/Constants
 */

namespace NovaPostForWoocommerce\Models\Constants;

/**
 * Google Analytics 4 Event Constants.
 */
class Ga4Constants {
    public const NP_COST_CALCULATED            = 'np_cost_calculated';
    public const NP_WIDGET_CHECKOUT_VIEWED     = 'np_widget_checkout_viewed'; // Frontend event.
    public const NP_DELIVERY_METHOD_SELECTED   = 'np_delivery_method_selected'; // Frontend Event.
    public const NP_BRANCH_SELECTED            = 'np_branch_selected'; // Frontend from admin panel.
    public const NP_MAP_CHECKOUT_OPENED        = 'np_map_checkout_opened'; // frontend event.
    public const NP_BRANCH_SEARCHED            = 'np_branch_searched';
    public const NP_CHECKOUT_COMPLETED         = 'np_checkout_completed';
    public const NP_BRANCH_REPLACED            = 'np_branch_replaced';
    public const NP_PLUGIN_ACTIVATED           = 'np_plugin_activated';
    public const NP_SHIPMENT_CREATED           = 'np_shipment_created';
    public const NP_SHIPMENT_CREATED_BY_SELLER = 'np_shipment_created_by_seller';
    public const NP_SHIPMENT_FAILED            = 'np_shipment_failed';
    public const NP_PRINT_CREATED              = 'np_print_created';
    public const NP_PRINT_FAILED               = 'np_print_failed';
    public const NP_SETTINGS_SAVED             = 'np_settings_saved';
    public const NP_SETTINGS_FAILED            = 'np_settings_failed';
    public const NP_BULK_PRINTS_CREATED        = 'np_bulk_prints_created';
    public const NP_BULK_PRINTS_FAILED         = 'np_bulk_prints_failed';
    public const NP_BULK_SHIPMENTS_CREATED     = 'np_bulk_shipments_created';
    public const NP_BULK_SHIPMENTS_FAILED      = 'np_bulk_shipments_failed';
    public const NP_PICKUP_CREATED             = 'np_pickup_created';
    public const NP_PICKUP_FAILED              = 'np_pickup_failed';
}
