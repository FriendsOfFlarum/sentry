<?php

/*
 * This file is part of fof/sentry
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Sentry;

use ErrorException;
use Flarum\Foundation\AbstractServiceProvider;
use Flarum\Foundation\Application;
use Flarum\Foundation\Config;
use Flarum\Foundation\ErrorHandling\Reporter;
use Flarum\Foundation\ErrorHandling\ViewFormatter;
use Flarum\Foundation\Paths;
use Flarum\Frontend\Assets;
use Flarum\Frontend\Compiler\Source\SourceCollector;
use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use FoF\Sentry\Contracts\Measure;
use FoF\Sentry\Formatters\SentryFormatter;
use FoF\Sentry\Reporters\SentryReporter;
use Illuminate\Support\Arr;
use Sentry\SentrySdk;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
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

    public function register()
    {
        $this->container->singleton('sentry.release', function () {
            return Application::VERSION;
        });

        $this->container->singleton(HubInterface::class, function ($container) {
            /** @var SettingsRepositoryInterface $settings */
            $settings = $container->make(SettingsRepositoryInterface::class);
            /** @var UrlGenerator $url */
            $url = $container->make(UrlGenerator::class);
            $dsn = $settings->get('fof-sentry.dsn_backend');
            /** @var string $release */
            $release = $container->make('sentry.release');
            $environment = empty($settings->get('fof-sentry.environment')) ? str_replace(['https://', 'http://'], '', $url->to('forum')->base()) : $settings->get('fof-sentry.environment');
            $performanceMonitoring = (int) $settings->get('fof-sentry.monitor_performance');
            $profilesSampleRate = (int) $settings->get('fof-sentry.profile_rate');

            if (empty($dsn)) {
                $dsn = $settings->get('fof-sentry.dsn');
            }

            /** @var Paths $paths */
            $paths = $container->make(Paths::class);

            $tracesSampleRate = round(max(0, min(100, $performanceMonitoring))) / 100;
            $profilesSampleRate = round(max(0, min(100, $profilesSampleRate))) / 100;

            init([
                'dsn'                   => $dsn,
                'in_app_include'        => [$paths->base],
                'traces_sample_rate'    => $tracesSampleRate,
                'profiles_sample_rate'  => $profilesSampleRate,
                'environment'           => $environment,
                'release'               => $release,
            ]);

            return SentrySdk::getCurrentHub();
        });

        $this->container->singleton('sentry', function ($container) {
            /** @var SettingsRepositoryInterface $settings */
            $settings = $container->make(SettingsRepositoryInterface::class);

            $dsn = $settings->get('fof-sentry.dsn');
            if (!$dsn) {
                return null;
            }

            /** @var Config $config */
            $config = $container->make('flarum.config');

            /** @var HubInterface $hub */
            $hub = $this->container->make(HubInterface::class);

            $hub->configureScope(function (Scope $scope) use ($config) {
                $scope->setTag('offline', $this->booleanToString(Arr::get($config, 'offline', false)));
                $scope->setTag('debug', $this->booleanToString(Arr::get($config, 'debug', true)));
                $scope->setTag('flarum', Application::VERSION);

                if ($this->container->bound('sentry.stack')) {
                    $scope->setTag('stack', $this->container->make('sentry.stack'));
                }
            });

            return $hub;
        });

        $this->container->extend(
            ViewFormatter::class,
            function (ViewFormatter $formatter) {
                return new SentryFormatter($formatter);
            }
        );

        $this->container->tag(SentryReporter::class, Reporter::class);

        // js assets
        $this->container->resolving(
            'flarum.assets.forum',
            function (Assets $assets) {
                $useJs = (int) resolve('flarum.settings')->get('fof-sentry.javascript');

                if ($useJs) {
                    $assets->js(function (SourceCollector $sources) {
                        $sources->addString(function () {
                            return 'var module={};';
                        });

                        $traceSampleRate = (int) resolve('flarum.settings')->get('fof-sentry.javascript.trace_sample_rate');
                        $replaysSessionSampleRate = (int) resolve('flarum.settings')->get('fof-sentry.javascript.replays_session_sample_rate');
                        $replaysErrorSampleRate = (int) resolve('flarum.settings')->get('fof-sentry.javascript.replays_error_sample_rate');

                        $usePerformanceMonitoring = $traceSampleRate > 0;
                        $useReplay = $replaysSessionSampleRate > 0 || $replaysErrorSampleRate > 0;

                        $filename = 'forum';

                        if ($usePerformanceMonitoring) {
                            $filename .= '.tracing';
                        }

                        if ($useReplay) {
                            $filename .= '.replay';
                        }

                        $sources->addFile(__DIR__."/../js/dist/$filename.js");
                        $sources->addString(function () {
                            return "flarum.extensions['fof-sentry']=module.exports;";
                        });
                    });
                }
            }
        );
    }

    public function boot(SettingsRepositoryInterface $settings)
    {
        set_error_handler([$this, 'handleError']);

        $dsn = $settings->get('fof-sentry.dsn');
        $performanceMonitoring = (int) $settings->get('fof-sentry.monitor_performance');

        if ($dsn && $performanceMonitoring > 0) {
            /** @var HubInterface $hub */
            $hub = $this->container->make(HubInterface::class);

            $transaction = $hub->startTransaction(new TransactionContext('flarum'));

            foreach ($this->measurements as $measurement) {
                /** @var Measure $measure */
                $measure = new $measurement($transaction, $this->container);
                if ($span = $measure->handle()) {
                    static::$transactionStack[] = $span;
                }
            }

            static::$transactionStack[] = $transaction;
        }
    }

    public function handleError($level, $message, $file = '', $line = 0)
    {
        // ignore STMT_PREPARE errors because Eloquent automatically tries reconnecting
        if (strpos($message, 'STMT_PREPARE packet') !== false) {
            return false;
        }

        if (error_reporting() & $level) {
            $error = new ErrorException($message, 0, $level, $file, $line);

            if (resolve('flarum')->inDebugMode()) {
                throw $error;
            } else {
                foreach ($this->container->tagged(Reporter::class) as $reporter) {
                    /**
                     * @var SentryReporter $reporter
                     */
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

    /**
     * A simple helper to convert a boolean to a string.
     */
    public function booleanToString(bool $value): string
    {
        return $value ? 'true' : 'false';
    }
}
