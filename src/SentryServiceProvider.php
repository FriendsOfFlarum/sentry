<?php

/*
 * This file is part of fof/sentry.
 *
 * Copyright (c) 2018 FriendsOfFlarum.
 *
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Sentry;

use Illuminate\Support\ServiceProvider;
use Raven_Client;

class SentryServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('sentry', function () {
            $dsn = $this->app->make('flarum.settings')->get('fof-sentry.dsn');

            if (!isset($dsn)) return null;

            $base_path = app()->basePath();

            return new Raven_Client(
                $dsn,
                [
                    'environment' => app()->environment(),
                    'app_path' => $base_path,
                    'prefixes' => array($base_path),
                    'tags' => [
                        'offline' => (bool) app()->isDownForMaintenance() ? 1 : 0,
                        'debug' => app()->inDebugMode(),
                    ]
                ]
            );
        });
    }
}
