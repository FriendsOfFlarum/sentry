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
use Sentry\State\HubInterface;
use Sentry\Tracing\TransactionSource;

/**
 * Runs after route resolution, so the request transaction can be named after the
 * matched route ("GET api.discussions.show") instead of its URL.
 */
class NameTransaction implements MiddlewareInterface
{
    public function __construct(protected Container $container)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $routeName = $request->getAttribute('routeName');

        // An internal API call must not rename the outer request's transaction.
        if ($routeName && !RequestUtil::isInternal($request)) {
            $transaction = $this->container->make(HubInterface::class)->getTransaction();

            if ($transaction !== null) {
                $frontend = $transaction->getTags()['frontend'] ?? null;

                $transaction->setName($request->getMethod().' '.($frontend ? "$frontend." : '').$routeName);
                $transaction->getMetadata()->setSource(TransactionSource::route());
            }
        }

        return $handler->handle($request);
    }
}
