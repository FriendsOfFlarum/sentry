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

use Flarum\Extend\ExtenderInterface;
use Flarum\Extension\Extension;
use Flarum\Testing\integration\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Container\Container;
use Laminas\Diactoros\Stream;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The browser SDK's tunnel: envelopes posted to the forum are forwarded to the
 * configured DSN, and only to it.
 */
class TunnelTest extends TestCase
{
    private const DSN = 'https://public@example.ingest.sentry.io/1';

    /** @var array<int, array{request: \Psr\Http\Message\RequestInterface}> */
    private static array $forwarded = [];

    protected function setUp(): void
    {
        parent::setUp();

        self::$forwarded = [];

        $this->extension('fof-sentry');

        $this->setting('fof-sentry.dsn', self::DSN);
        $this->setting('fof-sentry.javascript', 1);
        $this->setting('fof-sentry.javascript.tunnel', 1);

        // Nothing leaves the test: the forwarding client answers from a queue and records what it sent.
        $this->extend(new class() implements ExtenderInterface {
            public function extend(Container $container, ?Extension $extension = null): void
            {
                $stack = HandlerStack::create(new MockHandler([new Response(200), new Response(200)]));
                $stack->push(Middleware::history(TunnelTest::history()));
                $container->instance(Client::class, new Client(['handler' => $stack]));
            }
        });
    }

    /** @return array<int, mixed> */
    public static function &history(): array
    {
        return self::$forwarded;
    }

    private function envelope(string $dsn, string $extra = ''): string
    {
        return json_encode(['event_id' => str_repeat('a', 32), 'dsn' => $dsn])."\n"
            .json_encode(['type' => 'event'])."\n"
            .json_encode(['message' => 'from the browser'.$extra])."\n";
    }

    private function post(string $body): ServerRequestInterface
    {
        $stream = new Stream('php://temp', 'w+');
        $stream->write($body);
        $stream->rewind();

        return $this->request('POST', '/sentry/tunnel')
            ->withHeader('Content-Type', 'text/plain;charset=UTF-8')
            ->withBody($stream);
    }

    #[Test]
    public function an_envelope_for_the_configured_dsn_is_forwarded_to_its_envelope_endpoint(): void
    {
        $body = $this->envelope(self::DSN);

        $response = $this->send($this->post($body));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, self::$forwarded);

        $forwarded = self::$forwarded[0]['request'];
        $this->assertSame('POST', $forwarded->getMethod());
        $this->assertSame('https://example.ingest.sentry.io/api/1/envelope/', (string) $forwarded->getUri());
        $this->assertSame($body, (string) $forwarded->getBody());
        $this->assertSame('application/x-sentry-envelope', $forwarded->getHeaderLine('Content-Type'));
        // The visitor's address, not the forum server's.
        $this->assertNotSame('', $forwarded->getHeaderLine('X-Forwarded-For'));
    }

    #[Test]
    public function an_envelope_naming_another_dsn_is_refused_and_not_forwarded(): void
    {
        foreach ([
            'https://public@elsewhere.example.com/1',     // another host
            'https://public@example.ingest.sentry.io/2',  // another project
            'https://other@example.ingest.sentry.io/1',   // another key
        ] as $dsn) {
            $this->assertSame(400, $this->send($this->post($this->envelope($dsn)))->getStatusCode(), $dsn);
        }

        $this->assertCount(0, self::$forwarded);
    }

    #[Test]
    public function a_body_that_is_not_an_envelope_is_refused(): void
    {
        $this->assertSame(400, $this->send($this->post('not an envelope'))->getStatusCode());
        $this->assertCount(0, self::$forwarded);
    }

    #[Test]
    public function an_oversized_envelope_is_refused(): void
    {
        $body = $this->envelope(self::DSN, str_repeat('x', 1048576));

        $this->assertSame(413, $this->send($this->post($body))->getStatusCode());
        $this->assertCount(0, self::$forwarded);
    }

    #[Test]
    public function the_route_is_not_there_while_the_tunnel_is_off(): void
    {
        $this->setting('fof-sentry.javascript.tunnel', 0);

        $this->assertSame(404, $this->send($this->post($this->envelope(self::DSN)))->getStatusCode());
        $this->assertCount(0, self::$forwarded);
    }

    #[Test]
    public function the_route_is_not_there_while_browser_reporting_is_off(): void
    {
        $this->setting('fof-sentry.javascript', 0);

        $this->assertSame(404, $this->send($this->post($this->envelope(self::DSN)))->getStatusCode());
    }

    #[Test]
    public function the_forum_payload_points_the_browser_at_the_tunnel(): void
    {
        $html = (string) $this->send($this->request('GET', '/'))->getBody();

        preg_match('/<script id="flarum-json-payload"[^>]*>(.*?)<\/script>/is', $html, $matches);
        $payload = json_decode(html_entity_decode($matches[1] ?? '', ENT_QUOTES | ENT_HTML5), true);

        $this->assertStringEndsWith('/sentry/tunnel', $payload['fof-sentry']['tunnel'] ?? '');
    }

    #[Test]
    public function the_forum_payload_has_no_tunnel_while_it_is_off(): void
    {
        $this->setting('fof-sentry.javascript.tunnel', 0);

        $html = (string) $this->send($this->request('GET', '/'))->getBody();

        preg_match('/<script id="flarum-json-payload"[^>]*>(.*?)<\/script>/is', $html, $matches);
        $payload = json_decode(html_entity_decode($matches[1] ?? '', ENT_QUOTES | ENT_HTML5), true);

        $this->assertArrayNotHasKey('tunnel', $payload['fof-sentry'] ?? []);
    }
}
