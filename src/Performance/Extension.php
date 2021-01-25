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

use Flarum\Extension\Event;
use Illuminate\Contracts\Events\Dispatcher;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\TransactionContext;

class Extension extends Measure
{
    /** @var Span */
    protected static $parent;
    /** @var Span */
    protected static $measure;

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
        $span = $this->transaction->startChild(new SpanContext());
        $span->setOp('extension');

        if ($event instanceof Event\Enabling || $event instanceof Event\Disabling) {
            static::$measure = $span->startChild(new TransactionContext(
                $event instanceof Enabling ? 'extension.enabling' : 'extension.disabling'
            ));

            static::$measure->setDescription($event->extension->name);
        } elseif (static::$measure) {
            static::$measure->finish();
        }
    }

    public function __destruct()
    {
        static::$parent->finish();
    }
}
