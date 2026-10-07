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
     * Starts a transaction for one queued job and remembers the span it displaces.
     *
     * @return array{0: Transaction, 1: Span|null}
     */
    public function startJob(string $name): array
    {
        $previous = $this->container->make(HubInterface::class)->getSpan();

        $context = new TransactionContext($name);
        $context->setOp('queue.process');
        $context->setSource(TransactionSource::task());

        return [$this->start($context), $previous];
    }

    /**
     * Workers have no response to wait for, so a job's transaction is sent straight away.
     *
     * @param array{0: Transaction, 1: Span|null} $job
     */
    public function finishJob(array $job, SpanStatus $status): void
    {
        [$transaction, $previous] = $job;

        $transaction->setStatus($status);
        $transaction->finish();

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
