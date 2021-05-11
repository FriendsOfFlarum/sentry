<?php

/*
 * This file is part of fof/sentry
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Sentry\Contracts;

use Illuminate\Contracts\Container\Container;
use Sentry\Tracing\Span;
use Sentry\Tracing\Transaction;

interface Measure
{
    public function __construct(Transaction $transaction, Container $container);

    public static function name(): string;

    public function handle(): ?Span;
}
