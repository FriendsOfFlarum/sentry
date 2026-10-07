<?php

/*
 * This file is part of fof/sentry
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Sentry\Tests\integration;

use Flarum\Testing\integration\TestCase;
use FoF\Sentry\Tests\fixtures\FailingJob;
use FoF\Sentry\Tests\fixtures\RecordingTransport;
use FoF\Sentry\Tests\fixtures\TracedJob;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Sentry\Client;
use Sentry\State\HubInterface;

/**
 * Queue workers are long-running, so each job is traced on its own rather than as part of the process.
 */
class QueueTracingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-sentry');

        $this->setting('fof-sentry.dsn', 'https://public@example.ingest.sentry.io/1');
        $this->setting('fof-sentry.monitor_performance', 100);

        RecordingTransport::$events = [];
    }

    private function runJob(object $job): void
    {
        $hub = $this->app()->getContainer()->make(HubInterface::class);
        $hub->bindClient(new Client($hub->getClient()->getOptions(), new RecordingTransport()));

        // The test environment uses the sync driver, so the job runs right here.
        $this->app()->getContainer()->make('flarum.queue.connection')->push($job);
    }

    #[Test]
    public function a_job_is_traced_as_its_own_transaction(): void
    {
        $this->runJob(new TracedJob());

        $transactions = RecordingTransport::transactions();

        $this->assertCount(1, $transactions);
        $this->assertSame('queue.process', $transactions[0]->getContexts()['trace']['op'] ?? null);
        $this->assertSame(TracedJob::class, $transactions[0]->getTransaction());
    }

    #[Test]
    public function consecutive_jobs_are_separate_transactions(): void
    {
        // A worker runs job after job in one process; the second must not nest under the first.
        $this->runJob(new TracedJob());
        $this->runJob(new TracedJob());

        $this->assertCount(2, RecordingTransport::transactions());
    }

    #[Test]
    public function a_failing_job_is_marked_as_failed(): void
    {
        try {
            $this->runJob(new FailingJob());
        } catch (RuntimeException) {
            // The sync driver rethrows; the transaction is what's under test.
        }

        $this->assertSame('internal_error', RecordingTransport::transactions()[0]->getContexts()['trace']['status'] ?? null);
    }
}
