<?php

/*
 * This file is part of fof/sentry
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Sentry\Tests\unit;

use ErrorException;
use FoF\Sentry\SentryServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sentry\Event;
use Sentry\EventHint;

class BeforeSendTest extends TestCase
{
    #[Test]
    public function stmt_prepare_reconnect_warnings_are_dropped(): void
    {
        $hint = EventHint::fromArray([
            'exception' => new ErrorException('Warning: PDO::prepare(): Error while sending STMT_PREPARE packet. PID=1'),
        ]);

        $this->assertNull(SentryServiceProvider::beforeSend(Event::createEvent(), $hint));
    }

    #[Test]
    public function other_events_pass_through(): void
    {
        $event = Event::createEvent();
        $hint = EventHint::fromArray(['exception' => new RuntimeException('STMT_PREPARE packet')]);

        $this->assertSame($event, SentryServiceProvider::beforeSend($event, $hint));
        $this->assertSame($event, SentryServiceProvider::beforeSend($event, null));
    }
}
