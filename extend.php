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
use Flarum\Frontend\Document;
use FoF\Sentry\Middleware\HandleErrorsWithSentry;
use FoF\Sentry\Middleware\NameTransaction;

return [
    (new Flarum\ServiceProvider())
        ->register(SentryServiceProvider::class),

    (new Flarum\Frontend('forum'))
        ->css(__DIR__.'/resources/less/forum.less')
        ->content(Content\SentryJavaScript::class),

    (new Flarum\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/resources/less/admin.less')
        ->content(function (Document $document) {
            $document->payload['hasExcimer'] = extension_loaded('excimer');
        }),

    new Flarum\Locales(__DIR__.'/resources/locale'),

    (new Flarum\Middleware('forum'))
        ->add(HandleErrorsWithSentry::class)
        ->add(NameTransaction::class),

    (new Flarum\Middleware('admin'))
        ->add(HandleErrorsWithSentry::class)
        ->add(NameTransaction::class),

    (new Flarum\Middleware('api'))
        ->add(HandleErrorsWithSentry::class)
        ->add(NameTransaction::class),

    // These settings decide whether, and which variant of, the Sentry bundle is compiled into forum.js.
    (new Flarum\Settings())
        ->resetJsCacheFor('fof-sentry.dsn')
        ->resetJsCacheFor('fof-sentry.javascript')
        ->resetJsCacheFor('fof-sentry.javascript.trace_sample_rate')
        ->resetJsCacheFor('fof-sentry.javascript.replays_session_sample_rate')
        ->resetJsCacheFor('fof-sentry.javascript.replays_error_sample_rate')
        ->default('fof-sentry.dsn', '')
        ->default('fof-sentry.dsn_backend', '')
        ->default('fof-sentry.environment', '')
        ->default('fof-sentry.monitor_performance', 0)
        ->default('fof-sentry.send_emails_with_sentry_reports', false)
        ->default('fof-sentry.user_feedback', false)
        ->default('fof-sentry.javascript.console', false)
        ->default('fof-sentry.javascript.trace_sample_rate', 0)
        ->default('fof-sentry.javascript.replays_session_sample_rate', 0)
        ->default('fof-sentry.javascript.replays_error_sample_rate', 0)
        ->default('fof-sentry.profile_rate', 0)
        ->default('fof-sentry.javascript', true)
        // Database query performance monitoring settings
        ->default('fof-sentry.db.slow_query_threshold', 1000)
        ->default('fof-sentry.db.n_plus_one_detection', true)
        ->default('fof-sentry.db.n_plus_one_threshold', 10)
        ->default('fof-sentry.db.track_bindings', false)
        ->default('fof-sentry.db.query_sample_rate', 100),
];
