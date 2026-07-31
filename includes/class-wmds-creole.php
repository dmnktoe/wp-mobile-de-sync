<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Creole {
	const NL = "\n";

	/**
	 * @param string $text Raw value of the "description" field.
	 * @return string HTML, or an empty string for empty input.
	 */
	public static function to_html( $text ) {
		$text = (string) $text;
		if ( '' === trim( $text ) ) {
			return '';
		}

		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );

		$text = htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );

		$text = str_replace( array( "\r\n", "\r" ), self::NL, $text );

		$text = str_replace( '\\\\', self::NL, $text );

		$text = preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text );

		return self::assemble( explode( self::NL, $text ) );
	}

	/**
	 * @param string[] $lines
	 * @return string
	 */
	private static function assemble( array $lines ) {
		$html      = '';
		$paragraph = array();
		$list      = array();

		foreach ( $lines as $line ) {
			$trimmed = trim( $line );

			if ( '----' === $trimmed ) {
				$html .= self::flush_list( $list ) . self::flush_paragraph( $paragraph ) . '<hr>';
				continue;
			}

			if ( preg_match( '/^\*[ \t]+(.*)$/', $trimmed, $m ) ) {
				$html  .= self::flush_paragraph( $paragraph );
				$list[] = trim( $m[1] );
				continue;
			}

			if ( '' === $trimmed ) {
				$html .= self::flush_list( $list ) . self::flush_paragraph( $paragraph );
				continue;
			}

			$html       .= self::flush_list( $list );
			$paragraph[] = $trimmed;
		}

		return $html . self::flush_list( $list ) . self::flush_paragraph( $paragraph );
	}

	/**
	 * @param string[] $paragraph Emptied in place.
	 * @return string
	 */
	private static function flush_paragraph( array &$paragraph ) {
		if ( ! $paragraph ) {
			return '';
		}
		$html      = '<p>' . implode( '<br>', $paragraph ) . '</p>';
		$paragraph = array();
		return $html;
	}

	/**
	 * @param string[] $list Emptied in place.
	 * @return string
	 */
	private static function flush_list( array &$list ) {
		if ( ! $list ) {
			return '';
		}
		$html = '<ul>';
		foreach ( $list as $item ) {
			$html .= '<li>' . $item . '</li>';
		}
		$list = array();
		return $html . '</ul>';
	}

	/**
	 * @param string $value
	 * @return string
	 */
	public static function decode_field( $value ) {
		return html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8' );
	}
}
