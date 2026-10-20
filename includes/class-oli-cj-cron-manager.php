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
	 * Initialize hooks
	 */
	public static function init() {
		// Hook into all actions to execute URL and PHP events.
		add_action( 'init', array( __CLASS__, 'register_event_handlers' ), 1 );
	}

	/**
	 * Register handlers for URL and PHP events
	 */
	public static function register_event_handlers() {
		// Get all URL events.
		$url_events = get_option( 'oli_cj_url_events', array() );
		foreach ( $url_events as $hook => $url ) {
			add_action( $hook, array( __CLASS__, 'execute_url_event_wrapper' ) );
		}

		// PHP events still run via cron unless file editing is disabled.
		if ( ! defined( 'DISALLOW_FILE_EDIT' ) || ! DISALLOW_FILE_EDIT ) {
			$php_events = get_option( 'oli_cj_php_events', array() );
			foreach ( $php_events as $hook => $code ) {
				add_action( $hook, array( __CLASS__, 'execute_php_event_wrapper' ) );
			}
		}
	}

	/**
	 * Wrapper for executing URL events
	 */
	public static function execute_url_event_wrapper() {
		$hook = current_filter();
		self::execute_url_event( $hook );
	}

	/**
	 * Wrapper for executing PHP events
	 */
	public static function execute_php_event_wrapper() {
		$hook = current_filter();
		self::execute_php_event( $hook );
	}

	/**
	 * Check if current user can execute PHP events
	 *
	 * @return bool
	 */
	public static function can_execute_php_events() {
		// Disabled if file editing is disabled.
		if ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) {
			return false;
		}

		// Require both edit_files and unfiltered_html capabilities.
		return current_user_can( 'edit_files' ) && current_user_can( 'unfiltered_html' );
	}

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

		$event              = $crons[ $timestamp ][ $hook ][ $sig ];
		$event['hook']      = $hook;
		$event['timestamp'] = $timestamp;
		$event['sig']       = $sig;

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
			$result = wp_schedule_event( $timestamp, $schedule, $hook, $args );
		} else {
			$result = wp_schedule_single_event( $timestamp, $hook, $args );
		}

		if ( false !== $result ) {
			self::mark_managed( $hook );
		}

		return $result;
	}

	/**
	 * Remember a hook created through this plugin.
	 *
	 * @param string $hook Hook name.
	 */
	public static function mark_managed( $hook ) {
		$managed = get_option( 'oli_cj_managed_hooks', array() );
		if ( ! in_array( $hook, $managed, true ) ) {
			$managed[] = $hook;
			update_option( 'oli_cj_managed_hooks', $managed );
		}
	}

	/**
	 * Whether this plugin created or owns the hook.
	 *
	 * @param string $hook Hook name.
	 * @return bool
	 */
	public static function is_managed( $hook ) {
		if ( false !== self::get_url( $hook ) || false !== self::get_php_code( $hook ) ) {
			return true;
		}

		$managed = get_option( 'oli_cj_managed_hooks', array() );
		return in_array( $hook, $managed, true );
	}

	/**
	 * Classify a hook for filters: url, php, core, plugin, or custom.
	 *
	 * @param string $hook Hook name.
	 * @return string
	 */
	public static function get_event_kind( $hook ) {
		if ( false !== self::get_url( $hook ) ) {
			return 'url';
		}

		if ( false !== self::get_php_code( $hook ) ) {
			return 'php';
		}

		$callbacks  = self::get_hook_callbacks( $hook );
		$has_plugin = false;
		$has_core   = false;

		foreach ( $callbacks as $callback ) {
			if ( in_array( $callback['source_type'], array( 'plugin', 'mu-plugin' ), true ) ) {
				$has_plugin = true;
			}
			if ( 'core' === $callback['source_type'] ) {
				$has_core = true;
			}
		}

		if ( $has_plugin ) {
			return 'plugin';
		}

		if ( $has_core ) {
			return 'core';
		}

		if ( self::is_managed( $hook ) ) {
			return 'custom';
		}

		$core_hooks = array(
			'wp_version_check',
			'wp_update_plugins',
			'wp_update_themes',
			'wp_scheduled_delete',
			'delete_expired_transients',
			'wp_scheduled_auto_draft_delete',
			'wp_privacy_delete_old_export_files',
			'recovery_mode_clean_expired_keys',
			'wp_site_health_scheduled_check',
			'publish_future_post',
		);

		if ( in_array( $hook, $core_hooks, true ) ) {
			return 'core';
		}

		return 'custom';
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
					++$count;
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

		// Run the event.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Runs the selected cron hook.
		do_action_ref_array( $hook, $args );

		$duration = microtime( true ) - $start_time;

		// Log the execution.
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
		// Delete old event.
		self::delete_event( $old_hook, $old_timestamp, $old_sig );

		// Add new event.
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
				$source        = self::get_callback_source( $function['function'] );
				$callback_info = array(
					'priority'      => $priority,
					'function'      => self::format_callback( $function['function'] ),
					'accepted_args' => $function['accepted_args'],
					'source'        => $source['label'],
					'source_type'   => $source['type'],
				);
				$callbacks[]   = $callback_info;
			}
		}

		return $callbacks;
	}

	/**
	 * Get the source (plugin/theme) of a callback
	 *
	 * @param mixed $callback Callback function.
	 * @return array
	 */
	private static function get_callback_source( $callback ) {
		$reflection = null;

		try {
			if ( is_string( $callback ) && function_exists( $callback ) ) {
				$reflection = new ReflectionFunction( $callback );
			} elseif ( is_array( $callback ) && method_exists( $callback[0], $callback[1] ) ) {
				$reflection = new ReflectionMethod( $callback[0], $callback[1] );
			} elseif ( is_object( $callback ) && is_callable( $callback ) ) {
				if ( $callback instanceof Closure ) {
					$reflection = new ReflectionFunction( $callback );
				} else {
					$reflection = new ReflectionMethod( $callback, '__invoke' );
				}
			}

			if ( $reflection ) {
				$file = $reflection->getFileName();
				if ( $file ) {
					return self::determine_file_source( $file );
				}
			}
		} catch ( Exception $exception ) {
			unset( $exception );
		}

		return array(
			'type'  => 'unknown',
			'label' => __( 'Unknown', 'oli-cron-jobs' ),
		);
	}

	/**
	 * Determine the source of a file (plugin, theme, or core)
	 *
	 * @param string $file File path.
	 * @return array
	 */
	private static function determine_file_source( $file ) {
		if ( strpos( $file, ABSPATH . WPINC ) === 0 || strpos( $file, ABSPATH . 'wp-admin' ) === 0 ) {
			return array(
				'type'  => 'core',
				'label' => __( 'WordPress Core', 'oli-cron-jobs' ),
			);
		}

		if ( strpos( $file, WP_PLUGIN_DIR ) === 0 ) {
			$relative_path = str_replace( WP_PLUGIN_DIR . '/', '', $file );
			$plugin_slug   = explode( '/', $relative_path )[0];

			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$all_plugins = get_plugins();
			foreach ( $all_plugins as $plugin_file => $plugin_data ) {
				if ( strpos( $plugin_file, $plugin_slug . '/' ) === 0 || $plugin_file === $plugin_slug ) {
					return array(
						'type'  => 'plugin',
						'label' => sprintf(
							/* translators: %s: plugin name */
							__( 'Plugin: %s', 'oli-cron-jobs' ),
							$plugin_data['Name']
						),
					);
				}
			}

			return array(
				'type'  => 'plugin',
				'label' => sprintf(
					/* translators: %s: plugin slug */
					__( 'Plugin: %s', 'oli-cron-jobs' ),
					$plugin_slug
				),
			);
		}

		if ( strpos( $file, get_theme_root() ) === 0 ) {
			$relative_path = str_replace( get_theme_root() . '/', '', $file );
			$theme_slug    = explode( '/', $relative_path )[0];

			$theme = wp_get_theme( $theme_slug );
			$label = $theme->exists() ? $theme->get( 'Name' ) : $theme_slug;

			return array(
				'type'  => 'theme',
				'label' => sprintf(
					/* translators: %s: theme name */
					__( 'Theme: %s', 'oli-cron-jobs' ),
					$label
				),
			);
		}

		if ( defined( 'WPMU_PLUGIN_DIR' ) && strpos( $file, WPMU_PLUGIN_DIR ) === 0 ) {
			$relative_path  = str_replace( WPMU_PLUGIN_DIR . '/', '', $file );
			$mu_plugin_file = explode( '/', $relative_path )[0];
			return array(
				'type'  => 'mu-plugin',
				'label' => sprintf(
					/* translators: %s: mu-plugin file name */
					__( 'MU-Plugin: %s', 'oli-cron-jobs' ),
					$mu_plugin_file
				),
			);
		}

		return array(
			'type'  => 'unknown',
			'label' => __( 'Unknown', 'oli-cron-jobs' ),
		);
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
		$key          = array_search( $hook, $paused_hooks, true );
		if ( false !== $key ) {
			unset( $paused_hooks[ $key ] );
			return update_option( 'oli_cj_paused_hooks', array_values( $paused_hooks ) );
		}
		return true;
	}

	/**
	 * Block paused hooks from running
	 *
	 * @param string $hook Hook name.
	 */
	public static function maybe_block_paused_hook( $hook ) {
		if ( self::is_hook_paused( $hook ) ) {
			// Remove all actions for this hook temporarily.
			remove_all_actions( $hook );

			// Log that it was blocked.
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
		// Store PHP code in option.
		$php_events          = get_option( 'oli_cj_php_events', array() );
		$php_events[ $hook ] = $php_code;
		update_option( 'oli_cj_php_events', $php_events );

		// Schedule the event.
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
			$error      = '';

			try {
				// phpcs:ignore Squiz.PHP.Eval.Discouraged -- This is intentional for PHP cron events.
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
		// Store URL in option.
		$url_events          = get_option( 'oli_cj_url_events', array() );
		$url_events[ $hook ] = $url;
		update_option( 'oli_cj_url_events', $url_events );

		// Schedule the event.
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
			$error      = '';

			$response = wp_remote_get( $url, array( 'timeout' => 30 ) );

			if ( is_wp_error( $response ) ) {
				$error = $response->get_error_message();
			}

			$duration = microtime( true ) - $start_time;
			Oli_CJ_Logger::log_event( $hook, array(), 'url', $duration, $error );
		}
	}
}
