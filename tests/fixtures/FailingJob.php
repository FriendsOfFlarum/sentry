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

use Flarum\Queue\AbstractJob;
use RuntimeException;

class FailingJob extends AbstractJob
{
    public function handle(): void
    {
        throw new RuntimeException('Job failed on purpose');
    }
}
