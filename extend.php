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

use Flarum\Extend as Flarum;
use FoF\Sentry\Middleware\HandleErrorsWithSentry;

return [
    (new Flarum\ServiceProvider())
        ->register(SentryServiceProvider::class),
    (new Flarum\Frontend('forum'))
        ->css(__DIR__.'/resources/less/forum.less')
        ->content(Content\SentryJavaScript::class),
    (new Flarum\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),
    new Flarum\Locales(__DIR__.'/resources/locale'),
    (new Flarum\Middleware('forum'))
        ->add(HandleErrorsWithSentry::class),
    (new Flarum\Middleware('admin'))
        ->add(HandleErrorsWithSentry::class),
    (new Flarum\Middleware('api'))
        ->add(HandleErrorsWithSentry::class),
];
