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

use Flarum\Settings\SettingsRepositoryInterface;
use FoF\Sentry\Contracts\Measure;
use FoF\Sentry\SentryServiceProvider;
use Illuminate\Contracts\Container\Container;
use Sentry\State\HubInterface;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\SpanStatus;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;
use Sentry\Tracing\TransactionSource;

class Tracer
{
    /** @var array<int, Span>|null Spans opened by the measurements; null until they have been started. */
    protected ?array $measureSpans = null;

    public function __construct(
        protected Container $container,
        protected SettingsRepositoryInterface $settings,
        protected AfterResponse $afterResponse
    ) {
    }

    public function enabled(): bool
    {
        return SentryServiceProvider::backendDsn($this->settings) !== null
            && (int) $this->settings->get('fof-sentry.monitor_performance') > 0;
    }

    /**
     * Starts a transaction and makes it the hub's current span, so errors, child
     * spans and outgoing trace headers all belong to it.
     */
    public function start(TransactionContext $context): Transaction
    {
        /** @var HubInterface $hub */
        $hub = $this->container->make(HubInterface::class);

        $transaction = $hub->startTransaction($context);
        $hub->setSpan($transaction);

        if ($transaction->getSampled()) {
            $this->startMeasures($transaction);
        }

        return $transaction;
    }

    /**
     * Records the end time now but sends the transaction after the response.
     */
    public function finishAfterResponse(Transaction $transaction): void
    {
        $end = microtime(true);

        $this->afterResponse->defer(function () use ($transaction, $end) {
            foreach ($this->measureSpans ?? [] as $span) {
                $span->finish($end);
            }

            $transaction->finish($end);
        });
    }

    /**
     * Traces one queued job: its own transaction in a worker, or a child span when a sync
     * job runs inside something already being traced (such as a request).
     *
     * @return array{0: Span, 1: Span|null} The job's span and the span it displaced.
     */
    public function startJob(string $name): array
    {
        /** @var HubInterface $hub */
        $hub = $this->container->make(HubInterface::class);
        $previous = $hub->getSpan();

        if ($previous !== null) {
            $context = new SpanContext();
            $context->setOp('queue.process');
            $context->setDescription($name);

            $span = $previous->startChild($context);
            $hub->setSpan($span);

            return [$span, $previous];
        }

        $context = new TransactionContext($name);
        $context->setOp('queue.process');
        $context->setSource(TransactionSource::task());

        return [$this->start($context), $previous];
    }

    /**
     * Workers have no response to wait for, so a job's transaction is sent straight away.
     *
     * @param array{0: Span, 1: Span|null} $job
     */
    public function finishJob(array $job, SpanStatus $status): void
    {
        [$span, $previous] = $job;

        $span->setStatus($status);
        $span->finish();

        $this->container->make(HubInterface::class)->setSpan($previous);
    }

    protected function startMeasures(Transaction $transaction): void
    {
        if ($this->measureSpans !== null) {
            return;
        }

        $this->measureSpans = [];

        foreach ($this->container->make('fof.sentry.measurements') as $measurement) {
            /** @var Measure $measure */
            $measure = new $measurement($transaction, $this->container);

            if ($span = $measure->handle()) {
                $this->measureSpans[] = $span;
            }
        }
    }
}
