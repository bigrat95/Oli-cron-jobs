=== Oli Cron Jobs ===
Contributors: bigrat95
Donate link: https://olivierbigras.com/
Tags: cron, wp-cron, scheduled tasks, cron manager, debugging
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A comprehensive WP-Cron management plugin with advanced features for viewing, managing, and monitoring scheduled tasks.

== Description ==

Oli Cron Jobs is a powerful and user-friendly plugin for managing WordPress cron events. It provides complete control over scheduled tasks with an intuitive interface and advanced features.

= Key Features =

* **View All Cron Events** - See all scheduled events with detailed information including hook names, arguments, next run time, recurrence, and attached callbacks
* **Manage Events** - Add, edit, delete, or run cron events immediately
* **Bulk Operations** - Delete multiple events at once
* **Pause/Resume Hooks** - Temporarily disable specific hooks without deleting them
* **Custom Schedules** - Create and manage custom cron intervals
* **URL Cron Events** - Schedule HTTP requests to external URLs
* **PHP Cron Events** - Execute custom PHP code (restricted to users with appropriate capabilities)
* **Comprehensive Logging** - Track event executions with duration and error logging
* **Site Health Integration** - Automatic checks for WP-Cron issues including disabled cron, spawn failures, and missed events
* **Admin Bar Notices** - Quick access to cron management with issue notifications
* **WP-CLI Support** - Manage cron events from the command line
* **Search and Filter** - Easily find specific events with search and filter options
* **Timezone Support** - All times displayed in your site's configured timezone

= Site Health Checks =

The plugin integrates with WordPress Site Health to monitor:

* WP-Cron enabled/disabled status
* Cron spawn functionality
* Missed or overdue events
* Overall cron system health

= WP-CLI Commands =

Manage cron events from the command line:

* `wp oli-cron list` - List all cron events
* `wp oli-cron run <hook>` - Run a specific cron event
* `wp oli-cron delete <hook> --all` - Delete all instances of a hook
* `wp oli-cron pause <hook>` - Pause a cron hook
* `wp oli-cron resume <hook>` - Resume a paused hook

= Security =

Security is a top priority:

* All operations require `manage_options` capability
* PHP cron events restricted to users with `edit_files` AND `unfiltered_html` capabilities
* PHP events disabled when `DISALLOW_FILE_EDIT` is defined
* Proper nonce verification on all actions
* All input sanitized and output escaped

= Compatibility =

* Works with DISABLE_WP_CRON enabled (server cron)
* Compatible with multisite installations
* Integrates seamlessly with WordPress core

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/oli-cron-jobs` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to Tools → Cron Jobs to start managing your cron events.

== Frequently Asked Questions ==

= What is WP-Cron? =

WP-Cron is WordPress's built-in task scheduler that allows plugins to schedule tasks to run at specific times or intervals.

= Can I delete WordPress core cron events? =

Yes, but be careful! Deleting core WordPress events may affect functionality like scheduled posts, update checks, and backups.

= What happens if I pause a hook? =

When a hook is paused, it will not execute even if the scheduled time arrives. The event remains in the schedule but is blocked from running.

= Is it safe to use PHP cron events? =

PHP cron events should only be used if you understand the security implications. They are restricted to users with full PHP execution capabilities and are disabled when file editing is disabled.

= Does this work with DISABLE_WP_CRON? =

Yes! The plugin works whether WP-Cron is enabled or disabled. It will display a notice if WP-Cron is disabled to remind you to set up server cron.

= How long are logs kept? =

By default, logs are kept for 30 days. You can configure this in the settings page under Tools → Cron Settings.

== Screenshots ==

1. Cron Events list showing all scheduled tasks
2. Custom schedules management
3. Cron execution logs
4. Site Health integration
5. Admin bar quick access

== Changelog ==

= 0.1.1 =
* Added: Edit event UI with support for all event types
* Added: Export/Import functionality for events and schedules (JSON format)
* Added: Advanced filtering by status, schedule, and hook type (core, plugin, custom, URL, PHP)
* Added: Plugin/theme source detection for callbacks using reflection
* Enhanced: Callbacks column now shows which plugin/theme registered each callback
* Improved: Code quality with PHPCS WordPress Coding Standards compliance
* Fixed: Inline comment formatting and code alignment
* Fixed: Input sanitization and validation improvements

= 0.1.0 =
* Initial release
* View all WP-Cron events with detailed information
* Add, edit, delete, and run cron events
* Bulk delete operations
* Pause/resume individual hooks
* Custom cron schedule management
* URL cron events
* PHP cron events with proper security restrictions
* Comprehensive event logging with retention settings
* Site Health integration
* Admin bar and dashboard notices
* WP-CLI command support
* Search and filter functionality
* Full internationalization support

== Upgrade Notice ==

= 0.1.0 =
Initial release of Oli Cron Jobs.

== Privacy Policy ==

Oli Cron Jobs does not collect, store, or transmit any personal data outside of your WordPress installation. All data is stored locally in your WordPress database.

== Support ==

For support, please visit the plugin support forum on WordPress.org.
