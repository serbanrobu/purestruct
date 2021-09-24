<?php

namespace Purestruct;

trait Apply
{
    use Functor;

    abstract public function apply(self $other): static;

    public static function lift(callable $f, self $a, self ...$rest): static
    {
        return array_reduce($rest, static fn (self $acc, self $x) => $acc->apply($x), $a->map($f));
    }
}
