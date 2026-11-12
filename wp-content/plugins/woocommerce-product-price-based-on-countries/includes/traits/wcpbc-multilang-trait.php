<?php
/**
 * Multilang functions.
 *
 * @since 4.0.0
 * @package WCPBC/Trait
 */

defined( 'ABSPATH' ) || exit;

/**
 * WCPBC_Multilang_Trait Trait
 */
trait WCPBC_Multilang_Trait {

	/**
	 * The single instance of the class.
	 *
	 * @var object;
	 */
	private static $instance = null;

	/**
	 * Sync queue.
	 *
	 * @var array;
	 */
	protected $queue = [];

	/**
	 * Post ID in the sync process.
	 *
	 * @var array;
	 */
	protected $doing_sync = false;

	/**
	 * Get class instance.
	 *
	 * @return object Instance.
	 */
	final public static function instance() {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing.
	 */
	final public function __wakeup() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Unserializing instances of this class is forbidden.', 'woocommerce-product-price-based-on-countries' ), '4.6' );
		die();
	}

	/**
	 * Returns the translation post IDs for the give post.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	protected function get_translations( $post_id ) {
		return [];
	}

	/**
	 * Should copy metadata?
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	protected function should_copy_meta( $post_id ) {
		return true;
	}

	/**
	 * Enqueue a post ID for multilang sync.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $zone_id Zone ID. Optional.
	 */
	public function enqueue( $post_id, $zone_id = false ) {

		if ( absint( $post_id ) === $this->doing_sync ) {
			return;
		}

		if ( ! is_array( $this->queue ) ) {
			$this->queue = [];
		}

		if ( ! isset( $this->queue[ $post_id ] ) ) {
			$this->queue[ $post_id ] = [];
		}

		$zone_ids = $zone_id ? [ $zone_id ] : wc_list_pluck( WCPBC_Pricing_Zones::get_zones(), 'get_id' );

		foreach ( $zone_ids as $id ) {
			if ( in_array( $id, $this->queue[ $post_id ], true ) ) {
				continue;
			}

			$this->queue[ $post_id ][] = $id;
		}
	}

	/**
	 * Syncs the product meta with the translations.
	 *
	 * @param int                $post_id Post ID.
	 * @param int[]              $translations Array of translations.
	 * @param WCPBC_Pricing_Zone $zone Pricing zone instance.
	 */
	protected function sync_metadata( $post_id, $translations, $zone ) {

		$metadata = $this->get_metadata( $post_id, $zone );

		foreach ( $translations as $tr_post_id ) {

			$this->doing_sync = absint( $tr_post_id );

			$tr_metakeys = array_keys( $this->get_metadata( $tr_post_id, $zone ) );

			foreach ( $tr_metakeys as $meta_key ) {

				if ( ! isset( $metadata[ $meta_key ] ) ) {
					$zone->delete_postmeta( $tr_post_id, $meta_key );
				}
			}

			foreach ( $metadata as $meta_key => $meta_value ) {
				$zone->set_postmeta( $tr_post_id, $meta_key, $meta_value );
			}
		}

		$this->doing_sync = false;
	}

	/**
	 * Returns post metadata. Fix bug on WMPL.
	 *
	 * @see https://wordpress.org/support/topic/plugin-breaks-get_post_meta-function-for-product-variations/
	 * @param int                $post_id Post ID.
	 * @param WCPBC_Pricing_Zone $zone Pricing zone instance.
	 */
	protected function get_metadata( $post_id, $zone ) {
		add_filter( 'get_post_metadata', '__return_null', 99999 ); // Remove get_post_metadata filters.

		$metadata = $zone->get_postmeta( $post_id );

		remove_filter( 'get_post_metadata', '__return_null', 99999 );

		return $metadata;
	}

	/**
	 * Syncs the queue
	 */
	public function sync_queue() {

		if ( empty( $this->queue ) ) {
			return;
		}

		$zones = WCPBC_Pricing_Zones::get_zones();

		foreach ( $this->queue as $post_id => $zone_ids ) {
			if ( ! $this->should_copy_meta( $post_id ) ) {
				continue;
			}

			foreach ( $zone_ids as $id ) {
				if ( ! isset( $zones[ $id ] ) ) {
					continue;
				}

				$this->sync_metadata( $post_id, $this->get_translations( $post_id ), $zones[ $id ] );
			}
		}

		$this->queue = [];
	}
}

