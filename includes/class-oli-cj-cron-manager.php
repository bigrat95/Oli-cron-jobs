<?php
/**
 * Cron Manager Class
 *
 * @package Oli_Cron_Jobs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages WP-Cron events
 */
class Oli_CJ_Cron_Manager {

	/**
	 * Get all cron events
	 *
	 * @return array
	 */
	public static function get_all_events() {
		$crons  = _get_cron_array();
		$events = array();

		if ( empty( $crons ) ) {
			return $events;
		}

		foreach ( $crons as $timestamp => $cron ) {
			foreach ( $cron as $hook => $dings ) {
				foreach ( $dings as $sig => $data ) {
					$events[] = array(
						'hook'      => $hook,
						'timestamp' => $timestamp,
						'sig'       => $sig,
						'args'      => $data['args'],
						'schedule'  => isset( $data['schedule'] ) ? $data['schedule'] : false,
						'interval'  => isset( $data['interval'] ) ? $data['interval'] : 0,
					);
				}
			}
		}

		return $events;
	}

	/**
	 * Get event details
	 *
	 * @param string $hook Event hook.
	 * @param int    $timestamp Event timestamp.
	 * @param string $sig Event signature.
	 * @return array|false
	 */
	public static function get_event( $hook, $timestamp, $sig ) {
		$crons = _get_cron_array();

		if ( ! isset( $crons[ $timestamp ][ $hook ][ $sig ] ) ) {
			return false;
		}

		$event = $crons[ $timestamp ][ $hook ][ $sig ];
		$event['hook'] = $hook;
		$event['timestamp'] = $timestamp;
		$event['sig'] = $sig;

		return $event;
	}

	/**
	 * Add a new cron event
	 *
	 * @param string $hook Event hook.
	 * @param int    $timestamp Event timestamp.
	 * @param array  $args Event arguments.
	 * @param string $schedule Schedule name (false for single event).
	 * @return bool
	 */
	public static function add_event( $hook, $timestamp, $args = array(), $schedule = false ) {
		if ( $schedule ) {
			return wp_schedule_event( $timestamp, $schedule, $hook, $args );
		} else {
			return wp_schedule_single_event( $timestamp, $hook, $args );
		}
	}

	/**
	 * Delete a cron event
	 *
	 * @param string $hook Event hook.
	 * @param int    $timestamp Event timestamp.
	 * @param string $sig Event signature.
	 * @return bool
	 */
	public static function delete_event( $hook, $timestamp, $sig ) {
		$crons = _get_cron_array();

		if ( ! isset( $crons[ $timestamp ][ $hook ][ $sig ] ) ) {
			return false;
		}

		$args = $crons[ $timestamp ][ $hook ][ $sig ]['args'];
		wp_unschedule_event( $timestamp, $hook, $args );

		return true;
	}

	/**
	 * Delete all instances of a hook
	 *
	 * @param string $hook Event hook.
	 * @return int Number of events deleted.
	 */
	public static function delete_all_hook_events( $hook ) {
		$count = 0;
		$crons = _get_cron_array();

		if ( empty( $crons ) ) {
			return 0;
		}

		foreach ( $crons as $timestamp => $cron ) {
			if ( isset( $cron[ $hook ] ) ) {
				foreach ( $cron[ $hook ] as $sig => $data ) {
					wp_unschedule_event( $timestamp, $hook, $data['args'] );
					$count++;
				}
			}
		}

		return $count;
	}

	/**
	 * Run a cron event now
	 *
	 * @param string $hook Event hook.
	 * @param array  $args Event arguments.
	 * @return mixed Result of the action.
	 */
	public static function run_event_now( $hook, $args = array() ) {
		$start_time = microtime( true );

		// Run the event
		do_action_ref_array( $hook, $args );

		$duration = microtime( true ) - $start_time;

		// Log the execution
		Oli_CJ_Logger::log_event( $hook, $args, 'manual', $duration );

		return true;
	}

	/**
	 * Edit a cron event
	 *
	 * @param string $old_hook Old hook name.
	 * @param int    $old_timestamp Old timestamp.
	 * @param string $old_sig Old signature.
	 * @param string $new_hook New hook name.
	 * @param int    $new_timestamp New timestamp.
	 * @param array  $new_args New arguments.
	 * @param string $new_schedule New schedule name.
	 * @return bool
	 */
	public static function edit_event( $old_hook, $old_timestamp, $old_sig, $new_hook, $new_timestamp, $new_args, $new_schedule ) {
		// Delete old event
		self::delete_event( $old_hook, $old_timestamp, $old_sig );

		// Add new event
		return self::add_event( $new_hook, $new_timestamp, $new_args, $new_schedule );
	}

	/**
	 * Get callbacks attached to a hook
	 *
	 * @param string $hook Hook name.
	 * @return array
	 */
	public static function get_hook_callbacks( $hook ) {
		global $wp_filter;

		if ( ! isset( $wp_filter[ $hook ] ) ) {
			return array();
		}

		$callbacks = array();
		foreach ( $wp_filter[ $hook ]->callbacks as $priority => $functions ) {
			foreach ( $functions as $function ) {
				$callback_info = array(
					'priority' => $priority,
					'function' => self::format_callback( $function['function'] ),
					'accepted_args' => $function['accepted_args'],
				);
				$callbacks[] = $callback_info;
			}
		}

		return $callbacks;
	}

	/**
	 * Format callback for display
	 *
	 * @param mixed $callback Callback function.
	 * @return string
	 */
	private static function format_callback( $callback ) {
		if ( is_string( $callback ) ) {
			return $callback;
		} elseif ( is_array( $callback ) ) {
			if ( is_object( $callback[0] ) ) {
				return get_class( $callback[0] ) . '->' . $callback[1];
			} else {
				return $callback[0] . '::' . $callback[1];
			}
		} elseif ( is_object( $callback ) && is_callable( $callback ) ) {
			if ( $callback instanceof Closure ) {
				return 'Closure';
			} else {
				return get_class( $callback ) . '->__invoke';
			}
		}

		return 'Unknown';
	}

	/**
	 * Check if a hook is paused
	 *
	 * @param string $hook Hook name.
	 * @return bool
	 */
	public static function is_hook_paused( $hook ) {
		$paused_hooks = get_option( 'oli_cj_paused_hooks', array() );
		return in_array( $hook, $paused_hooks, true );
	}

	/**
	 * Pause a hook
	 *
	 * @param string $hook Hook name.
	 * @return bool
	 */
	public static function pause_hook( $hook ) {
		$paused_hooks = get_option( 'oli_cj_paused_hooks', array() );
		if ( ! in_array( $hook, $paused_hooks, true ) ) {
			$paused_hooks[] = $hook;
			return update_option( 'oli_cj_paused_hooks', $paused_hooks );
		}
		return true;
	}

	/**
	 * Resume a hook
	 *
	 * @param string $hook Hook name.
	 * @return bool
	 */
	public static function resume_hook( $hook ) {
		$paused_hooks = get_option( 'oli_cj_paused_hooks', array() );
		$key = array_search( $hook, $paused_hooks, true );
		if ( false !== $key ) {
			unset( $paused_hooks[ $key ] );
			return update_option( 'oli_cj_paused_hooks', array_values( $paused_hooks ) );
		}
		return true;
	}

	/**
	 * Block paused hooks from running
	 */
	public static function maybe_block_paused_hook( $hook ) {
		if ( self::is_hook_paused( $hook ) ) {
			// Remove all actions for this hook temporarily
			remove_all_actions( $hook );

			// Log that it was blocked
			Oli_CJ_Logger::log_event( $hook, array(), 'blocked', 0, 'Hook is paused' );
		}
	}

	/**
	 * Add a PHP cron event
	 *
	 * @param string $hook Hook name.
	 * @param int    $timestamp Timestamp.
	 * @param string $php_code PHP code to execute.
	 * @param array  $args Arguments.
	 * @param string $schedule Schedule name.
	 * @return bool
	 */
	public static function add_php_event( $hook, $timestamp, $php_code, $args = array(), $schedule = false ) {
		// Store PHP code in option
		$php_events = get_option( 'oli_cj_php_events', array() );
		$php_events[ $hook ] = $php_code;
		update_option( 'oli_cj_php_events', $php_events );

		// Schedule the event
		return self::add_event( $hook, $timestamp, $args, $schedule );
	}

	/**
	 * Get PHP code for a hook
	 *
	 * @param string $hook Hook name.
	 * @return string|false
	 */
	public static function get_php_code( $hook ) {
		$php_events = get_option( 'oli_cj_php_events', array() );
		return isset( $php_events[ $hook ] ) ? $php_events[ $hook ] : false;
	}

	/**
	 * Execute PHP code for an event
	 *
	 * @param string $hook Hook name.
	 */
	public static function execute_php_event( $hook ) {
		$php_code = self::get_php_code( $hook );
		if ( $php_code ) {
			$start_time = microtime( true );
			$error = '';

			try {
				// phpcs:ignore Squiz.PHP.Eval.Discouraged -- This is intentional for PHP cron events
				eval( $php_code );
			} catch ( Exception $e ) {
				$error = $e->getMessage();
			}

			$duration = microtime( true ) - $start_time;
			Oli_CJ_Logger::log_event( $hook, array(), 'php', $duration, $error );
		}
	}

	/**
	 * Add a URL cron event
	 *
	 * @param string $hook Hook name.
	 * @param int    $timestamp Timestamp.
	 * @param string $url URL to request.
	 * @param array  $args Arguments.
	 * @param string $schedule Schedule name.
	 * @return bool
	 */
	public static function add_url_event( $hook, $timestamp, $url, $args = array(), $schedule = false ) {
		// Store URL in option
		$url_events = get_option( 'oli_cj_url_events', array() );
		$url_events[ $hook ] = $url;
		update_option( 'oli_cj_url_events', $url_events );

		// Schedule the event
		return self::add_event( $hook, $timestamp, $args, $schedule );
	}

	/**
	 * Get URL for a hook
	 *
	 * @param string $hook Hook name.
	 * @return string|false
	 */
	public static function get_url( $hook ) {
		$url_events = get_option( 'oli_cj_url_events', array() );
		return isset( $url_events[ $hook ] ) ? $url_events[ $hook ] : false;
	}

	/**
	 * Execute URL event
	 *
	 * @param string $hook Hook name.
	 */
	public static function execute_url_event( $hook ) {
		$url = self::get_url( $hook );
		if ( $url ) {
			$start_time = microtime( true );
			$error = '';

			$response = wp_remote_get( $url, array( 'timeout' => 30 ) );

			if ( is_wp_error( $response ) ) {
				$error = $response->get_error_message();
			}

			$duration = microtime( true ) - $start_time;
			Oli_CJ_Logger::log_event( $hook, array(), 'url', $duration, $error );
		}
	}
}
