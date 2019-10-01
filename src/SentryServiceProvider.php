<?php

/*
 * This file is part of fof/sentry
 *
 * Copyright (c) 2018 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Sentry;

use Illuminate\Support\ServiceProvider;
use Sentry\ClientBuilder;
use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Sentry\State\Scope;

class SentryServiceProvider extends ServiceProvider
{
    public function register()
    {
        // ..
    }

    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'fof-sentry');

        $dsn = $this->app->make('flarum.settings')->get('fof-sentry.dsn');

        if ($dsn == null) {
            return;
        }
        $this->app->singleton('sentry', function () {
            $dsn = $this->app->make('flarum.settings')->get('fof-sentry.dsn');

            if ($dsn == null) {
                return;
            }

            $base_path = app('path.base');

            $clientBuilder = ClientBuilder::create([
                'dsn'            => $dsn,
                'environment'    => app()->environment(),
                'prefixes'       => [$base_path],
                'project_root'   => $base_path,
            ]);

            $hub = Hub::setCurrent(new Hub($clientBuilder->getClient()));

            $hub->configureScope(function (Scope $scope) {
                $scope->setTag('offline', (int) app()->isDownForMaintenance());
                $scope->setTag('debug', (int) app()->inDebugMode());
                $scope->setTag('flarum', app()->version());
                $scope->setTag('stack', app('sentry.stack'));
            });

            return $hub;
        });

        $this->app->alias('sentry', HubInterface::class);
    }
}
