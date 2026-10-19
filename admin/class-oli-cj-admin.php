<?php
/**
 * Admin Class
 *
 * @package Oli_Cron_Jobs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles admin interface
 */
class Oli_CJ_Admin {

	/**
	 * Single instance
	 *
	 * @var Oli_CJ_Admin
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return Oli_CJ_Admin
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
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'all', array( 'Oli_CJ_Cron_Manager', 'maybe_block_paused_hook' ), 1 );
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_management_page(
			__( 'Oli Cron Jobs', 'oli-cron-jobs' ),
			__( 'Cron Jobs', 'oli-cron-jobs' ),
			'manage_options',
			'oli-cron-jobs',
			array( $this, 'render_events_page' )
		);

		add_submenu_page(
			'tools.php',
			__( 'Add Cron Event', 'oli-cron-jobs' ),
			__( 'Add Cron Event', 'oli-cron-jobs' ),
			'manage_options',
			'oli-cron-jobs-add',
			array( $this, 'render_add_event_page' )
		);

		// Edit page (hidden from menu).
		add_submenu_page(
			null,
			__( 'Edit Cron Event', 'oli-cron-jobs' ),
			__( 'Edit Cron Event', 'oli-cron-jobs' ),
			'manage_options',
			'oli-cron-jobs-edit',
			array( $this, 'render_edit_event_page' )
		);

		add_submenu_page(
			'tools.php',
			__( 'Cron Schedules', 'oli-cron-jobs' ),
			__( 'Cron Schedules', 'oli-cron-jobs' ),
			'manage_options',
			'oli-cron-jobs-schedules',
			array( $this, 'render_schedules_page' )
		);

		add_submenu_page(
			'tools.php',
			__( 'Cron Logs', 'oli-cron-jobs' ),
			__( 'Cron Logs', 'oli-cron-jobs' ),
			'manage_options',
			'oli-cron-jobs-logs',
			array( $this, 'render_logs_page' )
		);

		add_submenu_page(
			'tools.php',
			__( 'Cron Settings', 'oli-cron-jobs' ),
			__( 'Cron Settings', 'oli-cron-jobs' ),
			'manage_options',
			'oli-cron-jobs-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin scripts and styles
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		if ( ! in_array( $hook, array( 'tools_page_oli-cron-jobs', 'tools_page_oli-cron-jobs-add', 'admin_page_oli-cron-jobs-edit', 'tools_page_oli-cron-jobs-schedules', 'tools_page_oli-cron-jobs-logs', 'tools_page_oli-cron-jobs-settings' ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'oli-cron-jobs-admin',
			OLI_CJ_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			OLI_CJ_VERSION
		);

		wp_enqueue_script(
			'oli-cron-jobs-admin',
			OLI_CJ_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			OLI_CJ_VERSION,
			true
		);
	}

	/**
	 * Handle admin actions
	 */
	public function handle_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';

		if ( empty( $action ) ) {
			return;
		}

		// Verify nonce.
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'oli_cj_action' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'oli-cron-jobs' ) );
		}

		switch ( $action ) {
			case 'delete_event':
				$this->handle_delete_event();
				break;
			case 'run_now':
				$this->handle_run_now();
				break;
			case 'pause_hook':
				$this->handle_pause_hook();
				break;
			case 'resume_hook':
				$this->handle_resume_hook();
				break;
			case 'delete_schedule':
				$this->handle_delete_schedule();
				break;
			case 'clear_logs':
				$this->handle_clear_logs();
				break;
			case 'export':
				$this->handle_export();
				break;
		}
	}

	/**
	 * Handle delete event action
	 */
	private function handle_delete_event() {
		$hook      = isset( $_GET['hook'] ) ? sanitize_text_field( wp_unslash( $_GET['hook'] ) ) : '';
		$timestamp = isset( $_GET['timestamp'] ) ? absint( $_GET['timestamp'] ) : 0;
		$sig       = isset( $_GET['sig'] ) ? sanitize_text_field( wp_unslash( $_GET['sig'] ) ) : '';

		if ( Oli_CJ_Cron_Manager::delete_event( $hook, $timestamp, $sig ) ) {
			wp_safe_redirect( add_query_arg( 'message', 'event_deleted', admin_url( 'tools.php?page=oli-cron-jobs' ) ) );
			exit;
		}
	}

	/**
	 * Handle run now action
	 */
	private function handle_run_now() {
		$hook      = isset( $_GET['hook'] ) ? sanitize_text_field( wp_unslash( $_GET['hook'] ) ) : '';
		$args_json = isset( $_GET['args'] ) ? sanitize_text_field( wp_unslash( $_GET['args'] ) ) : '[]';
		$args      = json_decode( $args_json, true );

		Oli_CJ_Cron_Manager::run_event_now( $hook, $args );

		wp_safe_redirect( add_query_arg( 'message', 'event_ran', admin_url( 'tools.php?page=oli-cron-jobs' ) ) );
		exit;
	}

	/**
	 * Handle pause hook action
	 */
	private function handle_pause_hook() {
		$hook = isset( $_GET['hook'] ) ? sanitize_text_field( wp_unslash( $_GET['hook'] ) ) : '';

		if ( Oli_CJ_Cron_Manager::pause_hook( $hook ) ) {
			wp_safe_redirect( add_query_arg( 'message', 'hook_paused', admin_url( 'tools.php?page=oli-cron-jobs' ) ) );
			exit;
		}
	}

	/**
	 * Handle resume hook action
	 */
	private function handle_resume_hook() {
		$hook = isset( $_GET['hook'] ) ? sanitize_text_field( wp_unslash( $_GET['hook'] ) ) : '';

		if ( Oli_CJ_Cron_Manager::resume_hook( $hook ) ) {
			wp_safe_redirect( add_query_arg( 'message', 'hook_resumed', admin_url( 'tools.php?page=oli-cron-jobs' ) ) );
			exit;
		}
	}

	/**
	 * Handle delete schedule action
	 */
	private function handle_delete_schedule() {
		$schedule = isset( $_GET['schedule'] ) ? sanitize_text_field( wp_unslash( $_GET['schedule'] ) ) : '';

		if ( Oli_CJ_Schedule_Manager::delete_schedule( $schedule ) ) {
			wp_safe_redirect( add_query_arg( 'message', 'schedule_deleted', admin_url( 'tools.php?page=oli-cron-jobs-schedules' ) ) );
			exit;
		}
	}

	/**
	 * Handle clear logs action
	 */
	private function handle_clear_logs() {
		if ( Oli_CJ_Logger::clear_all_logs() ) {
			wp_safe_redirect( add_query_arg( 'message', 'logs_cleared', admin_url( 'tools.php?page=oli-cron-jobs-logs' ) ) );
			exit;
		}
	}

	/**
	 * Handle export action
	 */
	private function handle_export() {
		$export_data = array(
			'version'          => OLI_CJ_VERSION,
			'export_date'      => current_time( 'mysql' ),
			'events'           => array(),
			'custom_schedules' => Oli_CJ_Schedule_Manager::get_custom_schedules(),
			'paused_hooks'     => get_option( 'oli_cj_paused_hooks', array() ),
		);

		// Get all events.
		$events = Oli_CJ_Cron_Manager::get_all_events();
		foreach ( $events as $event ) {
			$event_data = array(
				'hook'      => $event['hook'],
				'args'      => $event['args'],
				'timestamp' => $event['timestamp'],
				'schedule'  => $event['schedule'],
			);

			// Add URL or PHP code if applicable.
			$url      = Oli_CJ_Cron_Manager::get_url( $event['hook'] );
			$php_code = Oli_CJ_Cron_Manager::get_php_code( $event['hook'] );

			if ( $url ) {
				$event_data['type'] = 'url';
				$event_data['url']  = $url;
			} elseif ( $php_code ) {
				$event_data['type']     = 'php';
				$event_data['php_code'] = $php_code;
			} else {
				$event_data['type'] = 'standard';
			}

			$export_data['events'][] = $event_data;
		}

		// Output JSON file.
		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="oli-cron-jobs-export-' . gmdate( 'Y-m-d-H-i-s' ) . '.json"' );
		echo wp_json_encode( $export_data, JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * Handle import action
	 */
	private function handle_import() {
		if ( ! isset( $_FILES['import_file'] ) || UPLOAD_ERR_OK !== $_FILES['import_file']['error'] ) {
			wp_safe_redirect( add_query_arg( 'message', 'import_error', admin_url( 'tools.php?page=oli-cron-jobs-settings' ) ) );
			exit;
		}

		$file_contents = file_get_contents( $_FILES['import_file']['tmp_name'] );
		$import_data   = json_decode( $file_contents, true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $import_data ) ) {
			wp_safe_redirect( add_query_arg( 'message', 'import_error', admin_url( 'tools.php?page=oli-cron-jobs-settings' ) ) );
			exit;
		}

		$imported_count = 0;

		// Import custom schedules.
		if ( ! empty( $import_data['custom_schedules'] ) ) {
			foreach ( $import_data['custom_schedules'] as $name => $schedule ) {
				Oli_CJ_Schedule_Manager::add_schedule( $name, $schedule['interval'], $schedule['display'] );
			}
		}

		// Import paused hooks.
		if ( ! empty( $import_data['paused_hooks'] ) ) {
			update_option( 'oli_cj_paused_hooks', $import_data['paused_hooks'] );
		}

		// Import events.
		if ( ! empty( $import_data['events'] ) ) {
			foreach ( $import_data['events'] as $event ) {
				$hook      = $event['hook'];
				$args      = isset( $event['args'] ) ? $event['args'] : array();
				$timestamp = isset( $event['timestamp'] ) ? $event['timestamp'] : time();
				$schedule  = isset( $event['schedule'] ) ? $event['schedule'] : false;
				$type      = isset( $event['type'] ) ? $event['type'] : 'standard';

				if ( 'url' === $type && ! empty( $event['url'] ) ) {
					Oli_CJ_Cron_Manager::add_url_event( $hook, $timestamp, $event['url'], $args, $schedule );
					++$imported_count;
				} elseif ( 'php' === $type && ! empty( $event['php_code'] ) && Oli_CJ_Cron_Manager::can_execute_php_events() ) {
					Oli_CJ_Cron_Manager::add_php_event( $hook, $timestamp, $event['php_code'], $args, $schedule );
					++$imported_count;
				} else {
					Oli_CJ_Cron_Manager::add_event( $hook, $timestamp, $args, $schedule );
					++$imported_count;
				}
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'message' => 'imported',
					'count'   => $imported_count,
				),
				admin_url( 'tools.php?page=oli-cron-jobs-settings' )
			)
		);
		exit;
	}

	/**
	 * Render events page
	 */
	public function render_events_page() {
		$list_table = new Oli_CJ_List_Table();
		$list_table->prepare_items();

		$this->render_page_header( __( 'Cron Events', 'oli-cron-jobs' ) );
		?>
		<div class="wrap">
			<h1>
				<?php echo esc_html__( 'Cron Events', 'oli-cron-jobs' ); ?>
				<a href="<?php echo esc_url( admin_url( 'tools.php?page=oli-cron-jobs-add' ) ); ?>" class="page-title-action">
					<?php echo esc_html__( 'Add New', 'oli-cron-jobs' ); ?>
				</a>
			</h1>

			<?php $this->render_messages(); ?>

			<form method="get">
				<input type="hidden" name="page" value="oli-cron-jobs" />
				<?php $list_table->search_box( __( 'Search Events', 'oli-cron-jobs' ), 'oli-cj-events' ); ?>
				<?php $list_table->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render add event page
	 */
	public function render_add_event_page() {
		$this->render_page_header( __( 'Add Cron Event', 'oli-cron-jobs' ) );

		// Handle form submission.
		if ( isset( $_POST['oli_cj_add_event'] ) && check_admin_referer( 'oli_cj_add_event' ) ) {
			$event_type = isset( $_POST['event_type'] ) ? sanitize_text_field( wp_unslash( $_POST['event_type'] ) ) : 'standard';
			$hook       = isset( $_POST['hook'] ) ? sanitize_text_field( wp_unslash( $_POST['hook'] ) ) : '';
			$schedule   = isset( $_POST['schedule'] ) ? sanitize_text_field( wp_unslash( $_POST['schedule'] ) ) : '';
			$timestamp  = isset( $_POST['timestamp'] ) ? strtotime( wp_unslash( $_POST['timestamp'] ) ) : time();

			if ( ! $timestamp ) {
				$timestamp = time() + 3600;
			}

			$args = array();
			if ( ! empty( $_POST['args'] ) ) {
				$args_json = sanitize_textarea_field( wp_unslash( $_POST['args'] ) );
				$args      = json_decode( $args_json, true );
				if ( json_last_error() !== JSON_ERROR_NONE ) {
					$args = array();
				}
			}

			if ( $hook ) {
				if ( 'url' === $event_type && ! empty( $_POST['url'] ) ) {
					$url = esc_url_raw( wp_unslash( $_POST['url'] ) );
					Oli_CJ_Cron_Manager::add_url_event( $hook, $timestamp, $url, $args, $schedule ? $schedule : false );
					echo '<div class="notice notice-success"><p>' . esc_html__( 'URL cron event added successfully.', 'oli-cron-jobs' ) . '</p></div>';
				} elseif ( 'php' === $event_type && ! empty( $_POST['php_code'] ) ) {
					if ( Oli_CJ_Cron_Manager::can_execute_php_events() ) {
						$php_code = wp_unslash( $_POST['php_code'] );
						Oli_CJ_Cron_Manager::add_php_event( $hook, $timestamp, $php_code, $args, $schedule ? $schedule : false );
						echo '<div class="notice notice-success"><p>' . esc_html__( 'PHP cron event added successfully.', 'oli-cron-jobs' ) . '</p></div>';
					} else {
						echo '<div class="notice notice-error"><p>' . esc_html__( 'You do not have permission to create PHP cron events.', 'oli-cron-jobs' ) . '</p></div>';
					}
				} else {
					Oli_CJ_Cron_Manager::add_event( $hook, $timestamp, $args, $schedule ? $schedule : false );
					echo '<div class="notice notice-success"><p>' . esc_html__( 'Cron event added successfully.', 'oli-cron-jobs' ) . '</p></div>';
				}
			}
		}

		$schedules = Oli_CJ_Schedule_Manager::get_all_schedules();
		$can_php   = Oli_CJ_Cron_Manager::can_execute_php_events();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Add Cron Event', 'oli-cron-jobs' ); ?></h1>

			<form method="post" action="">
				<?php wp_nonce_field( 'oli_cj_add_event' ); ?>
				
				<table class="form-table">
					<tr>
						<th scope="row"><label for="event_type"><?php echo esc_html__( 'Event Type', 'oli-cron-jobs' ); ?></label></th>
						<td>
							<select name="event_type" id="event_type">
								<option value="standard"><?php echo esc_html__( 'Standard Event', 'oli-cron-jobs' ); ?></option>
								<option value="url"><?php echo esc_html__( 'URL Event', 'oli-cron-jobs' ); ?></option>
								<?php if ( $can_php ) : ?>
									<option value="php"><?php echo esc_html__( 'PHP Event', 'oli-cron-jobs' ); ?></option>
								<?php endif; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="hook"><?php echo esc_html__( 'Hook Name', 'oli-cron-jobs' ); ?></label></th>
						<td>
							<input type="text" name="hook" id="hook" class="regular-text" required />
							<p class="description"><?php echo esc_html__( 'The name of the action hook to execute.', 'oli-cron-jobs' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="timestamp"><?php echo esc_html__( 'Next Run', 'oli-cron-jobs' ); ?></label></th>
						<td>
							<input type="datetime-local" name="timestamp" id="timestamp" value="<?php echo esc_attr( gmdate( 'Y-m-d\TH:i', time() + 3600 ) ); ?>" required />
							<p class="description"><?php echo esc_html__( 'When the event should first run (in UTC).', 'oli-cron-jobs' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="schedule"><?php echo esc_html__( 'Recurrence', 'oli-cron-jobs' ); ?></label></th>
						<td>
							<select name="schedule" id="schedule">
								<option value=""><?php echo esc_html__( 'Non-repeating', 'oli-cron-jobs' ); ?></option>
								<?php foreach ( $schedules as $name => $schedule ) : ?>
									<option value="<?php echo esc_attr( $name ); ?>">
										<?php echo esc_html( $schedule['display'] . ' (' . Oli_CJ_Schedule_Manager::format_interval( $schedule['interval'] ) . ')' ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="args"><?php echo esc_html__( 'Arguments', 'oli-cron-jobs' ); ?></label></th>
						<td>
							<textarea name="args" id="args" class="large-text" rows="3" placeholder="[]"></textarea>
							<p class="description"><?php echo esc_html__( 'JSON array of arguments to pass to the hook. Example: ["arg1", "arg2"]', 'oli-cron-jobs' ); ?></p>
						</td>
					</tr>
					<tr id="url_field" style="display:none;">
						<th scope="row"><label for="url"><?php echo esc_html__( 'URL', 'oli-cron-jobs' ); ?></label></th>
						<td>
							<input type="url" name="url" id="url" class="regular-text" placeholder="https://example.com/api/endpoint" />
							<p class="description"><?php echo esc_html__( 'The URL to request when this cron event runs.', 'oli-cron-jobs' ); ?></p>
						</td>
					</tr>
					<?php if ( $can_php ) : ?>
						<tr id="php_field" style="display:none;">
							<th scope="row"><label for="php_code"><?php echo esc_html__( 'PHP Code', 'oli-cron-jobs' ); ?></label></th>
							<td>
								<div class="oli-cj-php-warning">
									<h3><?php echo esc_html__( '⚠️ Security Warning', 'oli-cron-jobs' ); ?></h3>
									<p><?php echo esc_html__( 'PHP cron events execute arbitrary code on your server. Only use this feature if you fully understand the security implications. Incorrect code can break your site.', 'oli-cron-jobs' ); ?></p>
								</div>
								<textarea name="php_code" id="php_code" class="large-text code" rows="10" placeholder="<?php echo esc_attr( "// Your PHP code here\necho 'Hello World';" ); ?>"></textarea>
								<p class="description"><?php echo esc_html__( 'PHP code to execute (without opening PHP tags).', 'oli-cron-jobs' ); ?></p>
							</td>
						</tr>
					<?php elseif ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) : ?>
						<tr>
							<td colspan="2">
								<div class="notice notice-info inline">
									<p><?php echo esc_html__( 'PHP cron events are disabled because file editing is disabled (DISALLOW_FILE_EDIT).', 'oli-cron-jobs' ); ?></p>
								</div>
							</td>
						</tr>
					<?php endif; ?>
				</table>
				
				<p class="submit">
					<input type="submit" name="oli_cj_add_event" class="button button-primary" value="<?php echo esc_attr__( 'Add Event', 'oli-cron-jobs' ); ?>" />
					<a href="<?php echo esc_url( admin_url( 'tools.php?page=oli-cron-jobs' ) ); ?>" class="button">
						<?php echo esc_html__( 'Cancel', 'oli-cron-jobs' ); ?>
					</a>
				</p>
			</form>
		</div>
		
		<script>
		jQuery(document).ready(function($) {
			$('#event_type').on('change', function() {
				var type = $(this).val();
				$('#url_field, #php_field').hide();
				if (type === 'url') {
					$('#url_field').show();
				} else if (type === 'php') {
					$('#php_field').show();
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * Render edit event page
	 */
	public function render_edit_event_page() {
		$this->render_page_header( __( 'Edit Cron Event', 'oli-cron-jobs' ) );

		// Get event details.
		$hook      = isset( $_GET['hook'] ) ? sanitize_text_field( wp_unslash( $_GET['hook'] ) ) : '';
		$timestamp = isset( $_GET['timestamp'] ) ? absint( $_GET['timestamp'] ) : 0;
		$sig       = isset( $_GET['sig'] ) ? sanitize_text_field( wp_unslash( $_GET['sig'] ) ) : '';

		if ( ! $hook || ! $timestamp || ! $sig ) {
			wp_die( esc_html__( 'Invalid event parameters.', 'oli-cron-jobs' ) );
		}

		$event = Oli_CJ_Cron_Manager::get_event( $hook, $timestamp, $sig );

		if ( ! $event ) {
			wp_die( esc_html__( 'Event not found.', 'oli-cron-jobs' ) );
		}

		// Determine event type.
		$url        = Oli_CJ_Cron_Manager::get_url( $hook );
		$php_code   = Oli_CJ_Cron_Manager::get_php_code( $hook );
		$event_type = 'standard';
		if ( $url ) {
			$event_type = 'url';
		} elseif ( $php_code ) {
			$event_type = 'php';
		}

		// Handle form submission.
		if ( isset( $_POST['oli_cj_edit_event'] ) && check_admin_referer( 'oli_cj_edit_event' ) ) {
			$new_event_type = isset( $_POST['event_type'] ) ? sanitize_text_field( wp_unslash( $_POST['event_type'] ) ) : 'standard';
			$new_hook       = isset( $_POST['hook'] ) ? sanitize_text_field( wp_unslash( $_POST['hook'] ) ) : '';
			$new_schedule   = isset( $_POST['schedule'] ) ? sanitize_text_field( wp_unslash( $_POST['schedule'] ) ) : '';
			$new_timestamp  = isset( $_POST['timestamp'] ) ? strtotime( wp_unslash( $_POST['timestamp'] ) ) : time();

			if ( ! $new_timestamp ) {
				$new_timestamp = time() + 3600;
			}

			$new_args = array();
			if ( ! empty( $_POST['args'] ) ) {
				$args_json = sanitize_textarea_field( wp_unslash( $_POST['args'] ) );
				$new_args  = json_decode( $args_json, true );
				if ( json_last_error() !== JSON_ERROR_NONE ) {
					$new_args = array();
				}
			}

			if ( $new_hook ) {
				// Delete old event.
				Oli_CJ_Cron_Manager::delete_event( $hook, $timestamp, $sig );

				// Add new event based on type.
				if ( 'url' === $new_event_type && ! empty( $_POST['url'] ) ) {
					$new_url = esc_url_raw( wp_unslash( $_POST['url'] ) );
					Oli_CJ_Cron_Manager::add_url_event( $new_hook, $new_timestamp, $new_url, $new_args, $new_schedule ? $new_schedule : false );
				} elseif ( 'php' === $new_event_type && ! empty( $_POST['php_code'] ) ) {
					if ( Oli_CJ_Cron_Manager::can_execute_php_events() ) {
						$new_php_code = wp_unslash( $_POST['php_code'] );
						Oli_CJ_Cron_Manager::add_php_event( $new_hook, $new_timestamp, $new_php_code, $new_args, $new_schedule ? $new_schedule : false );
					}
				} else {
					Oli_CJ_Cron_Manager::add_event( $new_hook, $new_timestamp, $new_args, $new_schedule ? $new_schedule : false );
				}

				wp_safe_redirect( add_query_arg( 'message', 'event_updated', admin_url( 'tools.php?page=oli-cron-jobs' ) ) );
				exit;
			}
		}

		$schedules        = Oli_CJ_Schedule_Manager::get_all_schedules();
		$can_php          = Oli_CJ_Cron_Manager::can_execute_php_events();
		$current_schedule = isset( $event['schedule'] ) ? $event['schedule'] : '';
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Edit Cron Event', 'oli-cron-jobs' ); ?></h1>

			<form method="post" action="">
				<?php wp_nonce_field( 'oli_cj_edit_event' ); ?>
				
				<table class="form-table">
					<tr>
						<th scope="row"><label for="event_type"><?php echo esc_html__( 'Event Type', 'oli-cron-jobs' ); ?></label></th>
						<td>
							<select name="event_type" id="event_type">
								<option value="standard" <?php selected( $event_type, 'standard' ); ?>><?php echo esc_html__( 'Standard Event', 'oli-cron-jobs' ); ?></option>
								<option value="url" <?php selected( $event_type, 'url' ); ?>><?php echo esc_html__( 'URL Event', 'oli-cron-jobs' ); ?></option>
								<?php if ( $can_php ) : ?>
									<option value="php" <?php selected( $event_type, 'php' ); ?>><?php echo esc_html__( 'PHP Event', 'oli-cron-jobs' ); ?></option>
								<?php endif; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="hook"><?php echo esc_html__( 'Hook Name', 'oli-cron-jobs' ); ?></label></th>
						<td>
							<input type="text" name="hook" id="hook" class="regular-text" value="<?php echo esc_attr( $event['hook'] ); ?>" required />
							<p class="description"><?php echo esc_html__( 'The name of the action hook to execute.', 'oli-cron-jobs' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="timestamp"><?php echo esc_html__( 'Next Run', 'oli-cron-jobs' ); ?></label></th>
						<td>
							<input type="datetime-local" name="timestamp" id="timestamp" value="<?php echo esc_attr( gmdate( 'Y-m-d\TH:i', $event['timestamp'] ) ); ?>" required />
							<p class="description"><?php echo esc_html__( 'When the event should first run (in UTC).', 'oli-cron-jobs' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="schedule"><?php echo esc_html__( 'Recurrence', 'oli-cron-jobs' ); ?></label></th>
						<td>
							<select name="schedule" id="schedule">
								<option value="" <?php selected( $current_schedule, '' ); ?>><?php echo esc_html__( 'Non-repeating', 'oli-cron-jobs' ); ?></option>
								<?php foreach ( $schedules as $name => $schedule ) : ?>
									<option value="<?php echo esc_attr( $name ); ?>" <?php selected( $current_schedule, $name ); ?>>
										<?php echo esc_html( $schedule['display'] . ' (' . Oli_CJ_Schedule_Manager::format_interval( $schedule['interval'] ) . ')' ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="args"><?php echo esc_html__( 'Arguments', 'oli-cron-jobs' ); ?></label></th>
						<td>
							<textarea name="args" id="args" class="large-text" rows="3"><?php echo esc_textarea( wp_json_encode( $event['args'] ) ); ?></textarea>
							<p class="description"><?php echo esc_html__( 'JSON array of arguments to pass to the hook. Example: ["arg1", "arg2"]', 'oli-cron-jobs' ); ?></p>
						</td>
					</tr>
					<tr id="url_field" style="<?php echo 'url' === $event_type ? '' : 'display:none;'; ?>">
						<th scope="row"><label for="url"><?php echo esc_html__( 'URL', 'oli-cron-jobs' ); ?></label></th>
						<td>
							<input type="url" name="url" id="url" class="regular-text" value="<?php echo esc_attr( $url ? $url : '' ); ?>" placeholder="https://example.com/api/endpoint" />
							<p class="description"><?php echo esc_html__( 'The URL to request when this cron event runs.', 'oli-cron-jobs' ); ?></p>
						</td>
					</tr>
					<?php if ( $can_php ) : ?>
						<tr id="php_field" style="<?php echo 'php' === $event_type ? '' : 'display:none;'; ?>">
							<th scope="row"><label for="php_code"><?php echo esc_html__( 'PHP Code', 'oli-cron-jobs' ); ?></label></th>
							<td>
								<div class="oli-cj-php-warning">
									<h3><?php echo esc_html__( '⚠️ Security Warning', 'oli-cron-jobs' ); ?></h3>
									<p><?php echo esc_html__( 'PHP cron events execute arbitrary code on your server. Only use this feature if you fully understand the security implications. Incorrect code can break your site.', 'oli-cron-jobs' ); ?></p>
								</div>
								<textarea name="php_code" id="php_code" class="large-text code" rows="10"><?php echo esc_textarea( $php_code ? $php_code : '' ); ?></textarea>
								<p class="description"><?php echo esc_html__( 'PHP code to execute (without opening PHP tags).', 'oli-cron-jobs' ); ?></p>
							</td>
						</tr>
					<?php endif; ?>
				</table>
				
				<p class="submit">
					<input type="submit" name="oli_cj_edit_event" class="button button-primary" value="<?php echo esc_attr__( 'Update Event', 'oli-cron-jobs' ); ?>" />
					<a href="<?php echo esc_url( admin_url( 'tools.php?page=oli-cron-jobs' ) ); ?>" class="button">
						<?php echo esc_html__( 'Cancel', 'oli-cron-jobs' ); ?>
					</a>
				</p>
			</form>
		</div>
		
		<script>
		jQuery(document).ready(function($) {
			$('#event_type').on('change', function() {
				var type = $(this).val();
				$('#url_field, #php_field').hide();
				if (type === 'url') {
					$('#url_field').show();
				} else if (type === 'php') {
					$('#php_field').show();
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * Render schedules page
	 */
	public function render_schedules_page() {
		$this->render_page_header( __( 'Cron Schedules', 'oli-cron-jobs' ) );

		// Handle form submission.
		if ( isset( $_POST['oli_cj_add_schedule'] ) && check_admin_referer( 'oli_cj_add_schedule' ) ) {
			$name     = isset( $_POST['schedule_name'] ) ? sanitize_key( $_POST['schedule_name'] ) : '';
			$interval = isset( $_POST['schedule_interval'] ) ? absint( $_POST['schedule_interval'] ) : 0;
			$display  = isset( $_POST['schedule_display'] ) ? sanitize_text_field( wp_unslash( $_POST['schedule_display'] ) ) : '';

			if ( $name && $interval && $display ) {
				Oli_CJ_Schedule_Manager::add_schedule( $name, $interval, $display );
				echo '<div class="notice notice-success"><p>' . esc_html__( 'Schedule added successfully.', 'oli-cron-jobs' ) . '</p></div>';
			}
		}

		$schedules = Oli_CJ_Schedule_Manager::get_all_schedules();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Cron Schedules', 'oli-cron-jobs' ); ?></h1>

			<?php $this->render_messages(); ?>

			<h2><?php echo esc_html__( 'Existing Schedules', 'oli-cron-jobs' ); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Name', 'oli-cron-jobs' ); ?></th>
						<th><?php echo esc_html__( 'Display Name', 'oli-cron-jobs' ); ?></th>
						<th><?php echo esc_html__( 'Interval', 'oli-cron-jobs' ); ?></th>
						<th><?php echo esc_html__( 'Actions', 'oli-cron-jobs' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $schedules as $name => $schedule ) : ?>
						<tr>
							<td><code><?php echo esc_html( $name ); ?></code></td>
							<td><?php echo esc_html( $schedule['display'] ); ?></td>
							<td><?php echo esc_html( Oli_CJ_Schedule_Manager::format_interval( $schedule['interval'] ) ); ?></td>
							<td>
								<?php if ( Oli_CJ_Schedule_Manager::is_custom_schedule( $name ) ) : ?>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'tools.php?page=oli-cron-jobs-schedules&action=delete_schedule&schedule=' . $name ), 'oli_cj_action' ) ); ?>" class="delete" onclick="return confirm('<?php echo esc_js( __( 'Are you sure?', 'oli-cron-jobs' ) ); ?>');">
										<?php echo esc_html__( 'Delete', 'oli-cron-jobs' ); ?>
									</a>
								<?php else : ?>
									<?php echo esc_html__( 'Built-in', 'oli-cron-jobs' ); ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php echo esc_html__( 'Add New Schedule', 'oli-cron-jobs' ); ?></h2>
			<form method="post" action="">
				<?php wp_nonce_field( 'oli_cj_add_schedule' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="schedule_name"><?php echo esc_html__( 'Internal Name', 'oli-cron-jobs' ); ?></label></th>
						<td>
							<input type="text" name="schedule_name" id="schedule_name" class="regular-text" required />
							<p class="description"><?php echo esc_html__( 'Lowercase letters, numbers, and underscores only.', 'oli-cron-jobs' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="schedule_display"><?php echo esc_html__( 'Display Name', 'oli-cron-jobs' ); ?></label></th>
						<td>
							<input type="text" name="schedule_display" id="schedule_display" class="regular-text" required />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="schedule_interval"><?php echo esc_html__( 'Interval (seconds)', 'oli-cron-jobs' ); ?></label></th>
						<td>
							<input type="number" name="schedule_interval" id="schedule_interval" class="regular-text" required min="1" />
							<p class="description"><?php echo esc_html__( 'Number of seconds between executions.', 'oli-cron-jobs' ); ?></p>
						</td>
					</tr>
				</table>
				<p class="submit">
					<input type="submit" name="oli_cj_add_schedule" class="button button-primary" value="<?php echo esc_attr__( 'Add Schedule', 'oli-cron-jobs' ); ?>" />
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render logs page
	 */
	public function render_logs_page() {
		$this->render_page_header( __( 'Cron Logs', 'oli-cron-jobs' ) );

		$logs       = Oli_CJ_Logger::get_logs( array( 'limit' => 100 ) );
		$total_logs = Oli_CJ_Logger::get_log_count();
		?>
		<div class="wrap">
			<h1>
				<?php echo esc_html__( 'Cron Logs', 'oli-cron-jobs' ); ?>
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'tools.php?page=oli-cron-jobs-logs&action=clear_logs' ), 'oli_cj_action' ) ); ?>" class="page-title-action" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to clear all logs?', 'oli-cron-jobs' ) ); ?>');">
					<?php echo esc_html__( 'Clear All Logs', 'oli-cron-jobs' ); ?>
				</a>
			</h1>

			<?php $this->render_messages(); ?>

			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of log entries */
						_n( 'Showing %d log entry', 'Showing %1$d of %2$d log entries', count( $logs ), 'oli-cron-jobs' ),
						count( $logs ),
						$total_logs
					)
				);
				?>
			</p>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Date/Time', 'oli-cron-jobs' ); ?></th>
						<th><?php echo esc_html__( 'Hook', 'oli-cron-jobs' ); ?></th>
						<th><?php echo esc_html__( 'Type', 'oli-cron-jobs' ); ?></th>
						<th><?php echo esc_html__( 'Duration', 'oli-cron-jobs' ); ?></th>
						<th><?php echo esc_html__( 'Status', 'oli-cron-jobs' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $logs ) ) : ?>
						<tr>
							<td colspan="5"><?php echo esc_html__( 'No logs found.', 'oli-cron-jobs' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $logs as $log ) : ?>
							<tr>
								<td><?php echo esc_html( $log->created_at ); ?></td>
								<td><code><?php echo esc_html( $log->hook ); ?></code></td>
								<td><?php echo esc_html( ucfirst( $log->event_type ) ); ?></td>
								<td><?php echo esc_html( number_format( $log->duration, 4 ) ); ?>s</td>
								<td>
									<?php if ( ! empty( $log->error_message ) ) : ?>
										<span style="color: red;">❌ <?php echo esc_html( $log->error_message ); ?></span>
									<?php else : ?>
										<span style="color: green;">✓ <?php echo esc_html__( 'Success', 'oli-cron-jobs' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		$this->render_page_header( __( 'Cron Settings', 'oli-cron-jobs' ) );

		// Handle import submission.
		if ( isset( $_POST['oli_cj_import'] ) && check_admin_referer( 'oli_cj_import' ) ) {
			$this->handle_import();
		}

		// Handle form submission.
		if ( isset( $_POST['oli_cj_save_settings'] ) && check_admin_referer( 'oli_cj_settings' ) ) {
			$retention_days = isset( $_POST['log_retention_days'] ) ? absint( $_POST['log_retention_days'] ) : 30;
			update_option( 'oli_cj_log_retention_days', $retention_days );
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved successfully.', 'oli-cron-jobs' ) . '</p></div>';
		}

		$retention_days = get_option( 'oli_cj_log_retention_days', 30 );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Cron Settings', 'oli-cron-jobs' ); ?></h1>

			<form method="post" action="">
				<?php wp_nonce_field( 'oli_cj_settings' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="log_retention_days"><?php echo esc_html__( 'Log Retention (days)', 'oli-cron-jobs' ); ?></label></th>
						<td>
							<input type="number" name="log_retention_days" id="log_retention_days" value="<?php echo esc_attr( $retention_days ); ?>" class="small-text" min="1" />
							<p class="description"><?php echo esc_html__( 'Number of days to keep log entries before automatic cleanup.', 'oli-cron-jobs' ); ?></p>
						</td>
					</tr>
				</table>
				<p class="submit">
					<input type="submit" name="oli_cj_save_settings" class="button button-primary" value="<?php echo esc_attr__( 'Save Settings', 'oli-cron-jobs' ); ?>" />
				</p>
			</form>

			<h2><?php echo esc_html__( 'System Information', 'oli-cron-jobs' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php echo esc_html__( 'WP-Cron Status', 'oli-cron-jobs' ); ?></th>
					<td>
						<?php if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) : ?>
							<span style="color: orange;">⚠ <?php echo esc_html__( 'Disabled', 'oli-cron-jobs' ); ?></span>
							<p class="description"><?php echo esc_html__( 'WP-Cron is disabled. Make sure you have a server cron job configured.', 'oli-cron-jobs' ); ?></p>
						<?php else : ?>
							<span style="color: green;">✓ <?php echo esc_html__( 'Enabled', 'oli-cron-jobs' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Total Events', 'oli-cron-jobs' ); ?></th>
					<td><?php echo esc_html( count( Oli_CJ_Cron_Manager::get_all_events() ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Total Logs', 'oli-cron-jobs' ); ?></th>
					<td><?php echo esc_html( Oli_CJ_Logger::get_log_count() ); ?></td>
				</tr>
			</table>

			<h2><?php echo esc_html__( 'Export / Import', 'oli-cron-jobs' ); ?></h2>
			
			<h3><?php echo esc_html__( 'Export Events and Schedules', 'oli-cron-jobs' ); ?></h3>
			<p><?php echo esc_html__( 'Export all cron events, custom schedules, and paused hooks as a JSON file.', 'oli-cron-jobs' ); ?></p>
			<p>
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'tools.php?page=oli-cron-jobs-settings&action=export' ), 'oli_cj_action' ) ); ?>" class="button button-secondary">
					<?php echo esc_html__( 'Export Data', 'oli-cron-jobs' ); ?>
				</a>
			</p>

			<h3><?php echo esc_html__( 'Import Events and Schedules', 'oli-cron-jobs' ); ?></h3>
			<p><?php echo esc_html__( 'Import events and schedules from a previously exported JSON file. This will not delete existing events.', 'oli-cron-jobs' ); ?></p>
			<form method="post" action="" enctype="multipart/form-data">
				<?php wp_nonce_field( 'oli_cj_import' ); ?>
				<p>
					<input type="file" name="import_file" accept=".json" required />
				</p>
				<p>
					<input type="submit" name="oli_cj_import" class="button button-secondary" value="<?php echo esc_attr__( 'Import Data', 'oli-cron-jobs' ); ?>" />
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render page header
	 *
	 * @param string $title Page title.
	 */
	private function render_page_header( $title ) {
		// This could be used for common page elements.
	}

	/**
	 * Render admin messages
	 */
	private function render_messages() {
		if ( ! isset( $_GET['message'] ) ) {
			return;
		}

		$message  = sanitize_text_field( wp_unslash( $_GET['message'] ) );
		$messages = array(
			'event_deleted'    => __( 'Event deleted successfully.', 'oli-cron-jobs' ),
			'event_updated'    => __( 'Event updated successfully.', 'oli-cron-jobs' ),
			'event_ran'        => __( 'Event executed successfully.', 'oli-cron-jobs' ),
			'hook_paused'      => __( 'Hook paused successfully.', 'oli-cron-jobs' ),
			'hook_resumed'     => __( 'Hook resumed successfully.', 'oli-cron-jobs' ),
			'schedule_deleted' => __( 'Schedule deleted successfully.', 'oli-cron-jobs' ),
			'logs_cleared'     => __( 'Logs cleared successfully.', 'oli-cron-jobs' ),
			'exported'         => __( 'Data exported successfully.', 'oli-cron-jobs' ),
			'imported'         => __( 'Data imported successfully.', 'oli-cron-jobs' ),
		);

		if ( isset( $messages[ $message ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $messages[ $message ] ) . '</p></div>';
		}
	}
}
