<?php
/**
 * The classic widget, so the block lands in any theme that still has a sidebar.
 *
 * A block variant is not needed to place this: the shortcode works inside a
 * block template, and the widget covers the classic ones. Keeping one renderer
 * behind all three means the markup cannot drift between placements.
 *
 * @package CatCodeSmartFilter
 */

namespace CatCode\SmartFilter\Frontend;

use CatCode\SmartFilter\Core\Settings;

defined( 'ABSPATH' ) || exit;

class FilterWidget extends \WP_Widget {

	public function __construct() {
		parent::__construct(
			'catcode_sf_filter',
			__( 'CatCode: Smart Filter', 'catcode-smart-filter' ),
			array(
				'description' => __( 'Faceted product filter for catalogue pages.', 'catcode-smart-filter' ),
			)
		);
	}

	/**
	 * @param array<string,mixed> $args
	 * @param array<string,mixed> $instance
	 */
	public function widget( $args, $instance ) {
		if ( ! Settings::is_on( 'enabled' ) ) {
			return;
		}

		// The filter belongs on a listing. Rendering it on a product page or the
		// cart would give the shopper controls that lead nowhere.
		if ( ! function_exists( 'is_shop' ) || ! ( is_shop() || is_product_taxonomy() || ( is_search() && 'product' === get_query_var( 'post_type' ) ) ) ) {
			return;
		}

		$html = Renderer::render();

		if ( '' === $html ) {
			return;
		}

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . esc_html( $instance['title'] ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built and escaped in the template.

		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * @param array<string,mixed> $instance
	 */
	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? (string) $instance['title'] : '';

		printf(
			'<p><label for="%1$s">%2$s</label><input class="widefat" id="%1$s" name="%3$s" type="text" value="%4$s"></p>',
			esc_attr( $this->get_field_id( 'title' ) ),
			esc_html__( 'Title:', 'catcode-smart-filter' ),
			esc_attr( $this->get_field_name( 'title' ) ),
			esc_attr( $title )
		);
	}

	/**
	 * @param array<string,mixed> $new_instance
	 * @param array<string,mixed> $old_instance
	 * @return array<string,mixed>
	 */
	public function update( $new_instance, $old_instance ) {
		return array(
			'title' => isset( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '',
		);
	}

	public static function register(): void {
		register_widget( __CLASS__ );
	}
}
