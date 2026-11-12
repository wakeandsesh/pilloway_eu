<?php
/**
 * Handle compatibility with WPML
 *
 * @package WCPBC
 * @since 4.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * WCPBC_WPML Class
 */
class WCPBC_WPML implements WCPBC_Multilang_Interface {

	use WCPBC_Multilang_Trait;

	/**
	 * Constructor
	 *
	 * @return void
	 */
	protected function __construct() {
		add_filter( 'wcml_js_lock_fields_ids', [ $this, 'js_lock_fields_ids' ] );
		add_filter( 'wcml_js_lock_fields_classes', [ $this, 'lock_fields_classes' ] );
		add_action( 'wcml_after_load_lock_fields_js', [ $this, 'load_lock_fields_js' ] );
		add_action( 'update_post_metadata', [ $this, 'after_copy_custom_field' ], 5, 3 );
		add_action( 'added_post_meta', [ $this, 'after_copy_custom_field' ], 5, 3 );
	}

	/**
	 * Returns the default WPML language.
	 *
	 * @return string
	 */
	protected function get_default_language() {
		global $sitepress;
		return is_callable( array( $sitepress, 'get_default_language' ) ) ? $sitepress->get_default_language() : '';
	}

	/**
	 * Returns the WPML languages.
	 *
	 * @return array
	 */
	protected function get_languages() {
		global $sitepress;
		return is_callable( array( $sitepress, 'get_active_languages' ) ) ? array_keys( $sitepress->get_active_languages() ) : array();
	}

	/**
	 * Returns the translate object ID.
	 *
	 * @param int    $object_id The ID of the post type (post, page, attachment, custom post) or taxonomy term.
	 * @param string $lang Slug of the lang to translate.
	 * @return int
	 */
	protected function get_translate_object_id( $object_id, $lang = null ) {
		return apply_filters( 'wpml_object_id', $object_id, get_post_type( $object_id ), false, $lang ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
	}

	/**
	 * Returns the original object ID.
	 *
	 * @param int $object_id The ID of the post type (post, page, attachment, custom post) or taxonomy term.
	 * @return int
	 */
	protected function get_original_object_id( $object_id ) {
		return apply_filters( 'wpml_original_element_id', null, $object_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
	}

	/**
	 * Is the post ID a translation?
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	protected function is_translation( $post_id ) {
		$master_post_id = $this->get_original_object_id( $post_id );
		return $master_post_id && absint( $post_id ) !== absint( $master_post_id );
	}

	/**
	 * Returns the translation post IDs for the give post.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	protected function get_translations( $post_id ) {
		$translation_post_ids = [];

		$langs = $this->get_languages();

		foreach ( $langs as $lang ) {

			$tr_id = $this->get_translate_object_id( $post_id, $lang );

			if ( $tr_id && absint( $tr_id ) !== absint( $post_id ) ) {
				$translation_post_ids[] = $tr_id;
			}
		}

		return $translation_post_ids;
	}

	/**
	 * Should copy metadata?
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	protected function should_copy_meta( $post_id ) {
		return ! $this->is_translation( $post_id );
	}

	/**
	 * Fields to lock in non-original products.
	 *
	 * @param array $fields Fields.
	 */
	public function js_lock_fields_ids( $fields ) {
		$meta_keys = [ '_price', '_regular_price', '_sale_price', '_sale_price_dates_from', '_sale_price_dates_to', '_price_method', '_sale_price_dates' ];

		foreach ( WCPBC_Pricing_Zones::get_zones() as $zone ) {
			foreach ( $meta_keys as $field ) {
				$fields[] = $zone->get_postmetakey( $field );
			}
		}
		return $fields;
	}

	/**
	 * Classes to lock in non-original products.
	 *
	 * @param array $classes Classes.
	 */
	public function lock_fields_classes( $classes ) {
		$classes[] = '_price_method_wcpbc_field';
		$classes[] = '_sale_price_dates_wcpbc_field';
		return $classes;
	}

	/**
	 * Add the JS to lock the variation fields.
	 */
	public function load_lock_fields_js() {
		wcpbc_enqueue_js(
			"jQuery( '#woocommerce-product-data' ).on( 'woocommerce_variations_loaded', function() {
				jQuery('._price_method_wcpbc_field').prop('disabled',true);
				jQuery('._price_method_wcpbc_field').after(jQuery('.wcml_lock_img').clone().removeClass('wcml_lock_img').show());

				jQuery('.wcpbc_pricing .wc_input_price').prop('readonly',true);
				jQuery('.wcpbc_pricing .wc_input_price').after(jQuery('.wcml_lock_img').clone().removeClass('wcml_lock_img').show());

				jQuery('.wcpbc_pricing .sale_price_dates_from').prop('readonly',true);
				jQuery('.wcpbc_pricing .sale_price_dates_from').after(jQuery('.wcml_lock_img').clone().removeClass('wcml_lock_img').show());
				jQuery('.wcpbc_pricing .sale_price_dates_to').prop('readonly',true);
				jQuery('.wcpbc_pricing .sale_price_dates_to').after(jQuery('.wcml_lock_img').clone().removeClass('wcml_lock_img').show());

				jQuery('.wcpbc_sale_price_dates_wrapper').prop('disabled',true);
				jQuery('.wcpbc_sale_price_dates_wrapper').after(jQuery('.wcml_lock_img').clone().removeClass('wcml_lock_img').show());
			} );"
		);
	}

	/**
	 * Enqueues a product for multilang price sync after copy custom field.
	 *
	 * @param null|bool $check      Whether to allow updating metadata for the given type.
	 * @param int       $object_id  Post ID.
	 * @param string    $meta_key   Metadata key.
	 */
	public function after_copy_custom_field( $check, $object_id, $meta_key ) {

		$original_id = false;

		if ( '_price' === $meta_key &&
			in_array( get_post_type( $object_id ), [ 'product', 'product_variation' ], true ) &&
			! in_array( get_post_status( $object_id ), [ 'trash', 'auto-draft' ], true )
		) {
			$original_id = $this->get_original_object_id( $object_id );
		}

		if ( $original_id && absint( $original_id ) !== absint( $object_id ) ) {
			// It is a translation. WPML runs the custom fields copy. Enqueue the original post.
			$this->enqueue( $original_id );
		}

		return $check;
	}
}
return WCPBC_WPML::instance();
