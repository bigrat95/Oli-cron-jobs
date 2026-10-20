<?php
/**
 * Main plugin class
 *
 * @package Oli_Cron_Jobs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bootstraps Oli Cron Jobs.
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
	}

	/**
	 * Load text domain for translations
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'oli-cron-jobs', false, dirname( plugin_basename( OLI_CJ_PLUGIN_FILE ) ) . '/languages' );
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
	public static function activate() {
		require_once OLI_CJ_PLUGIN_DIR . 'includes/class-oli-cj-logger.php';

		Oli_CJ_Logger::create_table();

		if ( false === get_option( 'oli_cj_log_retention_days' ) ) {
			update_option( 'oli_cj_log_retention_days', 30 );
		}

		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
