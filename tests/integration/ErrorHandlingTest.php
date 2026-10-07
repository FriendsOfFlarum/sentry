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
use Flarum\Foundation\ErrorHandling\HandledError;
use Flarum\Foundation\ErrorHandling\ViewFormatter;
use Flarum\Http\RequestUtil;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\Guest;
use Flarum\User\User;
use FoF\Sentry\Reporters\SentryReporter;
use FoF\Sentry\SentryServiceProvider;
use FoF\Sentry\Tests\fixtures\MarkingViewFormatter;
use FoF\Sentry\Tests\fixtures\ReplacesViewFormatter;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Sentry\Event;
use Sentry\State\HubInterface;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;

/**
 * End-to-end behaviour of the error path: reporter → hub → error page formatter.
 */
class ErrorHandlingTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    /** @var array<int, Event> */
    private static array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-sentry');

        $this->prepareDatabase([
            'users' => [$this->normalUser()],
        ]);

        self::$sent = [];
    }

    public static function record(Event $event): void
    {
        self::$sent[] = $event;
    }

    private function container()
    {
        return $this->app()->getContainer();
    }

    private function recordingHub(): HubInterface
    {
        $hub = $this->container()->make(HubInterface::class);

        $transport = new class() implements TransportInterface {
            public function send(Event $event): Result
            {
                ErrorHandlingTest::record($event);

                return new Result(ResultStatus::success(), $event);
            }

            public function close(?int $timeout = null): Result
            {
                return new Result(ResultStatus::success());
            }
        };

        $hub->bindClient(new \Sentry\Client($hub->getClient()->getOptions(), $transport));

        return $hub;
    }

    private function requestAs(?int $actorId): ServerRequest
    {
        $actor = $actorId === null ? new Guest() : User::query()->find($actorId);

        return RequestUtil::withActor(new ServerRequest([], [], '/', 'GET'), $actor);
    }

    private function formatReported(Exception $e, ServerRequest $request): string
    {
        $this->container()->make(SentryReporter::class)->report($e);

        $response = $this->container()->make(ViewFormatter::class)
            ->format(new HandledError($e, 'unknown', 500, true), $request);

        return (string) $response->getBody();
    }

    #[Test]
    public function reported_events_carry_the_flarum_debug_and_offline_tags(): void
    {
        $this->setting('fof-sentry.dsn', 'https://public@example.ingest.sentry.io/1');
        $this->recordingHub();

        $this->container()->make(SentryReporter::class)->report(new Exception('boom'));

        $tags = self::$sent[0]->getTags();

        $this->assertArrayHasKey('flarum', $tags);
        $this->assertContains($tags['debug'] ?? null, ['true', 'false']);
        $this->assertContains($tags['offline'] ?? null, ['true', 'false']);
    }

    #[Test]
    public function nothing_is_logged_when_no_dsn_is_configured(): void
    {
        $logger = new class() extends AbstractLogger {
            /** @var array<int, string> */
            public array $messages = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->messages[] = (string) $message;
            }
        };

        $this->container()->instance(LoggerInterface::class, $logger);

        $this->container()->make(SentryReporter::class)->report(new Exception('boom'));

        $this->assertSame([], $logger->messages);
    }

    #[Test]
    public function the_error_page_renders_when_the_middleware_never_bound_a_request(): void
    {
        // Errors thrown by core middleware ahead of HandleErrorsWithSentry leave `sentry.request` unbound.
        $this->setting('fof-sentry.dsn', 'https://public@example.ingest.sentry.io/1');
        $this->setting('fof-sentry.user_feedback', true);
        $this->recordingHub();

        $this->assertFalse($this->container()->bound('sentry.request'));

        $body = $this->formatReported(new Exception('boom'), $this->requestAs(2));

        $this->assertStringContainsString('showReportDialog', $body);
    }

    #[Test]
    public function the_feedback_dialog_uses_the_shared_dsn_when_no_backend_dsn_is_set(): void
    {
        $this->setting('fof-sentry.dsn', 'https://public@example.ingest.sentry.io/1');
        $this->setting('fof-sentry.user_feedback', true);
        $this->recordingHub();

        $body = $this->formatReported(new Exception('boom'), $this->requestAs(null));

        $this->assertStringContainsString('showReportDialog', $body);
        $this->assertStringContainsString('public@example.ingest.sentry.io', $body);
    }

    #[Test]
    public function the_feedback_dialog_uses_the_public_dsn_when_the_backend_reports_through_a_relay(): void
    {
        $this->setting('fof-sentry.dsn', 'https://public@example.ingest.sentry.io/1');
        $this->setting('fof-sentry.dsn_backend', 'http://public@relay.internal:3000/1');
        $this->setting('fof-sentry.user_feedback', true);
        $hub = $this->recordingHub();

        $body = $this->formatReported(new Exception('boom'), $this->requestAs(null));

        // The event itself went through the Relay...
        $this->assertSame('relay.internal', $hub->getClient()->getOptions()->getDsn()->getHost());
        // ...but the browser is only ever pointed at the public DSN.
        $this->assertStringContainsString('public@example.ingest.sentry.io', $body);
        $this->assertStringNotContainsString('relay.internal', $body);
    }

    #[Test]
    public function no_feedback_dialog_is_added_without_a_public_dsn(): void
    {
        $this->setting('fof-sentry.dsn_backend', 'http://public@relay.internal:3000/1');
        $this->setting('fof-sentry.user_feedback', true);
        $this->recordingHub();

        $body = $this->formatReported(new Exception('boom'), $this->requestAs(null));

        $this->assertCount(1, self::$sent);
        $this->assertStringNotContainsString('showReportDialog', $body);
        $this->assertStringNotContainsString('relay.internal', $body);
    }

    #[Test]
    public function no_feedback_dialog_is_added_when_feedback_is_off(): void
    {
        $this->setting('fof-sentry.dsn', 'https://public@example.ingest.sentry.io/1');
        $this->recordingHub();

        $body = $this->formatReported(new Exception('boom'), $this->requestAs(2));

        $this->assertStringNotContainsString('showReportDialog', $body);
    }

    #[Test]
    public function the_extension_does_not_install_its_own_php_error_handler(): void
    {
        $this->setting('fof-sentry.dsn', 'https://public@example.ingest.sentry.io/1');
        $this->app();

        $current = set_error_handler(fn () => false);
        restore_error_handler();

        $this->assertFalse(
            is_array($current) && $current[0] instanceof SentryServiceProvider,
            'The SDK registers its own error listener; a second handler double-reports every warning.'
        );
    }

    #[Test]
    public function the_sentry_bundle_is_not_shipped_without_a_public_dsn(): void
    {
        $this->setting('fof-sentry.javascript', 1);

        $html = (string) $this->send($this->request('GET', '/'))->getBody();

        $this->assertStringNotContainsString('Sentry.createClient', $html);
        $this->assertStringNotContainsString('"fof-sentry"', $html);
    }

    #[Test]
    public function the_feedback_dialog_coexists_with_another_extensions_error_page(): void
    {
        $this->extend((new Extend\ServiceProvider())->register(ReplacesViewFormatter::class));

        $this->setting('fof-sentry.dsn', 'https://public@example.ingest.sentry.io/1');
        $this->setting('fof-sentry.user_feedback', true);
        $this->recordingHub();

        $body = $this->formatReported(new Exception('boom'), $this->requestAs(null));

        $this->assertStringContainsString(MarkingViewFormatter::MARKER, $body);
        $this->assertStringContainsString('showReportDialog', $body);
    }
}
