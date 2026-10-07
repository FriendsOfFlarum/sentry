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

use Exception;
use Flarum\Testing\integration\TestCase;
use FoF\Sentry\Reporters\SentryReporter;
use FoF\Sentry\Tests\fixtures\RecordingTransport;
use PHPUnit\Framework\Attributes\Test;
use Sentry\Client;
use Sentry\State\HubInterface;

/**
 * Tags that say where an error happened: on the command line (console, scheduler, queue workers) or in a web request.
 */
class ContextTagsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-sentry');

        $this->setting('fof-sentry.dsn', 'https://public@example.ingest.sentry.io/1');

        RecordingTransport::$events = [];
    }

    private function recordEvents(): void
    {
        $hub = $this->app()->getContainer()->make(HubInterface::class);
        $hub->bindClient(new Client($hub->getClient()->getOptions(), new RecordingTransport()));
    }

    #[Test]
    public function errors_outside_a_web_request_are_tagged_as_cli(): void
    {
        // PHPUnit runs on the CLI SAPI, like console commands and queue workers.
        $this->recordEvents();

        $this->app()->getContainer()->make(SentryReporter::class)->report(new Exception('from a worker'));

        $this->assertSame('cli', RecordingTransport::errors()[0]->getTags()['stack'] ?? null);
    }
}
