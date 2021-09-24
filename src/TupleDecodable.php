<?php

namespace Purestruct;

use ReflectionClass;
use ReflectionParameter;

trait TupleDecodable
{
    use Decodable, Curry;

    public static function decoder(): Decoder
    {
        $params = (new ReflectionClass(static::class))->getConstructor()?->getParameters() ?? [];

        return match (count($params)) {
            0 => Decoder::pure(new static),
            1 => Decoder::namedType($params[0]->getType())->map(static::curry()),
            default => Decoder::lift(
                static::curry(),
                ...array_map(
                    static fn (ReflectionParameter $param) => Decoder::tupleParam($param),
                    $params,
                ),
            ),
        };
    }
}
