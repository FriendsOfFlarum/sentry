<?php


namespace FoF\Sentry\Performance;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\QueryExecuted;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;

class Eloquent extends Measure
{
    public function handle(): ?Span
    {
        /** @var Dispatcher $events */
        $events = $this->container->make(Dispatcher::class);

        $span = $this->transaction->startChild(new SpanContext);
        $span->setOp('eloquent');


        $events->listen(QueryExecuted::class, function (QueryExecuted $event) use ($span) {
            $end = microtime(true);
            $time = microtime(true) - ($event->time / 1000);

            $spanContext = new SpanContext();
            $spanContext->setOp('eloquent.query');
            $spanContext->setDescription($event->sql);
            $spanContext->setData(['connection' => $event->connectionName]);
            $spanContext->setStartTimestamp($time);
            $spanContext->setEndTimestamp($end);
            $spanContext->setSampled(true);
            $span->startChild($spanContext);
        });

        return $span;
    }
}
