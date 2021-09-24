<?php

namespace Purestruct;

use Closure;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use Stringable;

class Decoder
{
    use Monad;

    public function __construct(private Closure $decode)
    {
    }

    public function decode(mixed $value): Either
    {
        return ($this->decode)($value);
    }

    public static function pure(mixed $value): static
    {
        return static::succeed($value);
    }

    public function bind(callable $f): static
    {
        return new static(
            fn (mixed $v) => $this
                ->decode($v)
                ->match(
                    left: static fn (Error $e) => Either::left($e),
                    right: static fn (mixed $v_) => $f($v_)->decode($v_),
                ),
        );
    }

    public function apply(self $other): static
    {
        return new static(fn (mixed $v) => $this->decode($v)->apply($other->decode($v)));
    }

    public function array(): static
    {
        return new static(
            fn (mixed $v) => is_array($v)
                ? (count($v)
                    ? Either::lift(
                        curryHelp(static fn () => func_get_args(), count($v)),
                        ...array_map(
                            fn (mixed $v_, int $index) => $this
                                ->decode($v_)
                                ->mapLeft(static fn (Error $e) => $e->index($index)),
                            $v,
                            array_keys($v),
                        ),
                    )
                    : Either::right([]))
                : Either::left(Error::failure('Expecting an ARRAY', $v)),
        );
    }

    public function required(): static
    {
        return $this->bind(
            static fn (mixed $v) => empty($v)
                ? static::fail("Must not be empty")
                : static::succeed($v),
        );
    }

    public function field(string $name): static
    {
        return new static(
            fn (mixed $v) => $this
                ->decode($v[$name] ?? null)
                ->mapLeft(static fn (Error $e) => $e->field($name)),
        );
    }

    public function index(int $index): static
    {
        return new static(
            fn (mixed $v) => $this
                ->decode($v[$index] ?? null)
                ->mapLeft(static fn (Error $e) => $e->index($index)),
        );
    }

    public function max(int $length): static
    {
        return $this->bind(
            static fn (string|Stringable $s) => strlen($s) > $length
                ? static::fail("Expecting the string to be less than $length characters")
                : static::succeed($s),
        );
    }

    public function min(int $length): static
    {
        return $this->bind(
            static fn (string|Stringable $s) => strlen($s) < $length
                ? static::fail("Expecting the string to be at least $length characters")
                : static::succeed($s),
        );
    }

    public static function null(mixed $value): static
    {
        return static::mixed()->bind(
            static fn (mixed $v_) => is_null($v_)
                ? static::succeed($value)
                : static::fail('Expecting NULL'),
        );
    }

    public function nullable(): static
    {
        return static::oneOf(Seq::nil()->cons(static::null(null))->cons($this));
    }

    public function email(): static
    {
        return $this->bind(
            static fn (mixed $v) => filter_var($v, FILTER_VALIDATE_EMAIL)
                ? static::succeed($v)
                : static::fail('It\'s not a valid email address')
        );
    }

    public static function mixed(): static
    {
        return new static(static fn (mixed $v) => Either::right($v));
    }

    public static function int(): static
    {
        return static::mixed()->bind(
            static function (mixed $v) {
                $v_ = filter_var($v, FILTER_VALIDATE_INT);

                return is_int($v_) ? static::succeed($v_) : static::fail('Expecting an INT');
            },
        );
    }

    public static function float(): static
    {
        return static::mixed()->bind(
            static function (mixed $v) {
                $v_ = filter_var($v, FILTER_VALIDATE_FLOAT);

                return is_float($v_) ? static::succeed($v_) : static::fail('Expecting a FLOAT');
            },
        );
    }

    public static function bool(): static
    {
        return static::mixed()->bind(
            static function (mixed $v) {
                $v_ = filter_var($v, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

                return is_null($v_)
                    ? static::fail('Expecting a BOOL')
                    : static::succeed($v_);
            }
        );
    }

    public static function object(): static
    {
        return static::mixed()->bind(
            static fn (mixed $v) => is_object($v)
                ? static::succeed($v)
                : static::fail('Expecting an OBJECT'),
        );
    }

    public static function string(): static
    {
        return static::mixed()->bind(
            static fn (mixed $v) => is_string($v)
                ? static::succeed($v)
                : static::fail('Expecting a STRING'),
        );
    }

    public static function oneOf(Seq $decoders): static
    {
        return new static(
            static function (mixed $v) use ($decoders) {
                $errors = Seq::nil();

                /** @var static $decoder */
                foreach ($decoders->toArray() as $decoder) {
                    $either = $decoder->decode($v);

                    if ($either->isRight()) {
                        return $either;
                    }

                    $errors = $either->match(
                        left: static fn (Error $e) => $errors->cons($e),
                        right: static fn () => $errors,
                    );
                }

                return Either::left(Error::oneOf($errors));
            },
        );
    }

    public static function succeed(mixed $value): static
    {
        return new static(static fn () => Either::right($value));
    }

    public static function fail(string $message): static
    {
        return new static(static fn (mixed $v) => Either::left(Error::failure($message, $v)));
    }

    public static function default(string $class): static
    {
        $params = (new ReflectionClass($class))->getConstructor()?->getParameters() ?? [];

        return count($params)
            ? static::lift(
                curryHelp(static fn () => new $class(...func_get_args()), count($params)),
                ...array_map(
                    static fn (ReflectionParameter $param) => static::param($param),
                    $params,
                ),
            )
            : static::pure(new $class);
    }

    public static function namedType(ReflectionNamedType $type): static
    {
        $typeName = $type->getName();

        if ($type->isBuiltin()) {
            $decoder = static::{$typeName}();
        } else {
            $class = $type->getName();
            $reflClass = new ReflectionClass($class);

            if ($reflClass->hasMethod('decoder')) {
                $decoder = $typeName::decoder();
            } else {
                $decoder = static::default($class);
            }
        }

        return $type->allowsNull() ? $decoder->nullable() : $decoder;
    }

    private static function paramHelp(ReflectionParameter $param, callable $mapNamedType): static
    {
        $type = $param->getType();

        if ($type instanceof ReflectionUnionType) {
            return static::oneOf(Seq::fromArray(array_map($mapNamedType, $type->getTypes())));
        }

        if ($type instanceof ReflectionNamedType) {
            return $mapNamedType($type);
        }

        return static::mixed();
    }

    public static function param(ReflectionParameter $param): static
    {
        return static::paramHelp(
            $param,
            static fn (ReflectionNamedType $type) => static::namedType($type)->field($param->name),
        );
    }

    public static function enumParam(ReflectionParameter $param): static
    {
        return static::paramHelp(
            $param,
            static function (ReflectionNamedType $type) {
                $class = $type->getName();
                $reflClass = new ReflectionClass($class);

                $isUnitType = empty($reflClass
                    ->getConstructor()
                    ?->getParameters()
                    ?? []);

                $classBasename = $reflClass->getShortName();

                return $isUnitType
                    ? static::string()->bind(
                        static fn (string $s) => $s === $classBasename
                            ? static::succeed(new $class)
                            : static::fail("Does not match \"$classBasename\""),
                    )
                    : static::namedType($type)->field($classBasename);
            },
        );
    }

    public static function tupleParam(ReflectionParameter $param): static
    {
        return static::paramHelp(
            $param,
            static fn (ReflectionNamedType $type) => static::namedType($type)
                ->index($param->getPosition()),
        );
    }
}
