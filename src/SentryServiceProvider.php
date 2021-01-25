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

use Carbon\Carbon;
use ErrorException;
use Flarum\Foundation\AbstractServiceProvider;
use Flarum\Foundation\Application;
use Flarum\Foundation\ErrorHandling\Reporter;
use Flarum\Foundation\ErrorHandling\ViewFormatter;
use Flarum\Foundation\Paths;
use Flarum\Frontend\Assets;
use Flarum\Frontend\Compiler\Source\SourceCollector;
use Flarum\Settings\SettingsRepositoryInterface;
use FoF\Sentry\Contracts\Measure;
use FoF\Sentry\Formatters\SentryFormatter;
use FoF\Sentry\Reporters\SentryReporter;
use Illuminate\Support\Arr;
use Sentry\SentrySdk;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;

use function Sentry\init;

class SentryServiceProvider extends AbstractServiceProvider
{
    protected $measurements = [
        Performance\Eloquent::class,
        Performance\Extension::class,
        Performance\Frontend::class,
    ];

    protected static $transactionStack = [];

    public function boot()
    {
        error_reporting(-1);

        set_error_handler([$this, 'handleError']);

        ini_set('display_errors', 'Off');
    }

    public function register()
    {
        /** @var SettingsRepositoryInterface $settings */
        $settings = $this->app->make('flarum.settings');
        $dsn = $settings->get('fof-sentry.dsn');
        $performanceMonitoring = (int) $settings->get('fof-sentry.monitor_performance', 0);


        $this->app->singleton(HubInterface::class, function () use ($dsn, $performanceMonitoring) {
            $this->init($dsn, $performanceMonitoring);

            return SentrySdk::getCurrentHub();
        });

        if ($dsn && $performanceMonitoring > 0) {
            /** @var HubInterface $hub */
            $hub = $this->app->make(HubInterface::class);

            $transaction = $hub->startTransaction(new TransactionContext('flarum'));

            foreach ($this->measurements as $measurement) {
                /** @var Measure $measure */
                $measure = new $measurement($transaction, $this->app);
                if ($span = $measure->handle()) {
                    static::$transactionStack[] = $span;
                }
            }

            static::$transactionStack[] = $transaction;
        }

        $this->app->singleton('sentry', function () use ($dsn, $performanceMonitoring) {
                if (! $dsn) {
                    return null;
                }

                /** @var array $config */
                $config = $this->app->make('flarum.config');

                /** @var HubInterface $hub */
                $hub = $this->app->make(HubInterface::class);

                $hub->configureScope(function (Scope $scope) use ($config) {
                    $scope->setTag('offline', (int)Arr::get($config, 'offline', false));
                    $scope->setTag('debug', (int)Arr::get($config, 'debug', true));
                    $scope->setTag('flarum', Application::VERSION);

                    if ($this->app->bound('sentry.stack')) {
                        $scope->setTag('stack', $this->app->make('sentry.stack'));
                    }
                });

                return $hub;
            });

        $this->app->alias('sentry', HubInterface::class);

        $this->app->extend(ViewFormatter::class,
            function (ViewFormatter $formatter) {
                return new SentryFormatter($formatter);
            });

        $this->app->tag(SentryReporter::class, Reporter::class);

        // js assets
        $this->app->resolving('flarum.assets.forum',
            function (Assets $assets) {
                if ((bool)(int)$this->app->make('flarum.settings')->get('fof-sentry.javascript')) {
                    $assets->js(function (SourceCollector $sources) {
                        $sources->addString(function () {
                            return 'var module={}';
                        });
                        $sources->addFile(__DIR__ . '/../js/dist/forum.js');
                        $sources->addString(function () {
                            return "flarum.extensions['fof-sentry']=module.exports";
                        });
                    });
                }
            });
    }

    protected function init(string $dsn, int $performanceMonitoring)
    {
        /** @var Paths $paths */
        $paths = $this->app->make(Paths::class);

        $tracesSampleRate = $performanceMonitoring > 0 ? round($performanceMonitoring / 100, 2) : 0;

        $config = $this->app->make('flarum.config');

        init([
            'dsn'                   => $dsn,
            'in_app_include'        => [$paths->base],
            'traces_sample_rate'    => $tracesSampleRate,
            'environment'           => str_replace(['https://', 'http://'], null, Arr::get($config, 'url')),
            'release'               => Application::VERSION
        ]);
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

    public function __destruct()
    {
        /** @var Transaction $transaction */
        foreach (static::$transactionStack as $transaction) {
            $transaction->finish();
        }
    }
}
