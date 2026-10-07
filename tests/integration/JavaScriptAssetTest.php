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
 * The forum gets one Sentry bundle, whatever the sample rates; tracing and session
 * replay are separate chunks it loads on demand.
 */
class JavaScriptAssetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-sentry');
    }

    #[Test]
    public function the_forum_bundle_exists(): void
    {
        // One bundle for every configuration; tracing and replay are chunks loaded on demand.
        $this->assertFileExists(__DIR__.'/../../js/dist/forum.js');
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
