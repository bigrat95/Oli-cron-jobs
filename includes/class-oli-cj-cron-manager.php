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
	 * In-request cache of plugin options.
	 *
	 * @var array
	 */
	private static $option_cache = array();

	/**
	 * Whether a plugin option is currently being read from the database.
	 *
	 * @var bool
	 */
	private static $reading_option = false;

	/**
	 * Whether a paused hook is currently being blocked.
	 *
	 * @var bool
	 */
	private static $blocking_paused_hook = false;

	/**
	 * Register URL handlers and paused-hook guards immediately.
	 *
	 * Must not wait for a later `init` hook: Plugin::init() already runs on
	 * `init` priority 10, so an `init` priority 1 callback would never fire.
	 */
	public static function init() {
		self::register_event_handlers();
		self::register_paused_hook_guards();

		if ( ! has_action( 'wp_loaded', array( __CLASS__, 'strip_paused_hook_callbacks' ) ) ) {
			add_action( 'wp_loaded', array( __CLASS__, 'strip_paused_hook_callbacks' ), PHP_INT_MAX );
		}
	}

	/**
	 * Get a plugin option with a static cache and a re-entry guard.
	 *
	 * The guard prevents get_option() from re-entering this class when an
	 * option read fires `pre_option_*` / `all` callbacks.
	 *
	 * @param string $key     Option name.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get_plugin_option( $key, $default = array() ) {
		if ( array_key_exists( $key, self::$option_cache ) ) {
			return self::$option_cache[ $key ];
		}

		if ( self::$reading_option ) {
			return $default;
		}

		self::$reading_option = true;
		$value                = get_option( $key, $default );
		self::$reading_option = false;

		if ( is_array( $default ) && ! is_array( $value ) ) {
			$value = $default;
		}

		self::$option_cache[ $key ] = $value;

		return $value;
	}

	/**
	 * Update a plugin option and keep the static cache in sync.
	 *
	 * @param string $key   Option name.
	 * @param mixed  $value Option value.
	 * @return bool
	 */
	public static function update_plugin_option( $key, $value ) {
		self::$option_cache[ $key ] = $value;
		return update_option( $key, $value );
	}

	/**
	 * Register handlers for URL events.
	 *
	 * Stored PHP events from earlier versions stay in the options table
	 * and in the events list, but they are never executed.
	 */
	public static function register_event_handlers() {
		$url_events = self::get_plugin_option( 'oli_cj_url_events', array() );
		if ( ! is_array( $url_events ) ) {
			return;
		}

		foreach ( array_keys( $url_events ) as $hook ) {
			self::register_url_event_handler( $hook );
		}
	}

	/**
	 * Attach the URL execution callback to a hook once.
	 *
	 * @param string $hook Hook name.
	 */
	public static function register_url_event_handler( $hook ) {
		if ( ! is_string( $hook ) || '' === $hook ) {
			return;
		}

		if ( ! has_action( $hook, array( __CLASS__, 'execute_url_event_wrapper' ) ) ) {
			add_action( $hook, array( __CLASS__, 'execute_url_event_wrapper' ) );
		}
	}

	/**
	 * Attach execution-time blockers for every paused hook.
	 */
	public static function register_paused_hook_guards() {
		foreach ( self::get_paused_hooks() as $hook ) {
			self::register_paused_hook_guard( $hook );
		}
	}

	/**
	 * Attach a low-priority callback that strips a paused hook's actions.
	 *
	 * @param string $hook Hook name.
	 */
	public static function register_paused_hook_guard( $hook ) {
		if ( ! is_string( $hook ) || '' === $hook ) {
			return;
		}

		if ( ! has_action( $hook, array( __CLASS__, 'block_paused_execution' ) ) ) {
			add_action( $hook, array( __CLASS__, 'block_paused_execution' ), PHP_INT_MIN );
		}
	}

	/**
	 * Remove the execution-time blocker from a resumed hook.
	 *
	 * @param string $hook Hook name.
	 */
	public static function unregister_paused_hook_guard( $hook ) {
		if ( ! is_string( $hook ) || '' === $hook ) {
			return;
		}

		remove_action( $hook, array( __CLASS__, 'block_paused_execution' ), PHP_INT_MIN );
	}

	/**
	 * Remove callbacks from paused hooks after other plugins have registered.
	 *
	 * `remove_all_actions()` during `do_action` cannot stop later priorities:
	 * WP_Hook already copied the callback list. Stripping on `wp_loaded` does.
	 */
	public static function strip_paused_hook_callbacks() {
		if ( is_admin() && ! wp_doing_cron() ) {
			return;
		}

		foreach ( self::get_paused_hooks() as $hook ) {
			if ( ! is_string( $hook ) || '' === $hook ) {
				continue;
			}

			remove_all_actions( $hook );
			add_action( $hook, array( __CLASS__, 'block_paused_execution' ), PHP_INT_MIN );
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
	 * PHP events are no longer created or executed.
	 *
	 * @return bool Always false.
	 */
	public static function can_execute_php_events() {
		return false;
	}

	/**
	 * Get all stored PHP events from earlier plugin versions.
	 *
	 * Data is kept for display and export. The stored code is never executed.
	 *
	 * @return array
	 */
	public static function get_php_events() {
		$php_events = self::get_plugin_option( 'oli_cj_php_events', array() );
		return is_array( $php_events ) ? $php_events : array();
	}

	/**
	 * Whether any legacy PHP events are still stored.
	 *
	 * @return bool
	 */
	public static function has_legacy_php_events() {
		return ! empty( self::get_php_events() );
	}

	/**
	 * Whether a hook is a stored PHP event from an earlier version.
	 *
	 * @param string $hook Hook name.
	 * @return bool
	 */
	public static function is_php_event( $hook ) {
		return false !== self::get_php_code( $hook );
	}

	/**
	 * Pause stored PHP events so they appear disabled. Does not delete data.
	 */
	public static function disable_legacy_php_events() {
		foreach ( array_keys( self::get_php_events() ) as $hook ) {
			if ( is_string( $hook ) && '' !== $hook ) {
				self::pause_hook( $hook );
			}
		}
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
		$managed = self::get_plugin_option( 'oli_cj_managed_hooks', array() );
		if ( ! in_array( $hook, $managed, true ) ) {
			$managed[] = $hook;
			self::update_plugin_option( 'oli_cj_managed_hooks', $managed );
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

		$managed = self::get_plugin_option( 'oli_cj_managed_hooks', array() );
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
		if ( self::is_php_event( $hook ) ) {
			Oli_CJ_Logger::log_event( $hook, $args, 'blocked', 0, 'PHP events are no longer supported' );
			return false;
		}

		if ( self::is_hook_paused( $hook ) ) {
			Oli_CJ_Logger::log_event( $hook, $args, 'blocked', 0, 'Hook is paused' );
			return false;
		}

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
	 * Get the list of paused hooks.
	 *
	 * @return array
	 */
	public static function get_paused_hooks() {
		$paused_hooks = self::get_plugin_option( 'oli_cj_paused_hooks', array() );
		return is_array( $paused_hooks ) ? array_values( $paused_hooks ) : array();
	}

	/**
	 * Check if a hook is paused
	 *
	 * @param string $hook Hook name.
	 * @return bool
	 */
	public static function is_hook_paused( $hook ) {
		return in_array( $hook, self::get_paused_hooks(), true );
	}

	/**
	 * Pause a hook
	 *
	 * @param string $hook Hook name.
	 * @return bool
	 */
	public static function pause_hook( $hook ) {
		$paused_hooks = self::get_paused_hooks();
		if ( ! in_array( $hook, $paused_hooks, true ) ) {
			$paused_hooks[] = $hook;
			$updated        = self::update_plugin_option( 'oli_cj_paused_hooks', $paused_hooks );
			self::register_paused_hook_guard( $hook );
			return $updated;
		}

		self::register_paused_hook_guard( $hook );
		return true;
	}

	/**
	 * Resume a hook
	 *
	 * @param string $hook Hook name.
	 * @return bool
	 */
	public static function resume_hook( $hook ) {
		$paused_hooks = self::get_paused_hooks();
		$key          = array_search( $hook, $paused_hooks, true );
		if ( false !== $key ) {
			unset( $paused_hooks[ $key ] );
			$updated = self::update_plugin_option( 'oli_cj_paused_hooks', array_values( $paused_hooks ) );
			self::unregister_paused_hook_guard( $hook );
			return $updated;
		}

		self::unregister_paused_hook_guard( $hook );
		return true;
	}

	/**
	 * Strip remaining callbacks when a paused hook fires.
	 *
	 * Registered on each paused hook at PHP_INT_MIN. Never hooked to `all`.
	 */
	public static function block_paused_execution() {
		if ( self::$blocking_paused_hook || self::$reading_option ) {
			return;
		}

		$hook = current_filter();
		if ( ! self::is_hook_paused( $hook ) ) {
			return;
		}

		self::$blocking_paused_hook = true;
		remove_all_actions( $hook );
		Oli_CJ_Logger::log_event( $hook, array(), 'blocked', 0, 'Hook is paused' );
		self::$blocking_paused_hook = false;
	}

	/**
	 * Get stored PHP code for a legacy hook.
	 *
	 * The code is never executed. It is kept so existing events stay listed.
	 *
	 * @param string $hook Hook name.
	 * @return string|false
	 */
	public static function get_php_code( $hook ) {
		$php_events = self::get_php_events();
		return isset( $php_events[ $hook ] ) ? $php_events[ $hook ] : false;
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
		$url_events          = self::get_plugin_option( 'oli_cj_url_events', array() );
		$url_events[ $hook ] = $url;
		self::update_plugin_option( 'oli_cj_url_events', $url_events );
		self::register_url_event_handler( $hook );

		return self::add_event( $hook, $timestamp, $args, $schedule );
	}

	/**
	 * Get URL for a hook
	 *
	 * @param string $hook Hook name.
	 * @return string|false
	 */
	public static function get_url( $hook ) {
		$url_events = self::get_plugin_option( 'oli_cj_url_events', array() );
		return isset( $url_events[ $hook ] ) ? $url_events[ $hook ] : false;
	}

	/**
	 * Execute URL event
	 *
	 * @param string $hook Hook name.
	 */
	public static function execute_url_event( $hook ) {
		if ( self::is_hook_paused( $hook ) ) {
			return;
		}

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
