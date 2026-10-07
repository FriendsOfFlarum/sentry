<?php

/*
 * This file is part of fof/sentry
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Sentry\Middleware;

use Flarum\Http\RequestUtil;
use Illuminate\Contracts\Container\Container;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Only records the request. User context is built from it lazily, when an event
 * is actually sent (see SentryServiceProvider::attachUser).
 */
class HandleErrorsWithSentry implements MiddlewareInterface
{
    public function __construct(protected Container $container)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Internal API client calls run this stack too; keep the outer request, which carries the client IP.
        if (!RequestUtil::isInternal($request) || !$this->container->bound('sentry.request')) {
            $this->container->instance('sentry.request', $request);
        }

        return $handler->handle($request);
    }
}
