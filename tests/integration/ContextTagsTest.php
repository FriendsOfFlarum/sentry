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
use Flarum\Extend;
use Flarum\Extend\ExtenderInterface;
use Flarum\Extension\Extension;
use Flarum\Testing\integration\TestCase;
use FoF\Sentry\Reporters\SentryReporter;
use FoF\Sentry\Tests\fixtures\RecordingTransport;
use FoF\Sentry\Tests\fixtures\ThrowingRequestHandler;
use Illuminate\Contracts\Container\Container;
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

    #[Test]
    public function errors_in_a_web_request_are_tagged_with_http_and_the_frontend(): void
    {
        // Performance monitoring is off: these tags must not depend on tracing.
        $this->extend((new Extend\Routes('api'))->get('/sentry-test/fail', 'sentry-test.fail', ThrowingRequestHandler::class));
        $this->recordEvents();

        $this->send($this->request('GET', '/api/sentry-test/fail'));

        $tags = RecordingTransport::errors()[0]->getTags();

        $this->assertSame('http', $tags['stack'] ?? null);
        $this->assertSame('api', $tags['frontend'] ?? null);
    }

    #[Test]
    public function an_explicit_stack_binding_wins_over_the_detected_one(): void
    {
        $this->extend(new class() implements ExtenderInterface {
            public function extend(Container $container, ?Extension $extension = null): void
            {
                $container->instance('sentry.stack', 'octane');
            }
        });
        $this->extend((new Extend\Routes('api'))->get('/sentry-test/fail', 'sentry-test.fail', ThrowingRequestHandler::class));
        $this->recordEvents();

        $this->send($this->request('GET', '/api/sentry-test/fail'));

        $this->assertSame('octane', RecordingTransport::errors()[0]->getTags()['stack'] ?? null);
    }
}
