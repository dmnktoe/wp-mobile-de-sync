<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Client for the mobile.de Search API in the current data format (New JSON).
 *
 * Why this format and not the older XML:
 *   - Legacy XML is marked deprecated by mobile.de.
 *   - Field names are fully documented; the XML route required guessing
 *     several paths, and every guess was wrong.
 *   - No XML namespace XPath. That removes an entire class of bug: absolute
 *     paths in SimpleXML are document-wide rather than node-relative, so with
 *     several vehicles per page every one of them gets the first one's values.
 *   - Images carry an MD5 hash, which is what makes a swapped photo
 *     detectable at all.
 *
 * Two endpoints, because a search result carries fewer fields than the
 * single-ad call. Verified against a live feed: the formatted description
 * (description) and the seller name (companyName) exist ONLY in the single-ad
 * call; a search result has just plainTextDescription. The importer therefore
 * fetches details selectively for new and changed vehicles, identified via
 * modificationDate.
 *
 * Every response is validated before it is used. An error becomes a WP_Error,
 * never an exception or a fatal. In particular, an error response is never
 * processed as if it were a result list.
 */
class WMDS_Client {

	const BASE   = 'https://services.mobile.de/search-api';
	const ACCEPT = 'application/vnd.de.mobile.api+json';

	/** Largest page size the API allows. */
	const MAX_PAGE_SIZE = 100;

	/**
	 * Hard API ceiling: at most 2000 ads are reachable through paginated
	 * pages. Beyond that, further pages come back empty.
	 */
	const PAGINATION_CAP = 2000;

	/** @var string */
	private $user;
	/** @var string */
	private $pass;
	/** @var string */
	private $seller_id;
	/** @var string */
	private $dealer;

	/**
	 * @param string $user      API username.
	 * @param string $pass      API password.
	 * @param string $seller_id Numeric mobile.de seller ID (preferred).
	 * @param string $dealer    Vanity name, as a fallback.
	 */
	public function __construct( $user, $pass, $seller_id = '', $dealer = '' ) {
		$this->user      = (string) $user;
		$this->pass      = (string) $pass;
		$this->seller_id = trim( (string) $seller_id );
		$this->dealer    = trim( (string) $dealer );
	}

	/** @return bool Whether a username and password are stored. */
	public function has_credentials() {
		return '' !== $this->user && '' !== $this->pass;
	}

	/** @return bool Whether a seller ID or a dealer name is stored. */
	public function has_seller() {
		return '' !== $this->seller_id || '' !== $this->dealer;
	}

	/**
	 * Builds the search URL.
	 *
	 * Prefers the documented customerId parameter with the numeric seller ID.
	 * The dealer= parameter appears in no official parameter list - it works,
	 * but it can disappear at any time, so it stays a fallback only.
	 *
	 * Verified against a live feed: customerId works on its own, with no
	 * accompanying parameters.
	 *
	 * Public so the URL construction is testable without an HTTP call.
	 *
	 * @param int    $page           1-based.
	 * @param int    $per_page       max. 100.
	 * @param string $modified_since Optional, ISO-8601. Only ads changed since.
	 * @return string
	 */
	public function search_url( $page = 1, $per_page = self::MAX_PAGE_SIZE, $modified_since = '' ) {
		$args = array(
			'page.number' => max( 1, (int) $page ),
			'page.size'   => min( self::MAX_PAGE_SIZE, max( 1, (int) $per_page ) ),
		);

		if ( '' !== $this->seller_id ) {
			$args['customerId'] = $this->seller_id;
		} else {
			$args['dealer'] = $this->dealer;
		}

		if ( '' !== trim( (string) $modified_since ) ) {
			$args['modificationTime.min'] = trim( (string) $modified_since );
			// Oldest first: if a run aborts, the progress made up to that
			// point is still usable.
			$args['sort.field'] = 'modificationTime';
			$args['sort.order'] = 'ASCENDING';
		}

		// add_query_arg already encodes the values - encode nothing here, or
		// it ends up double-encoded.
		return add_query_arg( $args, self::BASE . '/search' );
	}

	/**
	 * Fetches one page of the dealer search.
	 *
	 * @param int    $page
	 * @param int    $per_page
	 * @param string $modified_since
	 * @return array|WP_Error ['ads'=>array[], 'total'=>int, 'max_pages'=>int, 'page_size'=>int, 'capped'=>bool]
	 */
	public function search( $page = 1, $per_page = self::MAX_PAGE_SIZE, $modified_since = '' ) {
		$data = $this->request( $this->search_url( $page, $per_page, $modified_since ) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$total = isset( $data['total'] ) ? (int) $data['total'] : 0;

		return array(
			'ads'       => ( isset( $data['ads'] ) && is_array( $data['ads'] ) ) ? $data['ads'] : array(),
			'total'     => $total,
			'max_pages' => isset( $data['maxPages'] ) ? max( 1, (int) $data['maxPages'] ) : 1,
			'page_size' => isset( $data['pageSize'] ) ? (int) $data['pageSize'] : (int) $per_page,
			// Past the ceiling, vehicles stay unreachable. The importer has
			// to log that, or the run looks successful.
			'capped'    => $total > self::PAGINATION_CAP,
		);
	}

	/**
	 * Fetches a single ad with the full field set.
	 *
	 * @param string $ad_id Ad ID. Deliberately a string - per the mobile.de
	 *                      changelog the format grows to up to 16 digits
	 *                      from 08/2026.
	 * @return array|WP_Error
	 */
	public function ad( $ad_id ) {
		$ad_id = trim( (string) $ad_id );
		if ( '' === $ad_id ) {
			return new WP_Error( 'wmds_no_ad_id', 'No ad ID given.' );
		}

		return $this->request( self::BASE . '/ad/' . rawurlencode( $ad_id ) );
	}

	/**
	 * Performs the HTTP request and returns a validated array or a WP_Error.
	 *
	 * @param string $url
	 * @return array|WP_Error
	 */
	protected function request( $url ) {
		if ( ! $this->has_credentials() ) {
			return new WP_Error( 'wmds_no_credentials', 'No mobile.de credentials stored.' );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 30,
				'redirection' => 2,
				'headers'     => array(
					'Accept'        => self::ACCEPT,
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic auth is defined in terms of base64.
					'Authorization' => 'Basic ' . base64_encode( $this->user . ':' . $this->pass ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'wmds_http', 'HTTP error: ' . $response->get_error_message() );
		}

		return self::interpret(
			(int) wp_remote_retrieve_response_code( $response ),
			(string) wp_remote_retrieve_body( $response )
		);
	}

	/**
	 * Evaluates a response's status code and body.
	 *
	 * Static and free of the WordPress HTTP layer, so the whole error path is
	 * testable without a network.
	 *
	 * @param int    $code
	 * @param string $body
	 * @return array|WP_Error
	 */
	public static function interpret( $code, $body ) {
		if ( 200 !== (int) $code ) {
			$hints  = array(
				401 => ' (check the credentials)',
				403 => ' (Search API may not be enabled for this account)',
				404 => ' (ad not found, or wrong seller ID)',
				406 => ' (data format not accepted)',
				429 => ' (rate limited - increase the interval)',
			);
			$hint   = isset( $hints[ (int) $code ] ) ? $hints[ (int) $code ] : '';
			$detail = self::error_message( $body );

			return new WP_Error(
				'wmds_status_' . (int) $code,
				trim( sprintf( 'mobile.de responded with HTTP %d%s. %s', (int) $code, $hint, $detail ) )
			);
		}

		if ( '' === trim( (string) $body ) ) {
			return new WP_Error( 'wmds_empty', 'Received an empty response from mobile.de.' );
		}

		$data = json_decode( (string) $body, true );
		if ( null === $data || ! is_array( $data ) ) {
			return new WP_Error(
				'wmds_parse',
				'Response is not valid JSON: ' . json_last_error_msg()
			);
		}

		// The API can return an error list even with HTTP 200, e.g. for
		// outdated search parameters.
		if ( isset( $data['errors'] ) && ! empty( $data['errors'] ) ) {
			return new WP_Error( 'wmds_api_error', 'mobile.de reports: ' . self::error_message( $body ) );
		}

		return $data;
	}

	/**
	 * Extracts a readable message from an error body.
	 *
	 * Covers both shapes the API actually sends:
	 *   {"errors":[{"key":"invalid-search-parameter","message":"...","args":[...]}]}
	 *   {"title":"Not Acceptable","detail":"Acceptable representations: ...","status":406}
	 *
	 * @param string $body
	 * @return string Empty string when there is nothing usable in it.
	 */
	public static function error_message( $body ) {
		$body = trim( (string) $body );
		if ( '' === $body ) {
			return '';
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			return '';
		}

		// Shape one: application/problem+json.
		if ( isset( $data['detail'] ) || isset( $data['title'] ) ) {
			$parts = array();
			if ( ! empty( $data['title'] ) ) {
				$parts[] = (string) $data['title'];
			}
			if ( ! empty( $data['detail'] ) ) {
				$parts[] = (string) $data['detail'];
			}
			return implode( ': ', $parts );
		}

		if ( empty( $data['errors'] ) || ! is_array( $data['errors'] ) ) {
			return '';
		}

		$out = array();
		foreach ( $data['errors'] as $error ) {
			if ( ! is_array( $error ) ) {
				continue;
			}
			$text = '';
			if ( ! empty( $error['key'] ) ) {
				$text = (string) $error['key'];
			}
			if ( ! empty( $error['message'] ) ) {
				$text = '' === $text ? (string) $error['message'] : $text . ' (' . $error['message'] . ')';
			}

			// args often names the specific field being rejected.
			if ( ! empty( $error['args'] ) && is_array( $error['args'] ) ) {
				$args = array();
				foreach ( $error['args'] as $arg ) {
					if ( is_array( $arg ) && isset( $arg['key'], $arg['value'] ) ) {
						$args[] = $arg['key'] . '=' . $arg['value'];
					}
				}
				if ( $args ) {
					$text .= ' [' . implode( ', ', $args ) . ']';
				}
			}

			if ( '' !== $text ) {
				$out[] = $text;
			}
		}

		return implode( '; ', $out );
	}
}
