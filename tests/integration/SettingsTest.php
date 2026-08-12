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

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Defaults are registered through the Settings extender so the extension
 * behaves sanely before an admin ever visits the settings page. Most
 * importantly, a freshly enabled extension must not start transmitting.
 */
class SettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-sentry');
    }

    private function settings(): SettingsRepositoryInterface
    {
        return $this->app()->getContainer()->make(SettingsRepositoryInterface::class);
    }

    #[Test]
    #[DataProvider('defaultProvider')]
    public function defaults_are_registered(string $key, mixed $expected): void
    {
        $this->assertEquals($expected, $this->settings()->get($key));
    }

    public static function defaultProvider(): array
    {
        return [
            'dsn is empty'                => ['fof-sentry.dsn', ''],
            'backend dsn is empty'        => ['fof-sentry.dsn_backend', ''],
            'environment is empty'        => ['fof-sentry.environment', ''],
            'performance is off'          => ['fof-sentry.monitor_performance', 0],
            'profiling is off'            => ['fof-sentry.profile_rate', 0],
            'emails are not sent'         => ['fof-sentry.send_emails_with_sentry_reports', false],
            'user feedback is off'        => ['fof-sentry.user_feedback', false],
            'console capture is off'      => ['fof-sentry.javascript.console', false],
            'js tracing is off'           => ['fof-sentry.javascript.trace_sample_rate', 0],
            'session replay is off'       => ['fof-sentry.javascript.replays_session_sample_rate', 0],
            'error replay is off'         => ['fof-sentry.javascript.replays_error_sample_rate', 0],
            'javascript is on'            => ['fof-sentry.javascript', true],
            'slow query threshold'        => ['fof-sentry.db.slow_query_threshold', 1000],
            'n+1 detection is on'         => ['fof-sentry.db.n_plus_one_detection', true],
            'n+1 threshold'               => ['fof-sentry.db.n_plus_one_threshold', 10],
            'binding tracking is off'     => ['fof-sentry.db.track_bindings', false],
            'query sampling is full'      => ['fof-sentry.db.query_sample_rate', 100],
        ];
    }

    #[Test]
    public function no_dsn_is_configured_by_default_so_nothing_is_transmitted(): void
    {
        // Enabling the extension must be inert until an admin supplies a DSN.
        $this->assertEmpty($this->settings()->get('fof-sentry.dsn'));
        $this->assertEmpty($this->settings()->get('fof-sentry.dsn_backend'));
    }

    #[Test]
    public function privacy_sensitive_settings_default_to_off(): void
    {
        // Emails and query bindings are the two settings that can put personal
        // data into an error report, so both must be opt-in.
        $this->assertFalse((bool) $this->settings()->get('fof-sentry.send_emails_with_sentry_reports'));
        $this->assertFalse((bool) $this->settings()->get('fof-sentry.db.track_bindings'));
    }

    #[Test]
    public function performance_monitoring_defaults_to_off_so_no_transactions_are_started(): void
    {
        // SentryServiceProvider::boot() only starts a transaction when this is
        // above zero; a default of 0 keeps the tracing machinery dormant.
        $this->assertSame(0, (int) $this->settings()->get('fof-sentry.monitor_performance'));
    }
}
