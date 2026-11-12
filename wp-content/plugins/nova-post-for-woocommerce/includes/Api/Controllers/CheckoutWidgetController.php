<?php
/**
 * Checkout Widget Controller
 *
 * Handles AJAX requests for the checkout widget in the Nova Post WooCommerce plugin.
 *
 * @package NovaPostForWoocommerce\Api\Controllers
 */

namespace NovaPostForWoocommerce\Api\Controllers;

use NovaPostForWoocommerce;
use NovaPostForWoocommerce\Factories\NovaPost;
use NovaPostForWoocommerce\Models\Traits\TrackEvent;
use NovaPostForWoocommerce\Resources\DivisionResource;
use NovaPostForWoocommerce\Services\DivisionsService;
use WP_REST_Response;

/**
 * Class CheckoutWidgetController
 *
 * Handles the checkout widget functionality including finding Nova Post divisions.
 */
class CheckoutWidgetController {

    use TrackEvent;

    /**
     * Find Nova Post divisions based on search criteria.
     *
     * @param WP_REST_Request $request The request object containing search parameters.
     * @return WP_REST_Response Response containing matching divisions.
     */
    public function find_divisions( $request ) {
        $params = array();

        $country_code = $request->get_param( 'countryCode' );
        if ( $country_code ) {
            $params['countryCodes'] = array( $country_code );
        }

        $city = $request->get_param( 'city' );

        if ( $city ) {
            $params['textSearch'] = $city;
        }

        $query = $request->get_param( 'query' );
        if ( $query ) {
            $params['textSearch'] = $city ? ( $city . ', ' . $query ) : $query;
        }

        $params['divisionCategories'] = $request->get_param( 'divisionCategories' );

        foreach ( array( 'prohibitedSending', 'prohibitedIssuance' ) as $availability_filter ) {
            if ( $request->has_param( $availability_filter ) ) {
                $params[ $availability_filter ] = $request->get_param( $availability_filter );
            }
        }

        $nova_post_client = NovaPost::get_client();
        $divisions        = DivisionsService::search_divisions( $params );

        $divisions = array_filter(
            $divisions,
            function ( $division ) {
                return ! isset( $division['source'] ) || 'UPS' !== $division['source'];
            }
        );

        $this->track_branch_searched(
            array(
                'city'          => $city,
                'country_code'  => $country_code,
                'division_type' => implode( ',', $params['divisionCategories'] ),
                'textSearch'    => $params['textSearch'] ?? '',
            )
        );

        return new WP_REST_Response(
            array(
                'success'    => true,
                '$request'   => $request->get_params(),
                '$params'    => $params,
                'total'      => count( $divisions ),
                'data'       => DivisionResource::collection( $divisions ),
                '$divisions' => $divisions,
            )
        );
    }

    /**
     * Get division categories based on the type.
     *
     * @param string $type The type of division.
     * @return array The division categories.
     */
    private function get_division_categories( $type ) {

        if ( strtolower( $type ) === 'postomat' ) {
            return array( 'Postomat' );
        }

        $settings = \NovaPostForWoocommerce\Models\NovaPostSettings::get_instance();

        $filter_postomat     = (bool) $settings->divisions_filter_postomat;
        $filter_post_branch  = (bool) $settings->divisions_filter_post_branch;
        $filter_cargo_branch = (bool) $settings->divisions_filter_cargo_branch;
        $filter_pudo         = (bool) $settings->divisions_filter_pudo;

        $all_filters_disabled = ! $filter_postomat && ! $filter_post_branch && ! $filter_cargo_branch && ! $filter_pudo;

        if ( $all_filters_disabled ) {
            return array( 'PostBranch', 'CargoBranch', 'PUDO' );
        }

        $division_categories = array();

        if ( $filter_post_branch ) {
            $division_categories[] = 'PostBranch';
        }

        if ( $filter_cargo_branch ) {
            $division_categories[] = 'CargoBranch';
        }

        if ( $filter_pudo ) {
            $division_categories[] = 'PUDO';
        }

        return $division_categories;
    }
}
