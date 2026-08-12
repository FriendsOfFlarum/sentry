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
use Flarum\Http\RequestUtil;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\Guest;
use Flarum\User\User;
use FoF\Sentry\Middleware\HandleErrorsWithSentry;
use FoF\Sentry\Tests\fixtures\PrefixedDisplayNameDriver;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sentry\Event;
use Sentry\State\HubInterface;
use Sentry\State\Scope;

/**
 * HandleErrorsWithSentry attaches the acting user to the Sentry scope on every
 * request. What lands in that scope decides what personal data leaves the
 * server, so each field is pinned individually.
 *
 * The same block is currently duplicated in SentryReporter and (partly)
 * SentryFormatter; these tests describe the behaviour that must survive
 * extracting it into one place.
 */
class UserContextTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-sentry');

        $this->setting('fof-sentry.dsn', 'https://public@example.ingest.sentry.io/1');

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
            ],
        ]);
    }

    private function handler(): RequestHandlerInterface
    {
        return new class() implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response();
            }
        };
    }

    private function requestFor(?int $actorId): ServerRequestInterface
    {
        $request = (new ServerRequest([], [], '/', 'GET'))
            ->withAttribute('ipAddress', '203.0.113.5');

        $actor = $actorId === null ? new Guest() : User::query()->find($actorId);

        return RequestUtil::withActor($request, $actor);
    }

    /**
     * Run the middleware for the given actor and return the user data it wrote
     * onto the Sentry scope.
     *
     * @return array<string, mixed>
     */
    private function scopeUserFor(?int $actorId): array
    {
        $container = $this->app()->getContainer();

        (new HandleErrorsWithSentry($container))->process($this->requestFor($actorId), $this->handler());

        // Read the user back off the scope the middleware configured.
        $captured = [];

        $container->make(HubInterface::class)->configureScope(function (Scope $scope) use (&$captured) {
            $event = $scope->applyToEvent(Event::createEvent());
            $user = $event?->getUser();

            if ($user === null) {
                return;
            }

            // Named fields come off dedicated accessors; anything the extension
            // adds beyond the SDK's own schema (username_slug, groups) lands in
            // the metadata bag.
            $captured = array_filter([
                'id'         => $user->getId(),
                'username'   => $user->getUsername(),
                'email'      => $user->getEmail(),
                'ip_address' => $user->getIpAddress(),
            ], fn ($value) => $value !== null) + $user->getMetadata();
        });

        return $captured;
    }

    #[Test]
    public function the_request_is_shared_on_the_container_for_the_reporter(): void
    {
        $container = $this->app()->getContainer();

        (new HandleErrorsWithSentry($container))->process($this->requestFor(null), $this->handler());

        $this->assertTrue($container->bound('sentry.request'));
    }

    #[Test]
    public function the_ip_address_is_recorded_for_a_guest(): void
    {
        $user = $this->scopeUserFor(null);

        $this->assertSame('203.0.113.5', $user['ip_address'] ?? null);
    }

    #[Test]
    public function a_guest_gets_no_identity_fields(): void
    {
        // A guest has no id or username to report; only the IP is useful.
        $user = $this->scopeUserFor(null);

        $this->assertArrayNotHasKey('id', $user);
        $this->assertArrayNotHasKey('username', $user);
        $this->assertArrayNotHasKey('email', $user);
    }

    #[Test]
    public function an_authenticated_user_is_identified_by_id_and_display_name(): void
    {
        $user = $this->scopeUserFor(2);

        $this->assertSame(2, $user['id'] ?? null);
        $this->assertSame('normal', $user['username'] ?? null);
    }

    #[Test]
    public function the_email_is_withheld_by_default(): void
    {
        $this->setting('fof-sentry.send_emails_with_sentry_reports', 0);

        $user = $this->scopeUserFor(2);

        $this->assertArrayNotHasKey('email', $user);
    }

    #[Test]
    public function the_email_is_included_only_when_explicitly_enabled(): void
    {
        $this->setting('fof-sentry.send_emails_with_sentry_reports', 1);

        $user = $this->scopeUserFor(2);

        $this->assertSame('normal@machine.local', $user['email'] ?? null);
    }

    #[Test]
    public function no_username_slug_is_added_when_the_display_name_matches(): void
    {
        // With core's default driver display_name is the username, so there is
        // nothing extra worth sending.
        $user = $this->scopeUserFor(2);

        $this->assertArrayNotHasKey('username_slug', $user);
    }

    #[Test]
    public function the_username_slug_is_added_when_a_display_name_differs(): void
    {
        // `username` carries the display name, so the real username is kept
        // separately to stay searchable in Sentry.
        $this->extend(
            (new Extend\User())->displayNameDriver('prefixed', PrefixedDisplayNameDriver::class)
        );
        $this->setting('display_name_driver', 'prefixed');

        $user = $this->scopeUserFor(2);

        $this->assertSame('Display normal', $user['username'] ?? null);
        $this->assertSame('normal', $user['username_slug'] ?? null);
    }

    #[Test]
    public function group_names_are_attached(): void
    {
        // Groups help triage whether an error only affects, say, admins.
        $user = $this->scopeUserFor(1);

        $this->assertArrayHasKey('groups', $user);
        $this->assertIsString($user['groups']);
    }

    #[Test]
    public function no_user_context_is_set_when_sentry_has_no_dsn(): void
    {
        // Without a DSN the hub is a no-op; the middleware must still pass the
        // request through untouched rather than erroring.
        $this->setting('fof-sentry.dsn', '');
        $this->setting('fof-sentry.dsn_backend', '');

        $response = (new HandleErrorsWithSentry($this->app()->getContainer()))
            ->process($this->requestFor(2), $this->handler());

        $this->assertSame(200, $response->getStatusCode());
    }
}
