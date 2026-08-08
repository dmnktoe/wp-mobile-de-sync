<?php

require_once __DIR__ . '/bootstrap.php';

define( 'WMDS_CPT', 'fahrzeuge' );

function is_singular( $post_types = '' ) {
	return false;
}

function get_permalink( $post = 0 ) {
	return 'https://example.invalid/fahrzeuge/one/';
}

function get_post_type_archive_link( $post_type ) {
	return 'https://example.invalid/fahrzeuge/';
}

function home_url( $path = '/' ) {
	return 'https://example.invalid' . $path;
}

require_once __DIR__ . '/../includes/class-wmds-str.php';
require_once __DIR__ . '/../includes/class-wmds-num.php';
require_once __DIR__ . '/../includes/class-wmds-date.php';
require_once __DIR__ . '/../includes/class-wmds-str.php';
require_once __DIR__ . '/../includes/class-wmds-num.php';
require_once __DIR__ . '/../includes/class-wmds-facet-request.php';
require_once __DIR__ . '/../includes/class-wmds-facet-query.php';
require_once __DIR__ . '/../includes/class-wmds-facet-store.php';
require_once __DIR__ . '/../includes/class-wmds-facets.php';

wmds_section( 'Reading a request' );

$selection = WMDS_Facets::parse(
	array(
		'wmds_make'      => 'Audi',
		'wmds_fuel'      => array( 'Diesel', 'Petrol', 'Diesel' ),
		'wmds_price_min' => '5000',
		'wmds_price_max' => '20000',
		'wmds_sort'      => 'price-asc',
		'unrelated'      => 'ignored',
	)
);

wmds_assert( 'a single-choice facet keeps one value', array( 'Audi' ), $selection['make'] );
wmds_assert( 'a multi-choice facet keeps them all, once each', array( 'Diesel', 'Petrol' ), $selection['fuel'] );
wmds_assert(
	'a range keeps both bounds',
	array(
		'min' => 5000.0,
		'max' => 20000.0,
	),
	$selection['price']
);
wmds_assert( 'a known sort survives', 'price-asc', $selection['sort'] );
wmds_assert( 'anything else is dropped', false, isset( $selection['unrelated'] ) );

wmds_assert(
	'a select given several values takes the first',
	array( 'BMW' ),
	WMDS_Facets::parse( array( 'wmds_make' => array( 'BMW', 'Audi' ) ) )['make']
);

wmds_assert(
	'an unknown sort is refused',
	array(),
	WMDS_Facets::parse( array( 'wmds_sort' => 'by-colour' ) )
);

wmds_assert(
	'bounds handed over the wrong way round are swapped',
	array(
		'min' => 1000.0,
		'max' => 9000.0,
	),
	WMDS_Facets::parse(
		array(
			'wmds_price_min' => '9000',
			'wmds_price_max' => '1000',
		)
	)['price']
);

wmds_assert(
	'a bound that is not a number is ignored',
	array( 'max' => 9000.0 ),
	WMDS_Facets::parse(
		array(
			'wmds_price_min' => 'cheap',
			'wmds_price_max' => '9000',
		)
	)['price']
);

wmds_assert(
	'a thousands separator is read, not truncated',
	array( 'min' => 12500.0 ),
	WMDS_Facets::parse( array( 'wmds_price_min' => '12.500' ) )['price']
);

wmds_assert(
	'an empty facet does not enter the selection',
	array(),
	WMDS_Facets::parse(
		array(
			'wmds_make' => '',
			'wmds_fuel' => array( '', '  ' ),
		)
	)
);

wmds_section( 'What a request may not smuggle in' );

wmds_assert(
	'markup is stripped',
	array( 'scriptalert(1)/script' ),
	WMDS_Facets::parse( array( 'wmds_make' => '<script>alert(1)</script>' ) )['make']
);

wmds_assert(
	'a long value is cut to a sane length',
	100,
	strlen( WMDS_Facets::parse( array( 'wmds_make' => str_repeat( 'a', 500 ) ) )['make'][0] )
);

wmds_assert(
	'an array where a scalar belongs is not passed through',
	array(),
	WMDS_Facets::parse( array( 'wmds_price_min' => array( '1' ) ) )
);

wmds_section( 'From a selection to a query' );

$meta = WMDS_Facets::meta_query(
	array(
		'make'  => array( 'Audi' ),
		'fuel'  => array( 'Diesel', 'Petrol' ),
		'price' => array(
			'min' => 5000.0,
			'max' => 20000.0,
		),
		'q'     => 'estate',
		'sort'  => 'price-asc',
	)
);

wmds_assert( 'one clause per facet, two for a range', 4, count( $meta ) - 1 );
wmds_assert( 'the clauses are combined with AND', 'AND', $meta['relation'] );
wmds_assert( 'a single value compares with =', '=', $meta[0]['compare'] );
wmds_assert( 'the facet points at its meta key', 'make', $meta[0]['key'] );
wmds_assert( 'several values compare with IN', 'IN', $meta[1]['compare'] );
wmds_assert( 'a lower bound compares with >=', '>=', $meta[2]['compare'] );
wmds_assert( 'an upper bound compares with <=', '<=', $meta[3]['compare'] );
wmds_assert( 'a range compares numerically', 'DECIMAL(20,4)', $meta[2]['type'] );

wmds_assert(
	'the search term is no meta clause',
	array(),
	WMDS_Facets::meta_query( array( 'q' => 'estate' ) )
);

wmds_assert(
	'a facet can be left out, which is how the counts stay honest',
	1,
	count(
		WMDS_Facets::meta_query(
			array(
				'make' => array( 'Audi' ),
				'fuel' => array( 'Diesel' ),
			),
			array( 'make' )
		)
	) - 1
);

wmds_section( 'Sorting' );

$default = WMDS_Facets::order_args( '' );
wmds_assert( 'without a choice the newest come first', 'date', $default['orderby'] );
wmds_assert( 'and that means descending', 'DESC', $default['order'] );

$by_price = WMDS_Facets::order_args( 'price-asc' );
wmds_assert( 'a sort orders by its named clause', array( 'wmds_sorted' => 'ASC' ), $by_price['orderby'] );
wmds_assert( 'and reads the right meta key', 'price_raw', $by_price['meta_query'][0]['wmds_sorted']['key'] );
wmds_assert(
	'a vehicle without that value stays in the list',
	'NOT EXISTS',
	$by_price['meta_query'][0]['wmds_absent']['compare']
);

wmds_assert( 'an unknown sort falls back to the default', 'date', WMDS_Facets::order_args( 'nonsense' )['orderby'] );

wmds_section( 'The finished query arguments' );

$args = WMDS_Facets::query_args(
	array(
		'make' => array( 'Audi' ),
		'q'    => 'estate',
		'sort' => 'price-asc',
	),
	array( 'posts_per_page' => 12 )
);

wmds_assert( 'the post type is ours', 'fahrzeuge', $args['post_type'] );
wmds_assert( 'only published vehicles', 'publish', $args['post_status'] );
wmds_assert( 'the caller keeps their own arguments', 12, $args['posts_per_page'] );
wmds_assert( 'the search term becomes s', 'estate', $args['s'] );
wmds_assert( 'the sort survives', array( 'wmds_sorted' => 'ASC' ), $args['orderby'] );
wmds_assert( 'filter and sort clauses are joined, not overwritten', 'AND', $args['meta_query']['relation'] );

wmds_assert(
	'an empty selection asks for nothing in particular',
	false,
	isset( WMDS_Facets::query_args( array() )['meta_query'] )
);

wmds_section( 'Is anything filtered' );

wmds_assert( 'an empty selection is not', false, WMDS_Facets::is_filtered( array() ) );
wmds_assert( 'a sort on its own is not', false, WMDS_Facets::is_filtered( array( 'sort' => 'price-asc' ) ) );
wmds_assert( 'a facet is', true, WMDS_Facets::is_filtered( array( 'make' => array( 'Audi' ) ) ) );

wmds_section( 'Active filters, and the way back out' );

$selection = array(
	'make'  => array( 'Audi' ),
	'fuel'  => array( 'Diesel', 'Petrol' ),
	'price' => array( 'max' => 20000.0 ),
	'sort'  => 'price-asc',
);

$chips = WMDS_Facets::chips( $selection );

wmds_assert( 'one chip per value, none for the sort', 4, count( $chips ) );
wmds_assert( 'a chip names its value', 'Audi', $chips[0]['value'] );
wmds_assert( 'a range chip reads as a range', 'up to 20,000 €', $chips[3]['value'] );

wmds_assert_contains( 'removing a facet drops it from the URL', 'wmds_fuel', $chips[0]['url'] );
wmds_assert( 'and the facet itself is gone', false, strpos( $chips[0]['url'], 'wmds_make' ) );

$one_left = WMDS_Facets::url_without( $selection, 'fuel', 'Diesel' );
wmds_assert_contains( 'removing one value of several keeps the others', 'Petrol', rawurldecode( $one_left ) );
wmds_assert( 'and drops only the one', false, strpos( rawurldecode( $one_left ), 'Diesel' ) );

wmds_assert(
	'a selection turns back into query arguments',
	array(
		'wmds_make'      => array( 'Audi' ),
		'wmds_fuel'      => array( 'Diesel', 'Petrol' ),
		'wmds_price_max' => 20000.0,
		'wmds_sort'      => 'price-asc',
	),
	WMDS_Facets::to_query( $selection )
);

wmds_assert(
	'a request survives the round trip',
	$selection,
	WMDS_Facets::parse( WMDS_Facets::to_query( $selection ) )
);

wmds_section( 'How a range reads' );

wmds_assert(
	'both bounds',
	'1,000 to 2,000 €',
	WMDS_Facets::range_label(
		array(
			'min' => 1000,
			'max' => 2000,
		),
		'€'
	)
);
wmds_assert( 'only a lower one', 'from 1,000 €', WMDS_Facets::range_label( array( 'min' => 1000 ), '€' ) );
wmds_assert( 'only an upper one', 'up to 2,000 €', WMDS_Facets::range_label( array( 'max' => 2000 ), '€' ) );
wmds_assert( 'neither', '', WMDS_Facets::range_label( array() ) );

wmds_result();
