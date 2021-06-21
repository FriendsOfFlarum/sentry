<?php

/*
 * This file is part of fof/sentry
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Sentry;

use Flarum\Extend as Flarum;
use Flarum\Frontend\RecompileFrontendAssets;
use Flarum\Locale\LocaleManager;
use Flarum\Settings\Event\Saved;
use FoF\Sentry\Middleware\HandleErrorsWithSentry;
use Illuminate\Container\Container;
use Illuminate\Support\Str;

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

    (new Flarum\Event())
        ->listen(Saved::class, function (Saved $event) {
            foreach ($event->settings as $key => $setting) {
                if (Str::startsWith($key, 'fof-sentry.javascript')) {
                    $container = Container::getInstance();
                    $recompile = new RecompileFrontendAssets(
                        $container->make('flarum.assets.forum'),
                        $container->make(LocaleManager::class)
                    );
                    $recompile->flush();

                    return;
                }
            }
        }),
];
