<?php

/*
 * This file is part of fof/sentry
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Sentry\Tests\fixtures;

use Sentry\Event;
use Sentry\EventType;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;

/**
 * Keeps every event the SDK would send in-process, so tests can inspect them without network access.
 */
class RecordingTransport implements TransportInterface
{
    /** @var array<int, Event> */
    public static array $events = [];

    public function send(Event $event): Result
    {
        self::$events[] = $event;

        return new Result(ResultStatus::success(), $event);
    }

    public function close(?int $timeout = null): Result
    {
        return new Result(ResultStatus::success());
    }

    /**
     * @return array<int, Event>
     */
    public static function transactions(): array
    {
        return array_values(array_filter(self::$events, fn (Event $event) => $event->getType() === EventType::transaction()));
    }
}
