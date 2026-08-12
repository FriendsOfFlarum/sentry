<?php

/*
 * This file is part of fof/sentry
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Sentry\Tests\unit\Extend;

use FoF\Sentry\Extend\Sentry;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The Sentry extender is the public API other extensions use to influence how
 * events are reported. These tests pin the container contract it relies on:
 * which keys it writes, and that it stays inert when nothing is configured.
 */
class SentryTest extends TestCase
{
    private function container(): Container
    {
        $container = new Container();

        // Mirrors the bindings SentryServiceProvider registers, since the
        // extender extends rather than defines them.
        $container->singleton('sentry.release', fn () => 'core-version');
        $container->singleton('fof.sentry.frontend.config', fn () => []);

        return $container;
    }

    #[Test]
    public function release_defaults_to_the_service_provider_value(): void
    {
        $container = $this->container();

        (new Sentry())->extend($container);

        $this->assertSame('core-version', $container->make('sentry.release'));
    }

    #[Test]
    public function set_release_overrides_the_release(): void
    {
        $container = $this->container();

        (new Sentry())->setRelease('1.2.3')->extend($container);

        $this->assertSame('1.2.3', $container->make('sentry.release'));
    }

    #[Test]
    public function environment_is_not_bound_unless_set(): void
    {
        // SentryJavaScript and the hub factory both branch on `bound()`, so an
        // unset environment must leave the key absent rather than empty.
        $container = $this->container();

        (new Sentry())->extend($container);

        $this->assertFalse($container->bound('fof.sentry.environment'));
    }

    #[Test]
    public function set_environment_binds_the_environment(): void
    {
        $container = $this->container();

        (new Sentry())->setEnvironment('staging')->extend($container);

        $this->assertTrue($container->bound('fof.sentry.environment'));
        $this->assertSame('staging', $container->make('fof.sentry.environment'));
    }

    #[Test]
    public function tags_are_not_bound_unless_added(): void
    {
        $container = $this->container();

        (new Sentry())->extend($container);

        $this->assertFalse($container->bound('fof.sentry.tags'));
    }

    #[Test]
    public function added_tags_are_exposed_for_the_frontend_payload(): void
    {
        $container = $this->container();

        (new Sentry())
            ->addTag('tenant', 'acme')
            ->addTag('tier', 'premium')
            ->extend($container);

        $this->assertSame(
            ['tenant' => 'acme', 'tier' => 'premium'],
            $container->make('fof.sentry.tags')
        );
    }

    #[Test]
    public function adding_the_same_tag_twice_keeps_the_last_value(): void
    {
        $container = $this->container();

        (new Sentry())
            ->addTag('tier', 'free')
            ->addTag('tier', 'premium')
            ->extend($container);

        $this->assertSame(['tier' => 'premium'], $container->make('fof.sentry.tags'));
    }

    #[Test]
    public function tags_are_merged_into_the_frontend_config(): void
    {
        $container = $this->container();

        (new Sentry())->addTag('tenant', 'acme')->extend($container);

        $config = $container->make('fof.sentry.frontend.config');

        $this->assertSame(['tenant' => 'acme'], $config['tags']);
    }

    #[Test]
    public function two_extenders_both_contribute_tags_to_the_frontend_config(): void
    {
        // Several extensions may each add tags; the later extender must not
        // discard what an earlier one contributed.
        $container = $this->container();

        (new Sentry())->addTag('first', 'a')->extend($container);
        (new Sentry())->addTag('second', 'b')->extend($container);

        $config = $container->make('fof.sentry.frontend.config');

        $this->assertSame(['first' => 'a', 'second' => 'b'], $config['tags']);
    }

    #[Test]
    public function the_extender_is_fluent(): void
    {
        $extender = new Sentry();

        $this->assertSame($extender, $extender->setRelease('1.0.0'));
        $this->assertSame($extender, $extender->setEnvironment('staging'));
        $this->assertSame($extender, $extender->addTag('key', 'value'));
        $this->assertSame($extender, $extender->sendTestMessage());
        $this->assertSame($extender, $extender->sendTestException());
    }
}
