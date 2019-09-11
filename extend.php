<?php

/*
 * This file is part of fof/sentry
 *
 * Copyright (c) 2018 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Sentry;

use Flarum\Event\ConfigureMiddleware;
use Flarum\Extend as Native;
use Flarum\Foundation\Application;
use FoF\Sentry\Middleware\HandleErrorsWithSentry;
use Illuminate\Events\Dispatcher;

return [
    (new Native\Frontend('forum'))
        ->content(Content\SentryJavaScript::class),
    (new Native\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),
    new Native\Locales(__DIR__.'/locale'),
    new Native\Compat(function (Dispatcher $events, Application $app) {
        $app->register(SentryServiceProvider::class);

        $events->listen(ConfigureMiddleware::class, function (ConfigureMiddleware $event) use ($app) {
            $app->instance('sentry.stack', $event->isApi() ? 'api' : ($event->isForum() ? 'forum' : 'admin'));

            $event->pipe(app(HandleErrorsWithSentry::class));
        });
    }),
];
