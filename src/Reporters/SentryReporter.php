<?php

/*
 * This file is part of fof/sentry
 *
 * Copyright (c) 2018 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Sentry\Reporters;

use Flarum\Foundation\Application;
use Flarum\Foundation\ErrorHandling\Reporter;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Symfony\Component\Console\Command\Command;
use Throwable;

class SentryReporter implements Reporter
{
    /**
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * @var Application
     */
    protected $app;

    public function __construct(LoggerInterface $logger, Application $app)
    {
        $this->logger = $logger;
        $this->app = $app;
    }

    public function report(Throwable $error)
    {
        /** @var HubInterface $hub */
        $hub = $this->app->make('sentry');
        /** @var string $stack */
        $stack = $this->app->runningInConsole() ? 'console' : $this->app->make('sentry.stack');

        $id = null;

        if ($hub == null) {
            $this->logger->warning('[fof/sentry] sentry dsn not set');

            return;
        }

        if ($stack !== 'console') {
            $this->httpScope($hub, app('sentry.request'));
        }

        $id = $hub->captureException($error);

        if ($id == null) {
            $this->logger->warning('[fof/sentry] exception of type '.get_class($error).' failed to send');
        }
    }

    protected function httpScope(HubInterface $hub, ServerRequestInterface $request): void
    {
        $hub->configureScope(function (Scope $scope) use ($request) {
            $user = $request->getAttribute('actor');

            if ($user != null && $user->id != 0) {
                $scope->setUser([
                    'id'       => $user->id,
                    'username' => $user->username,
                    'email'    => $user->email,
                ]);
            }
        });
    }
}
