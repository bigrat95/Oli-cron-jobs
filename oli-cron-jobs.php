<?php
/**
 * Plugin Name: Oli Cron Jobs
 * Plugin URI: https://github.com/bigrat95/oli-cron-jobs
 * Description: A comprehensive WP-Cron management plugin with advanced features for viewing, managing, and monitoring scheduled tasks.
 * Version: 0.1.0
 * Author: Olivier Bigras
 * Author URI: https://olivierbigras.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: oli-cron-jobs
 * Domain Path: /languages
 * Requires at least: 6.5
 * Requires PHP: 7.4
 *
 * @package Oli_Cron_Jobs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OLI_CJ_VERSION', '0.1.0' );
define( 'OLI_CJ_PLUGIN_FILE', __FILE__ );
define( 'OLI_CJ_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OLI_CJ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin class
 */
class Oli_CJ_Plugin {

	/**
	 * Single instance
	 *
	 * @var Oli_CJ_Plugin
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return Oli_CJ_Plugin
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
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Load plugin dependencies
	 */
	private function load_dependencies() {
		require_once OLI_CJ_PLUGIN_DIR . 'includes/class-oli-cj-cron-manager.php';
		require_once OLI_CJ_PLUGIN_DIR . 'includes/class-oli-cj-schedule-manager.php';
		require_once OLI_CJ_PLUGIN_DIR . 'includes/class-oli-cj-logger.php';
		require_once OLI_CJ_PLUGIN_DIR . 'includes/class-oli-cj-site-health.php';
		require_once OLI_CJ_PLUGIN_DIR . 'includes/class-oli-cj-notices.php';

		if ( is_admin() ) {
			require_once OLI_CJ_PLUGIN_DIR . 'admin/class-oli-cj-admin.php';
			require_once OLI_CJ_PLUGIN_DIR . 'admin/class-oli-cj-list-table.php';
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once OLI_CJ_PLUGIN_DIR . 'includes/class-oli-cj-cli.php';
		}
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'init' ) );

		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}

	/**
	 * Load text domain for translations
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'oli-cron-jobs', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Initialize plugin components
	 */
	public function init() {
		Oli_CJ_Cron_Manager::init();
		Oli_CJ_Logger::get_instance();
		Oli_CJ_Site_Health::get_instance();
		Oli_CJ_Notices::get_instance();

		if ( is_admin() ) {
			Oli_CJ_Admin::get_instance();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'oli-cron', 'Oli_CJ_CLI' );
		}
	}

	/**
	 * Plugin activation
	 */
	public function activate() {
		// Create logger table
		Oli_CJ_Logger::create_table();

		// Set default options
		if ( false === get_option( 'oli_cj_log_retention_days' ) ) {
			update_option( 'oli_cj_log_retention_days', 30 );
		}

		// Flush rewrite rules if needed
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation
	 */
	public function deactivate() {
		// Clean up if needed
		flush_rewrite_rules();
	}
}

/**
 * Initialize the plugin
 */
function oli_cj_init() {
	return Oli_CJ_Plugin::get_instance();
}

oli_cj_init();
