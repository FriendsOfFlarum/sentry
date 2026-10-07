<?php

/*
 * This file is part of fof/sentry
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Sentry\Tracing;

/**
 * Defers work until the response has been emitted. Shutdown functions run before
 * destructors, so state such as the database connection is still intact.
 */
class AfterResponse
{
    /** @var array<int, callable(): void> */
    protected array $callbacks = [];

    protected bool $registered = false;

    /**
     * @param callable(): void $callback
     */
    public function defer(callable $callback): void
    {
        $this->callbacks[] = $callback;

        if (!$this->registered) {
            register_shutdown_function([$this, 'run']);
            $this->registered = true;
        }
    }

    public function run(): void
    {
        $callbacks = $this->callbacks;
        $this->callbacks = [];

        foreach ($callbacks as $callback) {
            $callback();
        }
    }
}
