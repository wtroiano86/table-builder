<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Support;

use Closure;
use ReflectionFunction;

final class Callback
{
    public static function invoke(Closure $callback, mixed ...$arguments): mixed
    {
        $reflection = new ReflectionFunction($callback);

        return $callback(...($reflection->isVariadic()
            ? $arguments
            : array_slice($arguments, 0, $reflection->getNumberOfParameters())));
    }
}
