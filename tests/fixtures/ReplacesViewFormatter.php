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

use Flarum\Foundation\AbstractServiceProvider;
use Flarum\Foundation\ErrorHandling\ViewFormatter;

class ReplacesViewFormatter extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(ViewFormatter::class, MarkingViewFormatter::class);
    }
}
