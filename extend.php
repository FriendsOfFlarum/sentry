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

use Flarum\Extend as Native;
use Flarum\Foundation\Application;

return [
    (new Native\Frontend('forum'))
        ->content(Content\SentryJavaScript::class),
    (new Native\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),
    new Native\Locales(__DIR__.'/locale'),
    new Extend\HandleHttpErrors,
    new Native\Compat(function (Application $app) {
        $app->register(SentryServiceProvider::class);
    }),
];
