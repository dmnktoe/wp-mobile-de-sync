<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WMDS_Leads {
	const CPT = 'wmds_lead';

	const SOURCE = 'enquiry-form';

	const META_STATUS = '_wmds_status';
	const NEW_LEAD    = 'new';
	const ANSWERED    = 'answered';
	const SPAM        = 'spam';

	const META_SPAM = '_wmds_spam';

	const META_MAILED = '_wmds_mailed';

	const SPAM_DAYS = 30;

	const STATUS_ACTION = 'wmds_lead_status';

	const VIEW             = 'wmds_view';
	const VIEW_SPAM        = 'spam';
	const VIEW_UNDELIVERED = 'undelivered';

	/** @var int|null */
	private static $new = null;

	/** @var int|null */
	private static $spam = null;

	/** @var int|null */
	private static $undelivered = null;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_cpt' ) );

		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'privacy_exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'privacy_erasers' ) );

		if ( ! is_admin() ) {
			return;
		}

		add_filter( 'manage_' . self::CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_action( 'add_meta_boxes_' . self::CPT, array( __CLASS__, 'meta_box' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu_count' ), 99 );
		add_action( 'admin_post_' . self::STATUS_ACTION, array( __CLASS__, 'handle_status' ) );
		add_filter( 'bulk_actions-edit-' . self::CPT, array( __CLASS__, 'bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-' . self::CPT, array( __CLASS__, 'handle_bulk' ), 10, 3 );
		add_filter( 'views_edit-' . self::CPT, array( __CLASS__, 'views' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_list' ) );
		add_action( 'admin_notices', array( __CLASS__, 'undelivered_notice' ) );
	}

	/**
	 * @param int $post_id
	 * @return string
	 */
	public static function status( $post_id ) {
		$status = (string) get_post_meta( (int) $post_id, self::META_STATUS, true );

		return self::normalise_status( $status );
	}

	/**
	 * @param string $status
	 * @return string
	 */
	public static function normalise_status( $status ) {
		return in_array( $status, array( self::ANSWERED, self::SPAM ), true ) ? $status : self::NEW_LEAD;
	}

	/**
	 * @param int $post_id
	 * @return bool
	 */
	public static function is_spam( $post_id ) {
		return self::SPAM === self::status( $post_id );
	}

	/**
	 * @param int $post_id
	 * @return bool
	 */
	public static function is_new( $post_id ) {
		return self::NEW_LEAD === self::status( $post_id );
	}

	/**
	 * @param int $post_id
	 * @return string
	 */
	public static function status_label( $post_id ) {
		$labels = array(
			self::NEW_LEAD => __( 'New', 'wp-mobile-de-sync' ),
			self::ANSWERED => __( 'Answered', 'wp-mobile-de-sync' ),
			self::SPAM     => __( 'Spam', 'wp-mobile-de-sync' ),
		);

		return $labels[ self::status( $post_id ) ];
	}

	/**
	 * @return int
	 */
	public static function count_new() {
		if ( null !== self::$new ) {
			return self::$new;
		}

		global $wpdb;

		self::$new = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				 FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = %s
				 WHERE p.post_type = %s AND p.post_status = 'publish'
				   AND ( s.meta_value IS NULL OR s.meta_value NOT IN ( %s, %s ) )",
				self::META_STATUS,
				self::CPT,
				self::ANSWERED,
				self::SPAM
			)
		);

		return self::$new;
	}

	/** @return int */
	public static function count_spam() {
		if ( null !== self::$spam ) {
			return self::$spam;
		}

		global $wpdb;

		self::$spam = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = %s
				 WHERE p.post_type = %s AND p.post_status = 'publish' AND s.meta_value = %s",
				self::META_STATUS,
				self::CPT,
				self::SPAM
			)
		);

		return self::$spam;
	}

	/**
	 * @return int
	 */
	public static function count_undelivered() {
		if ( null !== self::$undelivered ) {
			return self::$undelivered;
		}

		global $wpdb;

		self::$undelivered = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s AND m.meta_value = '0'
				 LEFT JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = %s
				 WHERE p.post_type = %s AND p.post_status = 'publish'
				   AND ( s.meta_value IS NULL OR s.meta_value NOT IN ( %s, %s ) )",
				self::META_MAILED,
				self::META_STATUS,
				self::CPT,
				self::ANSWERED,
				self::SPAM
			)
		);

		return self::$undelivered;
	}

	/**
	 * @param int $post_id
	 * @return bool
	 */
	public static function is_undelivered( $post_id ) {
		return '0' === (string) get_post_meta( (int) $post_id, self::META_MAILED, true );
	}

	/**
	 * @param int    $post_id
	 * @param string $status
	 */
	public static function set_status( $post_id, $status ) {
		update_post_meta( (int) $post_id, self::META_STATUS, self::normalise_status( (string) $status ) );

		self::$new         = null;
		self::$spam        = null;
		self::$undelivered = null;
	}

	/**
	 * @param int    $post_id
	 * @param string $status
	 */
	public static function mark( $post_id, $status ) {
		$before = self::status( $post_id );
		$after  = self::normalise_status( (string) $status );

		self::set_status( $post_id, $after );

		if ( $before === $after ) {
			return;
		}

		$email = (string) get_post_meta( (int) $post_id, '_wmds_email', true );
		if ( '' === $email ) {
			return;
		}

		if ( self::SPAM === $after ) {
			WMDS_Spam::save_blocklist( WMDS_Spam::learn( WMDS_Spam::blocklist(), $email ) );
		} elseif ( self::SPAM === $before ) {
			WMDS_Spam::save_blocklist( WMDS_Spam::forget( WMDS_Spam::blocklist(), $email ) );
		}
	}

	public static function purge_spam() {
		$old = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'fields'         => 'ids',
				'meta_key'       => self::META_STATUS, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- a handful of rows, only when spam arrives.
				'meta_value'     => self::SPAM, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- see above.
				'date_query'     => array(
					array(
						'before' => self::SPAM_DAYS . ' days ago',
					),
				),
			)
		);

		foreach ( (array) $old as $id ) {
			wp_delete_post( (int) $id, true );
		}
	}

	public static function menu_count() {
		global $submenu;

		$new    = self::count_new();
		$parent = 'edit.php?post_type=' . WMDS_CPT;

		if ( ! $new || empty( $submenu[ $parent ] ) ) {
			return;
		}

		foreach ( $submenu[ $parent ] as $index => $item ) {
			if ( isset( $item[2] ) && 'edit.php?post_type=' . self::CPT === $item[2] ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the documented way to put a count on a menu entry.
				$submenu[ $parent ][ $index ][0] .= sprintf(
					' <span class="awaiting-mod count-%1$d"><span class="pending-count">%2$s</span></span>',
					$new,
					esc_html( number_format_i18n( $new ) )
				);
			}
		}
	}

	/**
	 * @param int    $post_id
	 * @param string $status
	 * @return string
	 */
	public static function status_url( $post_id, $status ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::STATUS_ACTION,
					'lead'   => (int) $post_id,
					'status' => $status,
				),
				admin_url( 'admin-post.php' )
			),
			self::STATUS_ACTION . '_' . (int) $post_id
		);
	}

	public static function handle_status() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- checked right below, against the ID it names.
		$post_id = isset( $_GET['lead'] ) ? (int) $_GET['lead'] : 0;
		$status  = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		check_admin_referer( self::STATUS_ACTION . '_' . $post_id );

		if ( ! $post_id || self::CPT !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'wp-mobile-de-sync' ), '', array( 'response' => 403 ) );
		}

		self::mark( $post_id, $status );

		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : admin_url( 'edit.php?post_type=' . self::CPT ) );
		exit;
	}

	/**
	 * @param int $post_id
	 * @return string
	 */
	public static function reply_url( $post_id ) {
		$email = (string) get_post_meta( (int) $post_id, '_wmds_email', true );
		if ( '' === $email ) {
			return '';
		}

		$vehicle = (int) get_post_meta( (int) $post_id, '_wmds_vehicle', true );
		$subject = $vehicle
			/* translators: %s: vehicle title. */
			? sprintf( __( 'Your enquiry about %s', 'wp-mobile-de-sync' ), get_the_title( $vehicle ) )
			: __( 'Your enquiry', 'wp-mobile-de-sync' );

		$name     = (string) get_post_meta( (int) $post_id, '_wmds_name', true );
		$message  = (string) get_post_field( 'post_content', (int) $post_id );
		$greeting = '' !== $name
			/* translators: %s: the name the enquirer gave. */
			? sprintf( __( 'Hello %s,', 'wp-mobile-de-sync' ), $name )
			: __( 'Hello,', 'wp-mobile-de-sync' );

		$quoted = '> ' . str_replace( "\n", "\n> ", str_replace( "\r", '', $message ) );
		$body   = $greeting . "\n\n\n\n" . __( 'Your message:', 'wp-mobile-de-sync' ) . "\n" . $quoted;

		return 'mailto:' . rawurlencode( $email ) . '?subject=' . rawurlencode( $subject ) . '&body=' . rawurlencode( $body );
	}

	/**
	 * @param array   $actions
	 * @param WP_Post $post
	 * @return array
	 */
	public static function row_actions( $actions, $post ) {
		if ( self::CPT !== $post->post_type ) {
			return $actions;
		}

		if ( self::is_spam( $post->ID ) ) {
			$actions['wmds_status'] = sprintf( '<a href="%s">%s</a>', esc_url( self::status_url( $post->ID, self::NEW_LEAD ) ), esc_html__( 'Not spam', 'wp-mobile-de-sync' ) );

			return $actions;
		}

		$reply = self::reply_url( $post->ID );
		if ( '' !== $reply ) {
			$actions['wmds_reply'] = sprintf( '<a href="%s">%s</a>', esc_attr( $reply ), esc_html__( 'Reply', 'wp-mobile-de-sync' ) );
		}

		$actions['wmds_status'] = self::is_new( $post->ID )
			? sprintf( '<a href="%s">%s</a>', esc_url( self::status_url( $post->ID, self::ANSWERED ) ), esc_html__( 'Mark as answered', 'wp-mobile-de-sync' ) )
			: sprintf( '<a href="%s">%s</a>', esc_url( self::status_url( $post->ID, self::NEW_LEAD ) ), esc_html__( 'Mark as new', 'wp-mobile-de-sync' ) );

		$actions['spam'] = sprintf( '<a href="%s">%s</a>', esc_url( self::status_url( $post->ID, self::SPAM ) ), esc_html__( 'Spam', 'wp-mobile-de-sync' ) );

		return $actions;
	}

	/**
	 * @param array $actions
	 * @return array
	 */
	public static function bulk_actions( $actions ) {
		$actions = is_array( $actions ) ? $actions : array();

		$actions['wmds_answered'] = __( 'Mark as answered', 'wp-mobile-de-sync' );
		$actions['wmds_spam']     = __( 'Mark as spam', 'wp-mobile-de-sync' );
		$actions['wmds_not_spam'] = __( 'Not spam', 'wp-mobile-de-sync' );

		return $actions;
	}

	/**
	 * @param string $redirect
	 * @param string $action
	 * @param int[]  $ids
	 * @return string
	 */
	public static function handle_bulk( $redirect, $action, $ids ) {
		$statuses = array(
			'wmds_answered' => self::ANSWERED,
			'wmds_spam'     => self::SPAM,
			'wmds_not_spam' => self::NEW_LEAD,
		);

		if ( ! isset( $statuses[ $action ] ) ) {
			return $redirect;
		}

		foreach ( (array) $ids as $id ) {
			$id = (int) $id;

			if ( self::CPT !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
				continue;
			}

			if ( 'wmds_not_spam' === $action && ! self::is_spam( $id ) ) {
				continue;
			}

			self::mark( $id, $statuses[ $action ] );
		}

		return $redirect;
	}

	/** @return string */
	public static function current_view() {
		$view = isset( $_GET[ self::VIEW ] ) ? sanitize_key( wp_unslash( $_GET[ self::VIEW ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which rows to show, nothing is changed.

		return in_array( $view, array( self::VIEW_SPAM, self::VIEW_UNDELIVERED ), true ) ? $view : '';
	}

	/**
	 * @param string $view
	 * @return string
	 */
	public static function view_url( $view ) {
		return add_query_arg(
			array(
				'post_type' => self::CPT,
				self::VIEW  => $view,
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * @param array $views
	 * @return array
	 */
	public static function views( $views ) {
		$views   = is_array( $views ) ? $views : array();
		$current = self::current_view();
		$counts  = array(
			self::VIEW_UNDELIVERED => array( self::count_undelivered(), __( 'Not delivered', 'wp-mobile-de-sync' ) ),
			self::VIEW_SPAM        => array( self::count_spam(), __( 'Spam', 'wp-mobile-de-sync' ) ),
		);

		if ( '' !== $current ) {
			foreach ( $views as $key => $link ) {
				$views[ $key ] = str_replace( array( ' class="current"', ' aria-current="page"' ), '', $link );
			}
		}

		foreach ( $counts as $view => $count ) {
			if ( ! $count[0] && $current !== $view ) {
				continue;
			}

			$views[ 'wmds_' . $view ] = sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
				esc_url( self::view_url( $view ) ),
				$current === $view ? ' class="current" aria-current="page"' : '',
				esc_html( $count[1] ),
				esc_html( number_format_i18n( $count[0] ) )
			);
		}

		return $views;
	}

	/**
	 * @param string $view
	 * @return array
	 */
	public static function view_query( $view ) {
		if ( self::VIEW_SPAM === $view ) {
			return array(
				array(
					'key'   => self::META_STATUS,
					'value' => self::SPAM,
				),
			);
		}

		$open = array(
			'relation' => 'OR',
			array(
				'key'     => self::META_STATUS,
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => self::META_STATUS,
				'value'   => self::VIEW_UNDELIVERED === $view ? array( self::ANSWERED, self::SPAM ) : array( self::SPAM ),
				'compare' => 'NOT IN',
			),
		);

		if ( self::VIEW_UNDELIVERED !== $view ) {
			return array( $open );
		}

		return array(
			'relation' => 'AND',
			$open,
			array(
				'key'   => self::META_MAILED,
				'value' => '0',
			),
		);
	}

	/**
	 * @param WP_Query $query
	 */
	public static function filter_list( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || self::CPT !== $query->get( 'post_type' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-' . self::CPT !== $screen->id ) {
			return;
		}

		$query->set( 'meta_query', self::view_query( self::current_view() ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- the list screen, a few hundred rows at most.
	}

	public static function undelivered_notice() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$where  = $screen ? (string) $screen->id : '';

		if ( ! in_array( $where, array( 'dashboard', 'edit-' . self::CPT ), true ) && ! WMDS_Admin::on_page() ) {
			return;
		}

		if ( self::VIEW_UNDELIVERED === self::current_view() ) {
			return;
		}

		$count = self::count_undelivered();
		if ( ! $count ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
			esc_html__( 'Enquiries:', 'wp-mobile-de-sync' ),
			esc_html(
				sprintf(
					/* translators: %d: number of enquiries. */
					_n( '%d enquiry could not be sent by e-mail and is only stored here.', '%d enquiries could not be sent by e-mail and are only stored here.', $count, 'wp-mobile-de-sync' ),
					$count
				)
			),
			esc_url( self::view_url( self::VIEW_UNDELIVERED ) ),
			esc_html__( 'Show them', 'wp-mobile-de-sync' )
		);
	}

	/**
	 * @param array $columns
	 * @return array
	 */
	public static function columns( $columns ) {
		return array(
			'cb'           => isset( $columns['cb'] ) ? $columns['cb'] : '',
			'title'        => __( 'Enquiry', 'wp-mobile-de-sync' ),
			'wmds_status'  => __( 'Status', 'wp-mobile-de-sync' ),
			'wmds_contact' => __( 'Contact', 'wp-mobile-de-sync' ),
			'wmds_vehicle' => __( 'Vehicle', 'wp-mobile-de-sync' ),
			'date'         => isset( $columns['date'] ) ? $columns['date'] : __( 'Received', 'wp-mobile-de-sync' ),
		);
	}

	/**
	 * @param string $column
	 * @param int    $post_id
	 */
	public static function column( $column, $post_id ) {
		if ( 'wmds_status' === $column ) {
			printf(
				'<span class="wmds-lead-status wmds-lead-status-%s">%s</span>',
				esc_attr( self::status( $post_id ) ),
				esc_html( self::status_label( $post_id ) )
			);

			if ( self::is_spam( $post_id ) ) {
				$reason = (string) get_post_meta( $post_id, self::META_SPAM, true );
				if ( '' !== $reason ) {
					printf( '<br><small>%s</small>', esc_html( $reason ) );
				}
			} elseif ( self::is_undelivered( $post_id ) ) {
				printf( '<br><small class="wmds-lead-undelivered">%s</small>', esc_html__( 'Not delivered by e-mail', 'wp-mobile-de-sync' ) );
			}

			return;
		}

		if ( 'wmds_contact' === $column ) {
			$email = (string) get_post_meta( $post_id, '_wmds_email', true );
			$phone = (string) get_post_meta( $post_id, '_wmds_phone', true );

			if ( '' !== $email ) {
				printf( '<a href="mailto:%1$s">%1$s</a>', esc_attr( $email ) );
			}
			if ( '' !== $phone ) {
				printf( '<br><span>%s</span>', esc_html( $phone ) );
			}

			return;
		}

		if ( 'wmds_vehicle' !== $column ) {
			return;
		}

		$vehicle = (int) get_post_meta( $post_id, '_wmds_vehicle', true );
		if ( ! $vehicle ) {
			echo '—';

			return;
		}

		printf(
			'<a href="%s">%s</a>',
			esc_url( (string) get_edit_post_link( $vehicle ) ),
			esc_html( (string) get_the_title( $vehicle ) )
		);
	}

	public static function meta_box() {
		add_meta_box(
			'wmds-lead',
			__( 'The enquiry', 'wp-mobile-de-sync' ),
			array( __CLASS__, 'render_meta_box' ),
			self::CPT,
			'normal',
			'high'
		);
	}

	/**
	 * @return array<string, string>
	 */
	public static function sources() {
		return array(
			self::SOURCE     => __( 'Enquiry form', 'wp-mobile-de-sync' ),
			WMDS_Cf7::SOURCE => 'Contact Form 7',
		);
	}

	/**
	 * @param string $key
	 * @param string $form
	 * @return string
	 */
	public static function source_label( $key, $form = '' ) {
		$sources = self::sources();
		$key     = (string) $key;

		if ( '' === $key ) {
			return '';
		}

		$label = isset( $sources[ $key ] ) ? $sources[ $key ] : $key;
		$form  = trim( (string) $form );

		return ( '' === $form ) ? $label : $label . ' – ' . $form;
	}

	/**
	 * @param WP_Post $post
	 */
	public static function render_meta_box( $post ) {
		$rows = array(
			__( 'Name', 'wp-mobile-de-sync' )             => (string) get_post_meta( $post->ID, '_wmds_name', true ),
			__( 'E-mail', 'wp-mobile-de-sync' )           => (string) get_post_meta( $post->ID, '_wmds_email', true ),
			__( 'Phone', 'wp-mobile-de-sync' )            => (string) get_post_meta( $post->ID, '_wmds_phone', true ),
			__( 'Listing number', 'wp-mobile-de-sync' )   => (string) get_post_meta( $post->ID, '_wmds_ad_id', true ),
			__( 'Vehicle', 'wp-mobile-de-sync' )          => (string) get_post_meta( $post->ID, '_wmds_url', true ),
			__( 'Consent', 'wp-mobile-de-sync' )          => wp_strip_all_tags( (string) get_post_meta( $post->ID, '_wmds_consent', true ) ),
			__( 'Received through', 'wp-mobile-de-sync' ) => self::source_label(
				(string) get_post_meta( $post->ID, '_wmds_source', true ),
				(string) get_post_meta( $post->ID, '_wmds_form', true )
			),
			__( 'From', 'wp-mobile-de-sync' )             => (string) get_post_meta( $post->ID, '_wmds_ip', true ),
			__( 'Delivery', 'wp-mobile-de-sync' )         => (string) get_post_meta( $post->ID, '_wmds_delivery', true ),
			__( 'Spam', 'wp-mobile-de-sync' )             => self::is_spam( $post->ID ) ? (string) get_post_meta( $post->ID, self::META_SPAM, true ) : '',
		);

		echo '<table class="form-table" role="presentation">';
		foreach ( $rows as $label => $value ) {
			if ( '' === $value ) {
				continue;
			}
			printf(
				'<tr><th scope="row">%s</th><td>%s</td></tr>',
				esc_html( $label ),
				esc_html( $value )
			);
		}
		echo '</table>';

		printf( '<p><strong>%s</strong></p>', esc_html_x( 'Message', 'what the enquirer wrote', 'wp-mobile-de-sync' ) );
		printf( '<p style="white-space:pre-wrap">%s</p>', esc_html( $post->post_content ) );

		self::render_actions( $post->ID );
	}

	/**
	 * @param int $post_id
	 */
	private static function render_actions( $post_id ) {
		$reply = self::reply_url( $post_id );
		$new   = self::is_new( $post_id );
		$spam  = self::is_spam( $post_id );
		?>
		<p class="wmds-lead-actions">
			<span class="wmds-lead-status wmds-lead-status-<?php echo esc_attr( self::status( $post_id ) ); ?>"><?php echo esc_html( self::status_label( $post_id ) ); ?></span>
			<?php if ( $spam ) : ?>
				<a class="button" href="<?php echo esc_url( self::status_url( $post_id, self::NEW_LEAD ) ); ?>">
					<?php esc_html_e( 'Not spam', 'wp-mobile-de-sync' ); ?>
				</a>
			<?php else : ?>
				<?php if ( '' !== $reply ) : ?>
					<a class="button button-primary" href="<?php echo esc_attr( $reply ); ?>"><?php esc_html_e( 'Reply by e-mail', 'wp-mobile-de-sync' ); ?></a>
				<?php endif; ?>
				<a class="button" href="<?php echo esc_url( self::status_url( $post_id, $new ? self::ANSWERED : self::NEW_LEAD ) ); ?>">
					<?php echo esc_html( $new ? __( 'Mark as answered', 'wp-mobile-de-sync' ) : __( 'Mark as new', 'wp-mobile-de-sync' ) ); ?>
				</a>
				<a class="button wmds-button-spam" href="<?php echo esc_url( self::status_url( $post_id, self::SPAM ) ); ?>">
					<?php esc_html_e( 'Mark as spam', 'wp-mobile-de-sync' ); ?>
				</a>
			<?php endif; ?>
		</p>
		<?php if ( $spam ) : ?>
			<p class="description"><?php esc_html_e( 'No e-mail was sent for this enquiry. "Not spam" moves it back to the enquiries and takes the sender off the blocklist; it is not sent afterwards.', 'wp-mobile-de-sync' ); ?></p>
		<?php elseif ( $new ) : ?>
			<p class="description"><?php esc_html_e( '"Mark as spam" also puts the sender on the blocklist: their next enquiries are sorted out straight away.', 'wp-mobile-de-sync' ); ?></p>
		<?php endif; ?>
		<?php
	}

	public static function register_cpt() {
		register_post_type(
			self::CPT,
			array(
				'labels'          => array(
					'name'          => __( 'Enquiries', 'wp-mobile-de-sync' ),
					'singular_name' => __( 'Enquiry', 'wp-mobile-de-sync' ),
					'menu_name'     => __( 'Enquiries', 'wp-mobile-de-sync' ),
					'all_items'     => __( 'Enquiries', 'wp-mobile-de-sync' ),
					'edit_item'     => __( 'Enquiry', 'wp-mobile-de-sync' ),
					'search_items'  => __( 'Search enquiries', 'wp-mobile-de-sync' ),
					'not_found'     => __( 'No enquiries yet', 'wp-mobile-de-sync' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'edit.php?post_type=' . WMDS_CPT,
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
				'supports'        => array( 'title' ),
				'has_archive'     => false,
				'rewrite'         => false,
				'query_var'       => false,
				'show_in_rest'    => false,
			)
		);
	}

	/** @return bool */
	public static function enabled() {
		return WMDS_Settings::flag( 'enquiry_enabled' );
	}

	/** @return string */
	public static function consent_text() {
		return trim( (string) WMDS_Settings::get( 'enquiry_consent' ) );
	}

	/**
	 * @param int               $post_id
	 * @param WMDS_Vehicle|null $vehicle
	 * @return array{title:string,url:string,ad_id:string,price:string}
	 */
	public static function context( $post_id, $vehicle ) {
		if ( ! $post_id || ! $vehicle ) {
			return array(
				'title' => '',
				'url'   => '',
				'ad_id' => '',
				'price' => '',
			);
		}

		return array(
			'title' => (string) get_the_title( $post_id ),
			'url'   => (string) get_permalink( $post_id ),
			'ad_id' => $vehicle->get( 'vehicleListingID' ),
			'price' => $vehicle->price(),
		);
	}

	/**
	 * @param array $data
	 * @param int   $post_id
	 * @param array $context
	 * @return int
	 */
	public static function store( array $data, $post_id, array $context ) {
		if ( ! WMDS_Settings::flag( 'enquiry_store' ) ) {
			return 0;
		}

		return self::file( $data, $post_id, $context );
	}

	/**
	 * @param array $data
	 * @param int   $post_id
	 * @param array $context
	 * @param array $extra
	 * @return int
	 */
	public static function file( array $data, $post_id, array $context, array $extra = array() ) {
		$name = isset( $data['name'] ) ? (string) $data['name'] : '';

		if ( '' === $name ) {
			$name = isset( $data['email'] ) ? (string) $data['email'] : __( 'Enquiry', 'wp-mobile-de-sync' );
		}

		$title = '' !== $context['title']
			/* translators: 1: the name the enquirer gave, 2: vehicle title. */
			? sprintf( __( '%1$s about %2$s', 'wp-mobile-de-sync' ), $name, $context['title'] )
			: $name;

		$lead = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => self::CPT,
					'post_status'  => 'publish',
					'post_title'   => $title,
					'post_content' => isset( $data['message'] ) ? $data['message'] : '',
				)
			),
			true
		);

		if ( is_wp_error( $lead ) || ! $lead ) {
			return 0;
		}

		$meta = array_merge(
			array(
				'_wmds_name'      => $name,
				'_wmds_email'     => isset( $data['email'] ) ? $data['email'] : '',
				'_wmds_phone'     => isset( $data['phone'] ) ? $data['phone'] : '',
				'_wmds_vehicle'   => $post_id,
				'_wmds_ad_id'     => $context['ad_id'],
				'_wmds_url'       => $context['url'],
				'_wmds_consent'   => empty( $data['consent'] ) ? '' : self::consent_text(),
				'_wmds_ip'        => WMDS_Lead_Form::client_ip(),
				'_wmds_source'    => self::SOURCE,
				self::META_STATUS => self::NEW_LEAD,
			),
			$extra
		);

		foreach ( $meta as $key => $value ) {
			update_post_meta( $lead, $key, wp_slash( $value ) );
		}

		return (int) $lead;
	}

	/**
	 * @param array $exporters
	 * @return array
	 */
	public static function privacy_exporters( $exporters ) {
		$exporters = is_array( $exporters ) ? $exporters : array();

		$exporters['wp-mobile-de-sync'] = array(
			'exporter_friendly_name' => __( 'Vehicle enquiries', 'wp-mobile-de-sync' ),
			'callback'               => array( __CLASS__, 'privacy_export' ),
		);

		return $exporters;
	}

	/**
	 * @param array $erasers
	 * @return array
	 */
	public static function privacy_erasers( $erasers ) {
		$erasers = is_array( $erasers ) ? $erasers : array();

		$erasers['wp-mobile-de-sync'] = array(
			'eraser_friendly_name' => __( 'Vehicle enquiries', 'wp-mobile-de-sync' ),
			'callback'             => array( __CLASS__, 'privacy_erase' ),
		);

		return $erasers;
	}

	/**
	 * @param string $email
	 * @param int    $page
	 * @return int[]
	 */
	private static function leads_of( $email, $page ) {
		$email = trim( (string) $email );
		if ( '' === $email ) {
			return array();
		}

		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'      => self::CPT,
					'post_status'    => 'any',
					'posts_per_page' => 50,
					'paged'          => max( 1, (int) $page ),
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'fields'         => 'ids',
					'meta_key'       => '_wmds_email', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- a privacy request, run by hand and rarely.
					'meta_value'     => $email, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- see above.
				)
			)
		);
	}

	/**
	 * @param string $email
	 * @param int    $page
	 * @return array{data:array,done:bool}
	 */
	public static function privacy_export( $email, $page = 1 ) {
		$ids  = self::leads_of( $email, $page );
		$data = array();

		$fields = array(
			'_wmds_name'    => __( 'Name', 'wp-mobile-de-sync' ),
			'_wmds_email'   => __( 'E-mail', 'wp-mobile-de-sync' ),
			'_wmds_phone'   => __( 'Phone', 'wp-mobile-de-sync' ),
			'_wmds_url'     => __( 'Vehicle', 'wp-mobile-de-sync' ),
			'_wmds_consent' => __( 'Consent', 'wp-mobile-de-sync' ),
			'_wmds_ip'      => __( 'From', 'wp-mobile-de-sync' ),
		);

		foreach ( $ids as $id ) {
			$rows = array(
				array(
					'name'  => __( 'Received', 'wp-mobile-de-sync' ),
					'value' => (string) get_post_field( 'post_date', $id ),
				),
				array(
					'name'  => __( 'Your message', 'wp-mobile-de-sync' ),
					'value' => (string) get_post_field( 'post_content', $id ),
				),
			);

			foreach ( $fields as $key => $label ) {
				$value = (string) get_post_meta( $id, $key, true );
				if ( '' !== $value ) {
					$rows[] = array(
						'name'  => $label,
						'value' => $value,
					);
				}
			}

			$data[] = array(
				'group_id'    => 'wmds-enquiries',
				'group_label' => __( 'Vehicle enquiries', 'wp-mobile-de-sync' ),
				'item_id'     => 'wmds-enquiry-' . $id,
				'data'        => $rows,
			);
		}

		return array(
			'data' => $data,
			'done' => count( $ids ) < 50,
		);
	}

	/**
	 * @param string $email
	 * @param int    $page
	 * @return array{items_removed:int,items_retained:bool,messages:array,done:bool}
	 */
	public static function privacy_erase( $email, $page = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- the signature is WordPress's.
		$ids     = self::leads_of( $email, 1 );
		$removed = 0;

		foreach ( $ids as $id ) {
			if ( wp_delete_post( $id, true ) ) {
				++$removed;
			}
		}

		return array(
			'items_removed'  => $removed,
			'items_retained' => $removed < count( $ids ),
			'messages'       => array(),
			'done'           => count( $ids ) < 50 || $removed < count( $ids ),
		);
	}
}
