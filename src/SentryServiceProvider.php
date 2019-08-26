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

use Flarum\Frontend\Assets;
use Flarum\Frontend\Compiler\Source\SourceCollector;
use Flarum\Foundation\ErrorHandling\Reporter;
use Flarum\Foundation\ErrorHandling\ViewFormatter;
use FoF\Sentry\Formatters\SentryFormatter;
use FoF\Sentry\Reporters\SentryReporter;
use Illuminate\Support\ServiceProvider;
use Sentry\ClientBuilder;
use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Sentry\State\Scope;

class SentryServiceProvider extends ServiceProvider
{
    public function register()
    {
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

        $this->app->extend(ViewFormatter::class, function (ViewFormatter $formatter) {
            return new SentryFormatter($formatter);
        });

        $this->app->tag(SentryReporter::class, Reporter::class);

        // js assets
        $this->app->resolving('flarum.assets.forum', function (Assets $assets) {
            if ((bool) (int) $this->app->make('flarum.settings')->get('fof-sentry.javascript')) {
                $assets->js(function (SourceCollector $sources) {
                    $sources->addString(function () {
                        return 'var module={}';
                    });
                    $sources->addFile(__DIR__.'/../js/dist/forum.js');
                    $sources->addString(function () {
                        return "flarum.extensions['fof-sentry']=module.exports";
                    });
                });
            }
        });
    }
}
