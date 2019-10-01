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

use Flarum\Event\ConfigureMiddleware;
use Flarum\Extend\ExtenderInterface;
use Flarum\Extension\Extension;
use FoF\Sentry\Middleware\HandleErrorsWithSentry;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;

class HandleHttpErrors implements ExtenderInterface
{
    public function extend(Container $container, Extension $extension = null)
    {
        /** @var Dispatcher $events */
        $events = $container->make(Dispatcher::class);

        $events->listen(ConfigureMiddleware::class, function (ConfigureMiddleware $event) use ($container) {
            $container->instance('sentry.stack', $event->isApi() ? 'api' : ($event->isForum() ? 'forum' : 'admin'));

            $event->pipe(app(HandleErrorsWithSentry::class));
        });
    }
}
