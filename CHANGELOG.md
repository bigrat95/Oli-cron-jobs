# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.4] - 2026-10-21

### Fixed
- Removed the `all` action used to block paused hooks. `get_option()` fired `pre_option_*` then `all` again, which recursed until every wp-admin screen returned HTTP 500
- Paused hooks are blocked at execution time: each paused hook gets a `PHP_INT_MIN` callback that removes remaining actions
- Plugin options are cached in a static array, with a re-entry guard around `get_option()`
- URL event handlers are registered immediately instead of on `init` priority 1 from inside `init` priority 10, so they never missed the hook and never ran

### Added
- PHPUnit / WP-CLI test that `wp cron event run` on a URL event performs an HTTP request and writes a log row
- `.distignore` and `.gitattributes` `export-ignore` rules so development files stay out of the WordPress.org ZIP

## [0.1.3] - 2026-10-21

### Removed
- PHP event creation, editing, import, and execution
- All `eval()` / `create_function()` usage

### Changed
- Only standard hook events and URL events can be created
- Stored PHP events from earlier versions stay listed and keep their option data
- Those PHP events are paused/disabled, never executed, and shown as read-only
- Admin notice explains that PHP events are no longer supported
- Plugin version and stable tag bumped to 0.1.3

## [0.1.2] - 2026-10-21

### Changed
- Extracted the two identical add/edit event-type `<script>` blocks into `assets/js/admin.js` and enqueue them with `wp_enqueue_script`
- Replaced inline `onclick` confirm dialogs and `style=""` color/visibility attributes with CSS classes
- Localized confirm strings via `wp_localize_script`
- Documented Site Health spawn checks and opt-in URL events in `readme.txt` under External services

### Added
- Versioned upgrade routine (`oli_cj_version`) that copies any leftover unprefixed options onto `oli_cj_*` keys, then deletes the old keys
- `uninstall.php` to remove options, the logs table, and the cleanup cron event

### Fixed
- Sanitized imported schedules, paused hooks, event hooks, and URLs
- Import now requires `manage_options` in addition to the existing nonce

## [0.1.1] - 2026-10-19

### Added
- Edit event UI with full support for all event types (standard, URL, PHP)
  - Change hook name, arguments, next run time, and schedule
  - Modify URL for URL events
  - Edit PHP code for PHP events (with same capability restrictions)
  - Full validation and security checks
- Export/Import functionality
  - Export custom/managed events, custom schedules, and paused hooks as JSON
  - Import previously exported data
  - Available from Settings page
  - Preserves all event types including URL and PHP events
- Advanced filtering system
  - Filter by status: scheduled, overdue, paused
  - Filter by schedule type: single events or specific schedules
  - Filter by hook type: core, plugin, custom, URL, PHP
  - Combined search and filter capabilities
- Plugin/theme source detection for callbacks
  - Shows which plugin or theme registered each callback
  - Uses reflection to determine source file
  - Displays plugin/theme name with version when available
  - Identifies WordPress core, plugins, themes, and MU-plugins

### Changed
- Enhanced callbacks column to show source information
- Improved admin interface with better filtering options
- Updated list table to include edit action link
- Refined filter dropdowns with better categorization

### Fixed
- Code quality improvements
  - Fixed inline comment formatting (added periods)
  - Improved code alignment and spacing
  - Enhanced PHPDoc comments
- Input sanitization and validation improvements
- Better error handling for edge cases

### Technical
- PHP lint (`php -l`) clean on all plugin PHP files
- PHPCS WordPress standard clean (0 errors, 0 warnings)
- GitHub repository URLs updated to `github.com/olivierbigras/Oli-cron-jobs`
- Added reflection-based source detection
- PHP cron events now execute under server cron even when no user is logged in
- GitHub Actions PHPCS job fails the pipeline when the standard is not clean

## [0.1.0] - 2026-10-19

### Added
- Initial release of Oli Cron Jobs plugin
- View all WP-Cron events with comprehensive details
  - Hook names, arguments, next run time, recurrence schedules
  - Attached callbacks and their priorities
  - Status indicators (scheduled, overdue, paused)
- Event management features
  - Add new cron events (single or recurring)
  - Edit existing events
  - Delete individual events
  - Bulk delete multiple events
  - Run events immediately (manual execution)
- Pause/Resume functionality for individual hooks
- Custom cron schedule management
  - Create custom intervals
  - Delete custom schedules
  - View all schedules (built-in and custom)
- URL cron events for scheduled HTTP requests
- PHP cron events with security restrictions
  - Restricted to users with `edit_files` AND `unfiltered_html` capabilities
  - Disabled when `DISALLOW_FILE_EDIT` constant is defined
  - Clear security warnings in the interface
- Comprehensive event logging system
  - Records all event executions
  - Tracks duration and errors
  - Configurable retention period (default 30 days)
  - Automatic cleanup of old logs
  - View logs by hook, event type, or all events
- Site Health integration
  - Check for disabled WP-Cron
  - Test cron spawn functionality
  - Detect missed/overdue events
  - Display plugin information in debug info
- Admin interface features
  - Admin bar menu with issue indicators
  - Dashboard notices for cron problems
  - Search and filter events
  - Sortable columns
  - Pagination for large event lists
- WP-CLI command support
  - `wp oli-cron list` - List all events
  - `wp oli-cron run` - Execute an event
  - `wp oli-cron delete` - Delete events
  - `wp oli-cron pause` - Pause a hook
  - `wp oli-cron resume` - Resume a paused hook
- Security features
  - All operations require `manage_options` capability
  - Nonce verification on all actions
  - Input sanitization and output escaping
  - Proper capability checks for PHP events
- Internationalization
  - Full i18n support
  - Text domain: oli-cron-jobs
  - POT file ready for translations
- Settings page
  - Configure log retention period
  - View system information
  - Monitor total events and logs
- Compatibility
  - Works with `DISABLE_WP_CRON` enabled
  - Displays appropriate notices and guidance
  - Compatible with server cron setups

### Technical Details
- Follows WordPress coding standards
- Plugin Check (PCP) compliant
- Prefixed all functions and classes with `oli_cj_` / `Oli_CJ_`
- No external API calls (except user-defined URL events)
- No tracking or data collection
- Proper database table creation with dbDelta
- Singleton pattern for main classes
- Object-oriented architecture
- Proper WordPress hooks and filters integration

[0.1.4]: https://github.com/olivierbigras/Oli-cron-jobs/releases/tag/0.1.4
[0.1.3]: https://github.com/olivierbigras/Oli-cron-jobs/releases/tag/0.1.3
[0.1.2]: https://github.com/olivierbigras/Oli-cron-jobs/releases/tag/0.1.2
[0.1.1]: https://github.com/olivierbigras/Oli-cron-jobs/releases/tag/0.1.1
[0.1.0]: https://github.com/olivierbigras/Oli-cron-jobs/releases/tag/0.1.0
