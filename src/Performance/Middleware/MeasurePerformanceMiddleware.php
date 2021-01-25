<?php

/*
 * This file is part of fof/sentry
 *
 * Copyright (c) 2020 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Sentry\Performance\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\Transaction;

class MeasurePerformanceMiddleware implements Middleware
{
    /**
     * @var Transaction
     */
    protected $transaction;
    protected $frontend;

    public function __construct(string $frontend, Span $transaction)
    {
        $this->transaction = $transaction;
        $this->frontend = $frontend;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $span = $this->transaction->startChild(new SpanContext());
        $span->setOp("frontend.{$this->frontend}");

        $response = $handler->handle($request);

        $span->finish();

        return $response;
    }
}
