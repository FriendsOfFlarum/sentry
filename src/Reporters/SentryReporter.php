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

    public function report(HandledError $error)
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
            app('log')->warn('[fof/sentry] sentry dsn not set');

            return;
        }

        $hub->configureScope(function (Scope $scope) use ($request, $error) {
            $scope->setExtra('details', $error->getDetails());

            $user = $request->getAttribute('actor');

            if ($user != null && $user->id != 0) {
                $scope->setUser([
                    'id'       => $user->id,
                    'username' => $user->username,
                    'email'    => $user->email,
                ]);
            }
        });

        $err = $error->getError();
        $id = $hub->captureException($error->getError());

        if ($id == null) {
            $this->logger->warning('[fof/sentry] exception of type '.get_class($err).' failed to send');
        }
    }
}
