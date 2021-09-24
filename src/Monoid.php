<?php

namespace Purestruct;

trait Monoid
{
    use Semigroup;

    abstract public static function mempty(): static;

    public function power(int $n): static
    {
        return match (true) {
            $n <= 0 => static::mempty(),
            $n === 1 => $this,
            default => $this->combine($this->power($n - 1)),
        };
    }
}
