<?php
/**
 * Schedule Manager Class
 *
 * @package Oli_Cron_Jobs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages custom cron schedules
 */
class Oli_CJ_Schedule_Manager {

	/**
	 * Initialize hooks
	 */
	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_custom_schedules' ) );
	}

	/**
	 * Add custom schedules to WP-Cron
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public static function add_custom_schedules( $schedules ) {
		$custom_schedules = self::get_custom_schedules();

		foreach ( $custom_schedules as $name => $data ) {
			$schedules[ $name ] = $data;
		}

		return $schedules;
	}

	/**
	 * Get all schedules (built-in + custom)
	 *
	 * @return array
	 */
	public static function get_all_schedules() {
		return wp_get_schedules();
	}

	/**
	 * Get custom schedules only
	 *
	 * @return array
	 */
	public static function get_custom_schedules() {
		return get_option( 'oli_cj_custom_schedules', array() );
	}

	/**
	 * Add a custom schedule
	 *
	 * @param string $name Schedule internal name.
	 * @param int    $interval Interval in seconds.
	 * @param string $display Display name.
	 * @return bool
	 */
	public static function add_schedule( $name, $interval, $display ) {
		$custom_schedules = self::get_custom_schedules();

		$custom_schedules[ $name ] = array(
			'interval' => absint( $interval ),
			'display'  => sanitize_text_field( $display ),
		);

		return update_option( 'oli_cj_custom_schedules', $custom_schedules );
	}

	/**
	 * Delete a custom schedule
	 *
	 * @param string $name Schedule name.
	 * @return bool
	 */
	public static function delete_schedule( $name ) {
		$custom_schedules = self::get_custom_schedules();

		if ( isset( $custom_schedules[ $name ] ) ) {
			unset( $custom_schedules[ $name ] );
			return update_option( 'oli_cj_custom_schedules', $custom_schedules );
		}

		return false;
	}

	/**
	 * Check if a schedule is custom
	 *
	 * @param string $name Schedule name.
	 * @return bool
	 */
	public static function is_custom_schedule( $name ) {
		$custom_schedules = self::get_custom_schedules();
		return isset( $custom_schedules[ $name ] );
	}

	/**
	 * Get schedule display name
	 *
	 * @param string $name Schedule name.
	 * @return string
	 */
	public static function get_schedule_display( $name ) {
		$schedules = self::get_all_schedules();
		return isset( $schedules[ $name ]['display'] ) ? $schedules[ $name ]['display'] : $name;
	}

	/**
	 * Get schedule interval
	 *
	 * @param string $name Schedule name.
	 * @return int
	 */
	public static function get_schedule_interval( $name ) {
		$schedules = self::get_all_schedules();
		return isset( $schedules[ $name ]['interval'] ) ? $schedules[ $name ]['interval'] : 0;
	}

	/**
	 * Format interval for display
	 *
	 * @param int $seconds Interval in seconds.
	 * @return string
	 */
	public static function format_interval( $seconds ) {
		$seconds = absint( $seconds );

		if ( $seconds < 60 ) {
			/* translators: %d: number of seconds */
			return sprintf( _n( '%d second', '%d seconds', $seconds, 'oli-cron-jobs' ), $seconds );
		}

		$minutes = floor( $seconds / 60 );
		if ( $minutes < 60 ) {
			/* translators: %d: number of minutes */
			return sprintf( _n( '%d minute', '%d minutes', $minutes, 'oli-cron-jobs' ), $minutes );
		}

		$hours = floor( $minutes / 60 );
		if ( $hours < 24 ) {
			/* translators: %d: number of hours */
			return sprintf( _n( '%d hour', '%d hours', $hours, 'oli-cron-jobs' ), $hours );
		}

		$days = floor( $hours / 24 );
		/* translators: %d: number of days */
		return sprintf( _n( '%d day', '%d days', $days, 'oli-cron-jobs' ), $days );
	}
}

// Initialize schedule manager.
Oli_CJ_Schedule_Manager::init();
