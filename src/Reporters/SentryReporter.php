<?php

/*
 * This file is part of fof/sentry
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Sentry\Reporters;

use Flarum\Foundation\ErrorHandling\Reporter;
use Flarum\Http\RequestUtil;
use Illuminate\Contracts\Container\Container;
use Psr\Log\LoggerInterface;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Throwable;

class SentryReporter implements Reporter
{
    /**
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * @var Container
     */
    private $container;

    public function __construct(LoggerInterface $logger, Container $container)
    {
        $this->logger = $logger;
        $this->container = $container;
    }

    public function report(Throwable $error)
    {
        /** @var HubInterface $hub */
        $hub = $this->container->make('sentry');

        if ($hub === null) {
            $this->logger->warning('[fof/sentry] sentry dsn not set');

            return;
        }

        if ($this->container->bound('sentry.request')) {
            $hub->configureScope(function (Scope $scope) {
                $request = $this->container->make('sentry.request');
                $user = RequestUtil::getActor($request);

                if ($user && $user->id !== 0) {
                    $data = $user->only('id', 'username');

                    // Only send email if enabled in settings
                    if ((int) @resolve('flarum.settings')->get('fof-sentry.send_emails_with_sentry_reports')) {
                        $data['email'] = $user->email;
                    }

                    $scope->setUser($data);
                }
            });
        }

        $id = $hub->captureException($error);

        if ($id === null) {
            $this->logger->warning('[fof/sentry] exception of type '.get_class($error).' failed to send');
        }
    }
}
