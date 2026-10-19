<?php
/**
 * Logger Class
 *
 * @package Oli_Cron_Jobs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logs cron event executions
 */
class Oli_CJ_Logger {

	/**
	 * Single instance
	 *
	 * @var Oli_CJ_Logger
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return Oli_CJ_Logger
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		add_action( 'all', array( $this, 'maybe_log_cron_event' ), 10, 3 );
		add_action( 'oli_cj_cleanup_logs', array( $this, 'cleanup_old_logs' ) );

		// Schedule log cleanup if not scheduled
		if ( ! wp_next_scheduled( 'oli_cj_cleanup_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'oli_cj_cleanup_logs' );
		}
	}

	/**
	 * Maybe log a cron event
	 *
	 * @param string $hook Hook name.
	 */
	public function maybe_log_cron_event( $hook ) {
		// Only log if this is a scheduled cron event
		if ( ! defined( 'DOING_CRON' ) || ! DOING_CRON ) {
			return;
		}

		// Skip our own cleanup event logging
		if ( 'oli_cj_cleanup_logs' === $hook ) {
			return;
		}

		// Get args if available
		$args = func_num_args() > 1 ? array_slice( func_get_args(), 1 ) : array();

		$start_time = microtime( true );

		// Store start time to calculate duration after event completes
		$GLOBALS['oli_cj_event_start'][ $hook ] = $start_time;
	}

	/**
	 * Create logs table
	 */
	public static function create_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'oli_cj_logs';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			hook varchar(191) NOT NULL,
			args longtext,
			event_type varchar(20) NOT NULL DEFAULT 'standard',
			duration float,
			error_message text,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY hook (hook),
			KEY created_at (created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Log a cron event
	 *
	 * @param string $hook Hook name.
	 * @param array  $args Event arguments.
	 * @param string $event_type Type of event (standard, manual, php, url, blocked).
	 * @param float  $duration Duration in seconds.
	 * @param string $error_message Error message if any.
	 * @return int|false Insert ID or false on failure.
	 */
	public static function log_event( $hook, $args = array(), $event_type = 'standard', $duration = 0, $error_message = '' ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'oli_cj_logs';

		$data = array(
			'hook'          => sanitize_text_field( $hook ),
			'args'          => wp_json_encode( $args ),
			'event_type'    => sanitize_text_field( $event_type ),
			'duration'      => floatval( $duration ),
			'error_message' => sanitize_textarea_field( $error_message ),
			'created_at'    => current_time( 'mysql' ),
		);

		$result = $wpdb->insert( $table_name, $data );

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get logs
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public static function get_logs( $args = array() ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'oli_cj_logs';

		$defaults = array(
			'hook'       => '',
			'event_type' => '',
			'limit'      => 100,
			'offset'     => 0,
			'order'      => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( '1=1' );
		$where_values = array();

		if ( ! empty( $args['hook'] ) ) {
			$where[] = 'hook = %s';
			$where_values[] = $args['hook'];
		}

		if ( ! empty( $args['event_type'] ) ) {
			$where[] = 'event_type = %s';
			$where_values[] = $args['event_type'];
		}

		$where_sql = implode( ' AND ', $where );

		$order = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

		$sql = "SELECT * FROM $table_name WHERE $where_sql ORDER BY created_at $order LIMIT %d OFFSET %d";
		$where_values[] = absint( $args['limit'] );
		$where_values[] = absint( $args['offset'] );

		if ( ! empty( $where_values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$results = $wpdb->get_results( $wpdb->prepare( $sql, $where_values ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$results = $wpdb->get_results( $sql );
		}

		return $results;
	}

	/**
	 * Get log count
	 *
	 * @param array $args Query arguments.
	 * @return int
	 */
	public static function get_log_count( $args = array() ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'oli_cj_logs';

		$where = array( '1=1' );
		$where_values = array();

		if ( ! empty( $args['hook'] ) ) {
			$where[] = 'hook = %s';
			$where_values[] = $args['hook'];
		}

		if ( ! empty( $args['event_type'] ) ) {
			$where[] = 'event_type = %s';
			$where_values[] = $args['event_type'];
		}

		$where_sql = implode( ' AND ', $where );

		$sql = "SELECT COUNT(*) FROM $table_name WHERE $where_sql";

		if ( ! empty( $where_values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$count = $wpdb->get_var( $wpdb->prepare( $sql, $where_values ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$count = $wpdb->get_var( $sql );
		}

		return absint( $count );
	}

	/**
	 * Delete old logs based on retention setting
	 */
	public function cleanup_old_logs() {
		global $wpdb;

		$retention_days = get_option( 'oli_cj_log_retention_days', 30 );
		$table_name = $wpdb->prefix . 'oli_cj_logs';

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $table_name WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				absint( $retention_days )
			)
		);
	}

	/**
	 * Clear all logs
	 *
	 * @return bool
	 */
	public static function clear_all_logs() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'oli_cj_logs';
		$result = $wpdb->query( "TRUNCATE TABLE $table_name" );

		return false !== $result;
	}

	/**
	 * Delete logs for a specific hook
	 *
	 * @param string $hook Hook name.
	 * @return int|false Number of rows deleted or false on failure.
	 */
	public static function delete_hook_logs( $hook ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'oli_cj_logs';

		return $wpdb->delete(
			$table_name,
			array( 'hook' => $hook ),
			array( '%s' )
		);
	}
}
