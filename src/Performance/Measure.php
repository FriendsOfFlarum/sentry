<?php

/*
 * This file is part of fof/sentry
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Sentry\Performance;

use FoF\Sentry\Contracts\Measure as Contract;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
use Sentry\Tracing\Transaction;

abstract class Measure implements Contract
{
    public function __construct(protected Transaction $transaction, protected Container $container)
    {
    }

    public static function name(): string
    {
        $name = get_called_class();
        $name = Str::afterLast($name, '\\');

        return Str::slug($name);
    }
}
