<?php

namespace Purestruct;

trait Decodable
{
    use Curry;

    public static function decode(mixed $value): Either
    {
        return static::decoder()->decode($value);
    }

    public static function decoder(): Decoder
    {
        return Decoder::default(static::class);
    }
}
