<?php

/*
 * This file is part of fof/sentry
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Sentry\Performance;

use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\QueryExecuted;
use Sentry\State\HubInterface;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\Transaction;
use WeakMap;

class Eloquent extends Measure
{
    /** A pattern repeated more than this many times counts towards `duplicate_query_patterns`. */
    protected const DUPLICATE_PATTERN_MIN = 5;

    /**
     * Per-transaction query statistics, so each request or job is measured on its own.
     *
     * @var WeakMap<Transaction, array{count: int, time: float, patterns: array<string, int>, duplicates: int, top: int}>
     */
    protected WeakMap $stats;

    /** @var SettingsRepositoryInterface */
    protected $settings;

    public function handle(): ?Span
    {
        $this->stats = new WeakMap();

        /** @var Dispatcher $events */
        $events = $this->container->make(Dispatcher::class);

        $this->settings = $this->container->make(SettingsRepositoryInterface::class);

        /** @var HubInterface $hub */
        $hub = $this->container->make(HubInterface::class);

        // Get configuration settings
        $slowQueryThreshold = (int) $this->settings->get('fof-sentry.db.slow_query_threshold', 1000);
        $enableNPlusOneDetection = (bool) $this->settings->get('fof-sentry.db.n_plus_one_detection', true);
        $nPlusOneThreshold = (int) $this->settings->get('fof-sentry.db.n_plus_one_threshold', 10);
        $trackQueryBindings = (bool) $this->settings->get('fof-sentry.db.track_bindings', false);
        $querySampleRate = (int) $this->settings->get('fof-sentry.db.query_sample_rate', 100);

        $events->listen(QueryExecuted::class, function (QueryExecuted $event) use (
            $hub,
            $slowQueryThreshold,
            $enableNPlusOneDetection,
            $nPlusOneThreshold,
            $trackQueryBindings,
            $querySampleRate
        ) {
            // Queries belong to whatever is being traced right now; outside a sampled transaction there is nothing to record.
            $parent = $hub->getSpan();
            $transaction = $parent?->getTransaction();

            if ($parent === null || $transaction === null || !$parent->getSampled()) {
                return;
            }

            $stats = $this->stats[$transaction] ?? ['count' => 0, 'time' => 0.0, 'patterns' => [], 'duplicates' => 0, 'top' => 0];

            $stats['count']++;
            $stats['time'] += $event->time;

            // Count every query, not just sampled ones, or N+1 detection undercounts below a 100% sample rate.
            $patternCount = 0;
            if ($enableNPlusOneDetection) {
                $pattern = $this->normalizeQueryPattern($event->sql);
                $patternCount = $stats['patterns'][$pattern] = ($stats['patterns'][$pattern] ?? 0) + 1;

                if ($patternCount === self::DUPLICATE_PATTERN_MIN + 1) {
                    $stats['duplicates']++;
                }

                if ($patternCount > self::DUPLICATE_PATTERN_MIN) {
                    $stats['top'] = max($stats['top'], $patternCount);
                }
            }

            $this->stats[$transaction] = $stats;
            $transaction->setData($this->aggregates($stats));

            $shouldTrack = $querySampleRate >= 100 ||
                           $event->time >= $slowQueryThreshold ||
                           mt_rand(1, 100) <= $querySampleRate;

            if (!$shouldTrack) {
                return;
            }

            $end = microtime(true);
            $time = $end - ($event->time / 1000);

            $spanContext = new SpanContext();
            // The op Sentry's Queries insights and N+1 detection look for.
            $spanContext->setOp('db.sql.query');
            $spanContext->setDescription($event->sql);

            $data = [
                'db.system'   => $this->dbSystem($event->connection->getDriverName()),
                'connection'  => $event->connectionName,
                'duration_ms' => $event->time,
            ];

            $tags = [];

            if ($trackQueryBindings && !empty($event->bindings)) {
                $data['bindings'] = $this->sanitizeBindings($event->bindings);
            }

            if ($event->time >= $slowQueryThreshold) {
                $tags['slow_query'] = 'true';

                if ($event->time >= $slowQueryThreshold * 5) {
                    $tags['severity'] = 'critical';
                } elseif ($event->time >= $slowQueryThreshold * 2) {
                    $tags['severity'] = 'high';
                } else {
                    $tags['severity'] = 'medium';
                }
            }

            if ($enableNPlusOneDetection && $patternCount >= $nPlusOneThreshold) {
                $tags['n_plus_one_candidate'] = 'true';
                $data['execution_count'] = $patternCount;

                // Only set severity on the threshold hit (not every subsequent query)
                if ($patternCount === $nPlusOneThreshold) {
                    $tags['severity'] = 'warning';
                }
            }

            // Classify query type
            $queryType = $this->getQueryType($event->sql);
            $tags['query_type'] = $queryType;

            // Extract and tag tables
            $tables = $this->extractTables($event->sql);
            if (!empty($tables)) {
                $data['tables'] = $tables;
                $tags['table'] = $tables[0]; // Primary table
            }

            // Set tags and data on span context
            $spanContext->setTags($tags);
            $spanContext->setData($data);
            $spanContext->setStartTimestamp($time);
            $spanContext->setEndTimestamp($end);

            $parent->startChild($spanContext);
        });

        return null;
    }

    /**
     * Sentry's `db.system` names differ from Laravel's driver names for Postgres.
     */
    protected function dbSystem(string $driver): string
    {
        return $driver === 'pgsql' ? 'postgresql' : $driver;
    }

    /**
     * Normalize query pattern by replacing values with placeholders for N+1 detection.
     */
    protected function normalizeQueryPattern(string $sql): string
    {
        // Replace quoted strings
        $pattern = preg_replace("/'[^']*'/", '?', $sql);
        // Replace numbers
        $pattern = preg_replace('/\b\d+\b/', '?', $pattern);
        // Replace IN clauses with variable length
        $pattern = preg_replace('/in\s*\([^)]+\)/i', 'in (?)', $pattern);
        // Normalize whitespace
        $pattern = preg_replace('/\s+/', ' ', $pattern);

        return trim($pattern);
    }

    /**
     * Determine the type of SQL query.
     */
    protected function getQueryType(string $sql): string
    {
        $sql = trim(strtoupper($sql));

        if (strpos($sql, 'SELECT') === 0) {
            return 'select';
        } elseif (strpos($sql, 'INSERT') === 0) {
            return 'insert';
        } elseif (strpos($sql, 'UPDATE') === 0) {
            return 'update';
        } elseif (strpos($sql, 'DELETE') === 0) {
            return 'delete';
        } elseif (strpos($sql, 'CREATE') === 0 || strpos($sql, 'ALTER') === 0 || strpos($sql, 'DROP') === 0) {
            return 'ddl';
        }

        return 'other';
    }

    /**
     * Extract table names from SQL query.
     */
    protected function extractTables(string $sql): array
    {
        $tables = [];

        // Match FROM, JOIN, INTO, UPDATE clauses
        if (preg_match_all('/(?:FROM|JOIN|INTO|UPDATE)\s+`?(\w+)`?/i', $sql, $matches)) {
            $tables = array_unique($matches[1]);
        }

        return array_values($tables);
    }

    /**
     * Sanitize query bindings to prevent sensitive data leakage.
     */
    protected function sanitizeBindings(array $bindings): array
    {
        return array_map(function ($binding) {
            // Truncate long strings
            if (is_string($binding) && strlen($binding) > 100) {
                return substr($binding, 0, 97).'...';
            }

            // Mask potential passwords/tokens
            if (is_string($binding) && preg_match('/^[a-f0-9]{32,}$/i', $binding)) {
                return '[HASH]';
            }

            return $binding;
        }, $bindings);
    }

    /**
     * @param array{count: int, time: float, patterns: array<string, int>, duplicates: int, top: int} $stats
     *
     * @return array<string, int|float>
     */
    protected function aggregates(array $stats): array
    {
        $aggregates = [
            'total_queries'       => $stats['count'],
            'total_query_time_ms' => round($stats['time'], 2),
            'avg_query_time_ms'   => round($stats['time'] / $stats['count'], 2),
        ];

        if ($stats['duplicates'] > 0) {
            $aggregates['duplicate_query_patterns'] = $stats['duplicates'];
            $aggregates['top_duplicate_count'] = $stats['top'];
        }

        return $aggregates;
    }
}
