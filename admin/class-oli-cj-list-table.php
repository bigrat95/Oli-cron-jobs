<?php
/**
 * List Table Class
 *
 * @package Oli_Cron_Jobs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Displays cron events in a list table
 */
class Oli_CJ_List_Table extends WP_List_Table {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => __( 'Cron Event', 'oli-cron-jobs' ),
				'plural'   => __( 'Cron Events', 'oli-cron-jobs' ),
				'ajax'     => false,
			)
		);
	}

	/**
	 * Display filter dropdowns
	 *
	 * @param string $which Top or bottom table nav.
	 */
	public function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list-table filters via GET.
		$status_filter   = isset( $_REQUEST['status_filter'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['status_filter'] ) ) : '';
		$schedule_filter = isset( $_REQUEST['schedule_filter'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['schedule_filter'] ) ) : '';
		$type_filter     = isset( $_REQUEST['type_filter'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['type_filter'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$schedules = Oli_CJ_Schedule_Manager::get_all_schedules();
		?>
		<div class="alignleft actions">
			<select name="status_filter">
				<option value=""><?php echo esc_html__( 'All Statuses', 'oli-cron-jobs' ); ?></option>
				<option value="scheduled" <?php selected( $status_filter, 'scheduled' ); ?>><?php echo esc_html__( 'Scheduled', 'oli-cron-jobs' ); ?></option>
				<option value="overdue" <?php selected( $status_filter, 'overdue' ); ?>><?php echo esc_html__( 'Overdue', 'oli-cron-jobs' ); ?></option>
				<option value="paused" <?php selected( $status_filter, 'paused' ); ?>><?php echo esc_html__( 'Paused', 'oli-cron-jobs' ); ?></option>
			</select>

			<select name="schedule_filter">
				<option value=""><?php echo esc_html__( 'All Schedules', 'oli-cron-jobs' ); ?></option>
				<option value="single" <?php selected( $schedule_filter, 'single' ); ?>><?php echo esc_html__( 'Single Events', 'oli-cron-jobs' ); ?></option>
				<?php foreach ( $schedules as $name => $schedule ) : ?>
					<option value="<?php echo esc_attr( $name ); ?>" <?php selected( $schedule_filter, $name ); ?>>
						<?php echo esc_html( $schedule['display'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<select name="type_filter">
				<option value=""><?php echo esc_html__( 'All Types', 'oli-cron-jobs' ); ?></option>
				<option value="core" <?php selected( $type_filter, 'core' ); ?>><?php echo esc_html__( 'WordPress Core', 'oli-cron-jobs' ); ?></option>
				<option value="plugin" <?php selected( $type_filter, 'plugin' ); ?>><?php echo esc_html__( 'Plugin', 'oli-cron-jobs' ); ?></option>
				<option value="custom" <?php selected( $type_filter, 'custom' ); ?>><?php echo esc_html__( 'Custom', 'oli-cron-jobs' ); ?></option>
				<option value="url" <?php selected( $type_filter, 'url' ); ?>><?php echo esc_html__( 'URL Events', 'oli-cron-jobs' ); ?></option>
				<option value="php" <?php selected( $type_filter, 'php' ); ?>><?php echo esc_html__( 'PHP Events', 'oli-cron-jobs' ); ?></option>
			</select>

			<input type="submit" class="button" value="<?php echo esc_attr__( 'Filter', 'oli-cron-jobs' ); ?>" />
		</div>
		<?php
	}

	/**
	 * Get columns
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'hook'       => __( 'Hook', 'oli-cron-jobs' ),
			'args'       => __( 'Arguments', 'oli-cron-jobs' ),
			'next_run'   => __( 'Next Run', 'oli-cron-jobs' ),
			'recurrence' => __( 'Recurrence', 'oli-cron-jobs' ),
			'callbacks'  => __( 'Callbacks', 'oli-cron-jobs' ),
			'status'     => __( 'Status', 'oli-cron-jobs' ),
		);
	}

	/**
	 * Get sortable columns
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'hook'     => array( 'hook', false ),
			'next_run' => array( 'timestamp', false ),
		);
	}

	/**
	 * Get bulk actions
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array(
			'delete' => __( 'Delete', 'oli-cron-jobs' ),
		);
	}

	/**
	 * Prepare items for display
	 */
	public function prepare_items() {
		$per_page     = 20;
		$current_page = $this->get_pagenum();

		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$this->process_bulk_action();

		$events = Oli_CJ_Cron_Manager::get_all_events();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list-table filters via GET.

		// Filter by status.
		if ( isset( $_REQUEST['status_filter'] ) && ! empty( $_REQUEST['status_filter'] ) ) {
			$status_filter = sanitize_text_field( wp_unslash( $_REQUEST['status_filter'] ) );
			$current_time  = time();
			$events        = array_filter(
				$events,
				function ( $event ) use ( $status_filter, $current_time ) {
					if ( 'overdue' === $status_filter ) {
						return $event['timestamp'] < ( $current_time - 600 );
					} elseif ( 'paused' === $status_filter ) {
						return Oli_CJ_Cron_Manager::is_hook_paused( $event['hook'] );
					} elseif ( 'scheduled' === $status_filter ) {
						return $event['timestamp'] >= $current_time && ! Oli_CJ_Cron_Manager::is_hook_paused( $event['hook'] );
					}
					return true;
				}
			);
		}

		// Filter by schedule.
		if ( isset( $_REQUEST['schedule_filter'] ) && ! empty( $_REQUEST['schedule_filter'] ) ) {
			$schedule_filter = sanitize_text_field( wp_unslash( $_REQUEST['schedule_filter'] ) );
			$events          = array_filter(
				$events,
				function ( $event ) use ( $schedule_filter ) {
					if ( 'single' === $schedule_filter ) {
						return ! $event['schedule'];
					} else {
						return $event['schedule'] === $schedule_filter;
					}
				}
			);
		}

		// Filter by hook type.
		if ( isset( $_REQUEST['type_filter'] ) && ! empty( $_REQUEST['type_filter'] ) ) {
			$type_filter = sanitize_text_field( wp_unslash( $_REQUEST['type_filter'] ) );
			$events      = array_filter(
				$events,
				function ( $event ) use ( $type_filter ) {
					return Oli_CJ_Cron_Manager::get_event_kind( $event['hook'] ) === $type_filter;
				}
			);
		}

		// Search.
		if ( isset( $_REQUEST['s'] ) && ! empty( $_REQUEST['s'] ) ) {
			$search = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) );
			$events = array_filter(
				$events,
				function ( $event ) use ( $search ) {
					return false !== stripos( $event['hook'], $search );
				}
			);
		}

		// Sort.
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'timestamp';
		$order   = isset( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : 'asc';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		usort(
			$events,
			function ( $a, $b ) use ( $orderby, $order ) {
				if ( 'hook' === $orderby ) {
					$result = strcmp( $a['hook'], $b['hook'] );
				} else {
					$result = $a['timestamp'] - $b['timestamp'];
				}

				return ( 'asc' === $order ) ? $result : -$result;
			}
		);

		$total_items = count( $events );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
			)
		);

		$this->items = array_slice( $events, ( ( $current_page - 1 ) * $per_page ), $per_page );
	}

	/**
	 * Process bulk actions
	 */
	public function process_bulk_action() {
		if ( 'delete' !== $this->current_action() ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'bulk-' . $this->_args['plural'] );

		$packed_events = array();
		if ( isset( $_REQUEST['event'] ) && is_array( $_REQUEST['event'] ) ) {
			$packed_events = array_map( 'sanitize_text_field', wp_unslash( $_REQUEST['event'] ) );
		}

		foreach ( $packed_events as $packed ) {
			$parts = explode( '|', $packed );
			if ( 3 === count( $parts ) ) {
				Oli_CJ_Cron_Manager::delete_event( $parts[0], absint( $parts[1] ), $parts[2] );
			}
		}
	}

	/**
	 * Column checkbox
	 *
	 * @param array $item Item data.
	 * @return string
	 */
	protected function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="event[]" value="%s" />',
			esc_attr( $item['hook'] . '|' . $item['timestamp'] . '|' . $item['sig'] )
		);
	}

	/**
	 * Column hook
	 *
	 * @param array $item Item data.
	 * @return string
	 */
	protected function column_hook( $item ) {
		$hook = esc_html( $item['hook'] );

		$actions = array();

		$nonce = wp_create_nonce( 'oli_cj_action' );

		$actions['run'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'tools.php?page=oli-cron-jobs&action=run_now&hook=' . rawurlencode( $item['hook'] ) . '&args=' . rawurlencode( wp_json_encode( $item['args'] ) ) . '&_wpnonce=' . $nonce ) ),
			__( 'Run Now', 'oli-cron-jobs' )
		);

		$actions['edit'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'tools.php?page=oli-cron-jobs-edit&hook=' . rawurlencode( $item['hook'] ) . '&timestamp=' . $item['timestamp'] . '&sig=' . rawurlencode( $item['sig'] ) ) ),
			__( 'Edit', 'oli-cron-jobs' )
		);

		$actions['delete'] = sprintf(
			'<a href="%s" class="delete oli-cj-confirm" data-oli-cj-confirm="%s">%s</a>',
			esc_url( admin_url( 'tools.php?page=oli-cron-jobs&action=delete_event&hook=' . rawurlencode( $item['hook'] ) . '&timestamp=' . $item['timestamp'] . '&sig=' . rawurlencode( $item['sig'] ) . '&_wpnonce=' . $nonce ) ),
			esc_attr( __( 'Are you sure?', 'oli-cron-jobs' ) ),
			__( 'Delete', 'oli-cron-jobs' )
		);

		if ( Oli_CJ_Cron_Manager::is_hook_paused( $item['hook'] ) ) {
			$actions['resume'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'tools.php?page=oli-cron-jobs&action=resume_hook&hook=' . rawurlencode( $item['hook'] ) . '&_wpnonce=' . $nonce ) ),
				__( 'Resume', 'oli-cron-jobs' )
			);
		} else {
			$actions['pause'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'tools.php?page=oli-cron-jobs&action=pause_hook&hook=' . rawurlencode( $item['hook'] ) . '&_wpnonce=' . $nonce ) ),
				__( 'Pause', 'oli-cron-jobs' )
			);
		}

		return sprintf( '<code>%s</code>%s', $hook, $this->row_actions( $actions ) );
	}

	/**
	 * Column args
	 *
	 * @param array $item Item data.
	 * @return string
	 */
	protected function column_args( $item ) {
		if ( empty( $item['args'] ) ) {
			return '<em>' . esc_html__( 'None', 'oli-cron-jobs' ) . '</em>';
		}

		return '<code>' . esc_html( wp_json_encode( $item['args'] ) ) . '</code>';
	}

	/**
	 * Column next run
	 *
	 * @param array $item Item data.
	 * @return string
	 */
	protected function column_next_run( $item ) {
		$timestamp    = $item['timestamp'];
		$current_time = time();

		$time_diff = $timestamp - $current_time;

		if ( $time_diff < 0 ) {
			return sprintf(
				'<span class="oli-cj-status-overdue">%s</span><br /><small>%s</small>',
				esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $timestamp ), get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ),
				/* translators: %s: human-readable time difference */
				esc_html( sprintf( __( '%s ago', 'oli-cron-jobs' ), human_time_diff( $timestamp, $current_time ) ) )
			);
		} else {
			return sprintf(
				'%s<br /><small>%s</small>',
				esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $timestamp ), get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ),
				/* translators: %s: human-readable time difference */
				esc_html( sprintf( __( 'in %s', 'oli-cron-jobs' ), human_time_diff( $current_time, $timestamp ) ) )
			);
		}
	}

	/**
	 * Column recurrence
	 *
	 * @param array $item Item data.
	 * @return string
	 */
	protected function column_recurrence( $item ) {
		if ( ! $item['schedule'] ) {
			return '<em>' . esc_html__( 'Non-repeating', 'oli-cron-jobs' ) . '</em>';
		}

		$display  = Oli_CJ_Schedule_Manager::get_schedule_display( $item['schedule'] );
		$interval = Oli_CJ_Schedule_Manager::format_interval( $item['interval'] );

		return sprintf(
			'%s<br /><small>%s</small>',
			esc_html( $display ),
			esc_html( $interval )
		);
	}

	/**
	 * Column callbacks
	 *
	 * @param array $item Item data.
	 * @return string
	 */
	protected function column_callbacks( $item ) {
		$callbacks = Oli_CJ_Cron_Manager::get_hook_callbacks( $item['hook'] );

		if ( empty( $callbacks ) ) {
			return '<em>' . esc_html__( 'None', 'oli-cron-jobs' ) . '</em>';
		}

		$output = array();
		foreach ( $callbacks as $callback ) {
			$output[] = sprintf(
				'<code>%s</code> <small>(priority: %d)</small><br><small>%s</small>',
				esc_html( $callback['function'] ),
				absint( $callback['priority'] ),
				esc_html( $callback['source'] )
			);
		}

		return implode( '<br />', $output );
	}

	/**
	 * Column status
	 *
	 * @param array $item Item data.
	 * @return string
	 */
	protected function column_status( $item ) {
		$current_time = time();
		$timestamp    = $item['timestamp'];

		if ( Oli_CJ_Cron_Manager::is_hook_paused( $item['hook'] ) ) {
			return '<span class="oli-cj-status-paused">' . esc_html__( 'Paused', 'oli-cron-jobs' ) . '</span>';
		}

		if ( $timestamp < ( $current_time - 600 ) ) {
			return '<span class="oli-cj-status-overdue">' . esc_html__( 'Overdue', 'oli-cron-jobs' ) . '</span>';
		}

		return '<span class="oli-cj-status-scheduled">' . esc_html__( 'Scheduled', 'oli-cron-jobs' ) . '</span>';
	}

	/**
	 * Render a single table row with status classes.
	 *
	 * @param array $item Item data.
	 */
	public function single_row( $item ) {
		$class = '';

		if ( Oli_CJ_Cron_Manager::is_hook_paused( $item['hook'] ) ) {
			$class = 'oli-cj-event-paused';
		} elseif ( $item['timestamp'] < ( time() - 600 ) ) {
			$class = 'oli-cj-event-overdue';
		}

		echo '<tr' . ( $class ? ' class="' . esc_attr( $class ) . '"' : '' ) . '>';
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	/**
	 * Default column
	 *
	 * @param array  $item Item data.
	 * @param string $column_name Column name.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
	}
}
