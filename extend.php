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
use Flarum\Extend as Native;
use Flarum\Foundation\Application;
use Illuminate\Events\Dispatcher;

return [
    (new Native\Frontend('forum'))
        ->content(Content\SentryJavaScript::class),
    (new Native\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),
    new Native\Locales(__DIR__.'/resources/locale'),
    new Native\Compat(function (Dispatcher $events, Application $app) {
        $app->register(SentryServiceProvider::class);

        $events->listen(ConfigureMiddleware::class, Listener\HandleHttpErrorsWithSentry::class);
        $events->listen(Configuring::class, Listener\HandleConsoleErrorsWithSentry::class);
    })
];
