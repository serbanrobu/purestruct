<?php

namespace Purestruct;

use Exception;
use Purestruct\Validation\Failure;
use Purestruct\Validation\Success;

class Validation
{
    use Applicative, Bind;

    public function __construct(public Failure|Success $variant)
    {
    }

    public static function success(mixed $value): static
    {
        return new self(new Success($value));
    }

    public static function failure(Seq $errors): static
    {
        return new self(new Failure($errors));
    }

    public function match(callable $failure, callable $success): mixed
    {
        $v = $this->variant;

        return match ($v::class) {
            Failure::class => $failure($v->errors),
            Success::class => $success($v->value),
        };
    }

    public function mapFailure(callable $f): static
    {
        return $this->match(
            failure: static fn (Seq $e) => static::failure($f($e)),
            success: fn (mixed $v) => static::success($v),
        );
    }

    public static function pure(mixed $value): static
    {
        return static::success($value);
    }

    public function apply(self $other): static
    {
        return $this->match(
            failure: static fn (Seq $e) => self::failure(
                $other->match(
                    failure: static fn (Seq $e_) => $e->append($e_),
                    success: static fn () => $e,
                ),
            ),
            success: static fn (callable $f) => $other->match(
                failure: static fn (Seq $e) => self::failure($e),
                success: static fn (mixed $v) => self::success($f($v)),
            ),
        );
    }

    public function bind(callable $f): static
    {
        return $this->match(
            failure: static fn (Seq $e) => static::failure($e),
            success: static fn (mixed $v) => $f($v),
        );
    }

    public function unwrapOrElse(callable $f): mixed
    {
        return $this->match(
            failure: $f,
            success: static fn (mixed $v) => $v,
        );
    }

    public function isSuccess(): bool
    {
        return $this->variant instanceof Success;
    }

    public function isFailure(): bool
    {
        return $this->variant instanceof Failure;
    }

    public function unwrap(): mixed
    {
        return $this
            ->unwrapOrElse(static fn (Seq $e) => throw new Exception($e->intercalate('; ')));
    }
}

namespace Purestruct\Validation;

use Purestruct\Seq;

class Success
{
    public function __construct(public mixed $value)
    {
    }
}

class Failure
{
    public function __construct(public Seq $errors)
    {
    }
}
