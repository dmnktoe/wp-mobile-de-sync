<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The dashboard side of the plugin: a widget with the newest vehicles and an
 * entry in "At a Glance".
 */
class WMDS_Dashboard {
	const WIDGET = 'wmds_latest_vehicles';
	const OPTION = 'wmds_dashboard';

	const MIN_ITEMS = 1;
	const MAX_ITEMS = 20;

	public static function init() {
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'register' ) );
		add_filter( 'dashboard_glance_items', array( __CLASS__, 'glance' ) );
	}

	public static function register() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			self::WIDGET,
			__( 'mobile.de — newest vehicles', 'wp-mobile-de-sync' ),
			array( __CLASS__, 'render' ),
			array( __CLASS__, 'configure' )
		);
	}

	/**
	 * @return array{count:int,orderby:string}
	 */
	public static function options() {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		$count = isset( $stored['count'] ) ? (int) $stored['count'] : 5;
		$count = max( self::MIN_ITEMS, min( self::MAX_ITEMS, $count ) );

		$orderby = isset( $stored['orderby'] ) ? (string) $stored['orderby'] : 'date';
		if ( ! array_key_exists( $orderby, self::orders() ) ) {
			$orderby = 'date';
		}

		return array(
			'count'   => $count,
			'orderby' => $orderby,
		);
	}

	/**
	 * @return array<string,string>
	 */
	private static function orders() {
		return array(
			'date'     => __( 'Newest import first', 'wp-mobile-de-sync' ),
			'modified' => __( 'Most recently changed first', 'wp-mobile-de-sync' ),
			'price'    => __( 'Most expensive first', 'wp-mobile-de-sync' ),
		);
	}

	/**
	 * The widget's own settings form, shown behind "Configure".
	 */
	public static function configure() {
		if ( isset( $_POST[ self::WIDGET ] ) && is_array( $_POST[ self::WIDGET ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- wp_dashboard_setup verifies the dashboard nonce before calling the control callback.
			$submitted = wp_unslash( $_POST[ self::WIDGET ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- both members are sanitised below.

			update_option(
				self::OPTION,
				array(
					'count'   => isset( $submitted['count'] ) ? (int) $submitted['count'] : 5,
					'orderby' => isset( $submitted['orderby'] ) ? sanitize_key( $submitted['orderby'] ) : 'date',
				),
				false
			);
		}

		$options = self::options();
		?>
		<p>
			<label for="wmds-widget-count"><?php esc_html_e( 'Number of vehicles', 'wp-mobile-de-sync' ); ?></label>
			<input type="number" class="small-text" id="wmds-widget-count"
				name="<?php echo esc_attr( self::WIDGET ); ?>[count]"
				min="<?php echo esc_attr( self::MIN_ITEMS ); ?>"
				max="<?php echo esc_attr( self::MAX_ITEMS ); ?>"
				value="<?php echo esc_attr( $options['count'] ); ?>">
		</p>
		<p>
			<label for="wmds-widget-orderby"><?php esc_html_e( 'Sort by', 'wp-mobile-de-sync' ); ?></label>
			<select id="wmds-widget-orderby" name="<?php echo esc_attr( self::WIDGET ); ?>[orderby]">
				<?php foreach ( self::orders() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $options['orderby'], $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	public static function render() {
		$options = self::options();

		self::render_status();

		$vehicles = self::query( $options['count'], $options['orderby'] );

		if ( ! $vehicles ) {
			echo '<p class="wmds-widget-empty">' . esc_html__( 'No vehicles yet.', 'wp-mobile-de-sync' ) . '</p>';
			self::render_footer();
			return;
		}

		echo '<ul class="wmds-widget-list">';
		foreach ( $vehicles as $post_id ) {
			self::render_item( (int) $post_id );
		}
		echo '</ul>';

		self::render_footer();
	}

	/**
	 * Only the parts of the state a non-administrator can act on; the repair
	 * links stay behind manage_options.
	 */
	private static function render_status() {
		$status = WMDS_Status::get();

		printf(
			'<p class="wmds-widget-status wmds-sev-bg-%s"><span class="wmds-bar-dot wmds-sev-%s" aria-hidden="true"></span> <strong>%s</strong>',
			esc_attr( $status['severity'] ),
			esc_attr( $status['severity'] ),
			esc_html( $status['label'] )
		);

		if ( ! empty( $status['last']['time'] ) ) {
			echo ' <span class="wmds-muted">';
			printf(
				/* translators: %s: date and time of the last run. */
				esc_html__( 'last run %s', 'wp-mobile-de-sync' ),
				esc_html( $status['last']['time'] )
			);
			echo '</span>';
		}

		echo '</p>';

		if ( current_user_can( 'manage_options' ) && WMDS_Status::is_actionable( $status['level'] ) ) {
			printf(
				'<p class="wmds-widget-hint">%s <a href="%s">%s</a></p>',
				esc_html( $status['explanation'] ),
				esc_url( WMDS_Status::settings_url( 'status' ) ),
				esc_html__( 'Open sync settings', 'wp-mobile-de-sync' )
			);
		}
	}

	/**
	 * @param int    $count
	 * @param string $orderby
	 * @return int[]
	 */
	private static function query( $count, $orderby ) {
		$args = array(
			'post_type'              => WMDS_CPT,
			'post_status'            => 'publish',
			'posts_per_page'         => $count,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'order'                  => 'DESC',
		);

		if ( 'price' === $orderby ) {
			$args['meta_key'] = 'price_raw'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- an admin widget capped at 20 rows.
			$args['orderby']  = 'meta_value_num';
		} else {
			$args['orderby'] = $orderby;
		}

		return get_posts( $args );
	}

	/**
	 * @param int $post_id
	 */
	private static function render_item( $post_id ) {
		$vehicle = new WMDS_Vehicle( $post_id );
		$edit    = get_edit_post_link( $post_id );

		$facts = array_filter(
			array(
				$vehicle->price(),
				$vehicle->mileage(),
				$vehicle->first_registration(),
				$vehicle->get( 'fuel' ),
			)
		);

		echo '<li class="wmds-widget-item">';

		echo '<span class="wmds-widget-thumb">';
		if ( has_post_thumbnail( $post_id ) ) {
			echo get_the_post_thumbnail( $post_id, array( 80, 60 ), array( 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress builds and escapes the tag.
		} else {
			echo '<span class="wmds-widget-thumb-empty dashicons dashicons-car" aria-hidden="true"></span>';
		}
		echo '</span>';

		echo '<span class="wmds-widget-body">';
		printf(
			'<a class="wmds-widget-title" href="%s">%s</a>',
			esc_url( $edit ? $edit : get_permalink( $post_id ) ),
			esc_html( get_the_title( $post_id ) )
		);
		if ( $facts ) {
			echo '<span class="wmds-widget-facts">' . esc_html( implode( ' · ', $facts ) ) . '</span>';
		}
		echo '</span>';

		echo '</li>';
	}

	private static function render_footer() {
		echo '<p class="wmds-widget-footer">';

		printf(
			'<a href="%s">%s</a>',
			esc_url( WMDS_Status::list_url() ),
			esc_html__( 'All vehicles', 'wp-mobile-de-sync' )
		);

		if ( current_user_can( 'manage_options' ) ) {
			printf(
				' <span class="wmds-muted">|</span> <a href="%s">%s</a>',
				esc_url( WMDS_Status::settings_url() ),
				esc_html__( 'Sync settings', 'wp-mobile-de-sync' )
			);
		}

		echo '</p>';
	}

	/**
	 * @param string[] $items
	 * @return string[]
	 */
	public static function glance( $items ) {
		$count = WMDS_Status::count();

		$text = sprintf(
			/* translators: %s: number of vehicles, already formatted. */
			_n( '%s Vehicle', '%s Vehicles', $count, 'wp-mobile-de-sync' ),
			number_format_i18n( $count )
		);

		$items[] = current_user_can( 'edit_posts' )
			? sprintf( '<a class="wmds-glance" href="%s">%s</a>', esc_url( WMDS_Status::list_url() ), esc_html( $text ) )
			: sprintf( '<span class="wmds-glance">%s</span>', esc_html( $text ) );

		return $items;
	}
}
