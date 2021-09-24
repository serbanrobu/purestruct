<?php

namespace Purestruct;

use ReflectionClass;

trait Curry
{
    public static function curry(): callable
    {
        return curryHelp(
            static fn () => new static(...func_get_args()),
            (new ReflectionClass(static::class))->getConstructor()?->getNumberOfParameters() ?? 0,
        );
    }
}
