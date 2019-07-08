<?php

/*
 * This file is part of fof/sentry
 *
 * Copyright (c) 2018 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Sentry\Listener;

use Flarum\Console\Event\Configuring;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleErrorEvent;

class HandleConsoleErrorsWithSentry
{
    public function handle(Configuring $event)
    {
        $event->eventDispatcher->addListener(ConsoleEvents::ERROR, [$this, 'listener']);
    }

    public function listener(ConsoleErrorEvent $event)
    {
        /** @var \Raven_Client $sentry */
        $sentry = app('sentry');

        if ($sentry) {
            /** @var Command $command */
            if ($command = $event->getCommand()) {
                $sentry->context->extra['command'] = $command->getName();
            }

            $sentry->captureException($event->getError());
        }
    }
}
