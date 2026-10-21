# Oli Cron Jobs

[![Code Quality](https://github.com/olivierbigras/Oli-cron-jobs/actions/workflows/code-quality.yml/badge.svg)](https://github.com/olivierbigras/Oli-cron-jobs/actions/workflows/code-quality.yml)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)
[![WordPress](https://img.shields.io/badge/WordPress-6.5%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)

A comprehensive WP-Cron management plugin for WordPress with advanced features for viewing, managing, and monitoring scheduled tasks.

## Features

### 🎯 Core Functionality

- **Complete Event Management**: View, add, edit, delete, and run cron events
- **Bulk Operations**: Delete multiple events at once
- **Pause/Resume**: Temporarily disable hooks without deleting them
- **Custom Schedules**: Create and manage custom cron intervals
- **Advanced Event Types**:
  - Standard WordPress cron events (hooks)
  - URL-based cron events (HTTP requests)
  - Legacy PHP events stay listed and stored but are disabled and never executed

### 📊 Monitoring & Logging

- **Comprehensive Logging**: Track all event executions with duration and error logging
- **Configurable Retention**: Set how long to keep log entries (default: 30 days)
- **Site Health Integration**: Automatic checks for cron issues
- **Admin Bar Notices**: Quick access with real-time issue indicators
- **Dashboard Warnings**: Proactive alerts for cron problems

### 🔍 Site Health Checks

The plugin automatically monitors:
- WP-Cron enabled/disabled status
- Cron spawn functionality
- Missed or overdue events
- Overall system health

### 💻 WP-CLI Support

Manage cron events from the command line:

```bash
# List all events
wp oli-cron list

# Run an event immediately
wp oli-cron run my_custom_hook

# Delete all instances of a hook
wp oli-cron delete my_custom_hook --all

# Pause/Resume hooks
wp oli-cron pause my_custom_hook
wp oli-cron resume my_custom_hook
```

### 🔒 Security Features

- All operations require `manage_options` capability
- No arbitrary PHP execution (`eval()` and `create_function()` are not used)
- Only standard hook events and URL events can be created
- Legacy PHP events remain stored and listed, but they are never executed
- Proper nonce verification on all actions
- Input sanitization and output escaping

## Installation

### From GitHub

1. Download the latest release or clone this repository
2. Upload to `/wp-content/plugins/oli-cron-jobs/`
3. Activate through the WordPress 'Plugins' menu
4. Navigate to **Tools → Cron Jobs** to start managing events

### Manual Installation

```bash
cd wp-content/plugins/
git clone https://github.com/olivierbigras/Oli-cron-jobs.git
```

## Requirements

- **WordPress**: 6.5 or higher
- **PHP**: 7.4 or higher
- **Tested up to**: WordPress 7.1

## Usage

### Viewing Events

Navigate to **Tools → Cron Jobs** to see all scheduled events with:
- Hook names and arguments
- Next run time (in your site's timezone)
- Recurrence schedule
- Attached callbacks
- Status (scheduled, overdue, paused)

### Managing Events

- **Run Now**: Execute any event immediately
- **Edit**: Modify hook name, arguments, schedule, or timing
- **Delete**: Remove single events or all instances of a hook
- **Pause/Resume**: Temporarily disable hooks without deleting

### Custom Schedules

Create custom intervals at **Tools → Cron Schedules**:
1. Enter an internal name (lowercase, underscores only)
2. Provide a display name
3. Set the interval in seconds

### Logs

View execution history at **Tools → Cron Logs**:
- Timestamp of each execution
- Duration and status
- Error messages (if any)
- Filter by hook or event type

### Settings

Configure at **Tools → Cron Settings**:
- Log retention period
- View system information
- Check WP-Cron status

## Development

### Setup

```bash
# Install dependencies
composer install

# Run PHP linter
composer run lint

# Run PHPCS
composer run phpcs

# Auto-fix PHPCS issues
composer run phpcbf
```

### Coding Standards

This plugin follows WordPress Coding Standards. All code is:
- Prefixed with `oli_cj_` or `Oli_CJ_`
- Fully documented with PHPDoc
- Internationalized (i18n ready)
- Sanitized and escaped appropriately

### Testing

PHP syntax is checked against PHP 7.4 through 8.3. The GitHub Actions workflow automatically tests on all supported PHP versions.

```bash
# PHPUnit (requires WP_ROOT pointing at a WordPress install)
composer install
WP_ROOT=/path/to/wordpress composer test

# WP-CLI: URL event must request and log
wp cron event run oli_cj_cli_url_event   # used by bin/run-url-cron-test.sh
```

## Known Limitations vs WP Crontrol

This is an independent implementation with feature parity goals. Some differences:

1. **UI/UX**: Different interface design and workflow
2. **Event Types**: Similar functionality with different implementation
3. **Security Model**: Strict capability checks with clear warnings

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Follow WordPress coding standards
4. Add tests if applicable
5. Submit a pull request

## Support

- **Issues**: [GitHub Issues](https://github.com/olivierbigras/Oli-cron-jobs/issues)
- **Documentation**: See readme.txt for detailed WordPress.org style docs

## License

This plugin is licensed under GPL v2 or later.

```
Copyright (C) 2026 Olivier Bigras

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.
```

See [LICENSE](LICENSE) file for full details.

## Author

**Olivier Bigras**
- Website: [https://olivierbigras.com/](https://olivierbigras.com/)
- WordPress.org: [@bigrat95](https://profiles.wordpress.org/bigrat95/)

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for detailed version history.

---

Made with ❤️ for the WordPress community
