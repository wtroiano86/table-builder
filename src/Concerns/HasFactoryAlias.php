<?php

declare(strict_types=1);

namespace Wtroiano\TableBuilder\Concerns;

use LogicException;
use ReflectionFunction;

trait HasFactoryAlias
{
    /** @deprecated Use make() and fluent configuration. */
    public static function create(mixed ...$arguments): static
    {
        $factory = new ReflectionFunction(static::make(...))->getClosure();
        $instance = $factory(...$arguments);

        throw_unless($instance instanceof static, LogicException::class, 'Factory must return an instance of '.static::class.'.');

        return $instance;
    }
}
