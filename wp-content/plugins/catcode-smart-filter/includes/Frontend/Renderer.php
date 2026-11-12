<?php
/**
 * The sidebar.
 *
 * Everything the shopper sees is built from the same three inputs the listing
 * used: the facet definitions, the decoded selection, and the counts. Each value
 * carries the URL that would result from clicking it, so the block works with
 * JavaScript disabled, works when a theme strips the plugin's script, and is
 * crawlable — the AJAX layer only ever intercepts links that already work.
 *
 * @package CatCodeSmartFilter
 */

namespace CatCode\SmartFilter\Frontend;

use CatCode\SmartFilter\Core\Counter;
use CatCode\SmartFilter\Core\Facets;
use CatCode\SmartFilter\Core\Params;
use CatCode\SmartFilter\Core\Query;
use CatCode\SmartFilter\Core\Runtime;
use CatCode\SmartFilter\Core\Settings;

defined( 'ABSPATH' ) || exit;

class Renderer {

	public static function render(): string {
		if ( ! Settings::is_on( 'enabled' ) ) {
			return '';
		}
		Runtime::boot();

		$facets = Facets::active_with_values();

		$selection = Runtime::selection();
		$scope     = Runtime::scope();
		$counts    = Counter::counts( $facets, $selection, $scope );

		$hide_empty = 'hide' === Settings::get( 'zero_values', 'hide' );

		$blocks = array();

		foreach ( $facets as $facet ) {
			$block = self::build_facet( $facet, $selection, $counts, $hide_empty );

			if ( ! $block ) {
				continue;
			}

			// A facet whose every product shares one value cannot narrow anything.
			// Rendering it is a promise of choice that does not exist.
			if ( Settings::is_on( 'hide_single_value' ) && ! $block['selected'] && count( $block['values'] ) < 2 ) {
				continue;
			}

			$blocks[] = $block;
		}

		if ( Settings::is_on( 'facet_price' ) ) {
			$price = self::build_price( $counts, $selection );

			if ( $price ) {
				$blocks[] = $price;
			}
		}

		if ( Settings::is_on( 'facet_stock' ) ) {
			$stock = self::build_stock( $counts, $selection, $hide_empty );

			if ( $stock && ( $stock['values'] || $stock['selected'] ) ) {
				$blocks[] = $stock;
			}
		}

		if ( Settings::is_on( 'facet_onsale' ) ) {
			$onsale = self::build_onsale( $counts, $selection, $hide_empty );

			if ( $onsale && ( $onsale['values'] || $onsale['selected'] ) ) {
				$blocks[] = $onsale;
			}
		}

		if ( Settings::is_on( 'facet_rating' ) ) {
			$rating = self::build_rating( $counts, $selection, $hide_empty );

			if ( $rating && ( $rating['values'] || $rating['selected'] ) ) {
				$blocks[] = $rating;
			}
		}

		$blocks = apply_filters( 'catcode_sf_render_facet_blocks', $blocks );
		$blocks = is_array( $blocks ) ? $blocks : array();

		$has_selection = ! empty( $selection );

		// With a selection in play the block always renders, even when every facet
		// came back empty: the chips and the reset link are the only way back from
		// a dead end.
		if ( ! $blocks && ! $has_selection ) {
			return '';
		}

		$data = array(
			'heading'         => (string) Settings::get( 'heading' ) ?: __( 'Filter', 'catcode-smart-filter' ),
			'total'           => (int) ( $counts['total'] ?? 0 ),
			'facets'          => $blocks,
			'chips'           => Settings::is_on( 'chips' ) ? self::build_chips( $blocks, $selection ) : array(),
			'chips_enabled'   => Settings::is_on( 'chips' ),
			'has_selection'   => $has_selection,
			'clear_url'       => Urls::clear(),
			'ajax'            => Settings::is_on( 'ajax' ),
			'auto_apply'      => Settings::is_on( 'auto_apply' ),
			'show_counts'     => Settings::is_on( 'show_counts' ),
			'zero_values'     => (string) Settings::get( 'zero_values', 'hide' ),
			'mobile_drawer'   => Settings::is_on( 'mobile_drawer' ),
			'container'       => Settings::container(),
			'container_extra' => implode( ',', Settings::extra_containers() ),
			'count_url'       => self::count_url(),
		);

		ob_start();

		include CATCODE_SF_DIR . 'templates/filter.php';

		return (string) ob_get_clean();
	}

	/** Keep the read-only counter request in the same Polylang language. */
	private static function count_url(): string {
		$url  = add_query_arg( 'action', 'catcode_sf_count', admin_url( 'admin-ajax.php' ) );
		$lang = Query::current_language();
		return is_string( $lang ) && '' !== $lang ? add_query_arg( 'lang', $lang, $url ) : $url;
	}

	/** Resolve the attribute label saved in Ukrainian to the active store language. */
	private static function facet_label( $facet ): string {
		$taxonomy = (string) $facet->source_key;
		$lang     = Query::current_language();
		$labels   = get_option( 'pilloway_attribute_labels', array() );
		$taxonomy_labels = get_option( 'pilloway_filter_taxonomy_labels', array() );
		if ( isset( $taxonomy_labels[ $taxonomy ][ $lang ] ) ) {
			return (string) $taxonomy_labels[ $taxonomy ][ $lang ];
		}
		if ( 'attribute' === $facet->source && isset( $labels[ $taxonomy ][ $lang ] ) ) {
			return (string) $labels[ $taxonomy ][ $lang ];
		}
		if ( 'taxonomy' === $facet->source && isset( $taxonomy_labels[ $taxonomy ][ $lang ] ) ) {
			return (string) $taxonomy_labels[ $taxonomy ][ $lang ];
		}
		if ( function_exists( 'pll_translate_string' ) && '' !== $lang ) {
			return (string) pll_translate_string( (string) $facet->label, $lang );
		}
		return (string) $facet->label;
	}

	/**
	 * @param object                          $facet
	 * @param array<string,array<int,string>> $selection
	 * @param array<string,mixed>             $counts
	 * @return array<string,mixed>
	 */
	private static function build_facet( $facet, array $selection, array $counts, bool $hide_empty ): array {
		$key = (string) $facet->facet_key;

		$facet_counts = isset( $counts['values'][ $key ] ) ? $counts['values'][ $key ] : array();

		$values  = array();
		$numeric = array();

		foreach ( (array) $facet->values as $value ) {
			$slug = (string) $value->value_slug;

			$count    = isset( $facet_counts[ $slug ] ) ? (int) $facet_counts[ $slug ] : 0;
			$selected = Params::is_selected( $selection, $key, $slug );

			if ( $count < 1 && ! $selected && $hide_empty ) {
				continue;
			}

			if ( null !== $value->num_value ) {
				$numeric[] = (float) $value->num_value;
			}

			$values[] = array(
				'value'    => $slug,
				'label'    => (string) $value->label,
				'count'    => $count,
				'selected' => $selected,
				'disabled' => ( $count < 1 && ! $selected ),
				'url'      => Urls::toggle( $selection, $key, $slug ),
				'color'    => (string) $value->color,
				'image'    => $value->image_id ? wp_get_attachment_image_url( (int) $value->image_id, 'thumbnail' ) : '',
			);
		}

		if ( ! $values && empty( $selection[ $key ] ) ) {
			return array();
		}

		$block = array(
			'key'           => $key,
			'name'          => self::facet_label( $facet ),
			'hint'          => (string) $facet->hint,
			'unit'          => (string) $facet->unit,
			'widget'        => (string) $facet->widget,
			'collapsed'     => ( (int) $facet->collapsed && empty( $selection[ $key ] ) ),
			'visible_limit' => (int) ( $facet->visible_limit ?: Settings::get( 'facet_visible', 8 ) ),
			'search_box'    => ( (int) $facet->search_box || count( $values ) >= (int) Settings::get( 'search_from', 12 ) ),
			'selected'      => ! empty( $selection[ $key ] ),
			'values'        => $values,
			'range'         => array(),
			'range_url'     => '',
			'clear_url'     => Urls::remove( $selection, $key ),
		);

		if ( 'range' === $block['widget'] && $numeric ) {
			$min = floor( min( $numeric ) );
			$max = ceil( max( $numeric ) );

			// A slider whose ends meet cannot be dragged anywhere.
			if ( $max <= $min ) {
				return array();
			}

			$current = isset( $selection[ $key ][0] ) ? (string) $selection[ $key ][0] : '';
			$parts   = Params::range_parts( $current );

			$block['range'] = array(
				'min'  => $min,
				'max'  => $max,
				'from' => $parts ? $parts['from'] : $min,
				'to'   => $parts ? $parts['to'] : $max,
				'step' => (int) $facet->decimals > 0 ? 1 / pow( 10, (int) $facet->decimals ) : 1,
			);

			$block['range_url'] = Urls::link( Params::without( $selection, $key ), array( 'paged' => null ) );
		}

		return $block;
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function build_price( array $counts, array $selection ): array {
		$bounds = isset( $counts['price'] ) ? $counts['price'] : array( 'min' => 0, 'max' => 0 );

		$min = (float) $bounds['min'];
		$max = (float) $bounds['max'];

		if ( $max <= $min ) {
			return array();
		}

		$current = isset( $selection['p'][0] ) ? (string) $selection['p'][0] : '';
		$parts   = Params::range_parts( $current );

		$step = (float) Settings::get( 'price_step', 0 );

		return array(
			'key'           => 'p',
			'name'          => __( 'Price', 'catcode-smart-filter' ),
			'hint'          => '',
			'unit'          => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '',
			'widget'        => 'range',
			'collapsed'     => false,
			'visible_limit' => 0,
			'search_box'    => false,
			'selected'      => isset( $selection['p'] ),
			'values'        => array(),
			'range'         => array(
				'min'  => $min,
				'max'  => $max,
				'from' => $parts ? $parts['from'] : $min,
				'to'   => $parts ? $parts['to'] : $max,
				'step' => $step > 0 ? $step : 1,
			),
			'range_url'     => Urls::link( Params::without( $selection, 'p' ), array( 'paged' => null ) ),
			'clear_url'     => Urls::remove( $selection, 'p' ),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function build_stock( array $counts, array $selection, bool $hide_empty ): array {
		$stock = isset( $counts['values']['s'] ) ? $counts['values']['s'] : array();

		$labels = array(
			'in'  => __( 'In stock', 'catcode-smart-filter' ),
			'out' => __( 'Out of stock', 'catcode-smart-filter' ),
		);

		$values = array();

		foreach ( $labels as $slug => $label ) {
			$count    = isset( $stock[ $slug ] ) ? (int) $stock[ $slug ] : 0;
			$selected = Params::is_selected( $selection, 's', $slug );

			if ( $count < 1 && ! $selected && $hide_empty ) {
				continue;
			}

			$values[] = array(
				'value'    => $slug,
				'label'    => $label,
				'count'    => $count,
				'selected' => $selected,
				'disabled' => ( $count < 1 && ! $selected ),
				'url'      => Urls::toggle( $selection, 's', $slug ),
				'color'    => '',
				'image'    => '',
			);
		}

		return array(
			'key'           => 's',
			'name'          => __( 'Stock status', 'catcode-smart-filter' ),
			'hint'          => '',
			'unit'          => '',
			'widget'        => 'checkbox',
			'collapsed'     => false,
			'visible_limit' => 0,
			'search_box'    => false,
			'selected'      => ! empty( $selection['s'] ),
			'values'        => $values,
			'range'         => array(),
			'range_url'     => '',
			'clear_url'     => Urls::remove( $selection, 's' ),
		);
	}

	/** @return array<string,mixed> */
	private static function build_onsale( array $counts, array $selection, bool $hide_empty ): array {
		$count    = (int) ( $counts['values']['o']['1'] ?? 0 );
		$selected = Params::is_selected( $selection, 'o', '1' );

		if ( $count < 1 && ! $selected && $hide_empty ) {
			return array();
		}

		return array(
			'key'           => 'o',
			'name'          => __( 'On sale', 'catcode-smart-filter' ),
			'hint'          => '',
			'unit'          => '',
			'widget'        => 'checkbox',
			'collapsed'     => false,
			'visible_limit' => 0,
			'search_box'    => false,
			'selected'      => $selected,
			'values'        => array(
				array(
					'value'    => '1',
					'label'    => __( 'Products on sale', 'catcode-smart-filter' ),
					'count'    => $count,
					'selected' => $selected,
					'disabled' => 0 === $count && ! $selected,
					'url'      => Urls::toggle( $selection, 'o', '1' ),
					'color'    => '',
					'image'    => '',
				),
			),
			'range'         => array(),
			'range_url'     => '',
			'clear_url'     => Urls::remove( $selection, 'o' ),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function build_rating( array $counts, array $selection, bool $hide_empty ): array {
		$rating = isset( $counts['values']['r'] ) ? $counts['values']['r'] : array();

		$values = array();

		for ( $stars = 5; $stars >= 1; $stars-- ) {
			$slug = (string) $stars;

			$count    = isset( $rating[ $slug ] ) ? (int) $rating[ $slug ] : 0;
			$selected = Params::is_selected( $selection, 'r', $slug );

			if ( $count < 1 && ! $selected && $hide_empty ) {
				continue;
			}

			$values[] = array(
				'value'    => $slug,
				'label'    => sprintf( /* translators: %s: number of stars. */ __( '%s stars and up', 'catcode-smart-filter' ), $stars ),
				'count'    => $count,
				'selected' => $selected,
				'disabled' => ( $count < 1 && ! $selected ),
				'url'      => Urls::toggle( $selection, 'r', $slug ),
				'color'    => '',
				'image'    => '',
			);
		}

		return array(
			'key'           => 'r',
			'name'          => __( 'Rating', 'catcode-smart-filter' ),
			'hint'          => '',
			'unit'          => '',
			'widget'        => 'radio',
			'collapsed'     => false,
			'visible_limit' => 0,
			'search_box'    => false,
			'selected'      => ! empty( $selection['r'] ),
			'values'        => $values,
			'range'         => array(),
			'range_url'     => '',
			'clear_url'     => Urls::remove( $selection, 'r' ),
		);
	}

	/**
	 * The selected values as removable chips, built from the rendered blocks so a
	 * chip always carries the label the shopper actually clicked — including for
	 * values a zero count would otherwise have hidden.
	 *
	 * @return array<int,array<string,string>>
	 */
	private static function build_chips( array $blocks, array $selection ): array {
		$out = array();

		foreach ( $blocks as $block ) {
			$key = (string) $block['key'];

			if ( empty( $selection[ $key ] ) ) {
				continue;
			}

			if ( 'range' === $block['widget'] ) {
				$range = $block['range'];

				$out[] = array(
					'label'      => $block['name'] . ': ' . Params::num( (float) $range['from'] ) . ' – ' . Params::num( (float) $range['to'] ) . ' ' . $block['unit'],
					'remove_url' => Urls::remove( $selection, $key ),
				);

				continue;
			}

			foreach ( $block['values'] as $value ) {
				if ( empty( $value['selected'] ) ) {
					continue;
				}

				$out[] = array(
					'label'      => (string) $value['label'],
					'remove_url' => Urls::toggle( $selection, $key, (string) $value['value'] ),
				);
			}
		}

		return $out;
	}
}
