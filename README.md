# Sentry by FriendsOfFlarum

![License](https://img.shields.io/badge/license-MIT-blue.svg) [![Latest Stable Version](https://img.shields.io/packagist/v/fof/sentry.svg)](https://packagist.org/packages/fof/sentry) [![OpenCollective](https://img.shields.io/badge/opencollective-fof-blue.svg)](https://opencollective.com/fof/donate) [![Donate](https://img.shields.io/badge/donate-datitisev-important.svg)](https://datitisev.me/donate)

A [Flarum](http://flarum.org) extension. Flarum integration for [Sentry](https://sentry.io).

![screenshot](https://i.imgur.com/qDzH06a.png)

### Installation

Install with composer:

```sh
composer require fof/sentry:"*"
```

### Updating

```sh
composer update fof/sentry:"*"
```

### Configuration

Configure the extension in the admin panel. You'll need to provide your Sentry DSN to get started.

### Customizing Sentry for Developers

This extension provides an extender that allows other extensions to customize Sentry configuration. You can use this to set custom release versions, add tags, and more. All settings are applied to both the PHP backend and JavaScript frontend.

#### Basic Usage

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

#### Available Methods

##### `setRelease(string $release)`

Set a custom release version for Sentry events (applied to both backend and frontend):

```php
(new Sentry())->setRelease('v2.0.0-beta.1');
```

##### `setEnvironment(string $environment)`

Set a custom environment name (applied to both backend and frontend):

```php
(new Sentry())->setEnvironment('staging');
```

##### `addTag(string $key, string $value)`

Add a custom tag to all Sentry events (applied to both backend and frontend):

```php
(new Sentry())
    ->addTag('server_type', 'dedicated')
    ->addTag('php_version', PHP_VERSION);
```

#### Example: Setting Environment Variables

You can use environment variables to configure Sentry:

```php
(new Sentry())
    ->setRelease(env('APP_VERSION', 'development'))
    ->setEnvironment(env('APP_ENV', 'production'))
    ->addTag('server_id', env('SERVER_ID', 'unknown'));
```

### Links

[![OpenCollective](https://img.shields.io/badge/donate-friendsofflarum-44AEE5?style=for-the-badge&logo=open-collective)](https://opencollective.com/fof/donate) [![GitHub](https://img.shields.io/badge/donate-datitisev-ea4aaa?style=for-the-badge&logo=github)](https://datitisev.me/donate/github)

- [Packagist](https://packagist.org/packages/fof/sentry)
- [GitHub](https://github.com/FriendsOfFlarum/sentry)

An extension by [FriendsOfFlarum](https://github.com/FriendsOfFlarum), commissioned by [webdeveloper.com](https://webdeveloper.com).
