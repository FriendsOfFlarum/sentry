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

use Illuminate\Contracts\Queue\Queue;
use Laminas\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * A route that queues a job; with the sync driver the job runs inside the request.
 */
class DispatchingRequestHandler implements RequestHandlerInterface
{
    public function __construct(protected Queue $queue)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->queue->push(new TracedJob());

        return new EmptyResponse();
    }
}
