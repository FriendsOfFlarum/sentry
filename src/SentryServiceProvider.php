<?php

/*
 * This file is part of fof/sentry
 *
 * Copyright (c) 2020 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Sentry;

use ErrorException;
use Flarum\Foundation\AbstractServiceProvider;
use Flarum\Foundation\Application;
use Flarum\Foundation\ErrorHandling\Reporter;
use Flarum\Foundation\ErrorHandling\ViewFormatter;
use Flarum\Foundation\Paths;
use Flarum\Frontend\Assets;
use Flarum\Frontend\Compiler\Source\SourceCollector;
use FoF\Sentry\Formatters\SentryFormatter;
use FoF\Sentry\Reporters\SentryReporter;
use Illuminate\Support\Arr;
use Sentry\ClientBuilder;
use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Sentry\State\Scope;

class SentryServiceProvider extends AbstractServiceProvider
{
    public function boot()
    {
        error_reporting(-1);

        set_error_handler([$this, 'handleError']);

        ini_set('display_errors', 'Off');
    }

    public function register()
    {
        $this->app->singleton('sentry', function () {
            $dsn = $this->app->make('flarum.settings')->get('fof-sentry.dsn');

            /**
             * @var array $config
             * @var Paths $paths
             */
            $config = $this->app->make('flarum.config');
            $paths = $this->app->make('flarum.paths');

            if ($dsn == null) {
                return null;
            }

            $base_path = $paths->base;

            $clientBuilder = ClientBuilder::create([
                'dsn'            => $dsn,
                'prefixes'       => [$base_path],
                'project_root'   => $base_path,
                'release'        => Application::VERSION,
            ]);

            $hub = Hub::setCurrent(new Hub($clientBuilder->getClient()));

            $hub->configureScope(function (Scope $scope) use ($config) {
                $scope->setTag('offline', (int) Arr::get($config, 'offline', false));
                $scope->setTag('debug', (int) Arr::get($config, 'debug', true));
                $scope->setTag('flarum', Application::VERSION);

                if ($this->app->bound('sentry.stack')) {
                    $scope->setTag('stack', $this->app->make('sentry.stack'));
                }
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

    public function handleError($level, $message, $file = '', $line = 0)
    {
        // ignore STMT_PREPARE errors because Eloquent automatically tries reconnecting
        if (strpos($message, 'STMT_PREPARE packet') !== false) {
            return false;
        }

        if (error_reporting() & $level) {
            $error = new ErrorException($message, 0, $level, $file, $line);

            if (app('flarum')->inDebugMode()) {
                throw $error;
            } else {
                foreach ($this->app->tagged(Reporter::class) as $reporter) {
                    $reporter->report($error);
                }
            }
        }
    }
}
