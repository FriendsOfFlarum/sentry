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
use Illuminate\Contracts\Container\Container;
use Psr\Log\LoggerInterface;
use Sentry\State\HubInterface;
use Throwable;

class SentryReporter implements Reporter
{
    public function __construct(protected LoggerInterface $logger, protected Container $container)
    {
    }

    public function report(Throwable $error): void
    {
        /** @var HubInterface $hub */
        $hub = $this->container->make(HubInterface::class);

        // No DSN configured: the SDK was never initialised.
        if ($hub->getClient() === null) {
            return;
        }

        if ($hub->captureException($error) === null) {
            $this->logger->warning('[fof/sentry] exception of type '.get_class($error).' was not sent to Sentry');
        }
    }
}
