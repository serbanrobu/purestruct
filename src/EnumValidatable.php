<?php

namespace Purestruct;

use ReflectionClass;
use ReflectionParameter;

trait EnumValidatable
{
    use Validatable, Curry;

    public static function validator(): Validator
    {
        $params = (new ReflectionClass(static::class))->getConstructor()?->getParameters() ?? [];

        return count($params)
            ? Validator::lift(
                static::curry(),
                ...array_map(
                    static fn (ReflectionParameter $param) => Validator::enumParam($param),
                    $params,
                ),
            )
            : Validator::pure(new static);
    }
}
