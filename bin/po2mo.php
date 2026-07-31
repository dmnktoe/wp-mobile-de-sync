<?php

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

/**
 * @param string $line A line that contains one quoted chunk.
 * @return string
 */
function wmds_po_chunk( $line ) {
	if ( ! preg_match( '/"((?:[^"\\\\]|\\\\.)*)"\s*$/', $line, $match ) ) {
		return '';
	}

	return strtr(
		$match[1],
		array(
			'\\n'  => "\n",
			'\\t'  => "\t",
			'\\r'  => "\r",
			'\\"'  => '"',
			'\\\\' => '\\',
		)
	);
}

/**
 * @param string $path
 * @return array<string,string> msgid (NUL-joined for plurals) => msgstr.
 */
function wmds_po_parse( $path ) {
	$lines = file( $path, FILE_IGNORE_NEW_LINES );
	if ( false === $lines ) {
		fwrite( STDERR, "Cannot read {$path}\n" );
		exit( 1 );
	}

	$entries = array();
	$current = array(
		'id'     => null,
		'ctx'    => '',
		'plural' => null,
		'str'    => array(),
	);
	$pending = '';
	$field   = null;
	$index   = 0;

	$flush = function () use ( &$entries, &$current ) {
		if ( null === $current['id'] ) {
			return;
		}

		$key = ( '' === $current['ctx'] )
			? $current['id']
			: $current['ctx'] . "\4" . $current['id'];

		if ( null !== $current['plural'] ) {
			$key .= "\0" . $current['plural'];
		}

		ksort( $current['str'] );
		$entries[ $key ] = implode( "\0", $current['str'] );

		$current = array(
			'id'     => null,
			'ctx'    => '',
			'plural' => null,
			'str'    => array(),
		);
	};

	foreach ( $lines as $line ) {
		$line = rtrim( $line );

		if ( '' === $line || '#' === substr( $line, 0, 1 ) ) {
			continue;
		}

		if ( 0 === strpos( $line, 'msgctxt ' ) ) {
			$flush();
			$pending = wmds_po_chunk( $line );
			$field   = 'ctx';
			continue;
		}

		if ( 0 === strpos( $line, 'msgid_plural ' ) ) {
			$current['plural'] = wmds_po_chunk( $line );
			$field             = 'plural';
			continue;
		}

		if ( 0 === strpos( $line, 'msgid ' ) ) {
			$flush();
			$current['ctx'] = $pending;
			$pending        = '';
			$current['id']  = wmds_po_chunk( $line );
			$field          = 'id';
			continue;
		}

		if ( preg_match( '/^msgstr\[(\d+)\] /', $line, $match ) ) {
			$index                    = (int) $match[1];
			$current['str'][ $index ] = wmds_po_chunk( $line );
			$field                    = 'str';
			continue;
		}

		if ( 0 === strpos( $line, 'msgstr ' ) ) {
			$index             = 0;
			$current['str'][0] = wmds_po_chunk( $line );
			$field             = 'str';
			continue;
		}

		if ( '"' === substr( $line, 0, 1 ) && null !== $field ) {
			$chunk = wmds_po_chunk( $line );

			if ( 'id' === $field ) {
				$current['id'] .= $chunk;
			} elseif ( 'ctx' === $field ) {
				$pending .= $chunk;
			} elseif ( 'plural' === $field ) {
				$current['plural'] .= $chunk;
			} else {
				$current['str'][ $index ] .= $chunk;
			}
		}
	}

	$flush();

	return $entries;
}

/**
 * @param array<string,string> $entries
 * @return string The binary .mo payload.
 */
function wmds_mo_build( array $entries ) {
	ksort( $entries );

	$count   = count( $entries );
	$ids     = '';
	$strings = '';
	$id_tbl  = '';
	$str_tbl = '';

	$ids_start = 28 + ( $count * 16 );

	foreach ( $entries as $id => $str ) {
		$id_tbl  .= pack( 'VV', strlen( $id ), $ids_start + strlen( $ids ) );
		$ids     .= $id . "\0";
		$strings .= $str . "\0";
	}

	$cursor = $ids_start + strlen( $ids );

	foreach ( $entries as $str ) {
		$str_tbl .= pack( 'VV', strlen( $str ), $cursor );
		$cursor  += strlen( $str ) + 1;
	}

	// The hash table is empty, but its address still has to sit right behind
	// the translation table: WordPress derives that table's length from
	// hash_addr - translations_addr and refuses the file when the two do not
	// come out at total * 8.
	$header = pack(
		'VVVVVVV',
		0x950412de,
		0,
		$count,
		28,
		28 + ( $count * 8 ),
		0,
		$ids_start
	);

	return $header . $id_tbl . $str_tbl . $ids . $strings;
}

array_shift( $argv );

if ( ! $argv ) {
	fwrite( STDERR, "Usage: php bin/po2mo.php <file.po> [...]\n" );
	exit( 1 );
}

foreach ( $argv as $po ) {
	if ( ! is_readable( $po ) ) {
		fwrite( STDERR, "Not readable: {$po}\n" );
		exit( 1 );
	}

	$entries = wmds_po_parse( $po );
	$mo      = preg_replace( '/\.po$/', '.mo', $po );

	if ( false === file_put_contents( $mo, wmds_mo_build( $entries ) ) ) {
		fwrite( STDERR, "Cannot write: {$mo}\n" );
		exit( 1 );
	}

	printf( "%s -> %s (%d entries)\n", $po, $mo, count( $entries ) );
}
