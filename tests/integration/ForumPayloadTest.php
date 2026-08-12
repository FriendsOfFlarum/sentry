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

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * SentryJavaScript puts the browser client's configuration into the forum
 * payload and emits an inline bootstrap script. The payload is the contract the
 * forum JS reads from, so it is asserted directly against the rendered HTML.
 */
class ForumPayloadTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-sentry');

        $this->prepareDatabase([
            'users' => [$this->normalUser()],
        ]);
    }

    private function forumHtml(): string
    {
        $response = $this->send($this->request('GET', '/'));

        $this->assertSame(200, $response->getStatusCode());

        return (string) $response->getBody();
    }

    /**
     * Pull the `fof-sentry` entry out of the serialised forum payload.
     *
     * @return array<string, mixed>|null
     */
    private function payload(string $html): ?array
    {
        if (preg_match('/<script id="flarum-json-payload"[^>]*>(.*?)<\/script>/is', $html, $matches) !== 1) {
            return null;
        }

        $decoded = json_decode(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5), true);

        return $decoded['fof-sentry'] ?? null;
    }

    #[Test]
    public function no_sentry_payload_or_bootstrap_is_emitted_when_javascript_is_disabled(): void
    {
        $this->setting('fof-sentry.dsn', 'https://public@example.ingest.sentry.io/1');
        $this->setting('fof-sentry.javascript', 0);

        $html = $this->forumHtml();

        $this->assertNull($this->payload($html));
        $this->assertStringNotContainsString('Sentry.createClient', $html);
    }

    #[Test]
    public function the_dsn_and_environment_reach_the_forum_payload(): void
    {
        $this->setting('fof-sentry.dsn', 'https://public@example.ingest.sentry.io/1');
        $this->setting('fof-sentry.javascript', 1);
        $this->setting('fof-sentry.environment', 'staging');

        $payload = $this->payload($this->forumHtml());

        $this->assertNotNull($payload);
        $this->assertSame('https://public@example.ingest.sentry.io/1', $payload['dsn']);
        $this->assertSame('staging', $payload['environment']);
    }

    #[Test]
    public function the_backend_dsn_is_not_leaked_to_the_browser(): void
    {
        // dsn_backend exists so the server can report to a separate project;
        // it must never be handed to the browser client.
        $this->setting('fof-sentry.dsn', 'https://public@example.ingest.sentry.io/1');
        $this->setting('fof-sentry.dsn_backend', 'https://secret@example.ingest.sentry.io/2');
        $this->setting('fof-sentry.javascript', 1);

        $html = $this->forumHtml();

        $this->assertStringNotContainsString('secret@example.ingest.sentry.io', $html);
    }

    #[Test]
    public function javascript_sample_rates_reach_the_payload_as_fractions(): void
    {
        $this->setting('fof-sentry.dsn', 'https://public@example.ingest.sentry.io/1');
        $this->setting('fof-sentry.javascript', 1);
        $this->setting('fof-sentry.javascript.trace_sample_rate', 50);
        $this->setting('fof-sentry.javascript.replays_session_sample_rate', 10);
        $this->setting('fof-sentry.javascript.replays_error_sample_rate', 100);

        $payload = $this->payload($this->forumHtml());

        // Compared loosely: a whole fraction such as 1.0 serialises to JSON as
        // `1` and decodes back as an int.
        $this->assertEquals(0.5, $payload['tracesSampleRate']);
        $this->assertEquals(0.1, $payload['replaysSessionSampleRate']);
        $this->assertEquals(1.0, $payload['replaysOnErrorSampleRate']);
    }

    #[Test]
    public function scrub_emails_is_enabled_unless_email_reporting_is_turned_on(): void
    {
        $this->setting('fof-sentry.dsn', 'https://public@example.ingest.sentry.io/1');
        $this->setting('fof-sentry.javascript', 1);
        $this->setting('fof-sentry.send_emails_with_sentry_reports', 0);

        $payload = $this->payload($this->forumHtml());

        $this->assertTrue($payload['scrubEmails']);
    }

    #[Test]
    public function scrub_emails_is_disabled_when_email_reporting_is_turned_on(): void
    {
        $this->setting('fof-sentry.dsn', 'https://public@example.ingest.sentry.io/1');
        $this->setting('fof-sentry.javascript', 1);
        $this->setting('fof-sentry.send_emails_with_sentry_reports', 1);

        $payload = $this->payload($this->forumHtml());

        $this->assertFalse($payload['scrubEmails']);
    }

    #[Test]
    public function the_flarum_version_is_tagged(): void
    {
        $this->setting('fof-sentry.dsn', 'https://public@example.ingest.sentry.io/1');
        $this->setting('fof-sentry.javascript', 1);

        $payload = $this->payload($this->forumHtml());

        $this->assertSame(\Flarum\Foundation\Application::VERSION, $payload['tags']['flarum']);
    }

    #[Test]
    public function debug_and_offline_tags_are_serialised_as_strings(): void
    {
        // Sentry tag values must be strings; booleans would be coerced
        // inconsistently between the PHP and JS clients.
        $this->setting('fof-sentry.dsn', 'https://public@example.ingest.sentry.io/1');
        $this->setting('fof-sentry.javascript', 1);

        $payload = $this->payload($this->forumHtml());

        $this->assertContains($payload['tags']['debug'], ['true', 'false']);
        $this->assertContains($payload['tags']['offline'], ['true', 'false']);
    }

    #[Test]
    public function the_bootstrap_script_is_emitted_when_javascript_is_enabled(): void
    {
        $this->setting('fof-sentry.dsn', 'https://public@example.ingest.sentry.io/1');
        $this->setting('fof-sentry.javascript', 1);

        $html = $this->forumHtml();

        $this->assertStringContainsString('Sentry.createClient', $html);
        $this->assertStringContainsString('client.init()', $html);
    }

    #[Test]
    public function the_scrub_emails_flag_is_exposed_for_the_forum_js(): void
    {
        // forum/index.js reads this separate key when building user data.
        $this->setting('fof-sentry.dsn', 'https://public@example.ingest.sentry.io/1');
        $this->setting('fof-sentry.javascript', 1);
        $this->setting('fof-sentry.send_emails_with_sentry_reports', 0);

        $html = $this->forumHtml();

        $this->assertStringContainsString('fof-sentry.scrub-emails', $html);
    }
}
