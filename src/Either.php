<?php

namespace Purestruct;

use Exception;
use Purestruct\Either\Left;
use Purestruct\Either\Right;

class Either
{
    use Monad;

    public function __construct(public Left|Right $variant)
    {
    }

    public static function left(mixed $value): static
    {
        return new static(new Left($value));
    }

    public static function right(mixed $value): static
    {
        return new static(new Right($value));
    }

    public function match(callable $left, callable $right): mixed
    {
        $v = $this->variant;

        return match ($v::class) {
            Left::class => $left($v->value),
            Right::class => $right($v->value),
        };
    }

    public function bind(callable $f): static
    {
        return $this->match(
            left: static fn (mixed $v) => static::left($v),
            right: static fn (mixed $v) => $f($v),
        );
    }

    public static function pure(mixed $value): static
    {
        return static::right($value);
    }

    public function apply(self $other): static
    {
        return $this->match(
            left: static fn (mixed $v) => static::left($v),
            right: static fn (callable $f) => $other->match(
                left: static fn (mixed $v) => static::left($v),
                right: static fn (mixed $v) => static::right($f($v)),
            ),
        );
    }

    public function unwrapOrElse(callable $f): mixed
    {
        return $this->match(
            left: $f,
            right: static fn (mixed $v) => $v,
        );
    }

    public function unwrap(): mixed
    {
        return $this->unwrapOrElse(fn ($error) => throw new Exception($error));
    }

    public function mapLeft(callable $f): static
    {
        return $this->match(
            left: static fn (mixed $v) => static::left($f($v)),
            right: static fn (mixed $v) => static::right($v),
        );
    }

    public function orElse(callable $f): static
    {
        return $this->match(
            left: $f,
            right: fn () => $this,
        );
    }

    public function isRight(): bool
    {
        return $this->variant instanceof Right;
    }

    public function isLeft(): bool
    {
        return $this->variant instanceof Left;
    }
}

namespace Purestruct\Either;

class Left
{
    public function __construct(public mixed $value)
    {
    }
}

class Right
{
    public function __construct(public mixed $value)
    {
    }
}
