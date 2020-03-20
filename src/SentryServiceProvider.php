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

use ErrorException;
use Flarum\Foundation\ErrorHandling\Reporter;
use Flarum\Foundation\ErrorHandling\ViewFormatter;
use Flarum\Frontend\Assets;
use Flarum\Frontend\Compiler\Source\SourceCollector;
use FoF\Sentry\Formatters\SentryFormatter;
use FoF\Sentry\Reporters\SentryReporter;
use Illuminate\Support\ServiceProvider;
use Sentry\ClientBuilder;
use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Sentry\State\Scope;

class SentryServiceProvider extends ServiceProvider
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

            if ($dsn == null) {
                return;
            }

            $base_path = $this->app->basePath();

            $clientBuilder = ClientBuilder::create([
                'dsn'            => $dsn,
                'environment'    => $this->app->environment(),
                'prefixes'       => [$base_path],
                'project_root'   => $base_path,
                'release'        => $this->app->version(),
            ]);

            $hub = Hub::setCurrent(new Hub($clientBuilder->getClient()));

            $hub->configureScope(function (Scope $scope) {
                $scope->setTag('offline', (int) $this->app->isDownForMaintenance());
                $scope->setTag('debug', (int) $this->app->inDebugMode());
                $scope->setTag('flarum', $this->app->version());

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

            if ($this->app->inDebugMode()) {
                throw $error;
            } else {
                foreach ($this->app->tagged(Reporter::class) as $reporter) {
                    $reporter->report($error);
                }
            }
        }
    }
}
