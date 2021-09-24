<?php

namespace Purestruct;

trait Validatable
{
    use Decodable;

    public static function validate(mixed $value): Validation
    {
        return self::validator()->validate($value);
    }

    public static function decoder(): Decoder
    {
        return self::validator()->toDecoder();
    }

    public static function validator(): Validator
    {
        return Validator::default(static::class);
    }
}
