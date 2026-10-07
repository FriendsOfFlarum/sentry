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

use Flarum\Extend;
use Flarum\Testing\integration\TestCase;
use FoF\Sentry\Tests\fixtures\RecordingTransport;
use FoF\Sentry\Tests\fixtures\ThrowingRequestHandler;
use FoF\Sentry\Tracing\AfterResponse;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sentry\Client;
use Sentry\State\HubInterface;

/**
 * With performance monitoring on, every HTTP request is traced as its own transaction.
 */
class RequestTracingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-sentry');

        $this->setting('fof-sentry.dsn', 'https://public@example.ingest.sentry.io/1');
        $this->setting('fof-sentry.monitor_performance', 100);

        RecordingTransport::$events = [];
    }

    private function sendTraced(ServerRequestInterface $request): ResponseInterface
    {
        $hub = $this->app()->getContainer()->make(HubInterface::class);
        $hub->bindClient(new Client($hub->getClient()->getOptions(), new RecordingTransport()));

        $response = $this->send($request);

        // Transactions are sent once the response is out, from a shutdown callback.
        $this->app()->getContainer()->make(AfterResponse::class)->run();

        return $response;
    }

    #[Test]
    public function a_request_is_traced_as_one_http_server_transaction(): void
    {
        $this->sendTraced($this->request('GET', '/api'));

        $transactions = RecordingTransport::transactions();

        $this->assertCount(1, $transactions);
        $this->assertSame('http.server', $transactions[0]->getContexts()['trace']['op'] ?? null);
    }

    #[Test]
    public function the_transaction_records_the_response_status(): void
    {
        $this->sendTraced($this->request('GET', '/api/discussions/999999'));

        $this->assertSame('not_found', RecordingTransport::transactions()[0]->getContexts()['trace']['status'] ?? null);
    }

    #[Test]
    public function the_transaction_is_tagged_with_its_frontend(): void
    {
        $this->sendTraced($this->request('GET', '/api'));

        $this->assertSame('api', RecordingTransport::transactions()[0]->getTags()['frontend'] ?? null);
    }

    #[Test]
    public function the_transaction_starts_when_php_received_the_request(): void
    {
        // Covers Flarum's bootstrap, which runs before any middleware.
        $receivedAt = microtime(true) - 2.5;

        $this->sendTraced(new ServerRequest(['REQUEST_TIME_FLOAT' => $receivedAt], [], '/api', 'GET'));

        $this->assertEqualsWithDelta($receivedAt, RecordingTransport::transactions()[0]->getStartTimestamp(), 0.001);
    }

    #[Test]
    public function internal_api_calls_do_not_start_their_own_transactions(): void
    {
        // Rendering the forum preloads its data through the internal API client.
        $this->sendTraced($this->request('GET', '/'));

        $transactions = RecordingTransport::transactions();

        $this->assertCount(1, $transactions);
        $this->assertSame('forum', $transactions[0]->getTags()['frontend'] ?? null);
    }

    #[Test]
    public function the_transaction_is_named_after_the_matched_route(): void
    {
        // Named by route rather than URL, so /api/discussions/1 and /api/discussions/2 group together.
        $this->sendTraced($this->request('GET', '/api/discussions/999999'));

        $this->assertSame('GET api.discussions.show', RecordingTransport::transactions()[0]->getTransaction());
    }

    #[Test]
    public function internal_api_calls_do_not_rename_the_page_transaction(): void
    {
        $this->sendTraced($this->request('GET', '/'));

        // `/` is served by Flarum's configurable default route, which is named "default".
        $this->assertSame('GET forum.default', RecordingTransport::transactions()[0]->getTransaction());
    }

    #[Test]
    public function errors_during_a_request_belong_to_its_transaction(): void
    {
        $this->extend((new Extend\Routes('api'))->get('/sentry-test/fail', 'sentry-test.fail', ThrowingRequestHandler::class));

        $this->sendTraced($this->request('GET', '/api/sentry-test/fail'));

        $this->assertSame(
            RecordingTransport::transactions()[0]->getContexts()['trace']['trace_id'],
            RecordingTransport::errors()[0]->getContexts()['trace']['trace_id'] ?? null
        );
    }
}
