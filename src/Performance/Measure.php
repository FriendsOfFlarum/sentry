<?php


namespace FoF\Sentry\Performance;

use FoF\Sentry\Contracts\Measure as Contract;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
use Sentry\Tracing\Transaction;

abstract class Measure implements Contract
{
    /** @var Transaction */
    protected $transaction;
    /** @var Container */
    protected $container;

    public function __construct(Transaction $transaction, Container $container)
    {
        $this->transaction = $transaction;
        $this->container = $container;
    }

    public static function name(): string
    {
        $name = get_called_class();
        $name = Str::afterLast($name, '\\');

        return Str::slug($name);
    }
}
