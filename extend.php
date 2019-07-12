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
use Flarum\Extend;
use Flarum\Foundation\Application;
use FoF\Sentry\Middleware\HandleErrorsWithSentry;
use Illuminate\Events\Dispatcher;
use Illuminate\View\Factory;

return [
    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),
    new Extend\Locales(__DIR__ . '/resources/locale'),
    new Extend\Compat(function (Dispatcher $events, Application $app, Factory $views) {
        $app->register(SentryServiceProvider::class);

        $events->listen(ConfigureMiddleware::class, function (ConfigureMiddleware $event) use ($app) {
            $app->instance('sentry.stack', $event->isApi() ? 'api' : ($event->isForum() ? 'forum' : 'admin'));

            $event->pipe(app(HandleErrorsWithSentry::class));
        });

        $views->addNamespace('fof-sentry', __DIR__.'/resources/views');
    })
];
