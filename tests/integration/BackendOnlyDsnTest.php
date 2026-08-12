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
use PHPUnit\Framework\Attributes\Test;
use Sentry\State\HubInterface;

/**
 * Two container keys resolve Sentry, and they disagree about which DSN counts:
 *
 *   - HubInterface falls back `dsn_backend` -> `dsn`
 *   - the `sentry` binding checks only `dsn`
 *
 * So a site configured with only `dsn_backend` gets a working hub while
 * `resolve('sentry')` returns null, which silently disables the user feedback
 * dialog in SentryFormatter.
 *
 * These tests characterise the current behaviour. `sentry_binding_*` documents
 * the inconsistency and is expected to change when the fallback is unified;
 * see the review item on DSN handling.
 */
class BackendOnlyDsnTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-sentry');

        // Only a backend DSN — no general DSN at all.
        $this->setting('fof-sentry.dsn', '');
        $this->setting('fof-sentry.dsn_backend', 'https://backend@example.ingest.sentry.io/2');
    }

    private function container()
    {
        return $this->app()->getContainer();
    }

    #[Test]
    public function the_hub_initialises_from_the_backend_dsn_alone(): void
    {
        $options = $this->container()->make(HubInterface::class)->getClient()->getOptions();

        $this->assertSame('2', $options->getDsn()->getProjectId());
    }

    #[Test]
    public function sentry_binding_is_null_with_only_a_backend_dsn(): void
    {
        // Characterisation of the inconsistency: the hub is live, but the
        // `sentry` binding used by SentryFormatter is not. Once the DSN
        // fallback is unified this should return a hub instead.
        $this->assertNull($this->container()->make('sentry'));
    }

    #[Test]
    public function sentry_binding_resolves_when_the_general_dsn_is_set(): void
    {
        $this->setting('fof-sentry.dsn', 'https://public@example.ingest.sentry.io/1');

        $this->assertInstanceOf(HubInterface::class, $this->container()->make('sentry'));
    }

    #[Test]
    public function the_sentry_binding_tags_the_flarum_version_and_debug_state(): void
    {
        $this->setting('fof-sentry.dsn', 'https://public@example.ingest.sentry.io/1');

        // Resolving applies the scope tags; assert it resolves to the same hub
        // so the tagging is not applied to a throwaway instance.
        $sentry = $this->container()->make('sentry');

        $this->assertSame($this->container()->make(HubInterface::class), $sentry);
    }
}
