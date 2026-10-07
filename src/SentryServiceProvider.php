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
use FoF\Sentry\Formatters\SentryFormatter;
use FoF\Sentry\Middleware\TraceRequest;
use FoF\Sentry\Reporters\SentryReporter;
use FoF\Sentry\Tracing\AfterResponse;
use FoF\Sentry\Tracing\Tracer;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Arr;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\SentrySdk;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Sentry\UserDataBag;

use function Sentry\init;

class SentryServiceProvider extends AbstractServiceProvider
{
    /** @var array<class-string> */
    protected array $measurements = [
        Performance\Eloquent::class,
    ];

    public function register()
    {
        // Register empty config containers that can be extended
        $this->container->singleton('fof.sentry.backend.config', function () {
            return [];
        });

        $this->container->singleton('fof.sentry.frontend.config', function () {
            return [];
        });

        $this->container->singleton('fof.sentry.measurements', function () {
            return $this->measurements;
        });

        $this->container->singleton('sentry.release', function () {
            return Application::VERSION;
        });

        $this->container->singleton(HubInterface::class, function ($container) {
            /** @var SettingsRepositoryInterface $settings */
            $settings = $container->make(SettingsRepositoryInterface::class);

            $dsn = static::backendDsn($settings);

            // Without a DSN the SDK is never initialised; the current hub has no client and drops everything.
            if ($dsn === null) {
                return SentrySdk::getCurrentHub();
            }

            /** @var UrlGenerator $url */
            $url = $container->make(UrlGenerator::class);
            /** @var string $release */
            $release = $container->make('sentry.release');

            // Use custom environment if set, otherwise use the setting or default
            $environment = $container->bound('fof.sentry.environment')
                ? $container->make('fof.sentry.environment')
                : (empty($settings->get('fof-sentry.environment'))
                    ? str_replace(['https://', 'http://'], '', $url->to('forum')->base())
                    : $settings->get('fof-sentry.environment'));

            $performanceMonitoring = (int) $settings->get('fof-sentry.monitor_performance');
            $profilesSampleRate = (int) $settings->get('fof-sentry.profile_rate');

            /** @var Paths $paths */
            $paths = $container->make(Paths::class);

            $tracesSampleRate = round(max(0, min(100, $performanceMonitoring))) / 100;
            $profilesSampleRate = round(max(0, min(100, $profilesSampleRate))) / 100;

            // Base configuration
            $config = [
                'dsn'                   => $dsn,
                'in_app_include'        => [$paths->base],
                'traces_sample_rate'    => $tracesSampleRate,
                'profiles_sample_rate'  => $profilesSampleRate,
                'environment'           => $environment,
                'release'               => $release,
                'before_send'           => [static::class, 'beforeSend'],
            ];

            // Merge with custom config
            if ($container->bound('fof.sentry.backend.config')) {
                $customConfig = $container->make('fof.sentry.backend.config');
                $config = array_merge($config, $customConfig);
            }

            init($config);

            $hub = SentrySdk::getCurrentHub();

            /** @var Config $flarumConfig */
            $flarumConfig = $container->make('flarum.config');

            $hub->configureScope(function (Scope $scope) use ($container, $flarumConfig) {
                $scope->setTag('offline', $this->booleanToString((bool) Arr::get($flarumConfig, 'offline', false)));
                $scope->setTag('debug', $this->booleanToString($flarumConfig->inDebugMode()));
                $scope->setTag('flarum', Application::VERSION);

                if ($container->bound('sentry.stack')) {
                    $scope->setTag('stack', $container->make('sentry.stack'));
                }

                $scope->addEventProcessor(fn (Event $event) => static::attachUser($event, $container));
            });

            return $hub;
        });

        $this->container->singleton('sentry', function ($container) {
            if (static::backendDsn($container->make(SettingsRepositoryInterface::class)) === null) {
                return null;
            }

            return $container->make(HubInterface::class);
        });

        $this->container->singleton(AfterResponse::class);
        $this->container->singleton(Tracer::class);

        // Outermost on every frontend, so the transaction covers the whole middleware stack.
        foreach (['forum', 'admin', 'api'] as $frontend) {
            $this->container->bind("fof.sentry.middleware.trace.$frontend", function (Container $container) use ($frontend) {
                return new TraceRequest($frontend, $container);
            });

            $this->container->extend("flarum.$frontend.middleware", function (array $middleware) use ($frontend) {
                return array_merge(["fof.sentry.middleware.trace.$frontend"], $middleware);
            });
        }

        $this->container->singleton(ViewFormatter::class, SentryFormatter::class);

        $this->container->tag(SentryReporter::class, Reporter::class);

        // js assets
        $this->container->resolving(
            'flarum.assets.forum',
            function (Assets $assets) {
                $settings = resolve('flarum.settings');

                // Without a public DSN the browser client is disabled, so don't ship the SDK at all.
                if ((int) $settings->get('fof-sentry.javascript') && $settings->get('fof-sentry.dsn')) {
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

    public function boot(SettingsRepositoryInterface $settings): void
    {
        if (static::backendDsn($settings) === null) {
            return;
        }

        // Initialise now rather than on first error, so the SDK's own handlers are in place
        // for uncaught exceptions, PHP warnings and fatal errors from the start of the request.
        $this->container->make(HubInterface::class);
    }

    /**
     * The DSN the backend reports to: the backend-only DSN when set, otherwise the general one.
     */
    public static function backendDsn(SettingsRepositoryInterface $settings): ?string
    {
        $dsn = $settings->get('fof-sentry.dsn_backend') ?: $settings->get('fof-sentry.dsn');

        return $dsn ? (string) $dsn : null;
    }

    /**
     * Eloquent reconnects transparently after a dropped MySQL connection, but PDO
     * still raises a warning for the failed STMT_PREPARE first. That is noise.
     */
    public static function beforeSend(Event $event, ?EventHint $hint): ?Event
    {
        $exception = $hint?->exception;

        if ($exception instanceof ErrorException && str_contains($exception->getMessage(), 'STMT_PREPARE packet')) {
            return null;
        }

        return $event;
    }

    /**
     * Builds user context only when an event is actually sent, from the request
     * bound by HandleErrorsWithSentry. A user set explicitly on the scope wins.
     */
    public static function attachUser(Event $event, Container $container): Event
    {
        if ($event->getUser() !== null || !$container->bound('sentry.request')) {
            return $event;
        }

        $data = $container->make(UserContext::class)->fromRequest($container->make('sentry.request'));

        if (!empty($data)) {
            $event->setUser(UserDataBag::createFromArray($data));
        }

        return $event;
    }

    /**
     * A simple helper to convert a boolean to a string.
     */
    public function booleanToString(bool $value): string
    {
        return $value ? 'true' : 'false';
    }
}
