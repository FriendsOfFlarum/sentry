<?php

/*
 * This file is part of fof/sentry
 *
 * Copyright (c) 2020 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Sentry;

use Flarum\Extend as Native;
use Flarum\Foundation\Application;
use FoF\Sentry\Middleware\HandleErrorsWithSentry;

return [
    (new Native\Frontend('forum'))
        ->css(__DIR__.'/resources/less/forum.less')
        ->content(Content\SentryJavaScript::class),
    (new Native\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),
    new Native\Locales(__DIR__.'/resources/locale'),
    (new Native\Middleware('forum'))
        ->add(HandleErrorsWithSentry::class),
    (new Native\Middleware('admin'))
        ->add(HandleErrorsWithSentry::class),
    (new Native\Middleware('api'))
        ->add(HandleErrorsWithSentry::class),
    function (Application $app) {
        $app->register(SentryServiceProvider::class);
    },
];
