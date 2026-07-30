<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts a mobile.de vehicle description into HTML.
 *
 * For the "extended vehicle description" mobile.de uses a CREOLE-flavoured
 * wiki syntax, NOT Markdown:
 *
 *   \\        line break (two backslashes; four in a row = two breaks)
 *   **bold**  bold, may span breaks and lists
 *   ----      horizontal rule, followed by an implicit break
 *   * item    bullet, each item ends with a break
 *
 * A Markdown parser is the wrong tool here: it knows neither \\ nor ----, so
 * literal backslashes survive into the output, and it reads a single * as an
 * italic marker, which tears the bullet lists apart.
 *
 * Safety: the text is escaped in full first, and only then are our own tags
 * inserted. HTML coming from the feed is therefore displayed but never
 * executed - per mobile.de's documentation, dealers may enter HTML.
 */
class WMDS_Creole {

	/** Placeholder for line breaks during conversion. */
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

		// The API leaves HTML entities inside values, e.g. &quot; in the middle
		// of a colour name. Decode first so they are escaped correctly below
		// instead of surfacing as &amp;quot; on the front end.
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );

		// From here on nothing is HTML - everything else we insert ourselves.
		$text = htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );

		// Normalise line endings.
		$text = str_replace( array( "\r\n", "\r" ), self::NL, $text );

		// CREOLE break: two backslashes. Four in a row therefore yield two
		// breaks, i.e. a paragraph - exactly as seen in the live feed.
		$text = str_replace( '\\\\', self::NL, $text );

		// Bold first: it may span breaks. Afterwards no ** is left that could
		// interfere with bullet detection.
		$text = preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text );

		return self::assemble( explode( self::NL, $text ) );
	}

	/**
	 * Assembles paragraphs, bullet lists and rules from the lines.
	 *
	 * @param string[] $lines
	 * @return string
	 */
	private static function assemble( array $lines ) {
		$html      = '';
		$paragraph = array();
		$list      = array();

		foreach ( $lines as $line ) {
			$trimmed = trim( $line );

			// Rule: exactly four hyphens, surrounding whitespace allowed.
			if ( '----' === $trimmed ) {
				$html .= self::flush_list( $list ) . self::flush_paragraph( $paragraph ) . '<hr>';
				continue;
			}

			// Bullet: one asterisk followed by whitespace. A second asterisk
			// would have been bold and was already handled above.
			if ( preg_match( '/^\*[ \t]+(.*)$/', $trimmed, $m ) ) {
				$html  .= self::flush_paragraph( $paragraph );
				$list[] = trim( $m[1] );
				continue;
			}

			// A blank line closes both the list and the paragraph.
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
	 * Decodes HTML entities in a plain field value.
	 *
	 * Affects fields such as manufacturerColorName, where the API returns
	 * values like  BLACK SOLID &quot;STONE&quot; / Solid . Without this step
	 * the &quot; shows up verbatim on the vehicle page.
	 *
	 * @param string $value
	 * @return string
	 */
	public static function decode_field( $value ) {
		return html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8' );
	}
}
