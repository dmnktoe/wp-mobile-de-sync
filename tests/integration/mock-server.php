<?php
/** @return string */
function wmds_mock_dir() {
	$dir = (string) getenv( 'WMDS_MOCK_STATE' );
	return '' !== $dir ? rtrim( $dir, '/' ) : sys_get_temp_dir();
}

/** @return array */
function wmds_mock_state() {
	$file = wmds_mock_dir() . '/state.json';
	if ( ! is_readable( $file ) ) {
		return array();
	}

	$state = json_decode( (string) file_get_contents( $file ), true );

	return is_array( $state ) ? $state : array();
}

/**
 * @param array  $state
 * @param string $key
 * @param mixed  $fallback
 * @return mixed
 */
function wmds_mock_setting( array $state, $key, $fallback ) {
	return isset( $state[ $key ] ) ? $state[ $key ] : $fallback;
}

/**
 * @param array $state
 * @return array
 */
function wmds_mock_catalog( array $state ) {
	$fixtures = dirname( __DIR__ ) . '/fixtures';

	$search = json_decode( (string) file_get_contents( $fixtures . '/search-2-ads.json' ), true );
	$ads    = isset( $search['ads'] ) && is_array( $search['ads'] ) ? $search['ads'] : array();

	$details = array();
	foreach ( glob( $fixtures . '/ad-detail*.json' ) as $file ) {
		$detail = json_decode( (string) file_get_contents( $file ), true );
		if ( is_array( $detail ) && isset( $detail['mobileAdId'] ) ) {
			$details[ (string) $detail['mobileAdId'] ] = $detail;
		}
	}

	$live     = wmds_mock_setting( $state, 'ads', null );
	$modified = wmds_mock_setting( $state, 'modified', array() );
	$model    = wmds_mock_setting( $state, 'model', array() );

	$catalog = array();
	foreach ( $ads as $ad ) {
		if ( ! is_array( $ad ) || empty( $ad['mobileAdId'] ) ) {
			continue;
		}

		$id = (string) $ad['mobileAdId'];
		if ( is_array( $live ) && ! in_array( $id, $live, true ) ) {
			continue;
		}

		$listing = $ad;
		$detail  = isset( $details[ $id ] ) ? array_merge( $ad, $details[ $id ] ) : $ad;
		unset( $listing['_note'], $detail['_note'] );

		if ( isset( $modified[ $id ] ) ) {
			$listing['modificationDate'] = (string) $modified[ $id ];
			$detail['modificationDate']  = (string) $modified[ $id ];
		}
		if ( isset( $model[ $id ] ) ) {
			$listing['modelDescription'] = (string) $model[ $id ];
			$detail['modelDescription']  = (string) $model[ $id ];
		}

		$catalog[ $id ] = array(
			'search' => $listing,
			'detail' => $detail,
		);
	}

	return $catalog;
}

/**
 * @param string $query_string
 * @return array
 */
function wmds_mock_query( $query_string ) {
	$out = array();

	foreach ( explode( '&', (string) $query_string ) as $pair ) {
		if ( '' === $pair ) {
			continue;
		}
		$parts       = explode( '=', $pair, 2 );
		$key         = urldecode( $parts[0] );
		$out[ $key ] = isset( $parts[1] ) ? urldecode( $parts[1] ) : '';
	}

	return $out;
}

/** @return bool */
function wmds_mock_authorised() {
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic auth is defined in terms of base64.
	$expected = 'Basic ' . base64_encode( getenv( 'WMDS_MOCK_USER' ) . ':' . getenv( 'WMDS_MOCK_PASS' ) );

	$sent = '';
	if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
		$sent = (string) $_SERVER['HTTP_AUTHORIZATION'];
	} elseif ( isset( $_SERVER['PHP_AUTH_USER'] ) ) {
		$pass = isset( $_SERVER['PHP_AUTH_PW'] ) ? $_SERVER['PHP_AUTH_PW'] : '';
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- see above.
		$sent = 'Basic ' . base64_encode( $_SERVER['PHP_AUTH_USER'] . ':' . $pass );
	}

	return hash_equals( $expected, $sent );
}

/**
 * @param int   $status
 * @param array $data
 */
function wmds_mock_send( $status, array $data ) {
	http_response_code( (int) $status );
	header( 'Content-Type: application/vnd.de.mobile.api+json; charset=utf-8' );
	echo json_encode( $data );
}

/**
 * @param int    $status
 * @param string $title
 * @param string $detail
 */
function wmds_mock_fail( $status, $title, $detail ) {
	wmds_mock_send(
		$status,
		array(
			'title'  => $title,
			'detail' => $detail,
			'errors' => array(
				array(
					'key'     => 'mock.' . (int) $status,
					'message' => $detail,
				),
			),
		)
	);
}

/** @return array */
function wmds_mock_refdata() {
	return array(
		'gearboxes'              => array(
			'MANUAL_GEAR'    => 'Manual',
			'AUTOMATIC_GEAR' => 'Automatic',
		),
		'fuels'                  => array(
			'DIESEL' => 'Diesel',
			'PETROL' => 'Petrol',
		),
		'colors'                 => array(
			'BLACK' => 'Black',
			'WHITE' => 'White',
		),
		'interiorcolors'         => array(
			'BLACK' => 'Black',
		),
		'interiortypes'          => array(
			'PARTIAL_LEATHER' => 'Part leather',
			'LEATHER'         => 'Full leather',
		),
		'conditions'             => array(
			'USED' => 'Used',
			'NEW'  => 'New',
		),
		'doorcounts'             => array(
			'TWO_OR_THREE' => '2/3',
			'FOUR_OR_FIVE' => '4/5',
		),
		'emissionclasses'        => array(
			'EURO5'  => 'Euro5',
			'EURO6D' => 'Euro6d',
		),
		'emissionstickers'       => array(
			'EMISSIONSSTICKER_GREEN' => 'Green',
		),
		'usagetypes'             => array(
			'USED' => 'Used',
		),
		'climatisations'         => array(
			'MANUAL_CLIMATISATION'            => 'Air conditioning',
			'AUTOMATIC_CLIMATISATION_2_ZONES' => 'Two zone climate control',
		),
		'classes/Car/categories' => array(
			'OffRoad'   => 'Off-Road/Pick-up',
			'EstateCar' => 'Estate car',
		),
		'classes/Car/makes'      => array(
			'LAND ROVER' => 'Land Rover',
			'VOLVO'      => 'Volvo',
		),
	);
}

/**
 * @param array $state
 * @param array $query
 */
function wmds_mock_route_search( array $state, array $query ) {
	$status = (int) wmds_mock_setting( $state, 'search_status', 200 );
	if ( 200 !== $status ) {
		wmds_mock_fail( $status, 'Search unavailable', 'The mock was asked to answer with ' . $status . '.' );
		return;
	}

	$seller = (string) getenv( 'WMDS_MOCK_SELLER' );
	$sent   = isset( $query['customerId'] ) ? (string) $query['customerId'] : '';
	if ( '' !== $seller && $sent !== $seller ) {
		wmds_mock_send(
			200,
			array(
				'total'       => 0,
				'currentPage' => 1,
				'maxPages'    => 1,
				'pageSize'    => 0,
				'ads'         => array(),
			)
		);
		return;
	}

	$ads = array();
	foreach ( wmds_mock_catalog( $state ) as $entry ) {
		$ads[] = $entry['search'];
	}

	if ( isset( $query['modificationTime.min'] ) && '' !== $query['modificationTime.min'] ) {
		$sent = (string) $query['modificationTime.min'];
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|[+-]\d{2}:\d{2})$/', $sent ) ) {
			wmds_mock_fail(
				400,
				'invalid-search-parameter',
				'field=modificationTime.min, code=typeMismatch, rejected-value=' . $sent
			);
			return;
		}

		$since   = strtotime( $sent );
		$matched = array();
		foreach ( $ads as $ad ) {
			$stamp = isset( $ad['modificationDate'] ) ? strtotime( (string) $ad['modificationDate'] ) : 0;
			if ( $stamp >= $since ) {
				$matched[] = $ad;
			}
		}
		$ads = $matched;
	}

	usort( $ads, 'wmds_mock_compare_modified' );

	$total     = count( $ads );
	$requested = isset( $query['page.size'] ) ? (int) $query['page.size'] : 100;
	$size      = max( 1, min( $requested, (int) wmds_mock_setting( $state, 'page_size', 100 ) ) );
	$page      = isset( $query['page.number'] ) ? max( 1, (int) $query['page.number'] ) : 1;
	$slice     = array_slice( $ads, ( $page - 1 ) * $size, $size );

	wmds_mock_send(
		200,
		array(
			'total'       => $total,
			'currentPage' => $page,
			'maxPages'    => max( 1, (int) ceil( $total / $size ) ),
			'pageSize'    => $size,
			'ads'         => $slice,
		)
	);
}

/**
 * @param array $left
 * @param array $right
 * @return int
 */
function wmds_mock_compare_modified( array $left, array $right ) {
	$a = isset( $left['modificationDate'] ) ? strtotime( (string) $left['modificationDate'] ) : 0;
	$b = isset( $right['modificationDate'] ) ? strtotime( (string) $right['modificationDate'] ) : 0;

	if ( $a === $b ) {
		return 0;
	}

	return ( $a < $b ) ? -1 : 1;
}

/**
 * @param array  $state
 * @param string $ad_id
 */
function wmds_mock_route_ad( array $state, $ad_id ) {
	$overrides = wmds_mock_setting( $state, 'detail_status', array() );
	if ( isset( $overrides[ $ad_id ] ) && 200 !== (int) $overrides[ $ad_id ] ) {
		wmds_mock_fail(
			(int) $overrides[ $ad_id ],
			'Ad unavailable',
			'The mock was asked to answer with ' . (int) $overrides[ $ad_id ] . ' for ad ' . $ad_id . '.'
		);
		return;
	}

	$catalog = wmds_mock_catalog( $state );
	if ( ! isset( $catalog[ $ad_id ] ) ) {
		wmds_mock_fail( 404, 'Not found', 'No ad with ID ' . $ad_id . '.' );
		return;
	}

	wmds_mock_send( 200, $catalog[ $ad_id ]['detail'] );
}

/**
 * @param string $path
 */
function wmds_mock_route_refdata( $path ) {
	$tables = wmds_mock_refdata();
	if ( ! isset( $tables[ $path ] ) ) {
		wmds_mock_fail( 404, 'Not found', 'No reference table at ' . $path . '.' );
		return;
	}

	$values = array();
	foreach ( $tables[ $path ] as $name => $description ) {
		$values[] = array(
			'name'        => $name,
			'description' => $description,
		);
	}

	wmds_mock_send( 200, array( 'values' => $values ) );
}

function wmds_mock_route_image() {
	$file = __DIR__ . '/vehicle.jpg';

	http_response_code( 200 );
	header( 'Content-Type: image/jpeg' );
	header( 'Content-Length: ' . filesize( $file ) );
	echo file_get_contents( $file );
}

$wmds_mock_uri   = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/';
$wmds_mock_path  = (string) parse_url( $wmds_mock_uri, PHP_URL_PATH );
$wmds_mock_query = wmds_mock_query( (string) parse_url( $wmds_mock_uri, PHP_URL_QUERY ) );
$wmds_mock_state = wmds_mock_state();

file_put_contents( wmds_mock_dir() . '/requests.log', $wmds_mock_uri . "\n", FILE_APPEND );

if ( '/__ping' === $wmds_mock_path ) {
	http_response_code( 200 );
	header( 'Content-Type: text/plain' );
	echo "ok\n";
} elseif ( '.jpg' === substr( $wmds_mock_path, -4 ) ) {
	wmds_mock_route_image();
} elseif ( 0 === strpos( $wmds_mock_path, '/refdata/' ) ) {
	wmds_mock_route_refdata( substr( $wmds_mock_path, strlen( '/refdata/' ) ) );
} elseif ( ! wmds_mock_authorised() ) {
	header( 'WWW-Authenticate: Basic realm="mock"' );
	wmds_mock_fail( 401, 'Unauthorized', 'Wrong or missing credentials.' );
} elseif ( '/search-api/search' === $wmds_mock_path ) {
	wmds_mock_route_search( $wmds_mock_state, $wmds_mock_query );
} elseif ( 0 === strpos( $wmds_mock_path, '/search-api/ad/' ) ) {
	wmds_mock_route_ad( $wmds_mock_state, rawurldecode( substr( $wmds_mock_path, strlen( '/search-api/ad/' ) ) ) );
} else {
	wmds_mock_fail( 404, 'Not found', 'No route for ' . $wmds_mock_path . '.' );
}
