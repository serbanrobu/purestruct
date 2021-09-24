<?php

namespace Purestruct;

use ReflectionClass;
use ReflectionParameter;

trait TupleValidatable
{
    use Validatable;

    public static function validator(): Validator
    {
        $params = (new ReflectionClass(static::class))->getConstructor()?->getParameters() ?? [];

        return match (count($params)) {
            0 => Validator::pure(new static),
            1 => Validator::namedType($params[0]->getType())->map(static::curry()),
            default => Validator::lift(
                static::curry(),
                ...array_map(
                    static fn (ReflectionParameter $param) => Validator::tupleParam($param),
                    $params,
                ),
            ),
        };
    }
}
