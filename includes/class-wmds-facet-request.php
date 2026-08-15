<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What a request means: reading it, and writing it back out as a URL.
 *
 * Everything here is pure. No database, no WordPress state beyond the two
 * calls that resolve a permalink, which is why the whole of it is testable
 * on its own.
 */
class WMDS_Facet_Request {
	/**
	 * Reads a request into the normalised selection every other method takes.
	 *
	 * Pure: it touches no superglobal and no WordPress function, so the
	 * decisions it makes are testable on their own.
	 *
	 * @param array $request Raw request values, already unslashed.
	 * @return array<string, mixed>
	 */
	public static function parse( array $request ) {
		$selection = array();

		foreach ( WMDS_Facets::definitions() as $key => $facet ) {
			$type = isset( $facet['type'] ) ? $facet['type'] : 'select';

			if ( 'range' === $type ) {
				$range = self::parse_range( $request, $key );
				if ( $range ) {
					$selection[ $key ] = $range;
				}
				continue;
			}

			if ( 'search' === $type ) {
				$term = self::clean( isset( $request[ WMDS_Facets::PREFIX . $key ] ) ? $request[ WMDS_Facets::PREFIX . $key ] : '' );
				if ( '' !== $term ) {
					$selection[ $key ] = $term;
				}
				continue;
			}

			$values = self::parse_values( isset( $request[ WMDS_Facets::PREFIX . $key ] ) ? $request[ WMDS_Facets::PREFIX . $key ] : null );
			if ( ! $values ) {
				continue;
			}

			$selection[ $key ] = ( 'checkbox' === $type ) ? $values : array( $values[0] );
		}

		$sort = self::clean( isset( $request[ WMDS_Facets::PREFIX . 'sort' ] ) ? $request[ WMDS_Facets::PREFIX . 'sort' ] : '' );
		if ( '' !== $sort && array_key_exists( $sort, WMDS_Facets::sorts() ) ) {
			$selection['sort'] = $sort;
		}

		return $selection;
	}

	/**
	 * The selection the current request carries.
	 *
	 * The one method here that reads the world. Everything downstream takes
	 * the array it returns, which is why everything downstream is pure.
	 *
	 * @return array<string, mixed>
	 */
	public static function selection() {
		$request = isset( $_GET ) ? wp_unslash( $_GET ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- a read-only list filter, and parse() normalises every value it keeps.

		return self::parse( is_array( $request ) ? $request : array() );
	}

	/**
	 * @param array  $request
	 * @param string $key
	 * @return array{min?:float,max?:float}
	 */
	public static function parse_range( array $request, $key ) {
		$out = array();

		foreach ( array( 'min', 'max' ) as $bound ) {
			$field = WMDS_Facets::PREFIX . $key . '_' . $bound;
			if ( ! isset( $request[ $field ] ) ) {
				continue;
			}

			$number = WMDS_Num::from_input( self::clean( $request[ $field ] ) );
			if ( null === $number ) {
				continue;
			}

			$out[ $bound ] = $number;
		}

		if ( isset( $out['min'], $out['max'] ) && $out['min'] > $out['max'] ) {
			$swap       = $out['min'];
			$out['min'] = $out['max'];
			$out['max'] = $swap;
		}

		return $out;
	}

	/**
	 * @param mixed $raw
	 * @return string[]
	 */
	public static function parse_values( $raw ) {
		if ( null === $raw ) {
			return array();
		}

		$values = is_array( $raw ) ? $raw : array( $raw );
		$out    = array();

		foreach ( $values as $value ) {
			if ( is_array( $value ) ) {
				continue;
			}
			$value = self::clean( $value );
			if ( '' !== $value && ! in_array( $value, $out, true ) ) {
				$out[] = $value;
			}
		}

		return $out;
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	public static function clean( $value ) {
		return WMDS_Str::scrub( $value, WMDS_Facets::MAX_LENGTH );
	}

	/**
	 * @param array $selection
	 * @return bool Whether anything narrows the inventory.
	 */
	public static function is_filtered( array $selection ) {
		foreach ( $selection as $key => $value ) {
			if ( 'sort' !== $key ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array $selection
	 * @return array<int, array{key:string,label:string,value:string,url:string}>
	 */
	public static function chips( array $selection ) {
		$facets = WMDS_Facets::definitions();
		$out    = array();

		foreach ( $selection as $key => $value ) {
			if ( 'sort' === $key || ! isset( $facets[ $key ] ) ) {
				continue;
			}

			$label = isset( $facets[ $key ]['label'] ) ? $facets[ $key ]['label'] : $key;
			$type  = isset( $facets[ $key ]['type'] ) ? $facets[ $key ]['type'] : 'select';

			if ( 'range' === $type ) {
				$out[] = array(
					'key'   => $key,
					'label' => $label,
					'value' => self::range_label( $value, isset( $facets[ $key ]['unit'] ) ? $facets[ $key ]['unit'] : '' ),
					'url'   => self::url_without( $selection, $key ),
				);
				continue;
			}

			if ( 'search' === $type ) {
				$out[] = array(
					'key'   => $key,
					'label' => $label,
					'value' => (string) $value,
					'url'   => self::url_without( $selection, $key ),
				);
				continue;
			}

			foreach ( (array) $value as $single ) {
				$out[] = array(
					'key'   => $key,
					'label' => $label,
					'value' => (string) $single,
					'url'   => self::url_without( $selection, $key, (string) $single ),
				);
			}
		}

		return $out;
	}

	/**
	 * @param array  $range
	 * @param string $unit
	 * @return string
	 */
	public static function range_label( array $range, $unit = '' ) {
		$unit = ( '' === $unit ) ? '' : ' ' . $unit;

		if ( isset( $range['min'], $range['max'] ) ) {
			/* translators: 1: lower bound, 2: upper bound including the unit. */
			return sprintf( __( '%1$s to %2$s', 'wp-mobile-de-sync' ), self::number( $range['min'] ), self::number( $range['max'] ) . $unit );
		}
		if ( isset( $range['min'] ) ) {
			/* translators: %s: lower bound including the unit. */
			return sprintf( __( 'from %s', 'wp-mobile-de-sync' ), self::number( $range['min'] ) . $unit );
		}
		if ( isset( $range['max'] ) ) {
			/* translators: %s: upper bound including the unit. */
			return sprintf( __( 'up to %s', 'wp-mobile-de-sync' ), self::number( $range['max'] ) . $unit );
		}

		return '';
	}

	/**
	 * @param float $value
	 * @return string
	 */
	public static function number( $value ) {
		return number_format_i18n( (float) $value );
	}

	/**
	 * Turns a selection back into query arguments.
	 *
	 * @param array $selection
	 * @return array<string, mixed>
	 */
	public static function to_query( array $selection ) {
		$facets = WMDS_Facets::definitions();
		$out    = array();

		foreach ( $selection as $key => $value ) {
			if ( 'sort' === $key ) {
				$out[ WMDS_Facets::PREFIX . 'sort' ] = $value;
				continue;
			}
			if ( ! isset( $facets[ $key ] ) ) {
				continue;
			}

			$type = isset( $facets[ $key ]['type'] ) ? $facets[ $key ]['type'] : 'select';

			if ( 'range' === $type ) {
				foreach ( array( 'min', 'max' ) as $bound ) {
					if ( isset( $value[ $bound ] ) ) {
						$out[ WMDS_Facets::PREFIX . $key . '_' . $bound ] = $value[ $bound ];
					}
				}
				continue;
			}

			$out[ WMDS_Facets::PREFIX . $key ] = ( 'search' === $type ) ? $value : array_values( (array) $value );
		}

		return $out;
	}

	/**
	 * @param array  $selection
	 * @param string $key
	 * @param string $value Only that value, for a facet that holds several.
	 * @return string
	 */
	public static function url_without( array $selection, $key, $value = '' ) {
		if ( '' !== $value && isset( $selection[ $key ] ) && is_array( $selection[ $key ] ) ) {
			$selection[ $key ] = array_values( array_diff( (array) $selection[ $key ], array( $value ) ) );
			if ( ! $selection[ $key ] ) {
				unset( $selection[ $key ] );
			}
		} else {
			unset( $selection[ $key ] );
		}

		return self::url( $selection );
	}

	/**
	 * @param array $selection
	 * @return string
	 */
	public static function url( array $selection ) {
		$base = self::base_url();
		$args = self::to_query( $selection );

		if ( ! $args ) {
			return $base;
		}

		return $base . ( ( false === strpos( $base, '?' ) ) ? '?' : '&' ) . http_build_query( $args );
	}

	/** @return string Where the filter form submits to. */
	public static function base_url() {
		if ( is_singular() ) {
			$link = get_permalink();
			if ( $link ) {
				return $link;
			}
		}

		$archive = get_post_type_archive_link( WMDS_CPT );

		return $archive ? $archive : home_url( '/' );
	}
}
