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

use Flarum\Foundation\ErrorHandling\HandledError;
use Flarum\Foundation\ErrorHandling\Reporter;
use Psr\Http\Message\ServerRequestInterface;
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

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function report(Throwable $error)
    {
        /**
         * @var HubInterface
         * @var $request     ServerRequestInterface
         * @var $stack       string
         */
        $hub = app('sentry');
        $request = app('sentry.request');
        $id = null;

        if ($hub == null) {
            $this->logger->warning('[fof/sentry] sentry dsn not set');

            return;
        }

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

        $id = $hub->captureException($error);

        if ($id == null) {
            $this->logger->warning('[fof/sentry] exception of type '.get_class($error).' failed to send');
        }
    }
}
