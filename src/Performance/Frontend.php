<?php

/*
 * This file is part of fof/sentry
 *
 * Copyright (c) 2020 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Sentry\Performance;

use Illuminate\Support\Arr;
use Sentry\Tracing\Span;

class Frontend extends Measure
{
    public function handle(): ?Span
    {
        foreach (['api', 'forum', 'admin'] as $frontend) {
            $this->container->resolving("flarum.$frontend.middleware", function (array $middleware) use ($frontend) {
                Arr::prepend($middleware, new Middleware\MeasurePerformanceMiddleware($frontend, $this->transaction));

                return $middleware;
            });
        }

        return null;
    }
}
