<?php

/*
 * This file is part of fof/sentry
 *
 * Copyright (c) 2018 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Sentry\Listener;

use Flarum\Event\ConfigureMiddleware;
use FoF\Sentry\Middleware\HandleErrorsWithSentry;

class HandleHttpErrorsWithSentry
{
    public function handle(ConfigureMiddleware $event)
    {
        app()->instance('sentry.stack', $event->isApi() ? 'api' : ($event->isForum() ? 'forum' : 'admin'));

        $event->pipe(app(HandleErrorsWithSentry::class));
    }
}
