<?php

namespace Purestruct;

use Stringable;
use Purestruct\Error\Field;
use Purestruct\Error\Index;
use Purestruct\Error\OneOf;
use Purestruct\Error\Failure;

class Error implements Stringable
{
    public function __construct(public Field|Index|OneOf|Failure $variant)
    {
    }

    public function field(string $name): static
    {
        return new static(new Field($name, $this));
    }

    public function index(int $index): static
    {
        return new static(new Index($index, $this));
    }

    public static function oneOf(Seq $errors): self
    {
        return new static(new OneOf($errors));
    }

    public static function failure(string $message, mixed $value): static
    {
        return new static(new Failure($message, $value));
    }

    public function match(
        callable $field,
        callable $index,
        callable $oneOf,
        callable $failure,
    ): mixed {
        $v = $this->variant;

        return match ($v::class) {
            Field::class => $field($v->name, $v->error),
            Index::class => $index($v->index, $v->error),
            OneOf::class => $oneOf($v->errors),
            Failure::class => $failure($v->message, $v->value),
        };
    }

    public function toPair(): Pair
    {
        return $this->toPairHelp(Seq::nil());
    }

    private function toPairHelp(Seq $ctx): Pair
    {
        return $this->match(
            field: static function (string $name, self $e) use ($ctx) {
                $isSimple = !!preg_match('/[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/', $name);
                $fieldName = $isSimple ? ($ctx->null() ? '' : '.') . $name : "['$name']";

                return $e->toPairHelp($ctx->cons($fieldName));
            },
            index: static fn (int $index, self $e) => $e->toPairHelp($ctx->cons("[$index]")),
            oneOf: static fn (Seq $errors) => $errors->match(
                nil: static fn () => new Pair(
                    $ctx->reverse()->intercalate(''),
                    'No possibilities!',
                ),
                cons: static fn (Error $head, Seq $tail) => $tail->match(
                    nil: static fn () => $head->toPairHelp($ctx),
                    cons: static fn () => new Pair(
                        $ctx->reverse()->intercalate(''),
                        $errors
                            ->map(static fn (Error $e) => $e->toPair()->second)
                            ->intercalate(' / '),
                    ),
                ),
            ),
            failure: static fn (string $msg) => new Pair(
                $ctx->reverse()->intercalate(''),
                $msg,
            ),
        );
    }

    public function __toString(): string
    {
        return $this->toStringHelp(Seq::nil());
    }

    private function toStringHelp(Seq $ctx): string
    {
        return $this->match(
            field: static function (string $name, self $e) use ($ctx) {
                $isSimple = !!preg_match('/[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/', $name);
                $fieldName = $isSimple ? ($ctx->null() ? '' : '.') . $name : "['$name']";

                return $e->toStringHelp($ctx->cons($fieldName));
            },
            index: static fn (int $index, self $e) => $e->toStringHelp($ctx->cons("[$index]")),
            oneOf: static fn (Seq $errors) => $errors->match(
                nil: static fn () => 'Ran into an oneOf with no possibilities'
                    . $ctx->match(
                        nil: static fn () => '!',
                        cons: static fn () => ' at ' . $ctx->reverse()->intercalate(''),
                    ),
                cons: static fn (Error $head, Seq $tail) => $tail->match(
                    nil: static fn () => $head->toStringHelp($ctx),
                    cons: static function () use ($ctx, $errors) {
                        $starter = $ctx->match(
                            nil: static fn () => 'oneOf',
                            cons: static fn () => 'The oneOf at '
                                . $ctx->reverse()->intercalate(''),
                        );

                        $introduction = $starter . ' failed in the following '
                            . $errors->length() . ' ways:';

                        return $introduction
                            . ' '
                            . $errors
                            ->zipWithIndex()
                            ->map(
                                static fn (Pair $pair) => '(' . $pair->first + 1 . ') '
                                    . $pair->second,
                            )
                            ->intercalate('; ');
                    },
                ),
            ),
            failure: static function (string $msg, mixed $value) use ($ctx) {
                $introduction = $ctx->match(
                    nil: static fn () => 'Problem with the given value: ',
                    cons: static fn () => 'Problem with the value at '
                        . $ctx->reverse()->intercalate('') . ': ',
                );

                return $introduction . json_encode($value) . ': ' . $msg;
            },
        );
    }
}

namespace Purestruct\Error;

use Purestruct\Error;
use Purestruct\Seq;

class Field
{
    public function __construct(public string $name, public Error $error)
    {
    }
}

class Index
{
    public function __construct(public int $index, public Error $error)
    {
    }
}

class OneOf
{
    public function __construct(public Seq $errors)
    {
    }
}

class Failure
{
    public function __construct(public string $message, public mixed $value)
    {
    }
}
