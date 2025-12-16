# Sentry by FriendsOfFlarum

![License](https://img.shields.io/badge/license-MIT-blue.svg) [![Latest Stable Version](https://img.shields.io/packagist/v/fof/sentry.svg)](https://packagist.org/packages/fof/sentry) [![OpenCollective](https://img.shields.io/badge/opencollective-fof-blue.svg)](https://opencollective.com/fof/donate) [![Donate](https://img.shields.io/badge/donate-datitisev-important.svg)](https://datitisev.me/donate)

A [Flarum](http://flarum.org) extension. Flarum integration for [Sentry](https://sentry.io).

![screenshot](https://i.imgur.com/qDzH06a.png)

## Features

- **Error Tracking**: Automatically capture and report PHP and JavaScript errors to Sentry
- **Performance Monitoring**: Track backend and frontend performance with configurable sample rates
- **Database Query Monitoring**: Advanced database performance tracking with N+1 detection
- **User Context**: Automatically include user information (ID, username, email, groups) with error reports
- **User Feedback**: Allow users to submit feedback when errors occur
- **Session Replay**: Record user sessions to reproduce bugs (frontend only)
- **Profiling**: PHP profiling support via Excimer extension

## Sentry SDK Versions

- **Backend (PHP)**: Sentry PHP SDK v4.x
- **Frontend (JavaScript)**: Sentry Browser SDK v10.0.0

## Installation

Install with composer:

```sh
composer require fof/sentry:"*"
```

### Updating

```sh
composer update fof/sentry:"*"
```

### Optional: PHP Profiling Support

For backend profiling support, install the Excimer PHP extension (Linux only):

```sh
pecl install excimer
```

## Configuration

Configure the extension in the admin panel. You'll need to provide your Sentry DSN to get started.

### General Settings

#### Sentry DSN
**Setting**: `fof-sentry.dsn` (required)

Your primary Sentry DSN. This is used for both frontend and backend error reporting.

**Note**: If you use a Relay DSN as the primary one, do not enable "User Feedback" as it will not work with Relay DSNs.

#### Sentry DSN (Backend Only)
**Setting**: `fof-sentry.dsn_backend` (optional)

An optional separate DSN for backend errors only. This is useful if you want to:
- Use a different Sentry project for backend errors
- Route backend errors through a Relay for improved performance
- Separate backend and frontend error tracking

If not set, the primary DSN will be used for both frontend and backend.

#### Environment
**Setting**: `fof-sentry.environment` (optional)

Set the environment name for all Sentry events (e.g., "production", "staging", "development"). This helps you filter and organize errors in Sentry.

### User Context Settings

#### User Feedback
**Setting**: `fof-sentry.user_feedback`
**Default**: `false`

When enabled, users will see a feedback dialog when an error occurs, allowing them to provide additional context about what happened. The dialog pre-fills with their username, email (if enabled), and group membership.

**Important**: This feature requires a direct Sentry DSN (not a Relay DSN).

#### Send User Email Addresses with Sentry Reports
**Setting**: `fof-sentry.send_emails_with_sentry_reports`
**Default**: `false`

When enabled, user email addresses are included in error reports and user feedback dialogs. This can help with user support but consider your privacy policy before enabling.

User context always includes:
- User ID
- Username
- User groups (e.g., "Admin", "Moderator")

### Backend Performance Monitoring

#### Back-end Performance Monitoring Rate
**Setting**: `fof-sentry.monitor_performance`
**Default**: `0` (disabled)
**Range**: 0-100

Percentage of backend requests to trace for performance monitoring. Set to:
- `0` to disable performance monitoring
- `100` to trace all requests
- Lower values (e.g., `10`) to sample 10% of requests

Performance monitoring tracks:
- Request/response times
- Database queries (when enabled)
- Extension loading times
- Custom spans

#### Back-end Profiling Rate
**Setting**: `fof-sentry.profile_rate`
**Default**: `0` (disabled)
**Range**: 0-100

Percentage of traced transactions to profile. This is **relative to the performance monitoring rate**.

**Requirements**:
- Performance monitoring must be enabled (`monitor_performance > 0`)
- Excimer PHP extension must be installed (Linux only)

**Example**: If `monitor_performance = 10` and `profile_rate = 50`, then:
- 10% of requests are traced
- 50% of those traced requests are profiled
- Result: 5% of all requests are profiled

**⚠️ Warning**: Profiling adds overhead and affects response time. See [Sentry documentation](https://docs.sentry.io/platforms/php/profiling/#improve-response-time) for optimization tips.

### Frontend (JavaScript) Monitoring

#### Report JavaScript Errors
**Setting**: `fof-sentry.javascript`
**Default**: `true`

Enable JavaScript error reporting to Sentry. Disable this if you only want backend error tracking.

#### Capture JavaScript Console
**Setting**: `fof-sentry.javascript.console`
**Default**: `false`

Capture console messages (log, info, warn, error) as breadcrumbs in error reports. Useful for debugging but increases data volume.

#### Front-end Performance Monitoring Rate
**Setting**: `fof-sentry.javascript.trace_sample_rate`
**Default**: `0` (disabled)
**Range**: 0-100

Percentage of frontend page loads to trace for performance monitoring.

**⚠️ Note**: Enabling this increases bundle size by ~30 KB.

#### Front-end Session Replays Rate
**Setting**: `fof-sentry.javascript.replays_session_sample_rate`
**Default**: `0` (disabled)
**Range**: 0-100

Percentage of user sessions to record from the beginning. Session replays capture user interactions, allowing you to see exactly what the user did before encountering an error.

**⚠️ Note**: Enabling any replay feature increases bundle size by ~150 KB.

#### Front-end Error Replays Rate
**Setting**: `fof-sentry.javascript.replays_error_sample_rate`
**Default**: `0` (disabled)
**Range**: 0-100

Percentage of sessions to record only when an error occurs. Captures up to 1 minute before the error and continues until the session ends.

**⚠️ Note**: Enabling any replay feature increases bundle size by ~150 KB.

### Database Query Performance Monitoring

These settings control advanced database query tracking and performance monitoring. Database monitoring is only active when backend performance monitoring is enabled (`monitor_performance > 0`).

#### Slow Query Threshold
**Setting**: `fof-sentry.db.slow_query_threshold`
**Default**: `1000` (1 second)
**Range**: 100-10000 milliseconds

Queries taking longer than this threshold are flagged as slow queries and tagged with severity levels:
- **Medium**: 1× to 2× threshold
- **High**: 2× to 5× threshold
- **Critical**: 5× threshold or more

Slow queries are **always tracked** regardless of the query sample rate.

#### Enable N+1 Query Detection
**Setting**: `fof-sentry.db.n_plus_one_detection`
**Default**: `true`

Automatically detect potential N+1 query problems by tracking repeated query patterns. When the same query pattern executes multiple times (with different parameters), it's flagged as a potential N+1 issue.

#### N+1 Detection Threshold
**Setting**: `fof-sentry.db.n_plus_one_threshold`
**Default**: `10`
**Range**: 5-100

Number of times a query pattern must repeat to be flagged as a potential N+1 issue. Lower values are more sensitive but may produce false positives.

Common patterns:
- `5-10`: Aggressive detection (may catch smaller loops)
- `10-20`: Balanced (recommended)
- `20+`: Conservative (only flags severe N+1 issues)

#### Track Query Parameter Bindings
**Setting**: `fof-sentry.db.track_bindings`
**Default**: `false`

Include query parameter values in Sentry reports. This helps with debugging but may expose sensitive data.

**⚠️ Warning**: Use with caution. While passwords and hashes are automatically sanitized, other sensitive data in query parameters may be exposed. Review your privacy and security requirements before enabling.

Automatic sanitization:
- Strings longer than 100 characters are truncated
- Hexadecimal strings (32+ chars) are masked as `[HASH]`

#### Query Sampling Rate
**Setting**: `fof-sentry.db.query_sample_rate`
**Default**: `100` (track all queries)
**Range**: 0-100

Percentage of database queries to track in performance monitoring.

- `100`: Track all queries (full visibility, higher overhead)
- `50`: Track 50% of queries (balanced)
- `10`: Track 10% of queries (lower overhead)
- `0`: Only track slow queries (minimum overhead)

**Note**: Slow queries (above threshold) are **always tracked** regardless of this setting.

Set lower values on high-traffic sites to reduce performance impact and data volume.

### Database Monitoring Features

When database query monitoring is enabled, you'll get:

1. **Individual Query Tracking**
   - Query execution time
   - Query type (SELECT, INSERT, UPDATE, DELETE, DDL)
   - Tables involved
   - Query bindings (if enabled)
   - Slow query detection with severity levels
   - N+1 query detection

2. **Aggregate Statistics** (per request)
   - Total number of queries
   - Total query time
   - Average query time
   - Number of duplicate query patterns
   - Top duplicate query count

3. **Query Tags** (for filtering in Sentry)
   - `query_type`: select, insert, update, delete, ddl, other
   - `table`: Primary table name
   - `slow_query`: true (if slow)
   - `n_plus_one_candidate`: true (if potential N+1)
   - `severity`: critical, high, medium, warning

## Recommended Configurations

### Production Site (High Traffic)

For production sites with high traffic, use conservative settings to minimize overhead:

```
Performance Monitoring: 10-25%
Profiling: 0% (disabled)
Database Query Sample Rate: 10-25%
Slow Query Threshold: 1000ms
N+1 Detection: Enabled
Track Bindings: Disabled (for privacy)
JavaScript Errors: Enabled
JavaScript Performance: 10%
Session Replays: 1-5% (error replays only)
```

### Staging/Development

For staging or development environments, use aggressive settings for full visibility:

```
Performance Monitoring: 100%
Profiling: 50-100% (if Excimer is installed)
Database Query Sample Rate: 100%
Slow Query Threshold: 500ms
N+1 Detection: Enabled (threshold: 5)
Track Bindings: Enabled
JavaScript Errors: Enabled
JavaScript Performance: 100%
Session Replays: 100% (both session and error)
```

### Troubleshooting Performance Issues

When investigating performance problems, temporarily increase monitoring:

```
Performance Monitoring: 100%
Database Query Sample Rate: 100%
Slow Query Threshold: 500ms (or lower)
N+1 Detection: Enabled (threshold: 5)
Track Bindings: Enabled
```

Remember to reduce these settings after troubleshooting to minimize overhead and costs.

## Privacy Considerations

This extension can collect user data. Review these privacy implications:

1. **User Email Addresses**: Only sent when `send_emails_with_sentry_reports` is enabled
2. **User Groups**: Always included in user context (can reveal user roles)
3. **Query Bindings**: May contain user-generated content when `track_bindings` is enabled
4. **Session Replays**: Capture all user interactions, including form inputs (with automatic PII masking)

Ensure your privacy policy covers data sent to Sentry.

## Security Notes

### Automatic Sanitization

The extension automatically sanitizes sensitive data:

1. **Query Bindings**:
   - Passwords and hashes (32+ hex chars) are masked as `[HASH]`
   - Long strings (100+ chars) are truncated

2. **User Feedback Dialog**:
   - All data is properly JSON-encoded to prevent XSS attacks
   - User input is sanitized through Sentry's SDK

### Best Practices

- **Don't track bindings in production** unless necessary
- **Use separate DSNs** for frontend and backend to isolate data
- **Set appropriate sample rates** to limit data collection
- **Review Sentry's privacy settings** to auto-scrub PII

## Customizing Sentry for Developers

This extension provides an extender that allows other extensions to customize Sentry configuration. You can use this to set custom release versions, add tags, and more. All settings are applied to both the PHP backend and JavaScript frontend.

### Basic Usage

In your extension's `extend.php` file:

```php
use FoF\Sentry\Extend\Sentry;

return [
    // Other extenders

    (new Sentry())
        ->setRelease('my-app-v1.2.3')
        ->setEnvironment('production')
        ->addTag('app_name', 'My Awesome App'),
];
```

### Available Methods

#### `setRelease(string $release)`

Set a custom release version for Sentry events (applied to both backend and frontend):

```php
(new Sentry())->setRelease('v2.0.0-beta.1');
```

#### `setEnvironment(string $environment)`

Set a custom environment name (applied to both backend and frontend):

```php
(new Sentry())->setEnvironment('staging');
```

#### `addTag(string $key, string $value)`

Add a custom tag to all Sentry events (applied to both backend and frontend):

```php
(new Sentry())
    ->addTag('server_type', 'dedicated')
    ->addTag('php_version', PHP_VERSION);
```

### Example: Setting Environment Variables

You can use environment variables to configure Sentry:

```php
(new Sentry())
    ->setRelease(env('APP_VERSION', 'development'))
    ->setEnvironment(env('APP_ENV', 'production'))
    ->addTag('server_id', env('SERVER_ID', 'unknown'));
```

## Changelog

### Recent Changes

#### Database Query Monitoring
- Added configurable slow query detection with severity levels
- Implemented N+1 query detection with pattern matching
- Added query sampling to reduce overhead on high-traffic sites
- Optional query parameter binding tracking with automatic sanitization
- Aggregate query statistics per request

#### User Context Enhancements
- Added user group membership to all Sentry reports
- Consistent user context across error reports, performance traces, and feedback dialogs
- Improved privacy controls for email addresses

#### Security Improvements
- Fixed XSS vulnerability in user feedback dialog
- Updated frontend Sentry SDK from v8.38.0 to v10.0.0
- Enhanced data sanitization for query bindings

### Links

[![OpenCollective](https://img.shields.io/badge/donate-friendsofflarum-44AEE5?style=for-the-badge&logo=open-collective)](https://opencollective.com/fof/donate) [![GitHub](https://img.shields.io/badge/donate-datitisev-ea4aaa?style=for-the-badge&logo=github)](https://datitisev.me/donate/github)

- [Packagist](https://packagist.org/packages/fof/sentry)
- [GitHub](https://github.com/FriendsOfFlarum/sentry)

An extension by [FriendsOfFlarum](https://github.com/FriendsOfFlarum), commissioned by [webdeveloper.com](https://webdeveloper.com).
