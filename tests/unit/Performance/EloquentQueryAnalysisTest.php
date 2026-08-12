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

use FoF\Sentry\Performance\Eloquent;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;

/**
 * The Eloquent measure decorates query spans with a query type, the tables
 * touched, a normalised pattern used for N+1 detection, and sanitised bindings.
 * That analysis is pure string work, so it is tested directly rather than
 * through a live transaction.
 */
class EloquentQueryAnalysisTest extends TestCase
{
    private Eloquent $measure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->measure = new Eloquent(
            new Transaction(new TransactionContext()),
            new Container()
        );
    }

    /**
     * The analysis helpers are protected implementation detail; reach them
     * directly so the behaviour can be pinned without standing up a hub.
     */
    private function call(string $method, mixed ...$args): mixed
    {
        $reflection = new ReflectionMethod(Eloquent::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($this->measure, ...$args);
    }

    #[Test]
    #[DataProvider('queryTypeProvider')]
    public function query_type_is_classified_from_the_leading_keyword(string $sql, string $expected): void
    {
        $this->assertSame($expected, $this->call('getQueryType', $sql));
    }

    public static function queryTypeProvider(): array
    {
        return [
            'select'          => ['select * from `users`', 'select'],
            'insert'          => ['insert into `users` (`id`) values (1)', 'insert'],
            'update'          => ['update `users` set `email` = ?', 'update'],
            'delete'          => ['delete from `users` where `id` = ?', 'delete'],
            'create is ddl'   => ['create table `users` (`id` int)', 'ddl'],
            'alter is ddl'    => ['alter table `users` add `bio` text', 'ddl'],
            'drop is ddl'     => ['drop table `users`', 'ddl'],
            'lowercase sql'   => ['SELECT 1', 'select'],
            'unknown is other'=> ['show tables', 'other'],
            'leading space'   => ['   select 1', 'select'],
        ];
    }

    #[Test]
    public function tables_are_extracted_from_from_join_into_and_update_clauses(): void
    {
        $tables = $this->call(
            'extractTables',
            'select * from `discussions` inner join `posts` on `posts`.`discussion_id` = `discussions`.`id`'
        );

        $this->assertSame(['discussions', 'posts'], $tables);
    }

    #[Test]
    public function extracted_tables_are_deduplicated_and_list_indexed(): void
    {
        // A self-join names the same table twice; the span data must not carry
        // duplicates, and must be a list so it serialises as a JSON array.
        $tables = $this->call(
            'extractTables',
            'select * from `users` inner join `users` as `u2` on `u2`.`id` = `users`.`id`'
        );

        $this->assertSame(['users'], $tables);
        $this->assertSame(array_keys($tables), range(0, count($tables) - 1));
    }

    #[Test]
    public function a_query_touching_no_tables_yields_an_empty_list(): void
    {
        $this->assertSame([], $this->call('extractTables', 'select 1'));
    }

    #[Test]
    public function differing_literals_normalise_to_the_same_n_plus_one_pattern(): void
    {
        // The whole point of normalisation: the classic N+1 shape is the same
        // query with a different id each time, and it must collapse to one key.
        $first = $this->call('normalizeQueryPattern', 'select * from `users` where `id` = 1');
        $second = $this->call('normalizeQueryPattern', 'select * from `users` where `id` = 99999');

        $this->assertSame($first, $second);
    }

    #[Test]
    public function quoted_strings_are_replaced_in_the_pattern(): void
    {
        $first = $this->call('normalizeQueryPattern', "select * from `users` where `email` = 'a@example.com'");
        $second = $this->call('normalizeQueryPattern', "select * from `users` where `email` = 'b@example.org'");

        $this->assertSame($first, $second);
        $this->assertStringNotContainsString('example.com', $first);
    }

    #[Test]
    public function in_clauses_of_differing_length_normalise_together(): void
    {
        $first = $this->call('normalizeQueryPattern', 'select * from `users` where `id` in (1, 2, 3)');
        $second = $this->call('normalizeQueryPattern', 'select * from `users` where `id` in (4, 5, 6, 7, 8)');

        $this->assertSame($first, $second);
    }

    #[Test]
    public function whitespace_differences_do_not_produce_distinct_patterns(): void
    {
        $first = $this->call('normalizeQueryPattern', "select *\n  from   `users`");
        $second = $this->call('normalizeQueryPattern', 'select * from `users`');

        $this->assertSame($first, $second);
    }

    #[Test]
    public function genuinely_different_queries_keep_distinct_patterns(): void
    {
        // Normalisation must not be so aggressive that unrelated queries merge
        // and get mislabelled as an N+1 candidate.
        $users = $this->call('normalizeQueryPattern', 'select * from `users` where `id` = 1');
        $posts = $this->call('normalizeQueryPattern', 'select * from `posts` where `id` = 1');

        $this->assertNotSame($users, $posts);
    }

    #[Test]
    public function long_string_bindings_are_truncated(): void
    {
        $bindings = $this->call('sanitizeBindings', [str_repeat('a', 250)]);

        $this->assertSame(100, strlen($bindings[0]));
        $this->assertStringEndsWith('...', $bindings[0]);
    }

    #[Test]
    public function hash_like_bindings_are_masked(): void
    {
        // Tokens and password hashes are the main leak risk when binding
        // tracking is switched on.
        $bindings = $this->call('sanitizeBindings', [str_repeat('a1b2c3d4', 8)]);

        $this->assertSame('[HASH]', $bindings[0]);
    }

    #[Test]
    public function short_and_non_string_bindings_pass_through_untouched(): void
    {
        $bindings = $this->call('sanitizeBindings', ['flarum', 42, null, true, 1.5]);

        $this->assertSame(['flarum', 42, null, true, 1.5], $bindings);
    }

    #[Test]
    public function binding_keys_are_preserved_when_sanitising(): void
    {
        $bindings = $this->call('sanitizeBindings', ['id' => 1, 'email' => 'a@example.com']);

        $this->assertSame(['id' => 1, 'email' => 'a@example.com'], $bindings);
    }
}
