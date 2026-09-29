<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class PMProACR_Recovery_Attempts_List_Table extends WP_List_Table {
	/**
	 * The text domain of this plugin.
	 *
	 * @since 0.1
	 *
	 * @access private
	 * @var string $plugin_text_domain	The text domain of this plugin.
	 */
	protected $plugin_text_domain;

	/**
	 * Call the parent constructor to override the defaults $args
	 *
	 * @param string $plugin_text_domain Text domain of the plugin.
	 *
	 * @since 0.1
	 */
	public function __construct() {

		$this->plugin_text_domain = 'pmpro-abandoned-cart-recovery';

		parent::__construct(
			array(
				'plural'   => 'recovery attempts',
				// Plural value used for labels and the objects being listed.
				'singular' => 'recovery attempt',
				// Singular label for an object being listed, e.g. 'post'.
				'ajax'	 => false,
				// If true, the parent class will call the _js_vars() method in the footer
			)
		);
	}

	/**
	 * Sets up screen options for the abandoned cart recovery list table.
	 *
	 * @since 0.1
	 */
	public static function hook_screen_options() {
		$list_table = new PMProACR_Recovery_Attempts_List_Table();
		add_screen_option(
			'per_page',
			array(
				'default' => 20,
				'label'   => __( 'Recovery attempts per page', 'pmpro-abandoned-cart-recovery' ),
				'option'  => 'pmproacr_recovery_attempts_per_page',
			)
		);
		add_filter(
			'screen_settings',
			array(
				$list_table,
				'screen_controls',
			),
			10,
			2
		);
		add_filter(
			'set-screen-option',
			array(
				$list_table,
				'set_screen_option',
			),
			10,
			3
		);
		set_screen_options();
	}

	/**
	 * Sets the screen options.
	 *
	 * @param string $dummy   Unused.
	 * @param string $option  Screen option name.
	 * @param string $value   Screen option value.
	 * @return string
	 */
	public function set_screen_option( $dummy, $option, $value ) {
		if ( 'pmproacr_recovery_attempts_per_page' === $option ) {
			return $value;
		} else {
			return $dummy;
		}
	}

	/**
	 * Prepares the list of items for displaying.
	 *
	 * Query, filter data, handle sorting, and pagination, and any other data-manipulation required prior to rendering
	 *
	 * @since 0.1
	 */
	public function prepare_items() {
		
		$columns = $this->get_columns();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array($columns, array(), $sortable);

		$this->items = $this->sql_table_data();

		$items_per_page = $this->get_items_per_page( 'pmproacr_recovery_attempts_per_page' );
		$total_items = $this->sql_table_data( true );
		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'	=> $items_per_page,
				'total_pages' => ceil( $total_items / $items_per_page ),
			)
		);
		
	}

	/**
	 * Get a list of columns.
	 *
	 * The format is: 'internal-name' => 'Title'
	 *
	 * @since 0.1
	 *
	 * @return array
	 */
	public function get_columns() {

		$columns = array(
			'id'			  => __( 'ID', 'pmpro-abandoned-cart-recovery' ),
			'user'			=> __( 'User', 'pmpro-abandoned-cart-recovery' ),
			'token_order'	 => __( 'Token Order', 'pmpro-abandoned-cart-recovery' ),
			'status'		  => __( 'Status', 'pmpro-abandoned-cart-recovery' ),
			'reminder_1'	  => __( 'Reminder 1', 'pmpro-abandoned-cart-recovery' ),
			'reminder_2'	  => __( 'Reminder 2', 'pmpro-abandoned-cart-recovery' ),
			'reminder_3'	  => __( 'Reminder 3', 'pmpro-abandoned-cart-recovery' ),
			'recovered_order' => __( 'Recovered Order', 'pmpro-abandoned-cart-recovery' ),
		);

		return $columns;
	}

	/**
	 * Get a list of sortable columns. The format is:
	 * 'internal-name' => 'orderby'
	 * or
	 * 'internal-name' => array( 'orderby', true )
	 *
	 * The second format will make the initial sorting order be descending
	 *
	 * @since 0.1
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		/**
		 * actual sorting still needs to be done by prepare_items.
		 * specify which columns should have the sort icon.
		 *
		 * key => value
		 * column name_in_list_table => columnname in the db
		 */
		$sortable_columns = array(
			'id' => array( 'id', true ),
			'user' => array( 'user_id', false ),
		);
		
		return $sortable_columns;
	}

	/**
	 * Text displayed when no user data is available
	 *
	 * @since 0.1
	 *
	 * @return void
	 */
	public function no_items() {
			
		esc_html_e( 'No recovery attempts found.', 'pmpro-abandoned-cart-recovery' );

	}

	/**
	 * Get the table data
	 *
	 * @return Array|integer if $count parameter = true
	 */
	private function sql_table_data( $count = false ) {

		global $wpdb;

		// some vars for pagination
		if( isset( $_REQUEST['paged'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list table pagination.
			$pn = intval( $_REQUEST['paged'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list table pagination.
		} else {
			$pn = 1;
		}
		
		$limit = $this->get_items_per_page( 'pmproacr_recovery_attempts_per_page' );

		$end = $pn * $limit;
		$start = $end - $limit;

		if ( $count ) {
			$sqlQuery = "SELECT COUNT( DISTINCT id ) FROM $wpdb->pmproacr_recovery_attempts ";
		} else {
			$sqlQuery = "SELECT * FROM $wpdb->pmproacr_recovery_attempts ";			
		}

		if ( ! $count ) {

			if( isset( $_REQUEST['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list table sorting.
				$orderby = $this->sanitize_orderby( sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list table sorting.
			} else {
				$orderby = 'id';
			}

			if( isset( $_REQUEST['order'] ) && $_REQUEST['order'] == 'asc' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list table sorting.
				$order = 'ASC';
			} else {
				$order = 'DESC';
			}

			//Ordering needs to happen here
			$sqlQuery .= "ORDER BY `$orderby` $order ";
			
			$sqlQuery .= "LIMIT " . esc_sql( $start ) . "," .  esc_sql( $limit );
		}

		if( $count ) {
			$sql_table_data = $wpdb->get_var( $sqlQuery ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Static query on a custom table.
		} else {
			$sql_table_data = $wpdb->get_results( $sqlQuery ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; orderby is whitelisted by sanitize_orderby(), order is ASC/DESC, and LIMIT values are integers.
		}

		return $sql_table_data;
	}

	/**
	 * Sanitize the orderby value.
	 * Only allow fields we want to order by.
	 * Make sure we append the correct table prefix.
	 * Make sure there is no other SQL in the value.
	 * @param string $orderby The column to order by.
	 * @return string The sanitized value.
	 */
	function sanitize_orderby( $orderby ) {
		$allowed_orderbys = array(
			'id'	  => 'id',
			'user_id' => 'user_id',
		);

	 	if ( ! empty( $allowed_orderbys[$orderby] ) ) {
			$orderby = $allowed_orderbys[$orderby];
		} else {
			$orderby = false;
		}

		return $orderby;
	}

	/**
	 * Render a column when no column specific method exists.
	 *
	 * @param object $item
	 * @param string $column_name
	 *
	 * @return mixed
	 */
	public function column_default( $item, $column_name ) {
		if ( property_exists( $item, $column_name ) ) {
			echo esc_html( $item->$column_name );
		}
	}

	/**
	 * Render the user value.
	 *
	 * @param object $item
	 */
	public function column_user( $item ) {
		$user = get_userdata( $item->user_id );
		if ( ! empty( $user ) ) { 
			echo '<a href="' . esc_url( add_query_arg( array( 'page' => 'pmpro-member', 'user_id' => (int)$user->ID ), admin_url( 'admin.php' ) ) ) . '">' . esc_html( $user->user_login ) . '</a><br />';
			echo esc_html( $user->user_email );
		 } elseif ( $item->user_id > 0 ) {
			echo '['. esc_html__( 'deleted', 'pmpro-abandoned-cart-recovery' ) . ']';
		} else {
			echo '['. esc_html__( 'none', 'pmpro-abandoned-cart-recovery' ) . ']';
		}
	}

	/**
	 * Render the token order value.
	 *
	 * @param object $item
	 */
	public function column_token_order( $item ) {
		self::ouptut_order_data( $item->token_order_id, $item->token_level_id, $item->token_total, $item->token_datetime );
	}

	/**
	 * Render the status value.
	 *
	 * @param object $item
	 */
	public function column_status( $item ) {
		if ( $item->status === 'in_progress' ) {
			echo '<div class="pmpro_tag pmpro_tag-alert">' . esc_html__( 'In Progress', 'pmpro-abandoned-cart-recovery' ) . '</div>';
		} elseif ( $item->status === 'recovered' ) {
			echo '<div class="pmpro_tag pmpro_tag-success">' . esc_html__( 'Recovered', 'pmpro-abandoned-cart-recovery' ) . '</div>';
		} elseif ( $item->status === 'lost' ) {
			echo '<div class="pmpro_tag pmpro_tag-error">' . esc_html__( 'Lost', 'pmpro-abandoned-cart-recovery' ) . '</div>';
		} else {
			echo '<div class="pmpro_tag pmpro_tag-info">' . esc_html__( 'Unknown', 'pmpro-abandoned-cart-recovery' ) . '</div>';
		}
	}

	/**
	 * Render the reminder 1 value.
	 *
	 * @param object $item
	 */
	public function column_reminder_1( $item ) {
		$reminder = $item->reminder_1_datetime;
		if ( $reminder ) {
			echo esc_html( self::format_date( $reminder ) );
		} else {
			esc_html_e( '&#8212;', 'pmpro-abandoned-cart-recovery' );
		}
	}

	/**
	 * Render the reminder 2 value.
	 *
	 * @param object $item
	 */
	public function column_reminder_2( $item ) {
		$reminder = $item->reminder_2_datetime;
		if ( $reminder ) {
			// Output the reminder date in the site's local time and date format and the correct timezone.
			echo esc_html( self::format_date( $reminder ) );
		} else {
			esc_html_e( '&#8212;', 'pmpro-abandoned-cart-recovery' );
		}
	}

	/**
	 * Render the reminder 3 value.
	 *
	 * @param object $item
	 */
	public function column_reminder_3( $item ) {
		$reminder = $item->reminder_3_datetime;
		if ( $reminder ) {
			echo esc_html( self::format_date( $reminder ) );
		} else {
			esc_html_e( '&#8212;', 'pmpro-abandoned-cart-recovery' );
		}
	}

	/**
	 * Render the recovered order value.
	 *
	 * @param object $item
	 */
	public function column_recovered_order( $item ) {
		if ( ! empty( $item->recovered_order_id ) ) {
			self::ouptut_order_data( $item->recovered_order_id, $item->recovered_level_id, $item->recovered_total, $item->recovered_datetime );
		} else {
			esc_html_e( '&#8212;', 'pmpro-abandoned-cart-recovery' );
		}
	}

	/**
	 * Helper function to output an order's timestamp, level, and total.
	 *
	 * @param int $order_id The order ID.
	 */
	public static function ouptut_order_data( $order_id, $level_id, $total, $datetime ) {
		$level = pmpro_getLevel( $level_id );
		$level_name = ! empty( $level ) ? $level->name : '#' . $level_id;
		echo '<p>' . esc_html( sprintf(
			/* translators: 1: level name, 2: total */
			__( 'Level: %1$s (%2$s)', 'pmpro-abandoned-cart-recovery' ),
			$level_name,
			pmpro_formatPrice( $total )
		) ) . '</p>';
		echo '<p>' . esc_html( self::format_date( $datetime ) ) . '</p>';
		
		// Check PMPRo version, anything before 3.6 used 'order' parameter, 3.6+ uses 'id' parameter.
		$order_id_param = version_compare( PMPRO_VERSION, '3.6', '>=' ) ? 'id' : 'order';
		
		// Show an edit link.
		echo '<p><a href="' . esc_url( add_query_arg( array( 'page' => 'pmpro-orders', $order_id_param => $order_id ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'View Order', 'pmpro-abandoned-cart-recovery' ) . '</a></p>';
	}

	/**
	 * Format the passed date for display.
	 *
	 * @param string|int $date The date to format in Y-m-d H:i:s or timestamp format.
	 * @return string The formatted date.
	 */
	public static function format_date( $date ) {
		return sprintf(
			// translators: %1$s is the date and %2$s is the time.
			__( '%1$s at %2$s', 'pmpro-abandoned-cart-recovery' ),
			get_date_from_gmt( $date, get_option( 'date_format' ) ),
			get_date_from_gmt( $date, get_option( 'time_format' ) )
		);
	}
}
