<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Posts {
	const MAKES_CACHE = 'wmds_makes';
	const METABOX     = 'wmds_source';

	public static function init() {
		add_filter( 'manage_' . WMDS_CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . WMDS_CPT . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_filter( 'manage_edit-' . WMDS_CPT . '_sortable_columns', array( __CLASS__, 'sortable' ) );

		add_action( 'restrict_manage_posts', array( __CLASS__, 'filters' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'query' ) );

		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );

		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ) );
		add_action( 'admin_notices', array( __CLASS__, 'edit_notice' ) );
	}

	/** @return bool Whether we are on one of the vehicle screens. */
	private static function on_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();

		return $screen && WMDS_CPT === $screen->post_type;
	}

	/**
	 * @param array $columns
	 * @return array
	 */
	public static function columns( $columns ) {
		$out = array();

		foreach ( $columns as $key => $label ) {
			if ( 'title' === $key ) {
				$out['wmds_thumb'] = '<span class="screen-reader-text">' . esc_html__( 'Photo', 'wp-mobile-de-sync' ) . '</span>';
			}

			$out[ $key ] = $label;

			if ( 'title' === $key ) {
				$out['wmds_make']    = __( 'Make & model', 'wp-mobile-de-sync' );
				$out['wmds_price']   = __( 'Price', 'wp-mobile-de-sync' );
				$out['wmds_mileage'] = __( 'Mileage', 'wp-mobile-de-sync' );
				$out['wmds_reg']     = __( 'First registration', 'wp-mobile-de-sync' );
				$out['wmds_ad']      = __( 'mobile.de ad', 'wp-mobile-de-sync' );
			}
		}

		return $out;
	}

	/**
	 * @param string $column
	 * @param int    $post_id
	 */
	public static function column( $column, $post_id ) {
		if ( 0 !== strpos( $column, 'wmds_' ) ) {
			return;
		}

		$vehicle = new WMDS_Vehicle( (int) $post_id );

		if ( 'wmds_thumb' === $column ) {
			self::thumb_cell( (int) $post_id );
			return;
		}

		if ( 'wmds_make' === $column ) {
			self::make_cell( $vehicle );
			return;
		}

		if ( 'wmds_price' === $column ) {
			$price = $vehicle->price();
			echo $price ? esc_html( $price ) : '<span class="wmds-muted">—</span>';
			return;
		}

		if ( 'wmds_mileage' === $column ) {
			$mileage = $vehicle->mileage();
			echo $mileage ? esc_html( $mileage ) : '<span class="wmds-muted">—</span>';
			return;
		}

		if ( 'wmds_reg' === $column ) {
			$reg = $vehicle->first_registration();
			echo $reg ? esc_html( $reg ) : '<span class="wmds-muted">—</span>';
			return;
		}

		if ( 'wmds_ad' === $column ) {
			self::ad_cell( (int) $post_id, $vehicle );
		}
	}

	/**
	 * @param int $post_id
	 */
	private static function thumb_cell( $post_id ) {
		if ( has_post_thumbnail( $post_id ) ) {
			echo '<span class="wmds-col-thumb">';
			echo get_the_post_thumbnail( $post_id, array( 80, 60 ), array( 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress builds and escapes the tag.
			echo '</span>';
			return;
		}

		printf(
			'<span class="wmds-col-thumb wmds-col-thumb-empty" title="%s"><span class="dashicons dashicons-format-image" aria-hidden="true"></span></span>',
			esc_attr__( 'No photo imported', 'wp-mobile-de-sync' )
		);
	}

	/**
	 * @param WMDS_Vehicle $vehicle
	 */
	private static function make_cell( WMDS_Vehicle $vehicle ) {
		$logo = $vehicle->logo_url();
		$make = $vehicle->get( 'make' );

		echo '<span class="wmds-col-make">';
		if ( $logo ) {
			printf( '<img src="%s" alt="" class="wmds-col-logo">', esc_url( $logo ) );
		}
		echo '<span>';
		echo $make ? '<strong>' . esc_html( $make ) . '</strong>' : '<span class="wmds-muted">—</span>';

		$model = $vehicle->get( 'model' );
		if ( $model ) {
			echo '<br><span class="wmds-muted">' . esc_html( $model ) . '</span>';
		}
		echo '</span></span>';
	}

	/**
	 * @param int          $post_id
	 * @param WMDS_Vehicle $vehicle
	 */
	private static function ad_cell( $post_id, WMDS_Vehicle $vehicle ) {
		$ad_id = (string) get_post_meta( $post_id, WMDS_Importer::META_AD_ID, true );

		if ( '' === $ad_id ) {
			printf(
				'<span class="wmds-badge wmds-badge-warning" title="%s">%s</span>',
				esc_attr__( 'Created by hand, not by the importer. A sync leaves it alone.', 'wp-mobile-de-sync' ),
				esc_html__( 'not synced', 'wp-mobile-de-sync' )
			);
			return;
		}

		$detail = $vehicle->get( 'detail_page_url' );

		if ( '' !== $detail ) {
			printf(
				'<a href="%s" target="_blank" rel="noopener">%s</a>',
				esc_url( $detail ),
				esc_html( $ad_id )
			);
		} else {
			echo esc_html( $ad_id );
		}
	}

	/**
	 * @param array $columns
	 * @return array
	 */
	public static function sortable( $columns ) {
		$columns['wmds_price']   = 'wmds_price';
		$columns['wmds_mileage'] = 'wmds_mileage';
		$columns['wmds_reg']     = 'wmds_reg';

		return $columns;
	}

	/** @return array<string, array{key:string,type:string}> */
	private static function sort_map() {
		return array(
			'wmds_price'   => array(
				'key'  => 'price_raw',
				'type' => 'NUMERIC',
			),
			'wmds_mileage' => array(
				'key'  => 'mileage_raw',
				'type' => 'NUMERIC',
			),
			'wmds_reg'     => array(
				'key'  => 'firstRegistration_year',
				'type' => 'NUMERIC',
			),
		);
	}

	public static function filters() {
		if ( ! self::on_screen() ) {
			return;
		}

		$makes = self::makes();
		if ( ! $makes ) {
			return;
		}

		$current = isset( $_GET['wmds_make'] ) ? sanitize_text_field( wp_unslash( $_GET['wmds_make'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read-only list filter.
		?>
		<label class="screen-reader-text" for="wmds_make"><?php esc_html_e( 'Filter by make', 'wp-mobile-de-sync' ); ?></label>
		<select name="wmds_make" id="wmds_make">
			<option value=""><?php esc_html_e( 'All makes', 'wp-mobile-de-sync' ); ?></option>
			<?php foreach ( $makes as $make ) : ?>
				<option value="<?php echo esc_attr( $make ); ?>" <?php selected( $current, $make ); ?>>
					<?php echo esc_html( $make ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/** @return string[] Distinct makes in the inventory. */
	private static function makes() {
		$cached = get_transient( self::MAKES_CACHE );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		$makes = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- cached in the transient below.
			$wpdb->prepare(
				"SELECT DISTINCT m.meta_value
				 FROM {$wpdb->postmeta} m
				 INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
				 WHERE m.meta_key = 'make' AND m.meta_value != ''
				   AND p.post_type = %s AND p.post_status = 'publish'
				 ORDER BY m.meta_value ASC",
				WMDS_CPT
			)
		);

		$makes = array_map( 'strval', (array) $makes );
		set_transient( self::MAKES_CACHE, $makes, 300 );

		return $makes;
	}

	/**
	 * @param WP_Query $query
	 */
	public static function query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || WMDS_CPT !== $query->get( 'post_type' ) ) {
			return;
		}

		self::apply_make_filter( $query );
		self::apply_sort( $query );
	}

	/**
	 * @param WP_Query $query
	 */
	private static function apply_make_filter( $query ) {
		$make = isset( $_GET['wmds_make'] ) ? sanitize_text_field( wp_unslash( $_GET['wmds_make'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read-only list filter.
		if ( '' === $make ) {
			return;
		}

		$meta   = (array) $query->get( 'meta_query' );
		$meta[] = array(
			'key'     => 'make',
			'value'   => $make,
			'compare' => '=',
		);

		$query->set( 'meta_query', $meta ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- an admin list screen, paginated by WordPress.
	}

	/**
	 * @param WP_Query $query
	 */
	private static function apply_sort( $query ) {
		$orderby = (string) $query->get( 'orderby' );
		$map     = self::sort_map();

		if ( ! isset( $map[ $orderby ] ) ) {
			return;
		}

		$field = $map[ $orderby ];
		$order = ( 'ASC' === strtoupper( (string) $query->get( 'order' ) ) ) ? 'ASC' : 'DESC';

		$meta   = (array) $query->get( 'meta_query' );
		$meta[] = array(
			'relation'    => 'OR',
			'wmds_sorted' => array(
				'key'     => $field['key'],
				'compare' => 'EXISTS',
				'type'    => $field['type'],
			),
			'wmds_absent' => array(
				'key'     => $field['key'],
				'compare' => 'NOT EXISTS',
			),
		);

		$query->set( 'meta_query', $meta ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- an admin list screen, paginated by WordPress.
		$query->set( 'orderby', array( 'wmds_sorted' => $order ) );
	}

	/**
	 * @param array   $actions
	 * @param WP_Post $post
	 * @return array
	 */
	public static function row_actions( $actions, $post ) {
		if ( WMDS_CPT !== $post->post_type || ! current_user_can( 'manage_options' ) ) {
			return $actions;
		}

		$ad_id = (string) get_post_meta( $post->ID, WMDS_Importer::META_AD_ID, true );
		if ( '' === $ad_id ) {
			return $actions;
		}

		$actions['wmds_refresh'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( WMDS_Status::action_url( 'refresh', array( 'post' => $post->ID ) ) ),
			esc_html__( 'Reload from mobile.de', 'wp-mobile-de-sync' )
		);

		$detail = (string) get_post_meta( $post->ID, 'detail_page_url', true );
		if ( '' !== $detail ) {
			$actions['wmds_source'] = sprintf(
				'<a href="%s" target="_blank" rel="noopener">%s</a>',
				esc_url( $detail ),
				esc_html__( 'View on mobile.de', 'wp-mobile-de-sync' )
			);
		}

		return $actions;
	}

	public static function meta_box() {
		add_meta_box(
			self::METABOX,
			__( 'mobile.de source', 'wp-mobile-de-sync' ),
			array( __CLASS__, 'render_meta_box' ),
			WMDS_CPT,
			'side',
			'high'
		);
	}

	/**
	 * @param WP_Post $post
	 */
	public static function render_meta_box( $post ) {
		$ad_id = (string) get_post_meta( $post->ID, WMDS_Importer::META_AD_ID, true );

		if ( '' === $ad_id ) {
			echo '<p>' . esc_html__( 'This vehicle was not created by the importer. A sync leaves it alone.', 'wp-mobile-de-sync' ) . '</p>';
			return;
		}

		$images = get_post_meta( $post->ID, WMDS_Importer::META_IMAGES, true );
		$rows   = array(
			__( 'Ad ID', 'wp-mobile-de-sync' )           => $ad_id,
			__( 'Changed on mobile.de', 'wp-mobile-de-sync' ) => (string) get_post_meta( $post->ID, WMDS_Importer::META_MODIFIED, true ),
			__( 'Photos imported', 'wp-mobile-de-sync' ) => (string) ( is_array( $images ) ? count( $images ) : 0 ),
		);

		echo '<table class="wmds-meta-table"><tbody>';
		foreach ( $rows as $label => $value ) {
			printf(
				'<tr><th scope="row">%s</th><td>%s</td></tr>',
				esc_html( $label ),
				esc_html( '' !== $value ? $value : '—' )
			);
		}
		echo '</tbody></table>';

		$detail = (string) get_post_meta( $post->ID, 'detail_page_url', true );
		if ( '' !== $detail ) {
			printf(
				'<p><a href="%s" target="_blank" rel="noopener">%s</a></p>',
				esc_url( $detail ),
				esc_html__( 'View the ad on mobile.de', 'wp-mobile-de-sync' )
			);
		}

		if ( current_user_can( 'manage_options' ) ) {
			printf(
				'<p><a class="button button-secondary" href="%s">%s</a></p>',
				esc_url( WMDS_Status::action_url( 'refresh', array( 'post' => $post->ID ) ) ),
				esc_html__( 'Reload this vehicle', 'wp-mobile-de-sync' )
			);
		}
	}

	public static function edit_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'post' !== $screen->base || WMDS_CPT !== $screen->post_type ) {
			return;
		}

		$post = get_post();
		if ( ! $post || '' === (string) get_post_meta( $post->ID, WMDS_Importer::META_AD_ID, true ) ) {
			return;
		}

		echo '<div class="notice notice-warning wmds-edit-notice"><p>';
		echo '<strong>' . esc_html__( 'Managed by the importer.', 'wp-mobile-de-sync' ) . '</strong> ';
		esc_html_e( 'Title, description, fields and photos are overwritten as soon as the ad changes on mobile.de. Edit the ad there, not here.', 'wp-mobile-de-sync' );
		echo '</p></div>';
	}

	public static function flush_makes() {
		delete_transient( self::MAKES_CACHE );
	}
}
