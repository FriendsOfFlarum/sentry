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

use Flarum\Foundation\Paths;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Sentry\Frame;
use Sentry\FrameBuilder;
use Sentry\Serializer\RepresentationSerializer;
use Sentry\State\HubInterface;

/**
 * How stack frames are classified decides what Sentry shows as "your code" and how it groups issues.
 */
class StackTraceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-sentry');

        $this->setting('fof-sentry.dsn', 'https://public@example.ingest.sentry.io/1');
    }

    /**
     * Builds a frame the way the SDK does for a real stack trace.
     */
    private function frame(string $file): Frame
    {
        $options = $this->app()->getContainer()->make(HubInterface::class)->getClient()->getOptions();

        return (new FrameBuilder($options, new RepresentationSerializer($options)))->buildFromBacktraceFrame($file, 1, []);
    }

    private function vendor(): string
    {
        return $this->app()->getContainer()->make(Paths::class)->vendor;
    }

    #[Test]
    public function upstream_library_frames_are_application_code(): void
    {
        // Frames from Illuminate, Symfony etc. stay first-class, so a problem upstream is visible.
        $this->assertTrue($this->frame($this->vendor().'/illuminate/container/Container.php')->isInApp());
    }

    #[Test]
    public function flarum_core_frames_are_application_code(): void
    {
        $this->assertTrue($this->frame($this->vendor().'/flarum/core/src/Foundation/Site.php')->isInApp());
    }

    #[Test]
    public function file_names_are_relative_to_the_install(): void
    {
        // Real frames carry absolute paths, while the test install's base path can be relative (as in CI).
        $base = realpath($this->app()->getContainer()->make(Paths::class)->base);

        $this->assertSame('/extend.php', $this->frame($base.'/extend.php')->getFile());
    }
}
