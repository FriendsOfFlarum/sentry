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
use Flarum\Settings\SettingsRepositoryInterface;
use FoF\Sentry\Contracts\Measure;
use FoF\Sentry\Formatters\SentryFormatter;
use FoF\Sentry\Reporters\SentryReporter;
use Illuminate\Support\Arr;
use function Sentry\init;
use Sentry\SentrySdk;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;

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
        $settings = resolve(SettingsRepositoryInterface::class);
        $dsn = $settings->get('fof-sentry.dsn');
        $performanceMonitoring = (int) $settings->get('fof-sentry.monitor_performance', 0);

        $this->container->singleton(HubInterface::class, function () use ($dsn, $performanceMonitoring) {
            $this->init($dsn, $performanceMonitoring);

            return SentrySdk::getCurrentHub();
        });

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

        $this->container->singleton('sentry', function () use ($dsn) {
            if (!$dsn) {
                return null;
            }

            /** @var array $config */
            $config = $this->container->make('flarum.config');

            /** @var HubInterface $hub */
            $hub = $this->container->make(HubInterface::class);

            $hub->configureScope(function (Scope $scope) use ($config) {
                $scope->setTag('offline', (int) Arr::get($config, 'offline', false));
                $scope->setTag('debug', (int) Arr::get($config, 'debug', true));
                $scope->setTag('flarum', Application::VERSION);

                if ($this->container->bound('sentry.stack')) {
                    $scope->setTag('stack', $this->container->make('sentry.stack'));
                }
            });

            return $hub;
        });

        $this->container->alias('sentry', HubInterface::class);

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
                if ((bool) (int) resolve('flarum.settings')->get('fof-sentry.javascript')) {
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
            }
        );
    }

    protected function init(string $dsn, int $performanceMonitoring)
    {
        /** @var Paths $paths */
        $paths = $this->container->make(Paths::class);

        $tracesSampleRate = $performanceMonitoring > 0 ? round($performanceMonitoring / 100, 2) : 0;

        /** @var Config $config */
        $config = $this->container->make(Config::class);

        init([
            'dsn'                   => $dsn,
            'in_app_include'        => [$paths->base],
            'traces_sample_rate'    => $tracesSampleRate,
            'environment'           => str_replace(['https://', 'http://'], '', Arr::get($config, 'url')),
            'release'               => Application::VERSION,
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
}
