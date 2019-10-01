<?php

/*
 * This file is part of fof/sentry
 *
 * Copyright (c) 2018 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Sentry\Extend;

use Flarum\Console\Event\Configuring;
use Flarum\Extend\ExtenderInterface;
use Flarum\Extension\Extension;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleErrorEvent;

class HandleConsoleErrors implements ExtenderInterface
{
    public function extend(Container $container, Extension $extension = null)
    {
        /** @var Dispatcher $events */
        $events = $container->make(Dispatcher::class);

        $events->listen(Configuring::class, function (Configuring $event) {
            $event->eventDispatcher->addListener(ConsoleEvents::ERROR, [$this, 'listen']);
        });
    }

    public function listen(ConsoleErrorEvent $event)
    {
        app()->instance('sentry.stack', 'console');

        if (app()->bound('sentry') && $sentry = app('sentry')) {
            /** @var Command $command */
            if ($command = $event->getCommand()) {
                $sentry->context->extra['command'] = $command->getName();
            }

            $sentry->captureException($event->getError());
        }
    }
}
