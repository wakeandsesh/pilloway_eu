<?php
/**
 * Summary of namespace NovaPostForWoocommerce\Helpers
 *
 * @package NovaPostForWoocommerce\Helpers
 */

namespace NovaPostForWoocommerce\Helpers;

/**
 * Summary of MeasurementUnitConstants
 */
class MeasurementUnitConstants {
    public const CM_TO_MM_MULTIPLIER = 10;
    public const M_TO_MM_MULTIPLIER  = 1000;
    public const IN_TO_MM_MULTIPLIER = 25.4;
    public const YD_TO_MM_MULTIPLIER = 914.4;

    public const KG_TO_GR_MULTIPLIER = 1000;
    public const LB_TO_GR_MULTIPLIER = 453.592;
    public const OZ_TO_GR_MULTIPLIER = 28.3495;

    public const LOWER_SIZE_LIMIT_1 = 20; // 2 cm
    public const LOWER_SIZE_LIMIT_2 = 250; // 25 cm
    public const LOWER_SIZE_LIMIT_3 = 350; // 35 cm

    public const UPPER_SIZE_LIMIT = 1200; // 120 cm

    public const LOWER_WEIGHT_LIMIT = 1000; // 1 kg
    public const UPPER_WEIGHT_LIMIT = 30000; // 30 kg

    public const VOLUMETRIC_WEIGHT_MULTIPLIER = 4000;
}
