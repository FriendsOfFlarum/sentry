<?php

/*
 * This file is part of fof/sentry
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Sentry\Tests\unit\Performance;

use Flarum\Settings\SettingsRepositoryInterface;
use FoF\Sentry\Performance\Eloquent;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Events\Dispatcher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;

class EloquentNPlusOneCountingTest extends TestCase
{
    #[Test]
    public function queries_skipped_by_sampling_still_count_towards_n_plus_one_detection(): void
    {
        $container = new Container();
        $events = new Dispatcher($container);
        $container->instance(DispatcherContract::class, $events);
        $settings = $this->createStub(SettingsRepositoryInterface::class);
        $settings->method('get')->willReturnCallback(fn (string $key, mixed $default = null) => [
            'fof-sentry.db.query_sample_rate'      => 0,
            'fof-sentry.db.n_plus_one_threshold'   => 3,
            'fof-sentry.db.slow_query_threshold'   => 1000,
        ][$key] ?? $default);
        $container->instance(SettingsRepositoryInterface::class, $settings);

        // Queries are only analysed while a sampled transaction is the hub's current span.
        $transaction = new Transaction(new TransactionContext());
        $transaction->setSampled(true);
        $transaction->initSpanRecorder();
        $hub = new Hub();
        $hub->setSpan($transaction);
        $container->instance(HubInterface::class, $hub);

        (new Eloquent($transaction, $container))->handle();

        $connection = new Connection(fn () => null, '', '', ['driver' => 'mysql']);

        // The two fast queries are skipped by the 0% sample rate; the slow third one is always recorded.
        foreach ([[1, 1.0], [2, 1.0], [3, 2500.0]] as [$id, $milliseconds]) {
            $events->dispatch(new QueryExecuted("select * from users where id = $id", [], $milliseconds, $connection));
        }

        $querySpans = array_values(array_filter(
            $transaction->getSpanRecorder()->getSpans(),
            fn ($span) => $span->getOp() === 'db.sql.query'
        ));

        $this->assertCount(1, $querySpans);
        $this->assertSame(3, $querySpans[0]->getData()['execution_count'] ?? null);
        $this->assertSame('true', $querySpans[0]->getTags()['n_plus_one_candidate'] ?? null);
    }
}
