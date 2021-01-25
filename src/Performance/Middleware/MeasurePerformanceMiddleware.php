<?php


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
        $span = $this->transaction->startChild(new SpanContext);
        $span->setOp("frontend.{$this->frontend}");

        $response = $handler->handle($request);

        $span->finish();

        return $response;
    }
}
