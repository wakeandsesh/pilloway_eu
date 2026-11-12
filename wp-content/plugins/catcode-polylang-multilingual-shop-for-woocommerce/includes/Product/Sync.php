<?php
/**
 * Keeps the shared data of translations in step: whichever language is edited,
 * the others follow (prices, stock, SKU… — see Fields::groups()). The product
 * type follows too (simple ↔ variable ↔ grouped ↔ external). Variations follow
 * their counterparts; a variation added or deleted in one language is added or
 * deleted in the others.
 *
 * @package CatCodePolylangShop
 */

namespace CatCode\PolylangShop\Product;

use CatCode\PolylangShop\Core\Lang;

defined( 'ABSPATH' ) || exit;

class Sync {

	/** @var int >0 while this class (or the translator) writes — saves it causes are not synced back. */
	private static $paused = 0;

	/** @var array<int,bool> Parents whose variation prices must be re-synced at the end. */
	private static $dirty_parents = array();

	public function register(): void {
		add_action( 'woocommerce_update_product', array( $this, 'product_saved' ), 20, 2 );
		add_action( 'woocommerce_new_product_variation', array( $this, 'variation_created' ), 20, 2 );
		add_action( 'woocommerce_update_product_variation', array( $this, 'variation_saved' ), 20, 2 );
		add_action( 'woocommerce_before_delete_product_variation', array( $this, 'variation_deleted' ), 20 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'variation_saved_in_admin' ), 20 );
		add_action( 'shutdown', array( __CLASS__, 'flush_parents' ), 5 );
	}

	public static function pause(): void {
		++self::$paused;
	}

	public static function resume(): void {
		self::$paused = max( 0, self::$paused - 1 );
	}

	public static function paused(): bool {
		return self::$paused > 0;
	}

	/**
	 * @param int              $product_id Product ID.
	 * @param \WC_Product|null $product    Product.
	 */
	public function product_saved( $product_id, $product = null ): void {
		if ( self::paused() || ! Lang::ready() ) {
			return;
		}
		$product = $product instanceof \WC_Product ? $product : wc_get_product( $product_id );
		if ( ! $product || $product->is_type( 'variation' ) ) {
			return;
		}
		self::push_product( $product );
	}

	/** Copy the shared data of $product onto all of its translations. */
	public static function push_product( \WC_Product $product ): void {
		$others = Lang::other_translations( $product->get_id() );
		if ( empty( $others ) ) {
			return;
		}
		$groups = Fields::active();
		self::pause();
		try {
			foreach ( $others as $lang => $tr_id ) {
				$tr = wc_get_product( $tr_id );
				if ( ! $tr ) {
					continue;
				}
				$tr = self::match_type( $product, $tr );
				Fields::copy_product( $product, $tr, (string) $lang, $groups, false );
				$tr->save();
				if ( in_array( 'taxonomies', $groups, true ) ) {
					Terms::copy_extra( $product->get_id(), (int) $tr_id, (string) $lang );
				}

				if ( $product->is_type( 'variable' ) && $tr->is_type( 'variable' ) ) {
					Variations::link_existing( $product->get_id(), $tr_id, (string) $lang );
					foreach ( $product->get_children() as $child_id ) {
						$child = wc_get_product( $child_id );
						if ( ! $child instanceof \WC_Product_Variation ) {
							continue;
						}
						$sibling = Variations::find( $tr_id, Variations::group_of( (int) $child_id ) );
						if ( $sibling <= 0 ) {
							Variations::create_counterpart( $child, $tr_id, (string) $lang, $groups );
						}
					}
					self::$dirty_parents[ $tr_id ] = true;
				}
			}
		} finally {
			self::resume();
		}
		self::flush_parents();
	}

	/**
	 * The translation takes the type of the product that was saved, the way the product
	 * screen changes it: WooCommerce stores the new type and, when a variable product
	 * becomes another type, removes its variations (in that language only — see
	 * variation_deleted()).
	 */
	private static function match_type( \WC_Product $source, \WC_Product $tr ): \WC_Product {
		$type = $source->get_type();
		if ( $tr->get_type() === $type || $tr->is_type( 'variation' ) ) {
			return $tr;
		}
		$class = \WC_Product_Factory::get_product_classname( $tr->get_id(), $type );
		if ( ! is_string( $class ) || ! class_exists( $class ) ) {
			return $tr;
		}
		$converted = new $class( $tr->get_id() );
		if ( ! $converted instanceof \WC_Product || $converted->get_type() !== $type ) {
			return $tr;
		}
		$converted->save();
		$fresh = wc_get_product( $tr->get_id() );
		return $fresh instanceof \WC_Product ? $fresh : $converted;
	}

	/** Variations belong under variable parents only. */
	private static function takes_variations( int $parent_id ): bool {
		return 'variable' === \WC_Product_Factory::get_product_type( $parent_id );
	}

	/**
	 * A variation was added (admin "Add variation", REST, import): add it to the
	 * translated parents too.
	 *
	 * @param int                        $variation_id Variation ID.
	 * @param \WC_Product_Variation|null $variation    Variation.
	 */
	public function variation_created( $variation_id, $variation = null ): void {
		if ( self::paused() || ! Lang::ready() ) {
			return;
		}
		$variation = $variation instanceof \WC_Product_Variation ? $variation : wc_get_product( $variation_id );
		if ( ! $variation instanceof \WC_Product_Variation ) {
			return;
		}
		$parent = $variation->get_parent_id();
		$others = Lang::other_translations( $parent );
		if ( empty( $others ) ) {
			return;
		}
		$groups = Fields::active();
		self::pause();
		try {
			foreach ( $others as $lang => $tr_parent ) {
				if ( ! self::takes_variations( (int) $tr_parent ) ) {
					continue;
				}
				Variations::create_counterpart( $variation, (int) $tr_parent, (string) $lang, $groups );
				self::$dirty_parents[ (int) $tr_parent ] = true;
			}
		} finally {
			self::resume();
		}
	}

	/**
	 * @param int                        $variation_id Variation ID.
	 * @param \WC_Product_Variation|null $variation    Variation.
	 */
	public function variation_saved( $variation_id, $variation = null ): void {
		if ( self::paused() || ! Lang::ready() ) {
			return;
		}
		$variation = $variation instanceof \WC_Product_Variation ? $variation : wc_get_product( $variation_id );
		if ( ! $variation instanceof \WC_Product_Variation ) {
			return;
		}
		self::push_variation( $variation );
	}

	/**
	 * WooCommerce's own admin screen saves variations and then re-syncs only the
	 * parent being edited — the translated parents are re-synced here.
	 *
	 * @param int $variation_id Variation ID.
	 */
	public function variation_saved_in_admin( $variation_id ): void {
		foreach ( Variations::siblings( (int) $variation_id ) as $sibling ) {
			$parent = (int) wp_get_post_parent_id( $sibling );
			if ( $parent > 0 ) {
				self::$dirty_parents[ $parent ] = true;
			}
		}
	}

	public static function push_variation( \WC_Product_Variation $variation ): void {
		$parent = $variation->get_parent_id();
		$others = Lang::other_translations( $parent );
		if ( empty( $others ) ) {
			return;
		}
		$groups = Fields::active();
		$group  = Variations::ensure_group( $variation->get_id() );
		self::pause();
		try {
			foreach ( $others as $lang => $tr_parent ) {
				if ( ! self::takes_variations( (int) $tr_parent ) ) {
					continue;
				}
				$sibling_id = Variations::find( (int) $tr_parent, $group );
				if ( $sibling_id <= 0 ) {
					Variations::link_existing( $parent, (int) $tr_parent, (string) $lang );
					$sibling_id = Variations::find( (int) $tr_parent, $group );
				}
				if ( $sibling_id <= 0 ) {
					Variations::create_counterpart( $variation, (int) $tr_parent, (string) $lang, $groups );
				} else {
					$sibling = wc_get_product( $sibling_id );
					if ( $sibling instanceof \WC_Product_Variation ) {
						Fields::copy_variation( $variation, $sibling, (string) $lang, $groups, false );
						$sibling->save();
					}
				}
				self::$dirty_parents[ (int) $tr_parent ] = true;
			}
		} finally {
			self::resume();
		}
	}

	/**
	 * A variation deleted in one language goes in the others too.
	 *
	 * @param int $variation_id Variation ID.
	 */
	public function variation_deleted( $variation_id ): void {
		if ( self::paused() || ! Lang::ready() ) {
			return;
		}
		// The whole product is being deleted, or turned from variable into another type, in
		// this language only — its translations keep their variations.
		if ( doing_action( 'delete_post' ) || doing_action( 'before_delete_post' ) || doing_action( 'woocommerce_product_type_changed' ) ) {
			return;
		}
		$siblings = Variations::siblings( (int) $variation_id );
		if ( empty( $siblings ) ) {
			return;
		}
		self::pause();
		try {
			foreach ( $siblings as $sibling_id ) {
				$sibling = wc_get_product( $sibling_id );
				if ( $sibling instanceof \WC_Product_Variation ) {
					$parent = $sibling->get_parent_id();
					$sibling->delete( true );
					self::$dirty_parents[ $parent ] = true;
				}
			}
		} finally {
			self::resume();
		}
	}

	/** Variable parents re-read their price range and stock status from the children. */
	public static function flush_parents(): void {
		if ( empty( self::$dirty_parents ) || ! class_exists( 'WC_Product_Variable' ) ) {
			return;
		}
		$ids                 = array_keys( self::$dirty_parents );
		self::$dirty_parents = array();
		self::pause();
		try {
			foreach ( $ids as $id ) {
				if ( 'product' === get_post_type( $id ) ) {
					\WC_Product_Variable::sync( $id );
					wc_delete_product_transients( $id );
				}
			}
		} finally {
			self::resume();
		}
	}
}
