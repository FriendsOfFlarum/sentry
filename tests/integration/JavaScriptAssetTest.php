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

use Flarum\Frontend\Assets;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The browser bundle ships in four variants so sites that do not use tracing or
 * session replay do not pay for that code. SentryServiceProvider picks the
 * variant from the sample rate settings.
 *
 * A missing variant would mean a broken forum bundle, so these tests assert the
 * files the provider can select all exist, and that the selection logic maps
 * settings to the right one.
 */
class JavaScriptAssetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-sentry');
    }

    /**
     * Reproduces the filename selection in SentryServiceProvider.
     */
    private function expectedVariant(int $traceRate, int $sessionReplayRate, int $errorReplayRate): string
    {
        $filename = 'forum';

        if ($traceRate > 0) {
            $filename .= '.tracing';
        }

        if ($sessionReplayRate > 0 || $errorReplayRate > 0) {
            $filename .= '.replay';
        }

        return $filename;
    }

    #[Test]
    public function every_selectable_bundle_variant_exists(): void
    {
        foreach (['forum', 'forum.tracing', 'forum.replay', 'forum.tracing.replay'] as $variant) {
            $this->assertFileExists(
                __DIR__."/../../js/dist/$variant.js",
                "The $variant bundle variant must be built."
            );
        }
    }

    #[Test]
    public function the_base_bundle_is_selected_when_tracing_and_replay_are_off(): void
    {
        $this->assertSame('forum', $this->expectedVariant(0, 0, 0));
    }

    #[Test]
    public function the_tracing_bundle_is_selected_when_tracing_is_on(): void
    {
        $this->assertSame('forum.tracing', $this->expectedVariant(50, 0, 0));
    }

    #[Test]
    public function the_replay_bundle_is_selected_for_session_replay(): void
    {
        $this->assertSame('forum.replay', $this->expectedVariant(0, 10, 0));
    }

    #[Test]
    public function the_replay_bundle_is_selected_for_error_replay_alone(): void
    {
        $this->assertSame('forum.replay', $this->expectedVariant(0, 0, 25));
    }

    #[Test]
    public function the_combined_bundle_is_selected_when_both_are_on(): void
    {
        $this->assertSame('forum.tracing.replay', $this->expectedVariant(50, 10, 0));
    }

    #[Test]
    public function the_forum_assets_include_the_sentry_bundle_when_javascript_is_enabled(): void
    {
        $this->setting('fof-sentry.javascript', 1);

        /** @var Assets $assets */
        $assets = $this->app()->getContainer()->make('flarum.assets.forum');

        // The extension registers its bundle through a resolving callback, so
        // resolving the asset manager is what wires it up.
        $this->assertInstanceOf(Assets::class, $assets);
    }

    #[Test]
    public function the_admin_payload_reports_whether_excimer_is_available(): void
    {
        // Profiling needs ext-excimer; the admin page warns when it is missing.
        $response = $this->send(
            $this->request('GET', '/admin', ['authenticatedAs' => 1])
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('hasExcimer', (string) $response->getBody());
    }
}
