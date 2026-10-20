# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[0.1.1]: https://github.com/olivierbigras/Oli-cron-jobs/releases/tag/0.1.1
[0.1.0]: https://github.com/olivierbigras/Oli-cron-jobs/releases/tag/0.1.0
