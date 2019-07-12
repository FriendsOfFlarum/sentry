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

use Flarum\Console\Event\Configuring;
use Flarum\Event\ConfigureMiddleware;
use Flarum\Extend;
use Flarum\Foundation\Application;
use Illuminate\Events\Dispatcher;

return [
    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),
    new Extend\Locales(__DIR__ . '/resources/locale'),
    new Extend\Compat(function (Dispatcher $events, Application $app) {
        $app->register(SentryServiceProvider::class);

        $events->listen(ConfigureMiddleware::class, Listener\HandleHttpErrorsWithSentry::class);
        $events->listen(Configuring::class, Listener\HandleConsoleErrorsWithSentry::class);
    })
];
