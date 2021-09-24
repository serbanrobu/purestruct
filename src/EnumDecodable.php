<?php

namespace Purestruct;

use ReflectionClass;
use ReflectionParameter;

trait EnumDecodable
{
    use Decodable, Curry;

    public static function decoder(): Decoder
    {
        $params = (new ReflectionClass(static::class))->getConstructor()?->getParameters() ?? [];

        return count($params)
            ? Decoder::lift(
                static::curry(),
                ...array_map(
                    static fn (ReflectionParameter $param) => Decoder::enumParam($param),
                    $params,
                ),
            )
            : Decoder::pure(new static);
    }
}
