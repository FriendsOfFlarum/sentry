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

use Flarum\Foundation\ErrorHandling\Reporter;
use Flarum\Foundation\ErrorHandling\ViewFormatter;
use Flarum\Testing\integration\TestCase;
use FoF\Sentry\Formatters\SentryFormatter;
use FoF\Sentry\Reporters\SentryReporter;
use PHPUnit\Framework\Attributes\Test;
use Sentry\State\HubInterface;

/**
 * The extension hangs everything off container bindings registered by
 * SentryServiceProvider. These tests pin that wiring, so the planned move to
 * core's ErrorHandling extender can be verified as behaviour-preserving.
 */
class BindingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-sentry');
    }

    private function container()
    {
        return $this->app()->getContainer();
    }

    #[Test]
    public function the_sentry_reporter_is_tagged_as_a_core_error_reporter(): void
    {
        // Core calls every reporter tagged with Reporter::class when it hits an
        // exception it does not know how to handle.
        $reporters = $this->container()->tagged(Reporter::class);

        $found = false;

        foreach ($reporters as $reporter) {
            if ($reporter instanceof SentryReporter) {
                $found = true;
            }
        }

        $this->assertTrue($found, 'SentryReporter must be tagged as a core error Reporter.');
    }

    #[Test]
    public function the_core_log_reporter_is_still_registered_alongside_sentry(): void
    {
        // Reporting to Sentry must not replace logging to storage/logs.
        $reporters = $this->container()->tagged(Reporter::class);

        $classes = [];

        foreach ($reporters as $reporter) {
            $classes[] = get_class($reporter);
        }

        $this->assertContains(\Flarum\Foundation\ErrorHandling\LogReporter::class, $classes);
    }

    #[Test]
    public function the_view_formatter_is_replaced_with_the_sentry_formatter(): void
    {
        // The formatter override is what injects the user feedback dialog into
        // the HTML error page.
        $this->assertInstanceOf(SentryFormatter::class, $this->container()->make(ViewFormatter::class));
    }

    #[Test]
    public function the_measurements_list_is_exposed_for_extension(): void
    {
        $measurements = $this->container()->make('fof.sentry.measurements');

        $this->assertContains(\FoF\Sentry\Performance\Eloquent::class, $measurements);
        $this->assertContains(\FoF\Sentry\Performance\Extension::class, $measurements);
        $this->assertContains(\FoF\Sentry\Performance\Frontend::class, $measurements);
    }

    #[Test]
    public function the_release_defaults_to_the_flarum_version(): void
    {
        $this->assertSame(
            \Flarum\Foundation\Application::VERSION,
            $this->container()->make('sentry.release')
        );
    }

    #[Test]
    public function the_config_containers_start_empty_so_extenders_can_fill_them(): void
    {
        $this->assertSame([], $this->container()->make('fof.sentry.backend.config'));
        $this->assertSame([], $this->container()->make('fof.sentry.frontend.config'));
    }

    #[Test]
    public function a_hub_resolves_even_when_no_dsn_is_configured(): void
    {
        // With no DSN the provider returns the ambient no-op hub rather than
        // initialising a client, so nothing is transmitted but nothing breaks.
        $this->setting('fof-sentry.dsn', '');
        $this->setting('fof-sentry.dsn_backend', '');

        $hub = $this->container()->make(HubInterface::class);

        $this->assertInstanceOf(HubInterface::class, $hub);
        $this->assertNull($hub->getClient()?->getOptions()->getDsn());
    }

    #[Test]
    public function the_backend_dsn_is_used_when_set(): void
    {
        $this->setting('fof-sentry.dsn', 'https://frontend@example.ingest.sentry.io/1');
        $this->setting('fof-sentry.dsn_backend', 'https://backend@example.ingest.sentry.io/2');

        $options = $this->container()->make(HubInterface::class)->getClient()->getOptions();

        $this->assertSame('2', $options->getDsn()->getProjectId());
    }

    #[Test]
    public function the_general_dsn_is_used_when_no_backend_dsn_is_set(): void
    {
        $this->setting('fof-sentry.dsn', 'https://frontend@example.ingest.sentry.io/1');
        $this->setting('fof-sentry.dsn_backend', '');

        $options = $this->container()->make(HubInterface::class)->getClient()->getOptions();

        $this->assertSame('1', $options->getDsn()->getProjectId());
    }

    #[Test]
    public function the_environment_falls_back_to_the_forum_host(): void
    {
        $this->setting('fof-sentry.dsn', 'https://public@example.ingest.sentry.io/1');
        $this->setting('fof-sentry.environment', '');

        $options = $this->container()->make(HubInterface::class)->getClient()->getOptions();

        // The scheme is stripped so the environment reads as a bare hostname.
        $this->assertStringNotContainsString('://', $options->getEnvironment());
        $this->assertNotSame('', $options->getEnvironment());
    }

    #[Test]
    public function a_configured_environment_wins_over_the_host_fallback(): void
    {
        $this->setting('fof-sentry.dsn', 'https://public@example.ingest.sentry.io/1');
        $this->setting('fof-sentry.environment', 'staging');

        $options = $this->container()->make(HubInterface::class)->getClient()->getOptions();

        $this->assertSame('staging', $options->getEnvironment());
    }

    #[Test]
    public function sample_rate_settings_reach_the_sdk_options_as_fractions(): void
    {
        $this->setting('fof-sentry.dsn', 'https://public@example.ingest.sentry.io/1');
        $this->setting('fof-sentry.monitor_performance', 50);
        $this->setting('fof-sentry.profile_rate', 25);

        $options = $this->container()->make(HubInterface::class)->getClient()->getOptions();

        $this->assertSame(0.5, $options->getTracesSampleRate());
        $this->assertSame(0.25, $options->getProfilesSampleRate());
    }

    #[Test]
    public function the_forum_base_path_is_marked_as_in_app(): void
    {
        // in_app_include is what makes Flarum and extension frames render as
        // application code rather than vendor noise in the Sentry UI.
        $this->setting('fof-sentry.dsn', 'https://public@example.ingest.sentry.io/1');

        $options = $this->container()->make(HubInterface::class)->getClient()->getOptions();

        $this->assertNotEmpty($options->getInAppIncludedPaths());
    }
}
