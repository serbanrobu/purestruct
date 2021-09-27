<?php

namespace Purestruct;

use Closure;
use Stringable;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;

class Validator
{
    use Monad, Curry;

    public function __construct(private Closure $validate)
    {
    }

    public function validate(mixed $value): Validation
    {
        return ($this->validate)($value);
    }

    public static function pure(mixed $value): static
    {
        return static::succeed($value);
    }

    public function bind(callable $f): static
    {
        return new static(
            fn (mixed $v) => $this
                ->validate($v)
                ->match(
                    failure: static fn (Seq $errors) => Validation::failure($errors),
                    success: static fn (mixed $v_) => $f($v_)->validate($v_),
                ),
        );
    }

    public function apply(self $other): static
    {
        return new static(
            fn (mixed $v) => $this->validate($v)->apply($other->validate($v)),
        );
    }

    public function array(): static
    {
        return new static(
            fn (mixed $v) => is_array($v)
                ? (count($v)
                    ? Validation::lift(
                        curryHelp(static fn () => func_get_args(), count($v)),
                        ...array_map(
                            fn (mixed $v_, int $index) => $this
                                ->validate($v_)
                                ->mapFailure(
                                    static fn (Seq $errors) => $errors
                                        ->map(static fn (Error $e) => $e->index($index)),
                                ),
                            $v,
                            array_keys($v),
                        ),
                    )
                    : Validation::success([]))
                : Validation::failure(
                    Seq::singleton(Error::failure('This field must be an array', $v)),
                ),
        );
    }

    public function required(): static
    {
        return static::mixed()
            ->bind(fn (mixed $v) => empty($v) ? static::fail('This field is required') : $this);
    }

    public function field(string $name): static
    {
        return new static(
            fn (mixed $v) => $this
                ->validate($v[$name] ?? null)
                ->mapFailure(
                    static fn (Seq $errors) => $errors
                        ->map(static fn (Error $e) => $e->field($name)),
                ),
        );
    }

    public function index(int $index): static
    {
        return new static(
            fn (mixed $v) => $this
                ->validate($v[$index] ?? null)
                ->mapFailure(
                    static fn (Seq $errors) => $errors
                        ->map(static fn (Error $e) => $e->index($index)),
                ),
        );
    }

    public function max(int $length): static
    {
        return $this->bind(
            static fn (string|Stringable $s) => strlen($s) > $length
                ? static::fail("Must not be greater than $length characters")
                : static::succeed($s),
        );
    }

    public function min(int $length): static
    {
        return $this->bind(
            static fn (string|Stringable $s) => strlen($s) < $length
                ? static::fail("Must be at least $length characters")
                : static::succeed($s),
        );
    }

    public static function null(mixed $value): static
    {
        return static::mixed()->bind(
            static fn (mixed $v_) => is_null($v_)
                ? static::succeed($value)
                : static::fail('This field must be null'),
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
        return new static(static fn (mixed $v) => Validation::success($v));
    }

    public static function int(): static
    {
        return static::mixed()->bind(
            static function (mixed $v) {
                $v_ = filter_var($v, FILTER_VALIDATE_INT);

                return is_int($v_)
                    ? static::succeed($v_)
                    : static::fail('This field must be an integer');
            },
        );
    }

    public static function float(): static
    {
        return static::mixed()->bind(
            static function (mixed $v) {
                $v_ = filter_var($v, FILTER_VALIDATE_FLOAT);

                return is_float($v_)
                    ? static::succeed($v_)
                    : static::fail('This field must be a number');
            },
        );
    }

    public static function bool(): static
    {
        return static::mixed()->bind(
            static function (mixed $v) {
                $v_ = filter_var($v, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

                return is_null($v_)
                    ? static::fail('This field must be true or false')
                    : static::succeed($v_);
            }
        );
    }

    public static function object(): static
    {
        return static::mixed()->bind(
            static fn (mixed $v) => is_object($v)
                ? static::succeed($v)
                : static::fail('This field must be an object'),
        );
    }

    public static function string(): static
    {
        return static::mixed()->bind(
            static fn (mixed $v) => is_string($v)
                ? static::succeed($v)
                : static::fail('This field must be a string'),
        );
    }

    public static function oneOf(Seq $validators): static
    {
        return new static(
            static function (mixed $v) use ($validators) {
                $errors = Seq::nil();

                /** @var static $validator */
                foreach ($validators->toArray() as $validator) {
                    $validation = $validator->validate($v);

                    if ($validation->isSuccess()) {
                        return $validation;
                    }

                    $errors = $validation->match(
                        failure: static fn (Seq $errs) => $errors->append($errs),
                        success: static fn () => $errors,
                    );
                }

                return Validation::failure(Seq::singleton(Error::oneOf($errors)));
            },
        );
    }

    public static function succeed(mixed $value): static
    {
        return new static(static fn () => Validation::success($value));
    }

    public static function fail(string $message): static
    {
        return new static(
            static fn (mixed $v) => Validation::failure(
                Seq::singleton(Error::failure($message, $v)),
            ),
        );
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
            $validator = static::{$typeName}();
        } else {
            $class = $type->getName();
            $reflClass = new ReflectionClass($class);

            if ($reflClass->hasMethod('validator')) {
                $validator = $typeName::validator();
            } else {
                $validator = static::default($class);
            }
        }

        return $type->allowsNull() ? $validator->nullable() : $validator;
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

                $isUnitType = empty(
                    $reflClass
                        ->getConstructor()
                        ?->getParameters()
                        ?? []
                );

                $classBasename = $reflClass->getShortName();

                return $isUnitType
                    ? static::string()
                        ->bind(
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

    public function toDecoder(): Decoder
    {
        return new Decoder(
            fn (mixed $v) => $this
                ->validate($v)
                ->match(
                    failure: static fn (Seq $errors) => Either::left($errors->head()),
                    success: static fn (mixed $v) => Either::right($v),
                ),
        );
    }
}
