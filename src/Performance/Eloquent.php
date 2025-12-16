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
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;

class Eloquent extends Measure
{
    /** @var array Track query patterns for N+1 detection */
    protected static $queryPatterns = [];

    /** @var int Track total query count */
    protected static $queryCount = 0;

    /** @var float Track total query time in milliseconds */
    protected static $totalQueryTime = 0;

    /** @var SettingsRepositoryInterface */
    protected $settings;

    public function handle(): ?Span
    {
        /** @var Dispatcher $events */
        $events = $this->container->make(Dispatcher::class);

        $this->settings = $this->container->make(SettingsRepositoryInterface::class);

        $span = $this->transaction->startChild(new SpanContext());
        $span->setOp('eloquent');

        // Get configuration settings
        $slowQueryThreshold = (int) $this->settings->get('fof-sentry.db.slow_query_threshold', 1000);
        $enableNPlusOneDetection = (bool) $this->settings->get('fof-sentry.db.n_plus_one_detection', true);
        $nPlusOneThreshold = (int) $this->settings->get('fof-sentry.db.n_plus_one_threshold', 10);
        $trackQueryBindings = (bool) $this->settings->get('fof-sentry.db.track_bindings', false);
        $querySampleRate = (int) $this->settings->get('fof-sentry.db.query_sample_rate', 100);

        $events->listen(QueryExecuted::class, function (QueryExecuted $event) use (
            $span,
            $slowQueryThreshold,
            $enableNPlusOneDetection,
            $nPlusOneThreshold,
            $trackQueryBindings,
            $querySampleRate
        ) {
            // Update aggregation stats
            static::$queryCount++;
            static::$totalQueryTime += $event->time;

            // Sample queries based on sample rate (0-100%)
            $shouldTrack = $querySampleRate >= 100 ||
                           $event->time >= $slowQueryThreshold ||
                           mt_rand(1, 100) <= $querySampleRate;

            if (!$shouldTrack) {
                return;
            }

            $end = microtime(true);
            $time = microtime(true) - ($event->time / 1000);

            $spanContext = new SpanContext();
            $spanContext->setOp('eloquent.query');
            $spanContext->setDescription($event->sql);

            // Base data
            $data = [
                'connection' => $event->connectionName,
                'duration_ms' => $event->time,
            ];

            // Collect tags for this span
            $tags = [];

            // Add query bindings if enabled
            if ($trackQueryBindings && !empty($event->bindings)) {
                $data['bindings'] = $this->sanitizeBindings($event->bindings);
            }

            // Detect slow queries
            if ($event->time >= $slowQueryThreshold) {
                $tags['slow_query'] = 'true';

                // Categorize severity
                if ($event->time >= $slowQueryThreshold * 5) {
                    $tags['severity'] = 'critical';
                } elseif ($event->time >= $slowQueryThreshold * 2) {
                    $tags['severity'] = 'high';
                } else {
                    $tags['severity'] = 'medium';
                }
            }

            // Detect N+1 queries
            if ($enableNPlusOneDetection) {
                $pattern = $this->normalizeQueryPattern($event->sql);

                if (!isset(static::$queryPatterns[$pattern])) {
                    static::$queryPatterns[$pattern] = 0;
                }
                static::$queryPatterns[$pattern]++;

                if (static::$queryPatterns[$pattern] >= $nPlusOneThreshold) {
                    $tags['n_plus_one_candidate'] = 'true';
                    $data['execution_count'] = static::$queryPatterns[$pattern];

                    // Only set severity on the threshold hit (not every subsequent query)
                    if (static::$queryPatterns[$pattern] === $nPlusOneThreshold) {
                        $tags['severity'] = 'warning';
                    }
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
            $spanContext->setSampled(true);

            $span->startChild($spanContext);
        });

        return $span;
    }

    /**
     * Normalize query pattern by replacing values with placeholders for N+1 detection
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
     * Determine the type of SQL query
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
     * Extract table names from SQL query
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
     * Sanitize query bindings to prevent sensitive data leakage
     */
    protected function sanitizeBindings(array $bindings): array
    {
        return array_map(function ($binding) {
            // Truncate long strings
            if (is_string($binding) && strlen($binding) > 100) {
                return substr($binding, 0, 97) . '...';
            }

            // Mask potential passwords/tokens
            if (is_string($binding) && preg_match('/^[a-f0-9]{32,}$/i', $binding)) {
                return '[HASH]';
            }

            return $binding;
        }, $bindings);
    }

    /**
     * Get aggregation statistics and attach to transaction
     */
    public function __destruct()
    {
        if (static::$queryCount > 0) {
            $aggregateData = [
                'total_queries' => static::$queryCount,
                'total_query_time_ms' => round(static::$totalQueryTime, 2),
                'avg_query_time_ms' => round(static::$totalQueryTime / static::$queryCount, 2),
            ];

            // Add N+1 summary
            if (!empty(static::$queryPatterns)) {
                $duplicatePatterns = array_filter(static::$queryPatterns, function ($count) {
                    return $count > 5;
                });

                if (!empty($duplicatePatterns)) {
                    arsort($duplicatePatterns);
                    $aggregateData['duplicate_query_patterns'] = count($duplicatePatterns);
                    $aggregateData['top_duplicate_count'] = reset($duplicatePatterns);
                }
            }

            $this->transaction->setData($aggregateData);
        }
    }
}
