=== Oli Cron Jobs ===
Contributors: bigrat95
Donate link: https://olivierbigras.com/
Tags: cron, wp-cron, scheduled tasks, cron manager, debugging
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.4
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
* The plugin does not execute arbitrary PHP. There is no `eval()` and no `create_function()`
* Only standard hook events and opt-in URL events can be created
* Existing PHP events from earlier versions stay stored and listed, but they are disabled and never executed
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

= What happened to PHP cron events? =

PHP cron events were removed in version 0.1.3. The plugin no longer executes stored PHP and no longer offers a PHP event type when you add or edit an event. Existing PHP events stay listed and their stored data is kept, but they are disabled, never executed, and their code cannot be edited. Use a standard hook or a URL event instead.

= Does this work with DISABLE_WP_CRON? =

Yes! The plugin works whether WP-Cron is enabled or disabled. It will display a notice if WP-Cron is disabled to remind you to set up server cron.

= How long are logs kept? =

By default, logs are kept for 30 days. You can configure this in the settings page under Tools → Cron Settings.

== External services ==

This plugin does not phone home and does not load third-party fonts, maps, or analytics.

= Site Health cron spawn check =

When a site administrator opens WordPress Site Health, the plugin may send an HTTP POST request to this site's own `wp-cron.php` URL to verify that WP-Cron can spawn. The request stays on the same site. It does not send personal data to a third party.

This check runs only for administrators viewing Site Health.

WordPress privacy policy: https://wordpress.org/about/privacy/

= URL cron events (opt-in) =

If an administrator creates a URL event, the plugin sends an HTTP GET request to the URL they entered when that event runs. The destination, timing, and query string are chosen by the administrator. The plugin does not add WordPress user data unless the administrator includes it in the URL.

This feature is unused until a URL event is created.

Terms and privacy depend on the destination the administrator configures. Review that service's terms and privacy policy before saving a URL event.

The plugin does not call olivierbigras.com, paypal.com, Google APIs, Cloudflare Turnstile, Carto, Leaflet, Google Sheets, or Google Fonts/Typekit from plugin code.

== Screenshots ==

1. Cron Events list showing all scheduled tasks
2. Custom schedules management
3. Cron execution logs
4. Site Health integration
5. Admin bar quick access

== Changelog ==

= 0.1.4 =
* Fixed an infinite recursion on the `all` hook that returned HTTP 500 on every wp-admin screen
* Paused hooks are now blocked at execution time with a static option cache and a re-entry guard
* URL cron events now register their handlers immediately so `wp cron event run` actually requests the URL and writes a log row

= 0.1.3 =
* Removed PHP event creation and execution. The plugin no longer uses eval() or create_function()
* Existing PHP events stay listed and stored, but they are disabled, never executed, and not editable
* Added an admin notice explaining that PHP events are no longer supported
* Creation UI now offers only standard hook events and URL events
* Import skips PHP events so they cannot be recreated

= 0.1.2 =
* Moved the add/edit event-type toggle scripts into the enqueued admin.js file
* Replaced remaining inline onclick handlers and color styles with classes and CSS
* Documented Site Health and URL-event HTTP requests under External services
* Added a versioned upgrade routine and uninstall cleanup
* Hardened JSON import sanitization

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

= 0.1.4 =
Fixes the wp-admin HTTP 500 caused by paused-hook handling, and makes URL cron events run.

= 0.1.3 =
PHP events are no longer supported or executed. Existing PHP events stay listed and stored, but they are disabled. Use a hook or URL event instead.

= 0.1.2 =
WordPress.org readiness: no inline scripts or styles, External services section, and lossless option upgrade.

= 0.1.0 =
Initial release of Oli Cron Jobs.

== Privacy Policy ==

Oli Cron Jobs does not collect, store, or transmit any personal data outside of your WordPress installation. All data is stored locally in your WordPress database.

== Support ==

For support, please visit the plugin support forum on WordPress.org.
