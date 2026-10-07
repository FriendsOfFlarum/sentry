<?php

/*
 * This file is part of fof/sentry
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Sentry\Tracing;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanStatus;
use Sentry\Tracing\Transaction;

/**
 * One transaction per queued job, so a long-running worker is never traced as a single endless transaction.
 */
class TraceQueueJobs
{
    /** @var array<int, array{0: Transaction, 1: Span|null}> Jobs in progress; a job can dispatch a sync job. */
    protected array $running = [];

    public function __construct(protected Container $container)
    {
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(JobProcessing::class, [$this, 'started']);
        $events->listen(JobProcessed::class, [$this, 'processed']);
        $events->listen(JobExceptionOccurred::class, [$this, 'failed']);
    }

    public function started(JobProcessing $event): void
    {
        $tracer = $this->tracer();

        if ($tracer->enabled()) {
            $this->running[] = $tracer->startJob($event->job->resolveName());
        }
    }

    public function processed(JobProcessed $event): void
    {
        $this->finish(SpanStatus::ok());
    }

    public function failed(JobExceptionOccurred $event): void
    {
        $this->finish(SpanStatus::internalError());
    }

    protected function finish(SpanStatus $status): void
    {
        if ($job = array_pop($this->running)) {
            $this->tracer()->finishJob($job, $status);
        }
    }

    protected function tracer(): Tracer
    {
        return $this->container->make(Tracer::class);
    }
}
