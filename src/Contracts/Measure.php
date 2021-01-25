<?php


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
