<?php

namespace FoF\Sentry\Performance;

use Illuminate\Support\Arr;
use Sentry\Tracing\Span;

class Frontend extends Measure
{
    public function handle(): ?Span
    {
        foreach(['api', 'forum', 'admin'] as $frontend) {
            $this->container->resolving("flarum.$frontend.middleware", function (array $middleware) use ($frontend) {
                Arr::prepend($middleware, new Middleware\MeasurePerformanceMiddleware($frontend, $this->transaction));

                return $middleware;
            });
        }

        return null;
    }
}
