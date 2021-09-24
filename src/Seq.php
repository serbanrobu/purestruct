<?php

namespace Purestruct;

use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;
use Purestruct\Seq\Cons;
use Purestruct\Seq\Nil;

class Seq implements JsonSerializable, IteratorAggregate
{
    use Monad, Monoid;

    public function __construct(public Nil|Cons $variant)
    {
    }

    public static function nil(): static
    {
        return new static(new Nil);
    }

    public function cons(mixed $head): static
    {
        return new static(new Cons($head, $this));
    }

    public function match(callable $nil, callable $cons): mixed
    {
        return match ($this->variant::class) {
            Nil::class => $nil(),
            Cons::class => $cons($this->variant->head, $this->variant->tail),
        };
    }

    public function bind(callable $f): static
    {
        return $this->match(
            nil: static fn () => static::nil(),
            cons: static fn (mixed $head, self $tail) => $f($head)->append($tail->bind($f)),
        );
    }

    public function head(): mixed
    {
        return $this->variant->head;
    }

    public static function singleton(mixed $value): self
    {
        return static::nil()->cons($value);
    }

    public function append(self $other): static
    {
        return $this->match(
            nil: static fn () => $other,
            cons: static fn (mixed $head, self $tail) => $tail->append($other)->cons($head),
        );
    }

    public function combine(self $other): static
    {
        return $this->append($other);
    }

    public static function pure(mixed $value): static
    {
        return static::singleton($value);
    }

    public function apply(self $other): static
    {
        return $this->match(
            nil: static fn () => static::nil(),
            cons: static fn (callable $f) => $other->match(
                nil: static fn () => static::nil(),
                cons: static fn (mixed $head, self $tail) => $tail->map($f)->cons($f($head)),
            )
        );
    }

    public static function mempty(): static
    {
        return static::nil();
    }

    public function foldl(callable $f, mixed $initial): mixed
    {
        return $this->match(
            nil: static fn () => $initial,
            cons: static fn (mixed $head, self $tail) => $tail->foldl($f, $f($initial, $head)),
        );
    }

    public function foldr(callable $f, mixed $initial): mixed
    {
        return $this->match(
            nil: static fn () => static::nil(),
            cons: static fn (mixed $head, self $tail) => $f($head, $tail->foldr($f, $initial)),
        );
    }

    public function take(int $n): static
    {
        return $n > 0
            ? $this->match(
                nil: static fn () => static::nil(),
                cons: static fn (mixed $head, self $tail) => $tail->take($n - 1)->cons($head),
            )
            : static::nil();
    }

    public function reverse(): static
    {
        return $this->foldl(static fn (self $acc, mixed $cur) => $acc->cons($cur), static::nil());
    }

    public function concat(): static
    {
        return $this->foldl(static fn (self $acc, self $cur) => $acc->append($cur), static::nil());
    }

    public function length(): int
    {
        return $this->foldl(static fn (int $acc) => $acc + 1, 0);
    }

    public static function fromArray(array $values)
    {
        return array_reduce($values, static fn (self $acc, mixed $cur) => $acc->cons($cur), static::nil());
    }

    public function null(): bool
    {
        return $this->match(nil: static fn () => true, cons: static fn () => false);
    }

    public function intercalate(string $separator): string
    {
        return $this->match(
            nil: static fn () => '',
            cons: static fn (string $head, self $tail) => $head 
                . $tail->foldl(static fn (string $acc, string $cur) => $acc . $separator . $cur, ''),
        );
    }

    public function zipWithIndex(): static
    {
        return $this->zipWithIndexHelp(0);
    }

    private function zipWithIndexHelp(int $index): static
    {
        return $this->match(
            nil: fn () => $this,
            cons: static fn (mixed $head, self $tail) => $tail
                ->zipWithIndexHelp($index + 1)
                ->cons(new Pair($index, $head)),
        );
    }

    public static function decoder(): Decoder
    {
        return Decoder::mixed()->array()->map(static fn (array $v) => static::fromArray($v));
    }

    public function toArray(): array
    {
        return $this->foldl(
            static function (array $acc, mixed $x) {
                $acc[] = $x;
                return $acc;
            },
            [],
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->toArray());
    }
}

namespace Purestruct\Seq;

use Purestruct\Seq;

class Nil
{
}

class Cons
{
    public function __construct(public mixed $head, public Seq $tail)
    {
    }
}
