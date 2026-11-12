<?php
/**
 * Nova Post Settings Model.
 *
 * @package   NovaPostForWoocommerce
 */

namespace NovaPostForWoocommerce\Models\Traits;

use NovaPostForWoocommerce\Logger\Logger;
use NovaPostForWoocommerce\Models\Constants\Ga4Constants;
use NovaPostForWoocommerce\Models\Constants\OrderMetaConstants;
use NovaPostForWoocommerce\Models\Ga4Event;
use NovaPostForWoocommerce\Models\WcSession;
use NovaPostForWoocommerce\Services\Analytics\Ga4Service;
use Throwable;
use WC_Log_Levels;

/**
 * Trait to add events tracking logic.
 */
trait TrackEvent {

    /**
     * Service for sending events.
     *
     * @var Ga4Service
     */
    private static Ga4Service $ga4_service;

    /**
     * Track that plugin was activated from admin panel.
     *
     * @return void
     */
    public function track_plugin_activated(): void {
        $this->track_event( Ga4Constants::NP_PLUGIN_ACTIVATED );
    }

    /**
     * Track Cost was calculated on checkout.
     *
     * @param array $params Event params.
     * @return void
     */
    public function track_cost_calculated( array $params ): void {
        $this->track_event( Ga4Constants::NP_COST_CALCULATED, $params );
    }

    /**
     * Track checkout completed.
     *
     * @param \WC_Order|int $order Completed.
     * @return void
     */
    public function track_checkout_completed( $order ): void {
        if ( ! $order instanceof \WC_Order ) {
            $order = wc_get_order( $order );
        }
        $shipping_methods = $order->get_shipping_methods();

        if ( empty( $shipping_methods ) ) {
            return;
        }
        $current_method = reset( $shipping_methods );
        if ( ! str_starts_with( $current_method->get_method_id(), 'nova_post_' ) ) {
            return;
        }

        $session    = WcSession::init_and_get();
        $session_id = $session->get( Ga4Event::SESSION_ID_SESSION_KEY, 0 );
        $order->update_meta_data( OrderMetaConstants::NP_SESSION_ID, $session_id );
        $order->save_meta_data();

        $this->track_event(
            Ga4Constants::NP_CHECKOUT_COMPLETED,
            array(
                'order_id'      => $order->get_id(),
                'division_id'   => $order->get_meta( OrderMetaConstants::NP_DIVISION_ID ),
                'division_type' => $order->get_meta( OrderMetaConstants::NP_DIVISION_TYPE ),
                'delivery_cost' => $order->get_shipping_total(),
                'currency_code' => $order->get_currency(),
                'country'       => $order->get_shipping_country(),
            )
        );
    }

    /**
     * Track branch was searched events.
     *
     * @param array $params Event params.
     * @return void
     */
    public function track_branch_searched( array $params ): void {
        $this->track_event( Ga4Constants::NP_BRANCH_SEARCHED, $params );
    }

    /**
     * Track Branch was selected.
     *
     * @param array $params Event params.
     * @return void
     */
    public function track_branch_selected( array $params ): void {
        $this->track_event(
            Ga4Constants::NP_BRANCH_SELECTED,
            array(
                'division_id'   => $params['division_id'],
                'division_type' => $params['division_type'],
                'address'       => $params['division']['address'] ?? '',
                'country'       => $params['country'] ?? '',
            )
        );
    }

    /**
     * Track branch was changed.
     *
     * @param array $params Event params.
     * @return void
     */
    public function track_branch_replaced( array $params ): void {
        $this->track_event(
            Ga4Constants::NP_BRANCH_REPLACED,
            array(
                'division_id'   => $params['division_id'],
                'division_type' => $params['division_type'],
                'address'       => $params['division']['address'] ?? '',
            )
        );
    }

    /**
     * Track settings save failed.
     *
     * @param array  $settings Settings data.
     * @param string $error Message.
     * @return void
     */
    public function track_settings_save_failed( array $settings, string $error ): void {
        $lang_settings = $this->get_ga4_service()->get_validated_language();

        $this->track_event(
            Ga4Constants::NP_SETTINGS_FAILED,
            array(
                'departure_method' => $settings['address_type'] ?? 'address',
                'cod'              => ! empty( $settings['cod_free_paid_seller'] ) ? 'on' : 'off',
                'hs_code'          => $settings['default_hs_tariff_code'] ?? '',
                'dimensions_mode'  => $settings['default_box_for'] ?? 'product',
                'dimension_size'   => ( $settings['default_height'] ?? '' ) . 'x' .
                    ( $settings['default_width'] ?? '' ) . 'x' . ( $settings['default_length'] ?? '' ),
                'free_shipping'    => ! empty( $settings['default_free_shipping'] ) ? 'yes' : 'no',
                'default_weight'   => $settings['default_weight'] ?? '',
                'lang_settings'    => $lang_settings,
                'error'            => $error,
            )
        );
    }

    /**
     * Track Settings saved.
     *
     * @param array $settings Settings data.
     * @return void
     */
    public function track_settings_saved( array $settings ): void {
        $lang_settings = $this->get_ga4_service()->get_validated_language();

        $this->track_event(
            Ga4Constants::NP_SETTINGS_SAVED,
            array(
                'departure_method' => $settings['address_type'] ?? 'address',
                'cod'              => ! empty( $settings['cod_fee_paid_seller'] ) ? 'on' : 'off',
                'hs_code'          => $settings['default_hs_tariff_code'] ?? '',
                'dimensions_mode'  => $settings['default_box_for'] ?? 'product',
                'dimension_size'   => ( $settings['default_height'] ?? '' ) . 'x' .
                    ( $settings['default_width'] ?? '' ) . 'x' . ( $settings['default_length'] ?? '' ),
                'free_shipping'    => ! empty( $settings['default_free_shipping'] ) ? 'yes' : 'no',
                'default_weight'   => $settings['default_weight'] ?? '',
                'lang_settings'    => $lang_settings,
            )
        );
    }

    /**
     * Track Shipment created.
     *
     * @param string $order_id Order.
     * @param string $number Shipment number.
     * @param float  $cost Delivery cost.
     * @return void
     */
    public function track_shipment_created( $order_id, string $number, float $cost ): void {
        $this->track_event(
            Ga4Constants::NP_SHIPMENT_CREATED,
            array(
                'numbers'  => $number,
                'order_id' => $order_id,
                'cost'     => $cost,
            ),
        );

        $this->track_shipment_created_by_seller( $order_id, $number, $cost );
    }

    /**
     * Track Shipment created for client link.
     *
     * @param string $order_id Order ID.
     * @param string $number Shipment number.
     * @param float  $cost Shipment cost.
     * @return void
     */
    public function track_shipment_created_by_seller( $order_id, string $number, float $cost ): void {
        $order      = wc_get_order( $order_id );
        $session_id = $order->get_meta( OrderMetaConstants::NP_SESSION_ID );

        $this->track_event(
            Ga4Constants::NP_SHIPMENT_CREATED_BY_SELLER,
            array(
                'order_id'   => $order_id,
                'numbers'    => $number,
                'cost'       => $cost,
                'session_id' => $session_id,
            ),
        );
    }

    /**
     * Track shipment failed.
     *
     * @param string $order_id Order ID.
     * @param array  $errors Error message.
     * @return void
     */
    public function track_shipment_failed( $order_id, array $errors ): void {
        $this->track_event(
            Ga4Constants::NP_SHIPMENT_FAILED,
            array(
                'order_id' => $order_id,
                'error'    => implode( ',', ( $errors['api_errors'] ?? array() ) ),
            ),
        );
    }

    /**
     * Track print created.
     *
     * @param array $params Event params.
     * @return void
     */
    public function track_print_created( array $params ): void {
        $this->track_event(
            Ga4Constants::NP_PRINT_CREATED,
            array(
                'number'        => implode( ',', ( $params['numbers'] ?? array() ) ),
                'type'          => $params['type'] ?? '',
                'printSizeType' => $params['printSizeType'] ?? '',
            ),
        );
    }

    /**
     * Track print failed.
     *
     * @param array $params Event params.
     * @param array $errors Error message.
     * @return void
     */
    public function track_print_failed( array $params, array $errors ): void {
        $this->track_event(
            Ga4Constants::NP_PRINT_FAILED,
            array(
                'number'        => implode( ',', ( $params['numbers'] ?? array() ) ),
                'type'          => $params['type'] ?? '',
                'printSizeType' => $params['printSizeType'] ?? '',
                'error'         => implode( ',', $errors ),
            ),
        );
    }


    /**
     * Processes the bulk shipment results and routes them to the correct tracker.
     *
     * @param array $bulk_shipments Expects ['results' => ['success' => [...], 'failed' => [...]]].
     */
    public function track_bulk_shipments_items( $bulk_shipments ): void {
        $bulk_shipments = $bulk_shipments['results'] ?? array();

        foreach ( $bulk_shipments as $type => $shipment ) {
            if ( empty( $shipment ) ) {
                continue;
            }

            if ( 'success' === $type ) {
                $this->track_bulk_shipments_created( $shipment );
            } elseif ( 'failed' === $type || 'skipped' === $type ) {
                $this->track_bulk_shipments_failed( $shipment );
            }
        }
    }

    /**
     * Tracks successful shipment creation.
     * Loops through each shipment to avoid GA4's 100-character parameter limit.
     *
     * @param array $shipments_info List of shipment data.
     * @return void
     */
    public function track_bulk_shipments_created( array $shipments_info ): void {
        $params = array();

        foreach ( $shipments_info as $index => $shipment ) {
            $line_key = 'shipments_line_' . ( $index + 1 );

            $line_data = array(
                'number'   => (string) ( $shipment['track_number'] ?? '' ),
                'order_id' => (string) ( $shipment['order_id'] ?? '' ),
                'cost'     => (float) ( $shipment['cost'] ?? 0 ),
            );

            $params[ $line_key ] = wp_json_encode( $line_data );
        }

        $this->track_event(
            Ga4Constants::NP_BULK_SHIPMENTS_CREATED,
            $params
        );
    }

    /**
     * Tracks failed or skipped shipments.
     * Loops through items to ensure every error is captured without being truncated.
     *
     * @param array $shipments_info List of failed shipment data.
     * @return void
     */
    public function track_bulk_shipments_failed( array $shipments_info ): void {
        $params = array();

        foreach ( $shipments_info as $index => $shipment ) {
            $line_key = 'error_line_' . ( $index + 1 );

            $line_data = array(
                'error'    => (string) ( $shipment['error'] ?? $shipment['reason'] ?? 'Unknown Error' ),
                'order_id' => (string) ( $shipment['order_id'] ?? '' ),
            );

            $params[ $line_key ] = wp_json_encode( $line_data );
        }

        $this->track_event(
            Ga4Constants::NP_BULK_SHIPMENTS_FAILED,
            $params
        );
    }

    /**
     * Tracks a successful bulk print creation event in GA4.
     *
     * Sends a single event containing the related order IDs,
     * print type, and print size type.
     *
     * @param array $prints_info {
     *     Data about the successfully created prints.
     *
     *     @type array  $order_ids     List of related order IDs.
     *     @type string $type          The print type.
     *     @type string $printSizeType The print size category.
     * }
     */
    public function track_bulk_prints_created( $prints_info ): void {
        $numbers = $prints_info['numbers'] ?? array();

        $number_params = array();
        if ( ! empty( $numbers ) ) {
            foreach ( $numbers as $index => $number ) {
                $number_params[ 'number_' . ( $index + 1 ) ] = $number;
            }
        }

        $this->track_event(
            Ga4Constants::NP_BULK_PRINTS_CREATED,
            array_merge(
                array(
                    'type'          => $prints_info['type'] ?? '',
                    'printSizeType' => $prints_info['printSizeType'] ?? '',
                ),
                $number_params
            )
        );
    }

    /**
     * Tracks a failed bulk print event in GA4.
     *
     * Sends a single event containing the related order IDs,
     * print type, print size type, and error message.
     *
     * @param array $prints_info {
     *     Data about the failed prints.
     *
     *     @type array  $order_ids     List of related order IDs.
     *     @type string $type          The print type.
     *     @type string $printSizeType The print size category.
     *     @type string $error_message Error message describing the failure.
     * }
     */
    public function track_bulk_prints_failed( $prints_info ): void {
        $numbers = $prints_info['numbers'] ?? array();

        $number_params = array();
        if ( ! empty( $numbers ) ) {
            foreach ( $numbers as $index => $number ) {
                $number_params[ 'number_' . ( $index + 1 ) ] = $number;
            }
        }

        $this->track_event(
            Ga4Constants::NP_BULK_PRINTS_FAILED,
            array_merge(
                array(
                    'type'          => $prints_info['type'] ?? '',
                    'printSizeType' => $prints_info['printSizeType'] ?? '',
                    'error'         => $prints_info['error_message'] ?? '',
                ),
                $number_params
            )
        );
    }

    /**
     * Tracks successful pickup creation events in GA4.
     * Maps shipment data and corresponding order IDs into individual flattened
     * event parameters (shipments_line_1, shipments_line_2, etc.) to comply
     * with GA4's preference for flat event properties over nested arrays.
     *
     * @param array  $pickup_info    Contains timing and identification for the pickup.
     * @param array  $shipments_arr  Wrapper containing 'shipments' data and 'order_ids'.
     * @param string $step           The current step in the pickup creation process.
     * @return void
     */
    public function track_pickup_created( $pickup_info, $shipments_arr, $step ): void {
        $shipment_data = $shipments_arr['shipments'] ?? array();
        $order_ids     = $shipments_arr['order_ids'] ?? array();

        $event_params = array(
            'pickup_number'    => $pickup_info['number'] ?? '',
            'picked_time_from' => $pickup_info['windowStart'] ?? $pickup_info['window_start'] ?? '',
            'picked_time_to'   => $pickup_info['windowEnd'] ?? $pickup_info['window_end'] ?? '',
            'step'             => $step,
        );

        if ( ! empty( $shipment_data ) ) {
            foreach ( $shipment_data as $index => $shipment ) {
                $line_key = 'shipments_line_' . ( $index + 1 );

                $order_id = $order_ids[ $index ] ?? '';

                $event_params[ $line_key ] = wp_json_encode(
                    array(
                        'order_id' => (string) $order_id,
                        'number'   => $shipment['shipmentId'] ?? $shipment['id'] ?? '',
                    )
                );
            }
        }

        $this->track_event(
            Ga4Constants::NP_PICKUP_CREATED,
            $event_params
        );
    }

    /**
     * Tracks failed pickup events by mapping specific error messages to their respective orders.
     * * It parses the $pickup_info['errors'] array to extract order-specific error messages
     * and flattens the output into individual 'errors_line_{n}' strings.
     *
     * @param string       $error_message  A fallback general error message.
     * @param array        $pickup_info    The response payload containing detailed error strings.
     * @param string|int   $pickup_id      The identifier for the attempted pickup.
     * @param array|string $order_ids      List of order IDs involved in the failed pickup.
     * @param string       $step           The current step where the failure occurred.
     * @return void
     */
    public function track_pickup_failed( $error_message, $pickup_info, $pickup_id, $order_ids, $step ): void {
        $detailed_errors = $pickup_info['errors'] ?? array();
        $error_map       = array();

        if ( ! empty( $detailed_errors ) ) {
            foreach ( $detailed_errors as $error_string ) {
                if ( preg_match( '/Order\s+#(\d+):\s*(.+)/', $error_string, $matches ) ) {
                    $error_map[ $matches[1] ] = $matches[2];
                }
            }
        }

        $params = array(
            'pickup_number' => $pickup_id ?? '',
        );

        foreach ( (array) $order_ids as $index => $order_id ) {
            $line_key = 'errors_line_' . ( $index + 1 );

            $line_data = array(
                'error'    => mb_strimwidth( (string) ( $error_map[ $order_id ] ?? $error_message ?? 'Unknown error' ), 0, 40, '...' ),
                'order_id' => (string) $order_id,
                'step'     => $step ?? '',
            );

            $params[ $line_key ] = wp_json_encode( $line_data );
        }

        $this->track_event(
            Ga4Constants::NP_PICKUP_FAILED,
            $params
        );
    }

    /**
     * Main track event method.
     *
     * @param string $event_name Name of event.
     * @param array  $params Events params.
     * @return void
     */
    private function track_event( string $event_name, array $params = array() ): void {
        try {
            $this->get_ga4_service()->send_event( $event_name, $params );
        } catch ( Throwable $e ) {
            Logger::write_log(
                WC_Log_Levels::ERROR,
                'NovaPost GA track event error.',
                array(
                    'error' => $e->getMessage(),
                    'event' => $event_name,
                )
            );
        }
    }

    /**
     * Get the GA4 service instance.
     *
     * Creates the service if it does not already exist.
     *
     * @return Ga4Service GA4 service instance.
     */
    private function get_ga4_service(): Ga4Service {
        if ( ! isset( self::$ga4_service ) ) {
            self::$ga4_service = new Ga4Service();
        }
        return self::$ga4_service;
    }
}
