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
use Flarum\Http\RequestUtil;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use FoF\Sentry\Reporters\SentryReporter;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\Test;
use Sentry\Event;
use Sentry\State\HubInterface;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;

/**
 * SentryReporter is what core calls when it hits an unhandled exception. These
 * tests install a recording transport so the event that would be transmitted
 * can be inspected without any network access.
 */
class ReporterTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    /** @var array<int, Event> */
    private static array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-sentry');

        $this->setting('fof-sentry.dsn', 'https://public@example.ingest.sentry.io/1');

        $this->prepareDatabase([
            'users' => [$this->normalUser()],
        ]);

        self::$sent = [];
    }

    /**
     * Swap the hub's transport for one that records events in-process.
     */
    private function recordingHub(): HubInterface
    {
        $hub = $this->app()->getContainer()->make(HubInterface::class);

        $transport = new class() implements TransportInterface {
            public function send(Event $event): Result
            {
                ReporterTest::record($event);

                return new Result(ResultStatus::success(), $event);
            }

            public function close(?int $timeout = null): Result
            {
                return new Result(ResultStatus::success());
            }
        };

        $options = $hub->getClient()->getOptions();

        $hub->bindClient(new \Sentry\Client(
            $options,
            $transport,
            'sentry.php.flarum',
            null,
            null,
            null
        ));

        return $hub;
    }

    public static function record(Event $event): void
    {
        self::$sent[] = $event;
    }

    private function reporter(): SentryReporter
    {
        return $this->app()->getContainer()->make(SentryReporter::class);
    }

    private function bindRequest(?int $actorId = null): void
    {
        $container = $this->app()->getContainer();

        $request = (new ServerRequest([], [], '/', 'GET'))
            ->withAttribute('ipAddress', '198.51.100.7');

        $actor = $actorId === null ? new \Flarum\User\Guest() : \Flarum\User\User::query()->find($actorId);

        $container->instance('sentry.request', RequestUtil::withActor($request, $actor));
    }

    #[Test]
    public function an_exception_is_transmitted_to_sentry(): void
    {
        $this->recordingHub();

        $this->reporter()->report(new Exception('boom'));

        $this->assertCount(1, self::$sent);
    }

    #[Test]
    public function the_exception_message_and_class_are_captured(): void
    {
        $this->recordingHub();

        $this->reporter()->report(new \RuntimeException('something specific broke'));

        $exceptions = self::$sent[0]->getExceptions();

        $this->assertSame(\RuntimeException::class, $exceptions[0]->getType());
        $this->assertSame('something specific broke', $exceptions[0]->getValue());
    }

    #[Test]
    public function reporting_works_when_no_request_has_been_bound(): void
    {
        // Console commands and queue workers report without ever passing
        // through the HTTP middleware, so `sentry.request` is absent.
        $this->recordingHub();

        $this->reporter()->report(new Exception('from the console'));

        $this->assertCount(1, self::$sent);
    }

    #[Test]
    public function the_acting_user_is_attached_to_the_reported_event(): void
    {
        $this->recordingHub();
        $this->bindRequest(2);

        $this->reporter()->report(new Exception('boom'));

        $user = self::$sent[0]->getUser();

        $this->assertSame(2, $user?->getId());
        $this->assertSame('normal', $user?->getUsername());
    }

    #[Test]
    public function the_email_is_not_attached_to_a_reported_event_by_default(): void
    {
        $this->setting('fof-sentry.send_emails_with_sentry_reports', 0);

        $this->recordingHub();
        $this->bindRequest(2);

        $this->reporter()->report(new Exception('boom'));

        $this->assertNull(self::$sent[0]->getUser()?->getEmail());
    }

    #[Test]
    public function the_flarum_version_is_tagged_on_reported_events(): void
    {
        // The `sentry` binding applies these scope tags; resolve it first so
        // the tagging has been applied.
        $this->app()->getContainer()->make('sentry');
        $this->recordingHub();

        $this->reporter()->report(new Exception('boom'));

        $this->assertSame(
            \Flarum\Foundation\Application::VERSION,
            self::$sent[0]->getTags()['flarum'] ?? null
        );
    }

    #[Test]
    public function nothing_is_transmitted_when_no_dsn_is_configured(): void
    {
        // A no-op hub has no client, so captureException cannot transmit.
        $this->setting('fof-sentry.dsn', '');
        $this->setting('fof-sentry.dsn_backend', '');

        $this->reporter()->report(new Exception('boom'));

        $this->assertCount(0, self::$sent);
    }
}
