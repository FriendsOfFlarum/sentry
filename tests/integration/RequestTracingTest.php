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
use FoF\Sentry\Tests\fixtures\RecordingTransport;
use FoF\Sentry\Tracing\AfterResponse;
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
}
