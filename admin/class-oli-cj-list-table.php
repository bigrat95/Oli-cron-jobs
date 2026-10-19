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
	 */
	public function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		$status_filter   = isset( $_REQUEST['status_filter'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['status_filter'] ) ) : '';
		$schedule_filter = isset( $_REQUEST['schedule_filter'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['schedule_filter'] ) ) : '';
		$type_filter     = isset( $_REQUEST['type_filter'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['type_filter'] ) ) : '';
		$schedules       = Oli_CJ_Schedule_Manager::get_all_schedules();
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
				<option value="custom" <?php selected( $type_filter, 'custom' ); ?>><?php echo esc_html__( 'Plugin/Theme', 'oli-cron-jobs' ); ?></option>
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

		$events = Oli_CJ_Cron_Manager::get_all_events();

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
					$hook = $event['hook'];
					if ( 'url' === $type_filter ) {
						return Oli_CJ_Cron_Manager::get_url( $hook ) !== false;
					} elseif ( 'php' === $type_filter ) {
						return Oli_CJ_Cron_Manager::get_php_code( $hook ) !== false;
					} elseif ( 'core' === $type_filter ) {
						return strpos( $hook, 'wp_' ) === 0;
					} elseif ( 'custom' === $type_filter ) {
						$is_url  = Oli_CJ_Cron_Manager::get_url( $hook ) !== false;
						$is_php  = Oli_CJ_Cron_Manager::get_php_code( $hook ) !== false;
						$is_core = strpos( $hook, 'wp_' ) === 0;
						return ! $is_url && ! $is_php && ! $is_core;
					}
					return true;
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
			esc_url( admin_url( 'tools.php?page=oli-cron-jobs&action=run_now&hook=' . urlencode( $item['hook'] ) . '&args=' . urlencode( wp_json_encode( $item['args'] ) ) . '&_wpnonce=' . $nonce ) ),
			__( 'Run Now', 'oli-cron-jobs' )
		);

		$actions['edit'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'tools.php?page=oli-cron-jobs-edit&hook=' . urlencode( $item['hook'] ) . '&timestamp=' . $item['timestamp'] . '&sig=' . urlencode( $item['sig'] ) ) ),
			__( 'Edit', 'oli-cron-jobs' )
		);

		$actions['delete'] = sprintf(
			'<a href="%s" class="delete" onclick="return confirm(\'%s\');">%s</a>',
			esc_url( admin_url( 'tools.php?page=oli-cron-jobs&action=delete_event&hook=' . urlencode( $item['hook'] ) . '&timestamp=' . $item['timestamp'] . '&sig=' . urlencode( $item['sig'] ) . '&_wpnonce=' . $nonce ) ),
			esc_js( __( 'Are you sure?', 'oli-cron-jobs' ) ),
			__( 'Delete', 'oli-cron-jobs' )
		);

		if ( Oli_CJ_Cron_Manager::is_hook_paused( $item['hook'] ) ) {
			$actions['resume'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'tools.php?page=oli-cron-jobs&action=resume_hook&hook=' . urlencode( $item['hook'] ) . '&_wpnonce=' . $nonce ) ),
				__( 'Resume', 'oli-cron-jobs' )
			);
		} else {
			$actions['pause'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'tools.php?page=oli-cron-jobs&action=pause_hook&hook=' . urlencode( $item['hook'] ) . '&_wpnonce=' . $nonce ) ),
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
				'<span style="color: red;">%s</span><br /><small>%s</small>',
				esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $timestamp ), get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ),
				esc_html( sprintf( __( '%s ago', 'oli-cron-jobs' ), human_time_diff( $timestamp, $current_time ) ) )
			);
		} else {
			return sprintf(
				'%s<br /><small>%s</small>',
				esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $timestamp ), get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ),
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
			return '<span style="color: orange;">⏸ ' . esc_html__( 'Paused', 'oli-cron-jobs' ) . '</span>';
		}

		if ( $timestamp < ( $current_time - 600 ) ) {
			return '<span style="color: red;">⚠ ' . esc_html__( 'Overdue', 'oli-cron-jobs' ) . '</span>';
		}

		return '<span style="color: green;">✓ ' . esc_html__( 'Scheduled', 'oli-cron-jobs' ) . '</span>';
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
