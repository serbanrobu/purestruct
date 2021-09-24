<?php

namespace Purestruct;

use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;

class Dict implements IteratorAggregate, JsonSerializable
{
    use Functor, Monoid;

    private function __construct(private array $inner)
    {
    }

    public static function singleton(string $key, mixed $value): static
    {
        return new static([$key => $value]);
    }

    public static function fromSeq(Seq $pairs): static
    {
        return $pairs->foldl(
            static fn (self $acc, Pair $pair) => $acc->insert($pair->first, $pair->second),
            static::mempty(),
        );
    }

    public function toSeq(): Seq
    {
        $seq = Seq::nil();

        foreach ($this->inner as $key => $value) {
            $seq = $seq->cons(new Pair($key, $value));
        }

        return $seq;
    }

    public function union(self $other): static
    {
        return new static(array_merge($this->inner, $other->inner));
    }

    public function unionWith(callable $f, self $other): static
    {
        $inner = $this->inner;

        foreach ($other->inner as $key => $value) {
            $inner[$key] = isset($inner[$key]) ? $f($inner[$key], $value) : $value;
        }

        return new static($inner);
    }

    public function combine(self $other): static
    {
        return $this->union($other);
    }

    public static function mempty(): static
    {
        return new static([]);
    }

    public function map(callable $f): static
    {
        return new static(array_map($f, $this->inner));
    }

    public function insert(string $key, mixed $value): static
    {
        $inner = $this->inner;
        $inner[$key] = $value;

        return new static($inner);
    }

    public function toArray(): array
    {
        return $this->inner;
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->toArray());
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
