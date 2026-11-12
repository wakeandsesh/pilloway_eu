<?php
/**
 * Server-side purchase delivery. Configure PILLOWAY_META_CAPI_TOKEN outside Git.
 */
defined( 'ABSPATH' ) || exit;

function pilloway_meta_purchase_confirmed( $order ) {
	if ( $order && 'liqpay' === $order->get_payment_method() && 'sandbox' === $order->get_meta( '_catcode_liqpay_status' ) ) {
		return false;
	}
	return $order && ( $order->is_paid() || (
		$order->has_status( 'on-hold' ) &&
		in_array( $order->get_payment_method(), array( 'cod', 'bacs', 'cheque' ), true )
	) );
}

function pilloway_meta_capi_token() {
	return defined( 'PILLOWAY_META_CAPI_TOKEN' ) ? (string) PILLOWAY_META_CAPI_TOKEN : (string) getenv( 'PILLOWAY_META_CAPI_TOKEN' );
}

/** Queue work after a confirmed order; never delay checkout with an API call. */
function pilloway_meta_queue_purchase( $order_id ) {
	if ( ! get_option('pilloway_meta_pixel_id') || ! pilloway_meta_capi_token() || ! function_exists( 'as_enqueue_async_action' ) ) {
		return;
	}
	$order = wc_get_order( $order_id );
	if ( ! pilloway_meta_purchase_confirmed( $order ) || $order->get_meta( '_pilloway_meta_purchase_sent' ) ) {
		return;
	}
	as_enqueue_async_action( 'pilloway_meta_send_purchase', array( (int) $order_id, 0 ), 'pilloway-meta', true );
}
add_action( 'woocommerce_payment_complete', 'pilloway_meta_queue_purchase', 20 );
add_action( 'woocommerce_order_status_processing', 'pilloway_meta_queue_purchase', 20 );
add_action( 'woocommerce_order_status_completed', 'pilloway_meta_queue_purchase', 20 );
add_action( 'woocommerce_order_status_on-hold', 'pilloway_meta_queue_purchase', 20 );

/** Store the browser identifiers at checkout, for the later payment callback. */
function pilloway_meta_capture_browser( $order ) {
	foreach ( array( '_fbp', '_fbc' ) as $cookie ) {
		if ( isset( $_COOKIE[ $cookie ] ) && is_string( $_COOKIE[ $cookie ] ) ) {
			$value = sanitize_text_field( wp_unslash( $_COOKIE[ $cookie ] ) );
			if ( strlen( $value ) <= 255 && preg_match( '/^fb\.\d+\.\d+\.[A-Za-z0-9_.-]+$/D', $value ) ) {
				$order->update_meta_data( '_pilloway_meta' . $cookie, $value );
			}
		}
	}
}
add_action( 'woocommerce_checkout_create_order', 'pilloway_meta_capture_browser', 20 );
add_action( 'woocommerce_store_api_checkout_update_order_from_request', 'pilloway_meta_capture_browser', 20 );

/** Build a purchase from the saved order, without exposing customer data in logs. */
function pilloway_meta_purchase_payload( $order ) {
	$user = array();
	$email = strtolower( trim( $order->get_billing_email() ) );
	$phone = preg_replace( '/\D/', '', $order->get_billing_phone() );
	if ( $email ) {
		$user['em'] = array( hash( 'sha256', $email ) );
	}
	if ( $phone ) {
		$user['ph'] = array( hash( 'sha256', $phone ) );
	}
	foreach ( array( 'fbp', 'fbc' ) as $field ) {
		$value = $order->get_meta( '_pilloway_meta_' . $field );
		if ( $value ) {
			$user[ $field ] = $value;
		}
	}
	$ip = $order->get_customer_ip_address();
	$agent = $order->get_customer_user_agent();
	if ( $ip ) {
		$user['client_ip_address'] = $ip;
	}
	if ( $agent ) {
		$user['client_user_agent'] = $agent;
	}
	$contents = array();
	foreach ( $order->get_items() as $item ) {
		$id = $item->get_variation_id() ?: $item->get_product_id();
		if ( $id ) {
			$contents[] = array( 'id' => (string) $id, 'quantity' => (int) $item->get_quantity() );
		}
	}
	$date = $order->get_date_paid() ?: $order->get_date_created();
	return array(
		'event_name'       => 'Purchase',
		'event_id'         => 'pilloway-purchase-' . $order->get_id(),
		'event_time'       => $date ? $date->getTimestamp() : time(),
		'action_source'    => 'website',
		'event_source_url' => wc_get_checkout_url(),
		'user_data'        => $user,
		'custom_data'      => array(
			'value'        => (float) $order->get_total(),
			'currency'     => $order->get_currency(),
			'content_type' => 'product',
			'content_ids'  => array_values( array_unique( array_column( $contents, 'id' ) ) ),
			'contents'     => $contents,
			'num_items'    => (int) $order->get_item_count(),
		),
	);
}

/** Acquire a purchase job lock with an atomic INSERT, including stale-lock cleanup. */
function pilloway_meta_acquire_purchase_lock( $lock ) {
    global $wpdb;
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND CAST(option_value AS UNSIGNED) < %d", $lock, time() - 300 ) );
    $rows = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $lock, (string) time() ) );
    wp_cache_delete( $lock, 'options' );
    wp_cache_delete( 'notoptions', 'options' );
    return (int) $rows > 0;
}

/** Persist acknowledgement only after Meta confirms receipt; retry transient failures. */
function pilloway_meta_send_purchase( $order_id, $attempt = 0 ) {
	if ( ! get_option('pilloway_meta_pixel_id') || ! pilloway_meta_capi_token() ) {
		return;
	}
	$order = wc_get_order( $order_id );
	if ( ! pilloway_meta_purchase_confirmed( $order ) || $order->get_meta( '_pilloway_meta_purchase_sent' ) ) {
		return;
	}
	// Atomic insert serializes jobs triggered by both payment and status hooks.
	$lock = 'pilloway_meta_purchase_lock_' . (int) $order_id;
	if ( ! pilloway_meta_acquire_purchase_lock( $lock ) ) {
		if ( function_exists( 'as_schedule_single_action' ) && $attempt < 5 ) {
			as_schedule_single_action( time() + 60, 'pilloway_meta_send_purchase', array( (int) $order_id, (int) $attempt + 1 ), 'pilloway-meta', true );
		}
		return;
	}
	try {
		$payload = array( 'data' => array( pilloway_meta_purchase_payload( $order ) ) );
		if ( defined( 'PILLOWAY_META_TEST_EVENT_CODE' ) && PILLOWAY_META_TEST_EVENT_CODE ) {
			$payload['test_event_code'] = PILLOWAY_META_TEST_EVENT_CODE;
		}
		$response = wp_remote_post(
			'https://graph.facebook.com/v25.0/' . rawurlencode((string) get_option('pilloway_meta_pixel_id', '')) . '/events',
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Authorization' => 'Bearer ' . pilloway_meta_capi_token(),
				),
				'body' => wp_json_encode( $payload ),
			)
		);
		$code = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );
		$body = is_wp_error( $response ) ? array() : json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code >= 200 && $code < 300 && ! empty( $body['events_received'] ) ) {
			$order->update_meta_data( '_pilloway_meta_purchase_sent', time() );
			$order->delete_meta_data( '_pilloway_meta_purchase_error' );
			$order->save_meta_data();
			return;
		}
		// Log only a status code; never the token, body, email, phone or URL.
		$order->update_meta_data( '_pilloway_meta_purchase_error', 'HTTP ' . (int) $code );
		$order->save_meta_data();
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->warning( 'Meta Purchase delivery failed: order ' . (int) $order_id . ', HTTP ' . (int) $code, array( 'source' => 'pilloway-meta' ) );
		}
		$transient = ! $code || $code === 429 || $code >= 500;
		if ( $transient && $attempt < 5 && function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time() + 60 * ( 2 ** (int) $attempt ), 'pilloway_meta_send_purchase', array( (int) $order_id, (int) $attempt + 1 ), 'pilloway-meta', true );
		}
	} finally {
		delete_option( $lock );
	}
}
add_action( 'pilloway_meta_send_purchase', 'pilloway_meta_send_purchase', 10, 2 );

/** Make a missing token visible to store administrators. */
function pilloway_meta_capi_notice() {
	if ( current_user_can( 'manage_woocommerce' ) && ! pilloway_meta_capi_token() ) {
		echo '<div class="notice notice-warning"><p>' . esc_html( 'Pilloway Meta: server-side Purchase tracking is disabled. Configure PILLOWAY_META_CAPI_TOKEN in wp-config.php or the server environment.' ) . '</p></div>';
	}
}
add_action( 'admin_notices', 'pilloway_meta_capi_notice' );
