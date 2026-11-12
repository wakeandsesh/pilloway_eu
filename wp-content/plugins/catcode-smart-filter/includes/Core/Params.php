<?php
/**
 * The selection string.
 *
 * One canonical text form carries the whole state of the filter:
 *
 *   ccf=pa_color:red,blue;p:100-2500;s:in;r:4
 *
 *   pa_color — a facet key (an attribute taxonomy, or a custom facet slug)
 *   p        — price, always a range in the shop's display currency
 *   s        — stock: `in` / `out`
 *   r        — minimum rating, 1..5
 *   o        — on sale, `1`
 *   c        — product categories, by term id
 *   a numeric facet may also carry a range: pa_size:1.5-3
 *
 * Everything that reaches the front end goes through decode(), which drops what
 * it does not recognise. That matters beyond tidiness: the decoded array is what
 * the SQL builder interpolates, so a value that survives decoding is already a
 * slug matched against the index, an int, a float or one of a few literals.
 *
 * Facets are sorted by key and values inside a facet are sorted too, so the same
 * selection always produces the same string — cache keys, canonical URLs and the
 * landing-page lookup all depend on that stability.
 *
 * @package CatCodeSmartFilter
 */

namespace CatCode\SmartFilter\Core;

defined( 'ABSPATH' ) || exit;

class Params {

	/** Reserved keys that are not facet slugs. */
	public const RESERVED = array( 'p', 's', 'r', 'o', 'c' );

	public static function is_range( string $value ): bool {
		return (bool) preg_match( '/^\d+(\.\d+)?-\d+(\.\d+)?$/', $value );
	}

	/**
	 * @return array{from: float, to: float}|null
	 */
	public static function range_parts( string $value ): ?array {
		if ( ! self::is_range( $value ) ) {
			return null;
		}

		list( $from, $to ) = explode( '-', $value, 2 );

		$from = (float) $from;
		$to   = (float) $to;

		if ( $from > $to ) {
			$swap = $from;
			$from = $to;
			$to   = $swap;
		}

		return array(
			'from' => $from,
			'to'   => $to,
		);
	}

	/**
	 * Text form -> normalised array. Unknown keys, empty facets and malformed
	 * values are dropped rather than repaired: a URL somebody hand-edited must
	 * not silently mean something else.
	 *
	 * @return array<string,array<int,string>>
	 */
	public static function decode( string $selection ): array {
		$out = array();

		foreach ( explode( self::SEP_FACET_CHAR, $selection ) as $part ) {
			$part = trim( $part );

			if ( '' === $part || false === strpos( $part, Settings::SEP_VALUE ) ) {
				continue;
			}

			list( $key, $raw ) = explode( Settings::SEP_VALUE, $part, 2 );

			$key = self::clean_key( $key );
			$raw = trim( $raw );

			if ( '' === $key || '' === $raw ) {
				continue;
			}

			$values = self::decode_values( $key, $raw );

			if ( ! $values ) {
				continue;
			}

			// A repeated key merges instead of overwriting: ?ccf=pa_color:red;pa_color:blue
			if ( isset( $out[ $key ] ) && ! self::is_range( $values[0] ) && ! self::is_range( $out[ $key ][0] ) ) {
				$values = array_merge( $out[ $key ], $values );
			}

			$values = array_values( array_unique( $values ) );

			sort( $values, SORT_NATURAL );

			$out[ $key ] = $values;
		}

		ksort( $out, SORT_NATURAL );

		return $out;
	}

	/** `;` as a class constant expression, kept readable at the call site. */
	private const SEP_FACET_CHAR = ';';

	/**
	 * A facet key is either a reserved literal or a taxonomy/slug-shaped string.
	 * Anything else is refused outright — the key ends up in a SQL comparison.
	 */
	private static function clean_key( string $key ): string {
		$key = trim( $key );

		if ( in_array( $key, self::RESERVED, true ) ) {
			return $key;
		}

		return (bool) preg_match( '/^[a-z0-9_\-]{1,64}$/', $key ) ? $key : '';
	}

	/**
	 * @return array<int,string>
	 */
	private static function decode_values( string $key, string $raw ): array {
		if ( 'p' === $key ) {
			return self::is_range( $raw ) ? array( self::canonical_range( $raw ) ) : array();
		}

		if ( 's' === $key ) {
			return in_array( $raw, array( 'in', 'out' ), true ) ? array( $raw ) : array();
		}

		if ( 'o' === $key ) {
			return '1' === $raw ? array( '1' ) : array();
		}

		if ( 'r' === $key ) {
			$rating = (int) $raw;

			return ( $rating >= 1 && $rating <= 5 ) ? array( (string) $rating ) : array();
		}

		if ( 'c' === $key ) {
			return self::int_list( $raw );
		}

		if ( self::is_range( $raw ) ) {
			return array( self::canonical_range( $raw ) );
		}

		return self::slug_list( $raw );
	}

	/**
	 * @return array<int,string>
	 */
	private static function int_list( string $raw ): array {
		$out = array();

		foreach ( explode( Settings::SEP_LIST, $raw ) as $value ) {
			$value = trim( $value );

			if ( preg_match( '/^\d+$/', $value ) && (int) $value > 0 ) {
				$out[ (int) $value ] = (string) (int) $value;
			}
		}

		return array_values( $out );
	}

	/**
	 * @return array<int,string>
	 */
	private static function slug_list( string $raw ): array {
		$out = array();

		foreach ( explode( Settings::SEP_LIST, $raw ) as $value ) {
			$value = trim( $value );

			// No rawurldecode here on purpose. PHP has already decoded the query
			// string once, and a Cyrillic term slug is stored percent-encoded by
			// WordPress itself (`червоний` lives in the table as `%d1%87%d0%b5…`).
			// Decoding a second time turns that stored slug into letters that match
			// nothing, and every filter on a Ukrainian or Russian catalogue returns
			// zero products.
			//
			// `%` is therefore part of the allowed set, and the pattern still keeps
			// anything that is not slug-shaped out of the query.
			if ( '' !== $value && preg_match( '/^[\p{L}\p{N}_\-\.%]{1,190}$/u', $value ) ) {
				$out[ $value ] = $value;
			}
		}

		return array_values( $out );
	}

	/** "0100-2500.00" -> "100-2500" so one range has one spelling. */
	private static function canonical_range( string $raw ): string {
		$parts = self::range_parts( $raw );

		if ( ! $parts ) {
			return '';
		}

		return self::num( $parts['from'] ) . '-' . self::num( $parts['to'] );
	}

	public static function num( float $value ): string {
		$out = rtrim( rtrim( number_format( $value, 4, '.', '' ), '0' ), '.' );

		return ( '' === $out || '-0' === $out ) ? '0' : $out;
	}

	/**
	 * @param array<string,array<int,string>> $selection
	 */
	public static function encode( array $selection ): string {
		$parts = array();

		ksort( $selection, SORT_NATURAL );

		foreach ( $selection as $key => $values ) {
			$values = array_values( array_unique( array_map( 'strval', (array) $values ) ) );

			if ( ! $values ) {
				continue;
			}

			sort( $values, SORT_NATURAL );

			$parts[] = $key . Settings::SEP_VALUE . implode( Settings::SEP_LIST, $values );
		}

		return implode( Settings::SEP_FACET, $parts );
	}

	/**
	 * The selection with one value flipped. Ranges replace, lists toggle — which
	 * is what a shopper expects from a slider versus a checkbox.
	 *
	 * @param array<string,array<int,string>> $selection
	 * @return array<string,array<int,string>>
	 */
	public static function toggle( array $selection, string $key, string $value ): array {
		if ( self::is_range( $value ) || 'p' === $key || 'r' === $key ) {
			$selection[ $key ] = array( $value );

			return $selection;
		}

		$current = isset( $selection[ $key ] ) ? $selection[ $key ] : array();

		if ( in_array( $value, $current, true ) ) {
			$current = array_values( array_diff( $current, array( $value ) ) );
		} else {
			$current[] = $value;
		}

		if ( $current ) {
			$selection[ $key ] = $current;
		} else {
			unset( $selection[ $key ] );
		}

		return $selection;
	}

	/**
	 * @param array<string,array<int,string>> $selection
	 * @return array<string,array<int,string>>
	 */
	public static function without( array $selection, string $key, ?string $value = null ): array {
		if ( null === $value ) {
			unset( $selection[ $key ] );

			return $selection;
		}

		return self::toggle( $selection, $key, $value );
	}

	/**
	 * @param array<string,array<int,string>> $selection
	 */
	public static function is_selected( array $selection, string $key, string $value ): bool {
		return isset( $selection[ $key ] ) && in_array( $value, $selection[ $key ], true );
	}

	/** How many facets the selection touches — the SEO rules key off this. */
	public static function depth( array $selection ): int {
		return count( $selection );
	}

	/** The selection as it arrived in the query string. */
	public static function from_request(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only listing state.
		$raw = isset( $_GET[ Settings::PARAM ] ) ? wp_unslash( $_GET[ Settings::PARAM ] ) : '';

		return is_string( $raw ) ? self::decode( $raw ) : array();
	}
}
