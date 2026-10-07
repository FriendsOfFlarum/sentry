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
use FoF\Sentry\Tracing\Tracer;
use Illuminate\Contracts\Container\Container;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sentry\Tracing\TransactionSource;

use function Sentry\continueTrace;

/**
 * Outermost middleware of each frontend: one `http.server` transaction per request.
 */
class TraceRequest implements MiddlewareInterface
{
    public function __construct(protected string $frontend, protected Container $container)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var Tracer $tracer */
        $tracer = $this->container->make(Tracer::class);

        // Internal API calls (e.g. the forum's preloads) belong to the outer request's transaction.
        if (RequestUtil::isInternal($request) || !$tracer->enabled()) {
            return $handler->handle($request);
        }

        // Joins the browser's trace when its SDK sent trace headers; starts a new one otherwise.
        $context = continueTrace($request->getHeaderLine('sentry-trace'), $request->getHeaderLine('baggage'));
        $context->setOp('http.server');
        $context->setName($request->getMethod().' '.$request->getUri()->getPath());
        $context->setSource(TransactionSource::url());
        $context->setTags(['frontend' => $this->frontend]);
        // Back-date to when PHP received the request, so bootstrap time is included.
        $context->setStartTimestamp((float) ($request->getServerParams()['REQUEST_TIME_FLOAT'] ?? microtime(true)));

        $transaction = $tracer->start($context);

        try {
            $response = $handler->handle($request);
            $transaction->setHttpStatus($response->getStatusCode());

            return $response;
        } finally {
            $tracer->finishAfterResponse($transaction);
        }
    }
}
