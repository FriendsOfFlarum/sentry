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
use ReflectionProperty;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;

class EloquentNPlusOneCountingTest extends TestCase
{
    #[Test]
    public function repeated_queries_are_counted_even_when_not_sampled_for_a_span(): void
    {
        $container = new Container();
        $events = new Dispatcher($container);
        $container->instance(DispatcherContract::class, $events);
        $settings = $this->createStub(SettingsRepositoryInterface::class);
        $settings->method('get')->willReturnCallback(fn (string $key, mixed $default = null) => [
            'fof-sentry.db.query_sample_rate' => 0,
        ][$key] ?? $default);
        $container->instance(SettingsRepositoryInterface::class, $settings);

        $patterns = new ReflectionProperty(Eloquent::class, 'queryPatterns');
        $patterns->setValue(null, []);

        (new Eloquent(new Transaction(new TransactionContext()), $container))->handle();

        $connection = new Connection(fn () => null);

        foreach ([1, 2, 3] as $id) {
            $events->dispatch(new QueryExecuted("select * from users where id = $id", [], 1.0, $connection));
        }

        $this->assertSame(['select * from users where id = ?' => 3], $patterns->getValue());
    }
}
