<?php


namespace FoF\Sentry\Performance;


use Flarum\Extension\Event;
use Illuminate\Contracts\Events\Dispatcher;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\TransactionContext;

class Extension extends Measure
{
    /** @var Span */
    static protected $parent;
    /** @var Span */
    static protected $measure;

    public function handle(): ?Span
    {
        /** @var Dispatcher $events */
        $events = $this->container->make(Dispatcher::class);

        $events->listen([
            Event\Enabled::class, Event\Enabling::class,
            Event\Disabled::class, Event\Disabling::class,
        ], [$this, 'measure']);

        return null;
    }

    public function measure($event)
    {
        $span = $this->transaction->startChild(new SpanContext);
        $span->setOp('extension');

        if ($event instanceof Event\Enabling || $event instanceof Event\Disabling) {
            static::$measure = $span->startChild(new TransactionContext(
                $event instanceof Enabling ? 'extension.enabling' : 'extension.disabling'
            ));

            static::$measure->setDescription($event->extension->name);
        } else if (static::$measure) {
            static::$measure->finish();
        }
    }

    public function __destruct()
    {
        static::$parent->finish();
    }
}
