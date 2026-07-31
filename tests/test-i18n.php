<?php

require_once __DIR__ . '/bootstrap.php';

$po = dirname( __DIR__ ) . '/languages/wp-mobile-de-sync-de_DE.po';
$mo = dirname( __DIR__ ) . '/languages/wp-mobile-de-sync-de_DE.mo';

/**
 * Reads a .mo the way WordPress does, including the two length checks in
 * MO::import_from_reader() that a hand-rolled writer can get wrong without
 * producing a file that looks broken to anything else.
 *
 * @param string $path
 * @return array{ok:bool,reason:string,entries:array<string,string>}
 */
function wmds_read_mo( $path ) {
	$fail = function ( $reason ) {
		return array(
			'ok'      => false,
			'reason'  => $reason,
			'entries' => array(),
		);
	};

	$data = file_get_contents( $path );
	if ( false === $data || strlen( $data ) < 28 ) {
		return $fail( 'shorter than a header' );
	}

	$magic = unpack( 'V', substr( $data, 0, 4 ) );
	if ( 0x950412de !== $magic[1] ) {
		return $fail( 'not little-endian MO magic' );
	}

	$head = unpack( 'Vrevision/Vtotal/Voriginals/Vtranslations/Vhash_length/Vhash_addr', substr( $data, 4, 24 ) );

	if ( 0 !== $head['revision'] ) {
		return $fail( 'revision is not 0' );
	}

	// WordPress derives each table's length from the address of the next
	// block. hash_addr therefore has to sit right behind the translation
	// table even when the hash table itself is empty.
	if ( $head['translations'] - $head['originals'] !== $head['total'] * 8 ) {
		return $fail( 'originals table length does not match the string count' );
	}
	if ( $head['hash_addr'] - $head['translations'] !== $head['total'] * 8 ) {
		return $fail( 'translations table length does not match the string count' );
	}

	$entries = array();

	for ( $i = 0; $i < $head['total']; $i++ ) {
		$o = unpack( 'Vlength/Vpos', substr( $data, $head['originals'] + ( $i * 8 ), 8 ) );
		$t = unpack( 'Vlength/Vpos', substr( $data, $head['translations'] + ( $i * 8 ), 8 ) );

		if ( $o['pos'] + $o['length'] > strlen( $data ) || $t['pos'] + $t['length'] > strlen( $data ) ) {
			return $fail( 'a string reaches past the end of the file' );
		}

		$entries[ substr( $data, $o['pos'], $o['length'] ) ] = substr( $data, $t['pos'], $t['length'] );
	}

	return array(
		'ok'      => true,
		'reason'  => '',
		'entries' => $entries,
	);
}

/**
 * @param string $path
 * @return array<string,string> msgid (NUL-joined for plurals) => msgstr.
 */
function wmds_read_po( $path ) {
	$out     = array();
	$id      = null;
	$ctx     = '';
	$pending = '';
	$pl      = null;
	$str     = array();
	$in      = null;

	$chunk = function ( $line ) {
		if ( ! preg_match( '/"((?:[^"\\\\]|\\\\.)*)"\s*$/', $line, $m ) ) {
			return '';
		}
		return strtr(
			$m[1],
			array(
				'\\n'  => "\n",
				'\\t'  => "\t",
				'\\"'  => '"',
				'\\\\' => '\\',
			)
		);
	};

	$flush = function () use ( &$out, &$id, &$ctx, &$pl, &$str ) {
		if ( null !== $id && '' !== $id ) {
			$key = ( '' !== $ctx ) ? $ctx . "\4" . $id : $id;
			$key = ( null === $pl ) ? $key : $key . "\0" . $pl;
			ksort( $str );
			$out[ $key ] = implode( "\0", $str );
		}
		$id  = null;
		$ctx = '';
		$pl  = null;
		$str = array();
	};

	foreach ( file( $path, FILE_IGNORE_NEW_LINES ) as $line ) {
		$line = rtrim( $line );

		if ( '' === $line || '#' === substr( $line, 0, 1 ) ) {
			continue;
		}
		if ( 0 === strpos( $line, 'msgctxt ' ) ) {
			$flush();
			$pending = $chunk( $line );
			$in      = 'ctx';
			continue;
		}
		if ( 0 === strpos( $line, 'msgid_plural ' ) ) {
			$pl = $chunk( $line );
			$in = 'plural';
			continue;
		}
		if ( 0 === strpos( $line, 'msgid ' ) ) {
			$flush();
			$ctx     = $pending;
			$pending = '';
			$id      = $chunk( $line );
			$in      = 'id';
			continue;
		}
		if ( preg_match( '/^msgstr\[(\d+)\] /', $line, $m ) ) {
			$idx         = (int) $m[1];
			$str[ $idx ] = $chunk( $line );
			$in          = 'str' . $idx;
			continue;
		}
		if ( 0 === strpos( $line, 'msgstr ' ) ) {
			$str[0] = $chunk( $line );
			$in     = 'str0';
			continue;
		}
		if ( '"' === substr( $line, 0, 1 ) && null !== $in ) {
			$add = $chunk( $line );
			if ( 'id' === $in ) {
				$id .= $add;
			} elseif ( 'ctx' === $in ) {
				$pending .= $add;
			} elseif ( 'plural' === $in ) {
				$pl .= $add;
			} elseif ( 0 === strpos( $in, 'str' ) ) {
				$str[ (int) substr( $in, 3 ) ] .= $add;
			}
		}
	}

	$flush();

	return $out;
}

wmds_section( 'The compiled catalogue is one WordPress can read' );

$read = wmds_read_mo( $mo );

wmds_assert( 'it parses', true, $read['ok'] );
if ( ! $read['ok'] ) {
	echo '        reason: ', $read['reason'], "\n";
}
wmds_assert( 'and holds entries', true, count( $read['entries'] ) > 100 );
wmds_assert( 'including the header entry', true, isset( $read['entries'][''] ) );
wmds_assert_contains( 'which names the language', 'de_DE', $read['entries'][''] );

$source = wmds_read_po( $po );

wmds_section( 'Strings the admin and the templates ask for' );

$wanted = array(
	'Mileage',
	'First registration',
	'Power',
	'Transmission',
	'Fuel',
	'VAT not reclaimable',
	'Uploads folder',
	'Requirements',
	'URL rewriting',
);

foreach ( $wanted as $id ) {
	$compiled = isset( $read['entries'][ $id ] ) ? $read['entries'][ $id ] : '';

	wmds_assert( $id . ': compiled', true, '' !== $compiled );
	wmds_assert( $id . ': as the source has it', isset( $source[ $id ] ) ? $source[ $id ] : '*** not in the .po ***', $compiled );
	wmds_assert( $id . ': actually translated', true, $compiled !== $id );
}

wmds_section( 'A string with a context is stored under that context' );

$key      = "requirement check\4Note";
$compiled = isset( $read['entries'][ $key ] ) ? $read['entries'][ $key ] : '';

wmds_assert( 'compiled under its context', true, '' !== $compiled );
wmds_assert( 'matching the source', isset( $source[ $key ] ) ? $source[ $key ] : '*** not in the .po ***', $compiled );
wmds_assert( 'and not the bare msgid', true, 'Note' !== $compiled );

wmds_section( 'Both plural forms survive the compilation' );

$plural = '';
foreach ( $read['entries'] as $id => $translation ) {
	if ( 0 === strpos( $id, 'Everything needed is in place.' ) ) {
		$plural = $translation;
	}
}

wmds_assert( 'the entry is there', true, '' !== $plural );
wmds_assert( 'with two forms', 2, count( explode( "\0", $plural ) ) );

wmds_section( 'The compiled file is in step with its source' );

$missing = array();

foreach ( $source as $key => $translation ) {
	if ( ! isset( $read['entries'][ $key ] ) ) {
		$missing[] = str_replace( array( "\4", "\0" ), array( ' | ', ' / ' ), $key );
	}
}

wmds_assert( 'every translated string was compiled', array(), array_slice( $missing, 0, 5 ) );
wmds_assert( 'the .mo is newer than the .po', true, filemtime( $mo ) >= filemtime( $po ) );

wmds_result();
